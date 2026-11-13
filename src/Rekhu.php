<?php

declare(strict_types=1);

namespace Rekhu\Core;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Rekhu\Core\Knowers\Knower;
use Rekhu\Core\Knowers\Machines;
use Rekhu\Core\Knowers\Seshat;

/**
 * Rekhu in a Laravel app: the knowers it installed, and the app's own rules around them: who a user's tenant is, the
 * language a user reads, the limits per tenant or plan, and the app's own price per call.
 *
 *     Rekhu::tenantUsing(fn ($user) => $user->team);              // multi-tenant: the customer a user belongs to
 *     Rekhu::limitsUsing(fn ($tenant, $knower) => $tenant['plan'] === 'basic' ? ['per_day_per_tenant' => 50] : []);
 *     Rekhu::priceUsing(fn ($tenant, $knower, $action, $meta) => 0.02);   // what you charge your customer for a call
 *
 *     Rekhu::machines()->identify('Exampla Offset 74-4 with coater');
 *     Rekhu::machines()->for($user)->check($machine);
 *     Rekhu::seshat()->for($user)->check($request->file('artwork'), $order, explain: true);
 */
class Rekhu
{
    public const VERSION = '0.12.0';

    private static ?Closure $tenant = null;

    private static ?Closure $locale = null;

    private static ?Closure $limits = null;

    private static ?Closure $price = null;

    public static function tenantUsing(?Closure $resolver): void
    {
        self::$tenant = $resolver;
    }

    public static function localeUsing(?Closure $resolver): void
    {
        self::$locale = $resolver;
    }

    public static function limitsUsing(?Closure $resolver): void
    {
        self::$limits = $resolver;
    }

    public static function priceUsing(?Closure $resolver): void
    {
        self::$price = $resolver;
    }

    /** ['key' => …, 'customer' => …, 'agent' => …] from the app key, or null when it is missing or not a Rekhu key. */
    public static function key(): ?array
    {
        $key = (string) config('rekhu.key');
        if (! preg_match('/^agk_([a-z0-9-]+)_([a-z0-9-]+)_[A-Za-z0-9_-]+$/', $key, $m)) {
            return null;
        }

        return ['key' => $key, 'customer' => $m[1], 'agent' => $m[2]];
    }

    /** The platform address for the mode the app runs in. */
    public static function platform(): string
    {
        $mode = (string) config('rekhu.deployment', 'cloud');
        $platform = rtrim((string) config('rekhu.platform'), '/');
        if ($mode === 'private' && ($platform === '' || $platform === 'https://api.rekhu.ai')) {
            throw new Exceptions\RekhuException('REKHU_DEPLOYMENT is private: set REKHU_PLATFORM to the address of Rekhu inside your network.', 0, 'configuration_error');
        }
        if (! in_array($mode, ['cloud', 'private'], true)) {
            throw new Exceptions\RekhuException("REKHU_DEPLOYMENT {$mode} is not available; use cloud or private.", 0, 'configuration_error');
        }

        return $platform;
    }

    /**
     * ['id' => …, 'name' => …] of the customer a user belongs to. Single tenancy: the app itself. A resolver may give a
     * model (its key and name), an array or an id.
     */
    public static function tenant(?Authenticatable $user): ?array
    {
        if (config('rekhu.tenancy') !== 'multi') {
            return ['id' => 'app', 'name' => (string) config('app.name')];
        }
        $tenant = self::$tenant && $user ? (self::$tenant)($user) : null;
        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return ['id' => (string) $tenant->getKey(), 'name' => (string) ($tenant->name ?? $tenant->title ?? $tenant->getKey())];
        }
        if (is_array($tenant) && isset($tenant['id'])) {
            return ['id' => (string) $tenant['id'], 'name' => (string) ($tenant['name'] ?? $tenant['id'])] + $tenant;
        }
        if (is_scalar($tenant) && $tenant !== '') {
            return ['id' => (string) $tenant, 'name' => (string) $tenant];
        }

        return null;
    }

    /** The language a user reads: the resolver's, else the app's locale, kept to the installed languages. */
    public static function locale(?Authenticatable $user): string
    {
        $locale = (string) (self::$locale && $user ? (self::$locale)($user) : app()->getLocale());
        $short = strtolower(substr($locale, 0, 2));
        $installed = array_map(fn ($l) => strtolower(trim($l)), (array) config('rekhu.locales', ['en']));

        return in_array($short, $installed, true) ? $short : ($installed[0] ?? 'en');
    }

    /** The limits a day for a tenant and knower: the config's, with the resolver's on top. */
    public static function limits(?array $tenant, string $knower): array
    {
        $limits = (array) config("rekhu.limits.{$knower}", []);
        if (self::$limits) {
            $limits = array_replace($limits, (array) (self::$limits)($tenant, $knower));
        }

        return $limits;
    }

    /** The app's own price for a call, when it set one. */
    public static function price(?array $tenant, string $knower, string $action, array $meta): ?float
    {
        $price = self::$price ? (self::$price)($tenant, $knower, $action, $meta) : null;

        return $price === null ? null : (float) $price;
    }

    /**
     * Whether a webhook Rekhu sent is genuine: the X-Rekhu-Signature header (t=…,v1=…) over the raw body, with the
     * webhook_secret the call answered, and not older than $tolerance seconds.
     */
    public static function verifyWebhook(string $body, ?string $signature, string $secret, int $tolerance = 300): bool
    {
        if (! $signature || ! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $signature, $m)) {
            return false;
        }
        if (abs(time() - (int) $m[1]) > $tolerance) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $m[1].'.'.$body, $secret), $m[2]);
    }

    public static function installed(string $knower): bool
    {
        return in_array($knower, (array) config('rekhu.knowers', []), true);
    }

    public static function machines(): Machines
    {
        return new Machines(app(Client::class));
    }

    public static function seshat(): Seshat
    {
        return new Seshat(app(Client::class));
    }

    /** Any knower by its name, for knowers this version has no class for yet. */
    public static function knower(string $knower): Knower
    {
        return new Knower(app(Client::class), $knower);
    }
}
