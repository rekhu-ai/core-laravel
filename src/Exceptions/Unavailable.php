<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** Rekhu or a service behind it could not answer; the client already tried again (502, 503, 529). */
class Unavailable extends RekhuException {}
