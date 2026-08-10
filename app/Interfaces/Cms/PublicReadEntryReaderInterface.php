<?php

declare(strict_types=1);

namespace App\Interfaces\Cms;

use App\DTO\Request\Cms\PublicReadEntryIndexRequestDTO;
use App\DTO\Request\Cms\PublicReadEntryShowRequestDTO;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

interface PublicReadEntryReaderInterface
{
    /** @param list<string> $fields */
    public function index(PublicReadEntryIndexRequestDTO $request, array $fields): ApiResult;

    /** @param list<string> $fields */
    public function show(PublicReadEntryShowRequestDTO $request, array $fields): ApiResult;
}
