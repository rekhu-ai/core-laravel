<?php

declare(strict_types=1);

namespace Rekhu\Core\Exceptions;

/** The account processes data in Europe only and this call cannot be served in the EU (403 region_unavailable). */
class RegionUnavailable extends RekhuException {}
