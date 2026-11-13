<?php

declare(strict_types=1);

namespace Rekhu\Core\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Rekhu\Core\Exceptions\AppPaused;
use Rekhu\Core\Exceptions\CreditExhausted;
use Rekhu\Core\Exceptions\InvalidKey;
use Rekhu\Core\Exceptions\LimitReached;
use Rekhu\Core\Exceptions\ModuleNotEnabled;
use Rekhu\Core\Exceptions\RegionUnavailable;
use Rekhu\Core\Exceptions\RekhuException;
use Rekhu\Core\Knowers\Seshat;
use Rekhu\Core\Rekhu;
use Symfony\Component\HttpFoundation\Response;

/** The file check on your own site: the browser sends the file here with the app's own login; Seshat is called with the key. */
class SeshatController extends Controller
{
    private const NUMBERS = ['width_mm', 'height_mm', 'bleed_mm', 'pages', 'min_ppi', 'safe_mm', 'max_ink'];

    private const TEXTS = ['colour', 'profile'];

    private const ACCOUNT = [InvalidKey::class, CreditExhausted::class, AppPaused::class, ModuleNotEnabled::class, RegionUnavailable::class];

    /** The fields of the specs only (size, bleed, pages, colour, limits), numbers as numbers: what a browser may send. */
    public static function cleanSpecs(mixed $asked): array
    {
        $specs = [];
        if (! is_array($asked)) {
            return $specs;
        }
        foreach (self::NUMBERS as $key) {
            if (isset($asked[$key]) && $asked[$key] !== '' && is_numeric($asked[$key])) {
                $specs[$key] = $asked[$key] + 0;
            }
        }
        foreach (self::TEXTS as $key) {
            if (isset($asked[$key]) && is_string($asked[$key]) && $asked[$key] !== '') {
                $specs[$key] = mb_substr($asked[$key], 0, 60);
            }
        }
        if (isset($asked['spot_colours_allowed']) && is_bool($asked['spot_colours_allowed'])) {
            $specs['spot_colours_allowed'] = $asked['spot_colours_allowed'];
        }

        return $specs;
    }

    /** The print file against its specs: the findings with their sentence and the explanation, without what Rekhu charged. */
    public function check(Request $request): Response
    {
        $config = $this->config($request);

        return $this->guard(function () use ($request, $config) {
            $file = $request->file('file');
            if (! $file || ! $file->isValid()) {
                return self::error(400, 'invalid_request_error', 'Send the print file as the field "file".');
            }
            if ($file->getSize() > $config['max_mb'] * 1024 * 1024) {
                return self::error(413, 'invalid_request_error', "The file is larger than {$config['max_mb']} MB.");
            }
            $asked = json_decode((string) $request->input('specs', '{}'), true);
            if (! is_array($asked)) {
                return self::error(400, 'invalid_request_error', 'The specs are not valid JSON.');
            }
            $recipe = $config['recipes'] && $request->filled('recipe') ? mb_substr((string) $request->input('recipe'), 0, 80) : null;
            $specs = $this->specs($config, $asked);
            if (! $recipe && (empty($specs['width_mm']) || empty($specs['height_mm']))) {
                return self::error(400, 'invalid_request_error', 'The specs need width_mm and height_mm.');
            }
            $language = $this->language($request);
            $explain = $config['explain'] && $request->input('explain') !== '0';
            $result = $this->seshat($request)->check($file, $specs, $explain, $file->getClientOriginalName(), $recipe, $language);
            $about = $this->catalogue($request);

            return response()->json([
                'protocol' => 1, 'file_id' => $result['file_id'] ?? null, 'name' => $file->getClientOriginalName(), 'pages' => $result['file']['pages'] ?? null,
                'specs' => (object) $specs, 'status' => $result['status'] ?? null, 'findings' => $this->findings($result, $about, $language), 'explanation' => $result['explanation'] ?? null,
            ], 200, ['Cache-Control' => 'no-store']);
        });
    }

    /** The fixes (all Seshat decided on, or the ones chosen and allowed), and the fixed file checked again. */
    public function fix(Request $request): Response
    {
        $config = $this->config($request);

        return $this->guard(function () use ($request, $config) {
            $fileId = (string) $request->input('file_id');
            if (! preg_match('/^f_[a-z0-9]{24}$/', $fileId)) {
                return self::error(404, 'not_found_error', 'No such file.');
            }
            $recipe = $config['recipes'] && $request->filled('recipe') ? mb_substr((string) $request->input('recipe'), 0, 80) : null;
            $chosen = $this->allowed($config, $request->input('fixes'));
            if ($chosen === null && is_array($config['fixes'])) {
                return self::error(422, 'invalid_request_error', 'Name the fixes to make: this app allows only some.');
            }
            if ($chosen === []) {
                return response()->json(['protocol' => 1, 'file_id' => $fileId, 'name' => null, 'fixed_from' => $fileId, 'applied' => [], 'check' => null], 200, ['Cache-Control' => 'no-store']);
            }
            $fixes = $chosen === null ? 'auto' : array_map(fn ($fix) => ['fix' => $fix], $chosen);
            $fixed = $this->seshat($request)->fix($fileId, $fixes, $this->specs($config, $request->input('specs', [])), $recipe);
            $about = $this->catalogue($request);
            $applied = collect((array) ($fixed['applied'] ?? []))
                ->filter(fn ($a) => ! empty($a['fix']) && empty($a['side_effect']) && ($a['changed'] ?? true) !== false && ! in_array($a['result'] ?? null, ['not_found', 'not_set'], true))
                ->map(fn ($a) => ['fix' => $a['fix'], 'about' => $about['fixes'][$a['fix']]['about'] ?? $a['fix']])->values();
            $check = isset($fixed['check']) ? ['status' => $fixed['check']['status'] ?? null, 'pages' => $fixed['check']['file']['pages'] ?? null, 'findings' => $this->findings((array) $fixed['check'], $about, $this->language($request))] : null;

            return response()->json([
                'protocol' => 1, 'file_id' => $fixed['file_id'] ?? $fileId, 'name' => $fixed['name'] ?? null, 'fixed_from' => $fixed['from_file_id'] ?? $fileId,
                'applied' => $applied, 'check' => $check,
            ], 200, ['Cache-Control' => 'no-store']);
        });
    }

