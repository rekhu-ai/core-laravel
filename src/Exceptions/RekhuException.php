<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

use RuntimeException;

/** A knower could not answer: the status Rekhu gave (0 when the call was not made) and its error type. */
class RekhuException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly string $type = 'api_error')
    {
        parent::__construct($message, $status);
    }
}
