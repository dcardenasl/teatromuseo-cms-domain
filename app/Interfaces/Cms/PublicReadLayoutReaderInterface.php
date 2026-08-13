<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use dcardenasl\Ci4ApiCore\Support\ApiResult;

/**
 * Composite PublicRead reader: navigation + collections + settings, the
 * three pieces every page render needs regardless of slug. See ADR 006.
 */
interface PublicReadLayoutReaderInterface
{
    public function show(string $locale): ApiResult;
}
