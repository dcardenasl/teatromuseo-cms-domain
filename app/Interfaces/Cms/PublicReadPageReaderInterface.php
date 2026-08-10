<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use App\DTO\Request\Cms\PublicReadPageRequestDTO;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

interface PublicReadPageReaderInterface
{
    /** @param list<string> $fields */
    public function index(PublicReadPageRequestDTO $request, array $fields): ApiResult;

    /** @param list<string> $fields */
    public function show(string $locale, string $path, array $fields): ApiResult;
}
