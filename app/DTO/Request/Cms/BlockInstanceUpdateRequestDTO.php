<?php

declare(strict_types=1);

namespace App\DTO\Request\Cms;

use CodeIgniter\Validation\ValidationInterface;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'BlockInstanceUpdateRequest')]
readonly class BlockInstanceUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'block_id', type: 'integer', nullable: true)]
    public ?int $block_id;
    #[OA\Property(description: 'owner_type', type: 'string', nullable: true)]
    public ?string $owner_type;
    #[OA\Property(description: 'owner_id', type: 'integer', nullable: true)]
    public ?int $owner_id;
    #[OA\Property(description: 'parent_instance_id', type: 'integer', nullable: true)]
    public ?int $parent_instance_id;
    #[OA\Property(description: 'sort_order', type: 'integer', nullable: true)]
    public ?int $sort_order;
    #[OA\Property(description: 'column_index', type: 'integer', nullable: true)]
    public ?int $column_index;
    #[OA\Property(description: 'is_active', type: 'boolean', nullable: true)]
    public ?bool $is_active;
    /** @var array<string, mixed>|null */
    #[OA\Property(description: 'block_config', type: 'object', nullable: true)]
    public ?array $block_config;

    /**
     * @var array<array{language_id: int, block_data: array<string, mixed>, is_published: bool}>
     */
    #[OA\Property(description: 'translations', type: 'array', items: new OA\Items(type: 'object'))]
    public ?array $translations;

    /** @var array<string, mixed> */
    private array $mappedFields;

    /**
     * Mirrors BaseRequestDTO's own promoted `$validation` property. Needed
     * because our validate() override (see below) must access the injected
     * ValidationInterface, and the parent's copy is private to BaseRequestDTO.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, private readonly ?ValidationInterface $validationInstance = null)
    {
        parent::__construct($data, $validationInstance);
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'block_id' => 'permit_empty|is_natural_no_zero|is_not_unique[cms_content_blocks.id]',
            'owner_type' => 'permit_empty|string|in_list[page,entry]',
            'owner_id' => 'permit_empty|integer',
            'parent_instance_id' => 'permit_empty|is_natural_no_zero|is_not_unique[cms_block_instances.id]',
            'sort_order' => 'permit_empty|integer',
            'column_index' => 'permit_empty|integer',
            'is_active' => 'permit_empty|boolean_like',
            'block_config' => 'permit_empty',
            'translations' => 'permit_empty',
            'translations.*.language_id' => 'required_with[translations]|is_natural_no_zero|is_not_unique[cms_languages.id]',
            'translations.*.block_data' => 'permit_empty|array',
            'translations.*.is_published' => 'required_with[translations]|boolean_like',
        ];
    }

    /**
     * CodeIgniter's Validation engine can't tell "translations has zero items"
     * apart from "translations is absent": for either case it synthesizes a
     * single phantom value keyed by the literal, unexpanded field name (e.g.
     * `translations.*.language_id` => null) and runs the per-item rules
     * against it, so `required_with[translations]` wrongly treats that
     * synthetic null as "translations is present" and fails on it. A block
     * instance with no translations yet (e.g. freshly created, or re-saved
     * via the admin's reorder endpoint) legitimately sends `translations: []`
     * and must not be rejected for it. Dropping the `translations.*` rules
     * when there are no items to validate sidesteps the framework's fallback
     * — the "for each translation" rules are vacuously satisfied when there
     * are no translations.
     *
     * This re-implements BaseRequestDTO::validate() rather than delegating to
     * it, since the parent's ValidationInterface is private to that class.
     *
     * @param array<string, mixed> $data
     */
    protected function validate(array $data): void
    {
        $rules = $this->rules();
        if ($rules === []) {
            return;
        }

        $validation = $this->validationInstance;
        if (! $validation instanceof ValidationInterface) {
            throw new \RuntimeException(
                static::class . ' has validation rules but no ValidationInterface was injected. '
                . 'Pass a ValidationInterface as the second constructor argument, or use RequestDtoFactory.'
            );
        }

        $validation->reset();

        $translations = $data['translations'] ?? null;
        if (!is_array($translations) || $translations === []) {
            foreach (array_keys($rules) as $field) {
                if (str_starts_with((string) $field, 'translations.*')) {
                    unset($rules[$field]);
                }
            }
        }

        if (!$validation->setRules($rules, $this->messages())->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $validation->getErrors()
            );
        }
    }

    /**
     * NOT NULL columns (block_id, owner_type, owner_id, sort_order,
     * is_active) never accept an explicit null — treated the same as
     * omitting the field, matching the DB constraint. Nullable columns
     * (parent_instance_id, column_index, block_config) preserve an
     * explicit null so it reaches toArray() and actually clears the
     * column — the bug this fixes is array_filter() silently dropping
     * every null, which made it impossible to ever clear a nullable field
     * via update.
     *
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->block_id = array_key_exists('block_id', $data) && $data['block_id'] !== null && $data['block_id'] !== '' ? (int) $data['block_id'] : null;
        $this->owner_type = array_key_exists('owner_type', $data) && $data['owner_type'] !== null ? (string) $data['owner_type'] : null;
        $this->owner_id = array_key_exists('owner_id', $data) && $data['owner_id'] !== null && $data['owner_id'] !== '' ? (int) $data['owner_id'] : null;
        $this->parent_instance_id = array_key_exists('parent_instance_id', $data) && $data['parent_instance_id'] !== null && $data['parent_instance_id'] !== '' ? (int) $data['parent_instance_id'] : null;
        $this->sort_order = array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '' ? (int) $data['sort_order'] : null;
        $this->column_index = array_key_exists('column_index', $data) && $data['column_index'] !== null && $data['column_index'] !== '' ? (int) $data['column_index'] : null;
        $this->is_active = array_key_exists('is_active', $data) && $data['is_active'] !== null ? (bool) $data['is_active'] : null;

        $blockConfigRaw = array_key_exists('block_config', $data) ? $data['block_config'] : null;
        if (is_string($blockConfigRaw) && trim($blockConfigRaw) !== '') {
            $decoded = json_decode($blockConfigRaw, true);
            $blockConfigRaw = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        $this->block_config = is_array($blockConfigRaw) ? $blockConfigRaw : null;

        $this->translations = array_key_exists('translations', $data) ? (array) $data['translations'] : null;

        $mappedFields = [];
        if ($this->block_id !== null) {
            $mappedFields['block_id'] = $this->block_id;
        }
        if ($this->owner_type !== null) {
            $mappedFields['owner_type'] = $this->owner_type;
        }
        if ($this->owner_id !== null) {
            $mappedFields['owner_id'] = $this->owner_id;
        }
        if (array_key_exists('parent_instance_id', $data)) {
            $mappedFields['parent_instance_id'] = $this->parent_instance_id;
        }
        if ($this->sort_order !== null) {
            $mappedFields['sort_order'] = $this->sort_order;
        }
        if (array_key_exists('column_index', $data)) {
            $mappedFields['column_index'] = $this->column_index;
        }
        if ($this->is_active !== null) {
            $mappedFields['is_active'] = $this->is_active;
        }
        if (array_key_exists('block_config', $data)) {
            $mappedFields['block_config'] = $this->block_config;
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
