<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/**
 * A file was not taken because it is not safe (422 refused_unsafe): a virus, a file that is not what its name says, or
 * something in it that could act on its own. $safety is the report with the findings, when Rekhu gave it.
 */
class UnsafeFile extends RekhuException
{
    public function __construct(string $message, int $status = 422, string $type = 'refused_unsafe', public readonly ?array $safety = null)
    {
        parent::__construct($message, $status, $type);
    }
}
