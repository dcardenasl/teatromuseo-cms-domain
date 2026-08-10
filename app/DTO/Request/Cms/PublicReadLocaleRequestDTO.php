<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/** Common path contract for locale-scoped CMS PublicRead resources. */
readonly class PublicReadLocaleRequestDTO extends BaseRequestDTO
{
    public string $locale;

    public function rules(): array
    {
        return ['locale' => 'required|regex_match[/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i]'];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->locale = strtolower(trim((string) ($data['locale'] ?? '')));
    }

    /** @return array{locale:string} */
    public function toArray(): array
    {
        return ['locale' => $this->locale];
    }
}