    /** A page as a PNG with its trim, bleed and safe zone; part of the check. */
    public function preview(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            $fileId = (string) $request->query('file');
            if (! preg_match('/^f_[a-z0-9]{24}$/', $fileId)) {
                return self::error(404, 'not_found_error', 'No such file.');
            }
            $preview = $this->seshat($request)->preview($fileId, max(1, (int) $request->query('page', 1)), 1400);
            $png = base64_decode((string) preg_replace('#^data:image/png;base64,#', '', (string) ($preview['image'] ?? '')));

            return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
        });
    }

    /** The file to the browser with its own name, removed from this server once sent. */
    public function download(Request $request, string $file): Response
    {
        return $this->guard(function () use ($request, $file) {
            $kept = $this->seshat($request)->fileTo($file, (string) tempnam(sys_get_temp_dir(), 'rekhu-seshat-'));

            return response()->download($kept['path'], $kept['name'], ['Content-Type' => $kept['type'], 'Cache-Control' => 'no-store'])->deleteFileAfterSend();
        });
    }

    /** An error as the protocol words it. */
    public static function error(int $status, string $type, string $message): JsonResponse
    {
        return response()->json(['error' => ['type' => $type, 'message' => $message]], $status, ['Cache-Control' => 'no-store']);
    }

    /** The settings of these routes: config('rekhu.seshat') with the options of Rekhu::seshatRoutes() on top. */
    private function config(Request $request): array
    {
        return array_replace(['max_mb' => 200, 'explain' => true, 'specs' => [], 'fixes' => null, 'recipes' => true], (array) ($request->route()?->defaults['rekhu_seshat'] ?? config('rekhu.seshat', [])));
    }

    /** The app's defaults with the page's specs on top. */
    private function specs(array $config, mixed $asked): array
    {
        return array_replace(self::cleanSpecs(array_filter((array) $config['specs'], fn ($v) => $v !== null)), self::cleanSpecs($asked));
    }

    /** The fixes the app allows from the names the page sent; null when it sent none. */
    private function allowed(array $config, mixed $names): ?array
    {
        if (! is_array($names)) {
            return null;
        }
        $names = array_values(array_unique(array_filter($names, fn ($n) => is_string($n) && preg_match('/^[a-z][a-z0-9_]{1,40}$/', $n))));

        return is_array($config['fixes']) ? array_values(array_intersect($names, $config['fixes'])) : $names;
    }

    /** The language of a request: the locale it sent, else the browser's first, as two letters. */
    private function language(Request $request): string
    {
        return strtolower(substr((string) ($request->input('locale') ?: $request->header('Accept-Language', 'en')), 0, 2)) ?: 'en';
    }

    /** The findings of a check, of a ZIP's files too, each with its sentence in the person's language (else English). */
    private function findings(array $result, array $about, string $language): array
    {
        $raw = $result['findings'] ?? [];
        if (! $raw) {
            foreach ((array) ($result['files'] ?? []) as $file) {
                foreach ((array) ($file['findings'] ?? []) as $f) {
                    $raw[] = $f + ['file' => $file['name'] ?? null];
                }
            }
        }

        return array_map(fn ($f) => array_filter([
            'code' => $f['code'] ?? null, 'decision' => $f['decision'] ?? null, 'about' => $about['texts'][$language][$f['code'] ?? ''] ?? $about['texts']['en'][$f['code'] ?? ''] ?? $about['codes'][$f['code'] ?? '']['about'] ?? ($f['about'] ?? $f['code'] ?? null),
            'fix' => $f['fix'] ?? null, 'pages' => $f['pages'] ?? null, 'file' => $f['file'] ?? null,
        ], fn ($v) => $v !== null), $raw);
    }

    /** The sentence per finding code and per fix, kept for a day. */
    private function catalogue(Request $request): array
    {
        return cache()->remember('rekhu.seshat.catalogue.2', 86400, function () use ($request) {
            $list = $this->seshat($request)->codes();

            return ['codes' => (array) ($list['codes'] ?? []), 'fixes' => (array) ($list['fixes'] ?? []), 'texts' => (array) ($list['texts'] ?? [])];
        });
    }

    private function seshat(Request $request): Seshat
    {
        return Rekhu::seshat()->for($request->user());
    }

    /** The user reads what they can act on; the app's own account (key, credit, modules) goes to the log, not to them. */
    private function guard(callable $run): Response
    {
        try {
            return $run();
        } catch (LimitReached $e) {
            return self::error(429, 'limit_reached', $e->getMessage());
        } catch (RekhuException $e) {
            foreach (self::ACCOUNT as $class) {
                if ($e instanceof $class || $e->type === 'configuration_error') {
                    Log::warning('Rekhu: the file check is not available: '.$e->getMessage());

                    return self::error(503, 'unavailable', 'The file check is not available right now. Try again later.');
                }
            }
            if (in_array($e->status, [400, 404, 413, 422, 429], true)) {
                return self::error($e->status, $e->type, $e->getMessage());
            }
            Log::warning('Rekhu: a file could not be checked: '.$e->getMessage());

            return self::error(503, 'unavailable', 'The file could not be checked right now; try again in a moment.');
        }
    }
}
