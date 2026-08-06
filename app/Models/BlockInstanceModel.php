<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\BlockInstanceEntity;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\Traits\Filterable;
use dcardenasl\Ci4ApiCore\Models\Traits\Searchable;

class BlockInstanceModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table = 'cms_block_instances';
    protected $primaryKey = 'id';
    protected $returnType = BlockInstanceEntity::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;

    protected $allowedFields = ['block_id', 'owner_type', 'owner_id', 'parent_instance_id', 'sort_order', 'column_index', 'is_active', 'block_config'];

    /** @var array<int, string> */
    protected array $searchableFields = [];

    /** @var array<int, string> */
    protected array $filterableFields = ['id', 'owner_type', 'owner_id'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'created_at', 'owner_type', 'owner_id', 'sort_order', 'column_index'];

    protected $validationRules = [
        'block_id' => 'required|is_natural_no_zero|is_not_unique[cms_content_blocks.id]',
        'owner_type' => 'required|string|in_list[page,entry]',
        'owner_id' => 'required|integer',
        'parent_instance_id' => 'permit_empty|is_natural_no_zero|is_not_unique[cms_block_instances.id]',
        'sort_order' => 'required|integer',
        'column_index' => 'permit_empty|integer',
        'is_active' => 'permit_empty|boolean_like',
        'block_config' => 'permit_empty',
    ];

    /**
     * Block instances joined with their block type's `block_key`/`schema_definition`
     * — needed by translation-audit callers to know which fields of a block's
     * `block_data` are actually translatable. Extracted from
     * BlockInstanceTranslationAuditor (LAYER-03), which used to run this join
     * via `Database::connect()` directly instead of going through this model.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllWithBlockType(?string $ownerType = null, ?int $ownerId = null, bool $onlyActive = false): array
    {
        $builder = $this->builder('cms_block_instances i')
            ->select('i.*, b.block_key, b.schema_definition')
            ->join('cms_content_blocks b', 'b.id = i.block_id');

        if ($onlyActive) {
            $builder->where('i.is_active', 1);
        }
        if ($ownerType !== null) {
            $builder->where('i.owner_type', $ownerType);
        }
        if ($ownerId !== null) {
            $builder->where('i.owner_id', $ownerId);
        }

        $query = $builder->orderBy('i.sort_order', 'ASC')->get();

        /** @var list<array<string, mixed>> $rows */
        $rows = $query ? $query->getResultArray() : [];

        return $rows;
    }

    /**
     * Single block instance joined with its block type's `block_key`/`schema_definition`.
     *
     * @return array<string, mixed>|null
     */
    public function findOneWithBlockType(int $id): ?array
    {
        $query = $this->builder('cms_block_instances i')
            ->select('i.*, b.block_key, b.schema_definition')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.id', $id)
            ->limit(1)
            ->get();

        $row = $query ? $query->getRowArray() : null;

        return is_array($row) ? $row : null;
    }

    /**
     * All block instances of a given block type, as plain arrays. Extracted
     * from BlockTypeService::getUsages()/afterUpdate() (LAYER-03), which
     * used to run this single-table select via an injected BaseConnection
     * directly instead of through this model.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForBlockType(int $blockId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->asArray()->where('block_id', $blockId)->findAll();

        return $rows;
    }
}
