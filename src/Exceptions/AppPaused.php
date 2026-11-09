<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** The application is paused or at its monthly limit in the Rekhu account (402 app_limit). */
class AppPaused extends RekhuException {}
