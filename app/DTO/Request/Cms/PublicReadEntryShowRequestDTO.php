<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

readonly class PublicReadEntryShowRequestDTO extends BaseRequestDTO
{
    public string $locale;
    public string $collection;
    public string $slug;

    public function rules(): array
    {
        return [
            'locale' => 'required|regex_match[/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i]',
            'collection' => 'required|string|max_length[50]',
            'slug' => 'required|string|max_length[255]',
        ];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->locale = strtolower(trim((string) ($data['locale'] ?? '')));
        $this->collection = trim((string) ($data['collection'] ?? ''));
        $this->slug = trim((string) ($data['slug'] ?? ''));
    }

    /** @return array{locale:string,collection:string,slug:string} */
    public function toArray(): array
    {
        return ['locale' => $this->locale, 'collection' => $this->collection, 'slug' => $this->slug];
    }
}
