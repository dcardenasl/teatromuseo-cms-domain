<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CategoryUpdateRequest')]
readonly class CategoryUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'collection_id', type: 'integer', nullable: true)]
    public ?int $collection_id;
    #[OA\Property(description: 'parent_id', type: 'integer', nullable: true)]
    public ?int $parent_id;
    #[OA\Property(description: 'sort_order', type: 'integer', nullable: true)]
    public ?int $sort_order;
    #[OA\Property(description: 'is_active', type: 'boolean', nullable: true)]
    public ?bool $is_active;

    /** @var list<array<string, mixed>>|null */
    #[OA\Property(description: 'translations', type: 'array', items: new OA\Items(type: 'object'))]
    public ?array $translations;

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'collection_id' => 'permit_empty|integer',
            'parent_id' => 'permit_empty|integer',
            'sort_order' => 'permit_empty|integer',
            'is_active' => 'permit_empty|boolean_like',
            'translations' => 'permit_empty',
        ];
    }

    /**
     * NOT NULL columns (collection_id, sort_order, is_active) never
     * accept an explicit null — treated the same as omitting the field,
     * matching the DB constraint. The nullable column (parent_id)
     * preserves an explicit null so it reaches toArray() and actually
     * clears the column — the bug this fixes is array_filter() silently
     * dropping every null, which made it impossible to ever clear a
     * nullable field via update.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->collection_id = array_key_exists('collection_id', $data) && $data['collection_id'] !== null && $data['collection_id'] !== '' ? (int) $data['collection_id'] : null;
        $this->parent_id = array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null;
        $this->sort_order = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;
        $this->is_active = array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : null;
        $this->translations = array_key_exists('translations', $data) ? $data['translations'] : null;

        $mappedFields = [];
        if ($this->collection_id !== null) {
            $mappedFields['collection_id'] = $this->collection_id;
        }
        if (array_key_exists('parent_id', $data)) {
            $mappedFields['parent_id'] = $this->parent_id;
        }
        if ($this->sort_order !== null) {
            $mappedFields['sort_order'] = $this->sort_order;
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
}
