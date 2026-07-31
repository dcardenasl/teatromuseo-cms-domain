<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use App\Libraries\Cms\CmsEnums;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'EntryUpdateRequest')]
readonly class EntryUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'collection_id', type: 'integer', nullable: true)]
    public ?int $collection_id;
    #[OA\Property(description: 'author_id', type: 'integer', nullable: true)]
    public ?int $author_id;
    #[OA\Property(description: 'workflow_status', type: 'string', nullable: true, enum: ['draft', 'in_review', 'approved', 'published', 'archived'])]
    public ?string $workflow_status;
    #[OA\Property(description: 'published_at', type: 'string', format: 'date-time', nullable: true)]
    public ?string $published_at;
    #[OA\Property(description: 'scheduled_at', type: 'string', format: 'date-time', nullable: true)]
    public ?string $scheduled_at;
    #[OA\Property(description: 'is_featured', type: 'boolean', nullable: true)]
    public ?bool $is_featured;
    #[OA\Property(description: 'view_count', type: 'integer', nullable: true)]
    public ?int $view_count;
    #[OA\Property(description: 'sort_order', type: 'integer', nullable: true)]
    public ?int $sort_order;
    #[OA\Property(description: 'sitemap_priority', type: 'number', format: 'float', nullable: true)]
    public ?float $sitemap_priority;
    #[OA\Property(description: 'sitemap_changefreq', type: 'string', nullable: true, enum: ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])]
    public ?string $sitemap_changefreq;
    #[OA\Property(description: 'is_in_sitemap', type: 'boolean', nullable: true)]
    public ?bool $is_in_sitemap;

    /**
     * @var array<array{language_id: int, slug: string, title: string, excerpt?: string, featured_image?: array{source_kind?: string, file_id?: int|null, url?: string|null}, meta_title?: string, meta_description?: string, og_image?: array{source_kind?: string, file_id?: int|null, url?: string|null}, og_type?: string, canonical_url?: string, robots?: string, schema_data?: array<mixed>}>|null
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
            'collection_id' => 'permit_empty|integer',
            'author_id' => 'permit_empty|integer',
            'workflow_status' => 'permit_empty|' . CmsEnums::inListRule(CmsEnums::WORKFLOW_STATUS),
            'published_at' => 'permit_empty|valid_date',
            'scheduled_at' => 'permit_empty|valid_date',
            'is_featured' => 'permit_empty|boolean_like',
            'view_count' => 'permit_empty|integer',
            'sort_order' => 'permit_empty|integer',
            'sitemap_priority' => 'permit_empty|decimal',
            'sitemap_changefreq' => 'permit_empty|' . CmsEnums::inListRule(CmsEnums::SITEMAP_CHANGEFREQ),
            'is_in_sitemap' => 'permit_empty|boolean_like',
            'translations' => 'permit_empty',
            'translations.*.language_id' => 'required_with[translations]|is_natural_no_zero',
            'translations.*.slug' => 'required_with[translations]|string|max_length[150]',
            'translations.*.title' => 'required_with[translations]|string|max_length[255]',
            'translations.*.excerpt' => 'permit_empty|string|max_length[500]',
            'translations.*.featured_image' => 'permit_empty',
            'translations.*.featured_file_id' => 'permit_empty|integer',
            'translations.*.featured_image_url' => 'permit_empty|string|max_length[2048]',
            'translations.*.meta_title' => 'permit_empty|string|max_length[255]',
            'translations.*.meta_description' => 'permit_empty|string|max_length[500]',
            'translations.*.og_image' => 'permit_empty',
            'translations.*.og_image_file_id' => 'permit_empty|integer',
            'translations.*.og_type' => 'permit_empty|string|max_length[50]',
            'translations.*.canonical_url' => 'permit_empty|string|max_length[500]',
            'translations.*.robots' => 'permit_empty|string|max_length[100]',
            'translations.*.schema_data' => 'permit_empty',
        ];
    }

    /**
     * NOT NULL columns (collection_id, workflow_status, is_featured,
     * view_count, sort_order, is_in_sitemap) never accept an explicit
     * null — treated the same as omitting the field, matching the DB
     * constraint. Nullable columns (author_id, published_at, scheduled_at,
     * sitemap_priority, sitemap_changefreq) preserve an explicit null so it
     * reaches toArray() and actually clears the column — the bug this
     * fixes is array_filter() silently dropping every null, which made it
     * impossible to ever clear a nullable field via update.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->collection_id = array_key_exists('collection_id', $data) && $data['collection_id'] !== null && $data['collection_id'] !== '' ? (int) $data['collection_id'] : null;
        $this->author_id = array_key_exists('author_id', $data) && $data['author_id'] !== null && $data['author_id'] !== '' ? (int) $data['author_id'] : null;
        $this->workflow_status = array_key_exists('workflow_status', $data) && $data['workflow_status'] !== null ? (string) $data['workflow_status'] : null;
        $this->published_at = array_key_exists('published_at', $data) && $data['published_at'] !== null && $data['published_at'] !== '' ? (string) $data['published_at'] : null;
        $this->scheduled_at = array_key_exists('scheduled_at', $data) && $data['scheduled_at'] !== null && $data['scheduled_at'] !== '' ? (string) $data['scheduled_at'] : null;
        $this->is_featured = array_key_exists('is_featured', $data) && $data['is_featured'] !== null ? (bool) $data['is_featured'] : null;
        $this->view_count = array_key_exists('view_count', $data) && $data['view_count'] !== null && $data['view_count'] !== '' ? (int) $data['view_count'] : null;
        $this->sort_order = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;
        $this->sitemap_priority = array_key_exists('sitemap_priority', $data) && $data['sitemap_priority'] !== null && $data['sitemap_priority'] !== '' ? (float) $data['sitemap_priority'] : null;
        $this->sitemap_changefreq = array_key_exists('sitemap_changefreq', $data) && $data['sitemap_changefreq'] !== null && $data['sitemap_changefreq'] !== '' ? (string) $data['sitemap_changefreq'] : null;
        $this->is_in_sitemap = array_key_exists('is_in_sitemap', $data) && $data['is_in_sitemap'] !== null ? (bool) $data['is_in_sitemap'] : null;
        $this->translations = array_key_exists('translations', $data) ? $data['translations'] : null;

        $mappedFields = [];
        if ($this->collection_id !== null) {
            $mappedFields['collection_id'] = $this->collection_id;
        }
        if (array_key_exists('author_id', $data)) {
            $mappedFields['author_id'] = $this->author_id;
        }
        if ($this->workflow_status !== null) {
            $mappedFields['workflow_status'] = $this->workflow_status;
        }
        if (array_key_exists('published_at', $data)) {
            $mappedFields['published_at'] = $this->published_at;
        }
        if (array_key_exists('scheduled_at', $data)) {
            $mappedFields['scheduled_at'] = $this->scheduled_at;
        }
        if ($this->is_featured !== null) {
            $mappedFields['is_featured'] = $this->is_featured;
        }
        if ($this->view_count !== null) {
            $mappedFields['view_count'] = $this->view_count;
        }
        if ($this->sort_order !== null) {
            $mappedFields['sort_order'] = $this->sort_order;
        }
        if (array_key_exists('sitemap_priority', $data)) {
            $mappedFields['sitemap_priority'] = $this->sitemap_priority;
        }
        if (array_key_exists('sitemap_changefreq', $data)) {
            $mappedFields['sitemap_changefreq'] = $this->sitemap_changefreq;
        }
        if ($this->is_in_sitemap !== null) {
            $mappedFields['is_in_sitemap'] = $this->is_in_sitemap;
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
