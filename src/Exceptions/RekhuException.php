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

    /** The typed exception for an answer from Rekhu: catch the one you handle, or this class for all of them. */
    public static function from(int $status, string $type, string $message, ?array $safety = null): self
    {
        if ($type === 'refused_unsafe') {
            return new UnsafeFile($message, $status, $type, $safety);
        }
        $class = match (true) {
            $status === 401 => InvalidKey::class,
            $type === 'module_not_enabled' => ModuleNotEnabled::class,
            $type === 'region_unavailable' => RegionUnavailable::class,
            $type === 'app_limit' => AppPaused::class,
            $status === 402 => CreditExhausted::class,
            $status === 404 => FileNotFound::class,
            $status === 422 || $status === 400 => InvalidRequest::class,
            $status === 429 => RateLimited::class,
            $status >= 500 => Unavailable::class,
            default => self::class,
        };

        return new $class($message, $status, $type);
    }
}
