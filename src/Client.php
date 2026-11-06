<?php

declare(strict_types=1);

namespace Rekhu\Core;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Rekhu\Core\Events\KnowerUsed;
use Rekhu\Core\Exceptions\LimitReached;
use Rekhu\Core\Exceptions\RekhuException;

/**
 * Calls a knower on Rekhu for a user of the app: the app's own limits first, then the call with the user's tenant and
 * language, then the usage kept in the app's ledger and KnowerUsed fired.
 */
class Client
{
    /**
     * @param  array{id: string, name: string}|null  $tenant  a tenant named directly (a queued job without a user)
     * @return array<string, mixed>
     */
    public function call(string $knower, string $action, array $input = [], ?Authenticatable $user = null, ?array $tenant = null, ?array $file = null): array
    {
        $key = Rekhu::key();
        if (! $key) {
            throw new RekhuException('REKHU_APP_KEY is missing or not a Rekhu app key. Run php artisan rekhu:core --install. No Rekhu account yet? Create one at https://rekhu.com/register?ref=core-laravel: it starts with $10 credit, no card needed.', 0, 'configuration_error');
        }
        if (! Rekhu::installed($knower)) {
            throw new RekhuException("The knower {$knower} is not installed in this app. Run php artisan rekhu:core --install={$knower}.", 0, 'configuration_error');
        }
        $user ??= auth()->user();
        $tenant ??= Rekhu::tenant($user);
        // the library's list is the same for everyone and not charged: it needs no tenant
        if (config('rekhu.tenancy') === 'multi' && ! $tenant && $action !== 'models') {
            throw new RekhuException('This app is multi-tenant, and no tenant was found for the user: set Rekhu::tenantUsing() or pass a tenant.', 0, 'configuration_error');
        }
        $this->checkLimits($tenant, $user, $knower);
        $language = Rekhu::locale($user);
        $input += in_array($action, ['check', 'explain'], true) ? ['language' => $language] : [];

        $headers = ['x-api-key' => $key['key'], 'x-agent' => $key['agent'], 'x-tenant' => (string) ($tenant['id'] ?? 'app'), 'x-tenant-name' => (string) ($tenant['name'] ?? '')];
        if ($user) {
            $headers['x-user'] = (string) $user->getAuthIdentifier();
        }
        $started = microtime(true);
        try {
            $request = Http::baseUrl(Rekhu::platform())->timeout((int) config('rekhu.timeout', 60))->acceptJson()->withHeaders($headers);
            // a file goes as the body itself, its name and the input in headers
            $response = $file
                ? $request->withHeaders(['x-file-name' => $file['name'], 'x-knower-input' => json_encode((object) $input)])->withBody($file['bytes'], 'application/octet-stream')->post("/v1/knowers/{$knower}/{$action}")
                : $request->post("/v1/knowers/{$knower}/{$action}", (object) $input);
        } catch (ConnectionException) {
            throw new RekhuException('Rekhu could not be reached; try again in a moment.', 503, 'connection_error');
        }
        if ($response->failed()) {
            throw new RekhuException((string) ($response->json('error.message') ?: "Rekhu answered {$response->status()}"), $response->status(), (string) ($response->json('error.type') ?: 'api_error'));
        }
        $answer = (array) $response->json();
        if ($action === 'models') {
            return $answer;
        }
        $this->keep($tenant, $user, $knower, $action, $answer, (int) round((microtime(true) - $started) * 1000));

        return $answer;
    }

    /** A file a knower kept or made for this app (a fixed print file), as bytes. */
    public function download(string $knower, string $fileId): string
    {
        $key = Rekhu::key();
        if (! $key) {
            throw new RekhuException('REKHU_APP_KEY is missing or not a Rekhu app key.', 0, 'configuration_error');
        }
        $response = Http::baseUrl(Rekhu::platform())->timeout((int) config('rekhu.timeout', 60))
            ->withHeaders(['x-api-key' => $key['key'], 'x-agent' => $key['agent']])->get("/v1/knowers/{$knower}/files/{$fileId}");
        if ($response->failed()) {
            throw new RekhuException((string) ($response->json('error.message') ?: "Rekhu answered {$response->status()}"), $response->status(), (string) ($response->json('error.type') ?: 'api_error'));
        }

        return $response->body();
    }

    /** What the app's user did with an answer: what the knower learns from. Feedback is not charged. */
    public function feedback(string $knower, string $ref, string $outcome, ?array $correction = null): array
    {
        $key = Rekhu::key();
        if (! $key) {
            throw new RekhuException('REKHU_APP_KEY is missing or not a Rekhu app key.', 0, 'configuration_error');
        }
        $response = Http::baseUrl(Rekhu::platform())->timeout((int) config('rekhu.timeout', 60))->acceptJson()
            ->withHeaders(['x-api-key' => $key['key'], 'x-agent' => $key['agent']])
            ->post("/v1/knowers/{$knower}/feedback", array_filter(['ref' => $ref, 'outcome' => $outcome, 'correction' => $correction]));
        if ($response->failed()) {
            throw new RekhuException((string) ($response->json('error.message') ?: "Rekhu answered {$response->status()}"), $response->status(), (string) ($response->json('error.type') ?: 'api_error'));
        }

        return (array) $response->json();
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
