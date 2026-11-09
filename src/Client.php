<?php

declare(strict_types=1);

namespace Rekhu\Core;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Rekhu\Core\Events\KnowerUsed;
use Rekhu\Core\Exceptions\InvalidKey;
use Rekhu\Core\Exceptions\LimitReached;
use Rekhu\Core\Exceptions\RekhuException;
use Rekhu\Core\Exceptions\Unavailable;

/**
 * Calls a knower on Rekhu for a user of the app: the app's own limits first, then the call with the user's tenant and
 * language, then the usage kept in the app's ledger and KnowerUsed fired. Calls that fail on the way (no connection,
 * 429, 502, 503) are tried again; a charged call carries an idempotency key, so a retry is never charged twice.
 */
class Client
{
    private const FREE = ['models', 'modules', 'codes', 'fonts', 'upload', 'poll', 'enhance_preview'];

    private const TRIES = 3;

    /**
     * @param  array{id: string, name: string}|null  $tenant  a tenant named directly (a queued job without a user)
     * @param  array{name: string, bytes?: string, path?: string}|null  $file  a file as bytes or as a path, streamed
     * @return array<string, mixed>
     */
    public function call(string $knower, string $action, array $input = [], ?Authenticatable $user = null, ?array $tenant = null, ?array $file = null): array
    {
        $key = $this->key();
        if (config('rekhu.knowers') && ! Rekhu::installed($knower)) {
            throw new RekhuException("The knower {$knower} is not in REKHU_KNOWERS for this app. Add it, or leave REKHU_KNOWERS empty to use every knower the key allows.", 0, 'configuration_error');
        }
        $user ??= app()->runningInConsole() ? null : auth()->user();
        $tenant ??= Rekhu::tenant($user);
        if (config('rekhu.tenancy') === 'multi' && ! $tenant && ! in_array($action, self::FREE, true)) {
            throw new RekhuException('This app is multi-tenant, and no tenant was found: set Rekhu::tenantUsing(), or pass the user or the tenant (in a queued job there is no signed-in user).', 0, 'configuration_error');
        }
        $this->checkLimits($tenant, $user, $knower);
        $input += in_array($action, ['check', 'explain'], true) ? ['language' => Rekhu::locale($user)] : [];

        $headers = $this->headers($key, $tenant, $user);
        if (! in_array($action, self::FREE, true)) {
            $headers['idempotency-key'] = (string) Str::uuid();
        }
        $started = microtime(true);
        $response = $this->send(function () use ($headers, $knower, $action, $input, $file) {
            $request = $this->request($headers);
            if (! $file) {
                return $request->post("/v1/knowers/{$knower}/{$action}", (object) $input);
            }
            $body = isset($file['path']) ? Utils::streamFor(fopen($file['path'], 'rb')) : (string) $file['bytes'];

            return $request->withHeaders(['x-file-name' => rawurlencode($file['name']), 'x-knower-input' => json_encode((object) ($input + ['name' => $file['name']]))])
                ->withBody($body, 'application/octet-stream')->post("/v1/knowers/{$knower}/{$action}");
        });
        $answer = (array) $response->json();
        if (! in_array($action, self::FREE, true) && ! ($answer['meta']['replayed'] ?? false)) {
            $this->keep($tenant, $user, $knower, $action, $answer, (int) round((microtime(true) - $started) * 1000));
        }

        return $answer;
    }

    /** A file a knower kept or made for this app (a fixed print file), as bytes. */
    public function download(string $knower, string $fileId, ?Authenticatable $user = null, ?array $tenant = null): string
    {
        return $this->send(fn () => $this->request($this->filesHeaders($user, $tenant))->get("/v1/knowers/{$knower}/files/{$fileId}"))->body();
    }

    /** The same, written straight to a path, without holding a large file in memory. */
    public function downloadTo(string $knower, string $fileId, string $path, ?Authenticatable $user = null, ?array $tenant = null): string
    {
        $this->send(fn () => $this->request($this->filesHeaders($user, $tenant))->withOptions(['sink' => $path])->get("/v1/knowers/{$knower}/files/{$fileId}"));

        return $path;
    }

    /**
     * A font of a knower's font library by its PostScript name (TrueType), with its licence; written to $path when given.
     *
     * @return array{name: string, licence: ?string, bytes?: string, path?: string}
     */
    public function fontFile(string $knower, string $name, ?string $path = null): array
    {
        $request = $this->request($this->filesHeaders(null, null));
        $response = $this->send(fn () => ($path ? $request->withOptions(['sink' => $path]) : $request)->get("/v1/knowers/{$knower}/fonts/".rawurlencode($name)));

        return ['name' => $name, 'licence' => $response->header('x-font-licence') ?: null] + ($path ? ['path' => $path] : ['bytes' => $response->body()]);
    }

    /** A file Rekhu keeps for a day, removed now (a customer took it out of their order). */
    public function remove(string $knower, string $fileId, ?Authenticatable $user = null, ?array $tenant = null): void
    {
        $this->send(fn () => $this->request($this->filesHeaders($user, $tenant))->delete("/v1/knowers/{$knower}/files/{$fileId}"));
    }

