<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use App\Libraries\Cms\BlockTemplateNormalizer;
use App\Libraries\Cms\CmsEnums;
use App\Validators\BlockTemplateValidator;
use App\Validators\WizardConfigValidator;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'CollectionUpdateRequest')]
readonly class CollectionUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'collection_type', type: 'string', nullable: true)]
    public ?string $collection_type;
    #[OA\Property(description: 'collection_key', type: 'string', nullable: true)]
    public ?string $collection_key;
    #[OA\Property(description: 'is_active', type: 'boolean', nullable: true)]
    public ?bool $is_active;
    #[OA\Property(description: 'requires_approval', type: 'boolean', nullable: true)]
    public ?bool $requires_approval;
    #[OA\Property(description: 'enables_categories', type: 'boolean', nullable: true)]
    public ?bool $enables_categories;
    #[OA\Property(description: 'enables_tags', type: 'boolean', nullable: true)]
    public ?bool $enables_tags;
    #[OA\Property(description: 'default_sitemap_priority', type: 'number', format: 'float', nullable: true)]
    public ?float $default_sitemap_priority;
    #[OA\Property(description: 'default_changefreq', type: 'string', nullable: true, enum: ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])]
    public ?string $default_changefreq;
    #[OA\Property(description: 'sort_order', type: 'integer', nullable: true)]
    public ?int $sort_order;

    /**
     * @var array<string, mixed>|null
     */
    #[OA\Property(description: 'block_template', type: 'object', nullable: true)]
    public ?array $block_template;

    /**
     * @var array<string, mixed>|null
     */
    #[OA\Property(description: 'wizard_config', type: 'object', nullable: true)]
    public ?array $wizard_config;

    /**
     * @var array<array{language_id: int, slug?: string, name: string, description?: string, listing_title?: string, listing_intro?: string, default_meta_title?: string, default_meta_description?: string, entry_cta_label?: string}>|null
     */
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
            'collection_type' => 'permit_empty|string|max_length[50]|regex_match[/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/]',
            'collection_key' => 'permit_empty|string|max_length[50]',
            'is_active' => 'permit_empty|boolean_like',
            'requires_approval' => 'permit_empty|boolean_like',
            'enables_categories' => 'permit_empty|boolean_like',
            'enables_tags' => 'permit_empty|boolean_like',
            'default_sitemap_priority' => 'permit_empty|decimal',
            'default_changefreq' => 'permit_empty|' . CmsEnums::inListRule(CmsEnums::SITEMAP_CHANGEFREQ),
            'sort_order' => 'permit_empty|integer',
            'translations' => 'permit_empty',
            'translations.*.language_id' => 'required_with[translations]|is_natural_no_zero',
            'translations.*.slug' => 'required_with[translations]|string|min_length[1]|max_length[150]',
            'translations.*.name' => 'required_with[translations]|string|max_length[150]',
            'translations.*.description' => 'permit_empty|string',
            'translations.*.listing_title' => 'permit_empty|string|max_length[255]',
            'translations.*.listing_intro' => 'permit_empty|string',
            'translations.*.default_meta_title' => 'permit_empty|string|max_length[255]',
            'translations.*.default_meta_description' => 'permit_empty|string|max_length[500]',
            'translations.*.entry_cta_label' => 'permit_empty|string|max_length[100]',
        ];
    }

    /**
     * NOT NULL columns (collection_type, collection_key, is_active,
     * requires_approval, enables_categories, enables_tags, sort_order)
     * never accept an explicit null — treated the same as omitting the
     * field, matching the DB constraint. Nullable columns
     * (default_sitemap_priority, default_changefreq, block_template,
     * wizard_config) preserve an explicit null so it reaches toArray() and
     * actually clears the column — the bug this fixes is array_filter()
     * silently dropping every null, which made it impossible to ever clear
     * a nullable field via update. block_template/wizard_config are JSON
     * columns encoded manually (not via the Entity's json cast, which
     * would json_encode(null) into the literal string "null" instead of a
     * real SQL NULL) — a real PHP null is passed through so the repository
     * writes an actual NULL when the client explicitly clears the field.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->collection_type = array_key_exists('collection_type', $data) && $data['collection_type'] !== null ? (string) $data['collection_type'] : null;
        $this->collection_key = array_key_exists('collection_key', $data) && $data['collection_key'] !== null ? (string) $data['collection_key'] : null;
        $this->is_active = array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : null;
        $this->requires_approval = array_key_exists('requires_approval', $data) && $data['requires_approval'] !== null ? (bool) $data['requires_approval'] : null;
        $this->enables_categories = array_key_exists('enables_categories', $data) && $data['enables_categories'] !== null ? (bool) $data['enables_categories'] : null;
        $this->enables_tags = array_key_exists('enables_tags', $data) && $data['enables_tags'] !== null ? (bool) $data['enables_tags'] : null;
        $this->default_sitemap_priority = array_key_exists('default_sitemap_priority', $data) && $data['default_sitemap_priority'] !== null && $data['default_sitemap_priority'] !== '' ? (float) $data['default_sitemap_priority'] : null;
        $this->default_changefreq = array_key_exists('default_changefreq', $data) && $data['default_changefreq'] !== null && $data['default_changefreq'] !== '' ? (string) $data['default_changefreq'] : null;
        $this->sort_order = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;
        $this->block_template = array_key_exists('block_template', $data)
            ? $this->parseBlockTemplate($data['block_template'])
            : null;
        $this->wizard_config = array_key_exists('wizard_config', $data)
            ? $this->parseWizardConfig($data['wizard_config'])
            : null;
        $this->translations = array_key_exists('translations', $data) ? $data['translations'] : null;

        $mappedFields = [];
        if ($this->collection_type !== null) {
            $mappedFields['collection_type'] = $this->collection_type;
        }
        if ($this->collection_key !== null) {
            $mappedFields['collection_key'] = $this->collection_key;
        }
        if ($this->is_active !== null) {
            $mappedFields['is_active'] = $this->is_active;
        }
        if ($this->requires_approval !== null) {
            $mappedFields['requires_approval'] = $this->requires_approval;
        }
        if ($this->enables_categories !== null) {
            $mappedFields['enables_categories'] = $this->enables_categories;
        }
        if ($this->enables_tags !== null) {
            $mappedFields['enables_tags'] = $this->enables_tags;
        }
        if (array_key_exists('default_sitemap_priority', $data)) {
            $mappedFields['default_sitemap_priority'] = $this->default_sitemap_priority;
        }
        if (array_key_exists('default_changefreq', $data)) {
            $mappedFields['default_changefreq'] = $this->default_changefreq;
        }
        if ($this->sort_order !== null) {
            $mappedFields['sort_order'] = $this->sort_order;
        }
        if (array_key_exists('block_template', $data)) {
            $mappedFields['block_template'] = $this->block_template !== null
                ? json_encode($this->block_template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
        }
        if (array_key_exists('wizard_config', $data)) {
            $mappedFields['wizard_config'] = $this->wizard_config !== null
                ? json_encode($this->wizard_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
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
     * @param mixed $raw
     * @return array<string, mixed>|null
     */
    private function parseBlockTemplate(mixed $raw): ?array
    {
        $normalized = BlockTemplateNormalizer::normalize($raw);
        if ($normalized === null) {
            return null;
        }

        (new BlockTemplateValidator())->validate($normalized);

        return $normalized;
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>|null
     */
    private function parseWizardConfig(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        $wizardConfig = is_array($raw) ? $raw : null;

        (new WizardConfigValidator())->validate($wizardConfig);

        return $wizardConfig;
    }
}
