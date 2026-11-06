<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** One of the app's own limits a day is reached: per tenant or per user. Nothing was sent to Rekhu. */
class LimitReached extends RekhuException
{
    public function __construct(string $message, public readonly string $scope)
    {
        parent::__construct($message, 429, 'limit_reached');
    }
}