    /** What the app's user did with an answer: what the knower learns from. Feedback is not charged. */
    public function feedback(string $knower, string $ref, string $outcome, ?array $correction = null, ?Authenticatable $user = null, ?array $tenant = null): array
    {
        $response = $this->send(fn () => $this->request($this->filesHeaders($user, $tenant))
            ->post("/v1/knowers/{$knower}/feedback", array_filter(['ref' => $ref, 'outcome' => $outcome, 'correction' => $correction])));

        return (array) $response->json();
    }

    /** Whether this app reaches Rekhu with its key: the platform, the key's names and what the key may use. */
    public function ping(): array
    {
        $response = $this->send(fn () => $this->request($this->headers($this->key(), null, null))->get('/v1/me'));

        return ['platform' => Rekhu::platform(), 'version' => $response->header('x-rekhu-version') ?: null] + (array) $response->json();
    }

    /** The key, or a refusal that says how to set it. */
    private function key(): array
    {
        $key = Rekhu::key();
        if (! $key) {
            throw new InvalidKey('REKHU_APP_KEY is missing or not a Rekhu app key: put the app key from your Rekhu account (Projects → the app → Connection) in .env, or run php artisan rekhu:core --install.', 0, 'configuration_error');
        }

        return $key;
    }

    private function headers(array $key, ?array $tenant, ?Authenticatable $user): array
    {
        $headers = ['x-api-key' => $key['key'], 'x-agent' => $key['agent'], 'x-tenant' => (string) ($tenant['id'] ?? 'app'), 'x-tenant-name' => (string) ($tenant['name'] ?? '')];
        if ($user) {
            $headers['x-user'] = (string) $user->getAuthIdentifier();
        }

        return $headers;
    }

    private function filesHeaders(?Authenticatable $user, ?array $tenant): array
    {
        $user ??= app()->runningInConsole() ? null : auth()->user();

        return $this->headers($this->key(), $tenant ?? Rekhu::tenant($user), $user);
    }

    private function request(array $headers): PendingRequest
    {
        return Http::baseUrl(Rekhu::platform())->timeout((int) config('rekhu.timeout', 180))->acceptJson()->withHeaders($headers);
    }

    /**
     * One call, tried again on a dropped connection or a 429, 502, 503 or 529, waiting a little longer each time (and as
     * long as Rekhu's retry-after asks, up to 5 seconds). Any other failure is the typed exception for it.
     */
    private function send(callable $make): Response
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $make();
            } catch (ConnectionException) {
                if ($attempt >= self::TRIES) {
                    throw new Unavailable('Rekhu could not be reached; try again in a moment.', 503, 'connection_error');
                }
                usleep(250_000 * 2 ** $attempt);

                continue;
            }
            if ($response->successful()) {
                return $response;
            }
            if ($attempt < self::TRIES && in_array($response->status(), [429, 502, 503, 529], true)) {
                $wait = min(5.0, (float) ($response->header('retry-after') ?: 0.25 * 2 ** $attempt));
                usleep((int) ($wait * 1_000_000));

                continue;
            }
            throw RekhuException::from($response->status(), (string) ($response->json('error.type') ?: 'api_error'), (string) ($response->json('error.message') ?: "Rekhu answered {$response->status()}"));
        }
    }

    /** The app's own limits a day, counted in its ledger: per tenant and per user. */
    private function checkLimits(?array $tenant, ?Authenticatable $user, string $knower): void
    {
        if (! config('rekhu.ledger.enabled')) {
            return;
        }
        $limits = Rekhu::limits($tenant, $knower);
        $today = now()->startOfDay();
        $perTenant = $limits['per_day_per_tenant'] ?? null;
        if ($perTenant !== null && $tenant && $this->ledger()->where('knower', $knower)->where('tenant_id', $tenant['id'])->where('created_at', '>=', $today)->count() >= (int) $perTenant) {
            throw new LimitReached("{$tenant['name']} has used today's {$perTenant} calls to {$knower}.", 'tenant');
        }
        $perUser = $limits['per_day_per_user'] ?? null;
        if ($perUser !== null && $user && $this->ledger()->where('knower', $knower)->where('user_id', (string) $user->getAuthIdentifier())->where('created_at', '>=', $today)->count() >= (int) $perUser) {
            throw new LimitReached("Today's {$perUser} calls to {$knower} are used up.", 'user');
        }
    }

    private function keep(?array $tenant, ?Authenticatable $user, string $knower, string $action, array $answer, int $ms): void
    {
        $meta = (array) ($answer['meta'] ?? []);
        $row = [
            'tenant_id' => $tenant['id'] ?? null, 'tenant_name' => $tenant['name'] ?? null, 'user_id' => $user ? (string) $user->getAuthIdentifier() : null,
            'knower' => $knower, 'action' => $action, 'ref' => $answer['ref'] ?? null, 'ok' => true, 'model_calls' => (int) ($meta['model_calls'] ?? 0),
            'cost_usd' => (float) ($meta['cost_usd'] ?? 0), 'price' => Rekhu::price($tenant, $knower, $action, $meta), 'ms' => $ms, 'created_at' => now(),
        ];
        if (config('rekhu.ledger.enabled')) {
            $this->ledger()->insert($row);
        }
        event(new KnowerUsed($tenant, $user, $knower, $action, $row['ref'], $row['model_calls'], $row['cost_usd'], $row['price'], $ms));
    }

    private function ledger()
    {
        return DB::connection(config('rekhu.ledger.connection'))->table((string) config('rekhu.ledger.table', 'rekhu_usage'));
    }
}
