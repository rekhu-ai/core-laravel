<?php

declare(strict_types=1);

namespace Rekhu\Core\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A knower answered for a user of the app: who (the tenant and user), which knower and action, what Rekhu charged the
 * app for it (cost_usd) and the app's own price for it, when Rekhu::priceUsing() sets one. Charge your customer here.
 */
class KnowerUsed
{
    public function __construct(
        public readonly ?array $tenant,
        public readonly ?Authenticatable $user,
        public readonly string $knower,
        public readonly string $action,
        public readonly ?string $ref,
        public readonly int $modelCalls,
        public readonly float $costUsd,
        public readonly ?float $price,
        public readonly int $milliseconds,
    ) {}
}
