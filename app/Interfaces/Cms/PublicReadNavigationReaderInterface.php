<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use dcardenasl\Ci4ApiCore\Support\ApiResult;

interface PublicReadNavigationReaderInterface
{
    public function show(string $locale): ApiResult;
}
