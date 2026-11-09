<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** The call was refused before anything ran, with what was wrong (422); nothing was charged. */
class InvalidRequest extends RekhuException {}
