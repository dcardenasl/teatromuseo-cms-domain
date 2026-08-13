<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

readonly class PublicReadEntryShowRequestDTO extends BaseRequestDTO
{
    public string $locale;
    public string $collection;
    public string $slug;
    public bool $previewRequested;
    public ?string $previewExpires;
    public ?string $previewSig;

    public function rules(): array
    {
        return [
            'locale' => 'required|regex_match[/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i]',
            'collection' => 'required|string|max_length[50]',
            'slug' => 'required|string|max_length[255]',
            'preview' => 'permit_empty|in_list[0,1]',
            'preview_expires' => 'permit_empty|string|max_length[20]',
            'preview_sig' => 'permit_empty|string|max_length[64]',
        ];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->locale = strtolower(trim((string) ($data['locale'] ?? '')));
        $this->collection = trim((string) ($data['collection'] ?? ''));
        $this->slug = trim((string) ($data['slug'] ?? ''));
        $this->previewRequested = ($data['preview'] ?? null) === '1';
        $this->previewExpires = isset($data['preview_expires']) && is_string($data['preview_expires'])
            ? $data['preview_expires']
            : null;
        $this->previewSig = isset($data['preview_sig']) && is_string($data['preview_sig'])
            ? $data['preview_sig']
            : null;
    }

    /** @return array{locale:string,collection:string,slug:string,preview:bool,preview_expires:?string,preview_sig:?string} */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'collection' => $this->collection,
            'slug' => $this->slug,
            'preview' => $this->previewRequested,
            'preview_expires' => $this->previewExpires,
            'preview_sig' => $this->previewSig,
        ];
    }
}
