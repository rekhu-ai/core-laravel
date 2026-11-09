<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** The key is missing, revoked, or belongs to another Rekhu platform (401). */
class InvalidKey extends RekhuException {}
