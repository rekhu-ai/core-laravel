<?php

declare(strict_types=1);

namespace Rekhu\Core\Knowers;

use Illuminate\Contracts\Auth\Authenticatable;
use Rekhu\Core\Client;

/**
 * A knower on Rekhu, called for the signed-in user unless another user or a tenant is named:
 *
 *     Rekhu::machines()->for($user)->check($machine);
 *     Rekhu::machines()->forTenant('shop-42', 'Print shop 42')->choose($job, $machines);   // a queued job without a user
 */
class Knower
{
    protected ?Authenticatable $user = null;

    protected ?array $tenant = null;

    public function __construct(protected Client $client, protected string $knower) {}

    public function for(?Authenticatable $user): static
    {
        $copy = clone $this;
        $copy->user = $user;

        return $copy;
    }

    public function forTenant(string $id, ?string $name = null): static
    {
        $copy = clone $this;
        $copy->tenant = ['id' => $id, 'name' => $name ?? $id];

        return $copy;
    }

    /** Any action by its name, with the knower's own input, and a file (['name' => …, 'bytes' => …]) for an action that takes one. */
    public function call(string $action, array $input = [], ?array $file = null): array
    {
        return $this->client->call($this->knower, $action, $input, $this->user, $this->tenant, $file);
    }

    /** What the user did with an answer (its ref): accepted, corrected (with the correction) or rejected. */
    public function feedback(string $ref, string $outcome, ?array $correction = null): array
    {
        return $this->client->feedback($this->knower, $ref, $outcome, $correction);
    }
}
