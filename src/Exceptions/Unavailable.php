<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** Rekhu or a service behind it could not answer (5xx); tried again first when the platform was unreachable. */
class Unavailable extends RekhuException {}
