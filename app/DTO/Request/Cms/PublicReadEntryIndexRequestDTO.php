<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

/** Query contract for the collection-scoped public entry listing. */
readonly class PublicReadEntryIndexRequestDTO extends BaseRequestDTO
{
    public string $locale;
    public string $collection;
    public int $page;
    public int $perPage;
    public ?string $category;
    public ?int $categoryId;
    public ?string $tag;
    public ?string $search;
    public string $orderBy;
    public string $orderDirection;
    public ?string $filterBy;
    public ?string $filterValue;
    public string $filterOperator;
    public bool $includeListingContent;

    public function rules(): array
    {
        return [
            'locale' => 'required|regex_match[/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i]',
            'collection' => 'required|string|max_length[50]',
            'page' => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search' => 'permit_empty|string|max_length[255]',
            'category' => 'permit_empty|string|max_length[150]',
            'category_id' => 'permit_empty|is_natural_no_zero',
            'tag' => 'permit_empty|string|max_length[100]',
            'order_by' => 'permit_empty|regex_match[/^(published_at|sort_order|created_at|title|field:[a-z][a-z0-9_]{0,49}|field:(entry|block|taxonomy)\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)?)$/]',
            'order_direction' => 'permit_empty|in_list[asc,desc,upcoming,ASC,DESC,UPCOMING]',
            'filter_by' => 'permit_empty|string|max_length[100]',
            'filter_value' => 'permit_empty|string|max_length[255]',
            'filter_operator' => 'permit_empty|in_list[equals,contains]',
            'include' => 'permit_empty|in_list[listing_content]',
        ];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->locale = strtolower(trim((string) ($data['locale'] ?? '')));
        $this->collection = trim((string) ($data['collection'] ?? ''));
        $this->page = max(1, (int) ($data['page'] ?? 1));
        $this->perPage = min(100, max(1, (int) ($data['per_page'] ?? 20)));
        $this->category = ($data['category'] ?? '') !== '' ? trim((string) $data['category']) : null;
        $this->categoryId = ($data['category_id'] ?? '') !== '' ? (int) $data['category_id'] : null;
        $this->tag = ($data['tag'] ?? '') !== '' ? trim((string) $data['tag']) : null;
        $this->search = ($data['search'] ?? '') !== '' ? trim((string) $data['search']) : null;
        $this->orderBy = (string) ($data['order_by'] ?? 'sort_order');
        $direction = strtoupper((string) ($data['order_direction'] ?? 'ASC'));
        $this->orderDirection = match ($direction) {
            'DESC' => 'DESC',
            'UPCOMING' => 'UPCOMING',
            default => 'ASC',
        };
        $this->filterBy = ($data['filter_by'] ?? '') !== '' ? trim((string) $data['filter_by']) : null;
        $this->filterValue = ($data['filter_value'] ?? '') !== '' ? trim((string) $data['filter_value']) : null;
        $this->filterOperator = in_array((string) ($data['filter_operator'] ?? 'equals'), ['equals', 'contains'], true)
            ? (string) ($data['filter_operator'] ?? 'equals')
            : 'equals';
        $this->includeListingContent = (string) ($data['include'] ?? '') === 'listing_content';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'collection' => $this->collection,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'category' => $this->category,
            'category_id' => $this->categoryId,
            'tag' => $this->tag,
            'search' => $this->search,
            'order_by' => $this->orderBy,
            'order_direction' => $this->orderDirection,
            'filter_by' => $this->filterBy,
            'filter_value' => $this->filterValue,
            'filter_operator' => $this->filterOperator,
            'include' => $this->includeListingContent ? 'listing_content' : null,
        ];
    }
}
