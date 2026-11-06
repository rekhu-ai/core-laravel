<?php

declare(strict_types=1);

namespace Rekhu\Core\Knowers;

use Rekhu\Core\Client;

/**
 * Rekhu Machines: knows printing and finishing machines. Machines are sent as a Rekhu machine profile: map your own
 * machine records to it (type, method, sheet_max_mm, gsm, colour_units, speed, makeready_minutes, …; see the README).
 */
class Machines extends Knower
{
    public function __construct(Client $client)
    {
        parent::__construct($client, 'machines');
    }

    /** The models the library knows, for a picker. Not charged. */
    public function models(): array
    {
        return $this->call('models')['models'] ?? [];
    }

    /** Which model a description is: match (with confidence), others, questions to ask when unsure. */
    public function identify(string $text): array
    {
        return $this->call('identify', ['text' => $text]);
    }

    /** The whole profile of a library model, each value with where it came from; the shop's known values win. */
    public function profile(string $model, array $known = []): array
    {
        return $this->call('profile', ['model' => $model, 'known' => (object) $known]);
    }

    /** Values that cannot be right and fields a price needs. explain: the findings told in the user's language. */
    public function check(array $machine, ?string $model = null, bool $explain = false): array
    {
        return $this->call('check', array_filter(['machine' => $machine, 'model' => $model, 'explain' => $explain ?: null], fn ($v) => $v !== null));
    }

    /**
     * The machines for a job, best first, each with why; the ones that cannot do it with why not.
     *
     * @param  array{width_mm: float, height_mm: float, quantity: int, bleed_mm?: float, colours_front?: int, colours_back?: int, gsm?: int}  $job
     * @param  list<array<string, mixed>>  $machines  profiles, each with a ref of your own and optionally hourly_rate
     */
    public function choose(array $job, array $machines): array
    {
        return $this->call('choose', ['job' => $job, 'machines' => $machines]);
    }

    /** A question about a machine, answered in the user's language. */
    public function explain(string $question, array $machine = [], ?string $model = null): array
    {
        return $this->call('explain', array_filter(['question' => $question, 'machine' => $machine ?: null, 'model' => $model]));
    }
}
