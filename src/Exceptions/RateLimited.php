<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** Too many calls at once; the client already tried again (429). */
class RateLimited extends RekhuException {}
