<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'BlockTypeUpdateRequest')]
readonly class BlockTypeUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'block_key', type: 'string', nullable: true)]
    public ?string $block_key;
    #[OA\Property(description: 'name', type: 'string', nullable: true)]
    public ?string $name;
    #[OA\Property(description: 'description', type: 'string', nullable: true)]
    public ?string $description;
    #[OA\Property(description: 'category', type: 'string', nullable: true)]
    public ?string $category;
    #[OA\Property(description: 'icon', type: 'string', nullable: true)]
    public ?string $icon;
    /** @var array<string, mixed>|null */
    #[OA\Property(description: 'schema_definition', type: 'object', nullable: true)]
    public ?array $schema_definition;
    #[OA\Property(description: 'supports_pages', type: 'boolean', nullable: true)]
    public ?bool $supports_pages;
    #[OA\Property(description: 'supports_entries', type: 'boolean', nullable: true)]
    public ?bool $supports_entries;
    #[OA\Property(description: 'is_container', type: 'boolean', nullable: true)]
    public ?bool $is_container;
    #[OA\Property(description: 'is_active', type: 'boolean', nullable: true)]
    public ?bool $is_active;
    #[OA\Property(description: 'sort_order', type: 'integer', nullable: true)]
    public ?int $sort_order;

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'block_key' => 'permit_empty|string|max_length[255]',
            'name' => 'permit_empty|string|max_length[255]',
            'description' => 'permit_empty|string',
            'category' => 'permit_empty|string|max_length[255]',
            'icon' => 'permit_empty|string|max_length[255]',
            'schema_definition' => 'permit_empty|permit_empty',
            'supports_pages' => 'permit_empty|boolean_like',
            'supports_entries' => 'permit_empty|boolean_like',
            'is_container' => 'permit_empty|boolean_like',
            'is_active' => 'permit_empty|boolean_like',
            'sort_order' => 'permit_empty|integer',
        ];
    }

    /**
     * NOT NULL columns (block_key, name, category, schema_definition,
     * supports_pages, supports_entries, is_container, is_active, sort_order)
     * never accept an explicit null — treated the same as omitting the
     * field, matching the DB constraint. Nullable columns (description,
     * icon) preserve an explicit null so it reaches toArray() and actually
     * clears the column — the bug this fixes is array_filter() silently
     * dropping every null, which made it impossible to ever clear a
     * nullable field via update.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->block_key = array_key_exists('block_key', $data) && $data['block_key'] !== null ? (string) $data['block_key'] : null;
        $this->name = array_key_exists('name', $data) && $data['name'] !== null ? (string) $data['name'] : null;
        $this->description = array_key_exists('description', $data) && $data['description'] !== null && $data['description'] !== '' ? (string) $data['description'] : null;
        $this->category = array_key_exists('category', $data) && $data['category'] !== null ? (string) $data['category'] : null;
        $this->icon = array_key_exists('icon', $data) && $data['icon'] !== null && $data['icon'] !== '' ? (string) $data['icon'] : null;
        $this->schema_definition = array_key_exists('schema_definition', $data) && $data['schema_definition'] !== null ? (array) $data['schema_definition'] : null;
        $this->supports_pages = array_key_exists('supports_pages', $data) && $data['supports_pages'] !== null ? (bool) $data['supports_pages'] : null;
        $this->supports_entries = array_key_exists('supports_entries', $data) && $data['supports_entries'] !== null ? (bool) $data['supports_entries'] : null;
        $this->is_container = array_key_exists('is_container', $data) && $data['is_container'] !== null ? (bool) $data['is_container'] : null;
        $this->is_active = array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : null;
        $this->sort_order = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;

        $mappedFields = [];
        if ($this->block_key !== null) {
            $mappedFields['block_key'] = $this->block_key;
        }
        if ($this->name !== null) {
            $mappedFields['name'] = $this->name;
        }
        if (array_key_exists('description', $data)) {
            $mappedFields['description'] = $this->description;
        }
        if ($this->category !== null) {
            $mappedFields['category'] = $this->category;
        }
        if (array_key_exists('icon', $data)) {
            $mappedFields['icon'] = $this->icon;
        }
        if ($this->schema_definition !== null) {
            $mappedFields['schema_definition'] = $this->schema_definition;
        }
        if ($this->supports_pages !== null) {
            $mappedFields['supports_pages'] = $this->supports_pages;
        }
        if ($this->supports_entries !== null) {
            $mappedFields['supports_entries'] = $this->supports_entries;
        }
        if ($this->is_container !== null) {
            $mappedFields['is_container'] = $this->is_container;
        }
        if ($this->is_active !== null) {
            $mappedFields['is_active'] = $this->is_active;
        }
        if ($this->sort_order !== null) {
            $mappedFields['sort_order'] = $this->sort_order;
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
