<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'MenuUpdateRequest')]
readonly class MenuUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'menu_key', type: 'string', nullable: true)]
    public ?string $menu_key;
    #[OA\Property(description: 'location', type: 'string', nullable: true)]
    public ?string $location;
    #[OA\Property(description: 'is_active', type: 'boolean', nullable: true)]
    public ?bool $is_active;

    /**
     * @var array<array{language_id: int, name: string}>|null
     */
    #[OA\Property(description: 'translations', type: 'array', items: new OA\Items(type: 'object'), nullable: true)]
    public ?array $translations;

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'menu_key'                  => 'permit_empty|string|max_length[50]',
            'location'                  => 'permit_empty|string|max_length[50]',
            'is_active'                 => 'permit_empty|boolean_like',
            'translations'              => 'permit_empty',
            'translations.*.language_id' => 'permit_empty|is_natural_no_zero',
            'translations.*.name'       => 'permit_empty|string|max_length[150]',
        ];
    }

    /**
     * NOT NULL columns (menu_key, location, is_active — cms_menus has no
     * nullable columns of its own) never accept an explicit null —
     * treated the same as omitting the field, matching the DB constraint.
     * The bug this fixes is array_filter() (used elsewhere in this DTO
     * family) silently dropping every null, which made it impossible to
     * ever clear a nullable field via update; this file already used
     * array_key_exists() to distinguish "provided" from "omitted", it is
     * only restructured here (if/else -> single ternary per property) for
     * consistency with the rest of the *UpdateRequestDTO family.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->menu_key = array_key_exists('menu_key', $data) && $data['menu_key'] !== null ? (string) $data['menu_key'] : null;
        $this->location = array_key_exists('location', $data) && $data['location'] !== null ? (string) $data['location'] : null;
        $this->is_active = array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : null;
        $this->translations = array_key_exists('translations', $data)
            ? $this->normalizeTranslations((array) $data['translations'])
            : null;

        $mappedFields = [];
        if ($this->menu_key !== null) {
            $mappedFields['menu_key'] = $this->menu_key;
        }
        if ($this->location !== null) {
            $mappedFields['location'] = $this->location;
        }
        if ($this->is_active !== null) {
            $mappedFields['is_active'] = $this->is_active;
        }
        if ($this->translations !== null) {
            $mappedFields['translations'] = $this->translations;
        }

        $this->mappedFields = $mappedFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mappedFields;
    }

    /**
     * @param array<mixed> $translations
     * @return array<int, array{language_id: int, name: string}>
     */
    private function normalizeTranslations(array $translations): array
    {
        return array_values(array_filter(
            array_map(
                static function (mixed $translation): ?array {
                    if (! is_array($translation)) {
                        return null;
                    }

                    $languageId = isset($translation['language_id']) ? (int) $translation['language_id'] : 0;
                    $name = trim((string) ($translation['name'] ?? ''));

                    if ($languageId <= 0 || $name === '') {
                        return null;
                    }

                    return [
                        'language_id' => $languageId,
                        'name' => $name,
                    ];
                },
                $translations
            ),
            static fn (?array $translation): bool => $translation !== null
        ));
    }
}
