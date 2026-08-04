<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

readonly class PublicEntryIndexRequestDTO extends BaseRequestDTO
{
    public string $lang;
    public string $collection_key;
    public int $page;
    public int $per_page;
    public ?string $category;
    public ?int $category_id;
    public ?string $tag;
    public ?string $q;
    public string $order_by;
    public string $order_direction;
    public ?string $listing_field;
    public ?string $filter_by;
    public ?string $filter_value;
    public string $filter_operator;
    /** @var list<string> */
    public array $projection_fields;
    public bool $include_listing_content;

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'lang'           => 'required|string|max_length[10]',
            'collection_key' => 'required|string|max_length[50]',
            'page'           => 'permit_empty|is_natural_no_zero',
            'per_page'       => 'permit_empty|is_natural_no_zero|less_than[101]',
            'limit'          => 'permit_empty|is_natural_no_zero|less_than[101]',
            'category'       => 'permit_empty|string|max_length[150]',
            'category_id'    => 'permit_empty|is_natural_no_zero',
            'tag'            => 'permit_empty|string|max_length[100]',
            'q'              => 'permit_empty|string|max_length[255]',
            'order_by'       => 'permit_empty|regex_match[/^(published_at|sort_order|created_at|title|field:[a-z][a-z0-9_]{0,49}|field:(entry|block|taxonomy)\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)?)$/]',
            'order_direction' => 'permit_empty|in_list[asc,desc,ASC,DESC]',
            'include'         => 'permit_empty|in_list[listing_content]',
            'fields'          => 'permit_empty|string|max_length[2000]',
            'filter_by'       => 'permit_empty|string|max_length[100]',
            'filter_value'    => 'permit_empty|string|max_length[255]',
            'filter_operator' => 'permit_empty|in_list[equals,contains]',
        ];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->lang           = (string) ($data['lang'] ?? '');
        $this->collection_key = (string) ($data['collection_key'] ?? '');
        $this->page           = isset($data['page']) && $data['page'] !== '' ? (int) $data['page'] : 1;
        $perPage              = $data['per_page'] ?? ($data['limit'] ?? 20);
        $this->per_page       = $perPage !== '' ? (int) $perPage : 20;
        $this->category       = isset($data['category']) && $data['category'] !== '' ? (string) $data['category'] : null;
        $this->category_id    = isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null;
        $this->tag            = isset($data['tag']) && $data['tag'] !== '' ? (string) $data['tag'] : null;
        $this->q              = isset($data['q']) && $data['q'] !== '' ? (string) $data['q'] : null;
        $rawOrderBy           = (string) ($data['order_by'] ?? 'sort_order');
        $this->listing_field  = str_starts_with($rawOrderBy, 'field:') ? substr($rawOrderBy, 6) : null;
        $this->order_by       = $this->listing_field !== null ? 'listing_field' : $rawOrderBy;
        $direction            = strtoupper((string) ($data['order_direction'] ?? 'ASC'));
        $this->order_direction = $direction === 'DESC' ? 'DESC' : 'ASC';
        $this->include_listing_content = (string) ($data['include'] ?? '') === 'listing_content';
        $rawFields = is_string($data['fields'] ?? null) ? explode(',', (string) $data['fields']) : [];
        $this->projection_fields = array_values(array_filter(array_map(
            static fn (string $field): string => trim($field),
            $rawFields,
        ), static fn (string $field): bool => $field !== '' && preg_match('/^(entry|taxonomy|block)\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)?$/', $field) === 1));
        $rawFilterBy = trim((string) ($data['filter_by'] ?? ''));
        $this->filter_by = $rawFilterBy !== '' ? $rawFilterBy : null;
        $rawFilterValue = trim((string) ($data['filter_value'] ?? ''));
        $this->filter_value = $rawFilterValue !== '' ? $rawFilterValue : null;
        $rawFilterOperator = (string) ($data['filter_operator'] ?? 'equals');
        $this->filter_operator = in_array($rawFilterOperator, ['equals', 'contains'], true)
            ? $rawFilterOperator
            : 'equals';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lang'           => $this->lang,
            'collection_key' => $this->collection_key,
            'page'           => $this->page,
            'per_page'       => $this->per_page,
            'category'       => $this->category,
            'category_id'    => $this->category_id,
            'tag'            => $this->tag,
            'q'              => $this->q,
            'order_by'       => $this->order_by,
            'listing_field'  => $this->listing_field,
            'filter_by'      => $this->filter_by,
            'filter_value'   => $this->filter_value,
            'filter_operator' => $this->filter_operator,
            'fields'         => $this->projection_fields,
            'order_direction' => $this->order_direction,
            'include'         => $this->include_listing_content ? 'listing_content' : null,
        ];
    }
}
