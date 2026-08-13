<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use dcardenasl\Ci4ApiCore\Support\ApiResult;

/**
 * Composite PublicRead reader: redirect check + page (with blocks) for a
 * given path, the pair Web's route resolver always requests together.
 * See ADR 006.
 */
interface PublicReadPageBootstrapReaderInterface
{
    /** @param list<string> $fields */
    public function show(string $locale, string $path, array $fields = []): ApiResult;
}
