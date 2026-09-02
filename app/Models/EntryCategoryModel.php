<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pivot table for `cms_entries` <-> `cms_categories` (composite PK
 * `[entry_id, category_id]`, no autoincrement `id` — see
 * 2026-06-11-070007_CreateCmsEntryRelations.php). Plain `Model`, not
 * `BaseAuditableModel`: individual pivot-row churn isn't meaningful audit
 * trail on its own, the entry-level change is what's audited.
 *
 * Extracted from EntryService::replaceEntryCategories() (LAYER-03), which
 * used to run this delete+insertBatch pair directly against
 * `Database::connect()`.
 */
class EntryCategoryModel extends Model
{
    protected $table = 'cms_entry_categories';
    protected $primaryKey = 'entry_id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;

    protected $allowedFields = ['entry_id', 'category_id', 'sort_order'];

    /**
     * Replace every category pivot row for an entry with exactly the given
     * ordered set of category ids.
     *
     * @param list<int> $categoryIds
     */
    public function replaceForEntry(int $entryId, array $categoryIds): void
    {
        $this->where('entry_id', $entryId)->delete();

        if ($categoryIds === []) {
            return;
        }

        $rows = [];
        foreach ($categoryIds as $order => $categoryId) {
            $rows[] = [
                'entry_id'    => $entryId,
                'category_id' => $categoryId,
                'sort_order'  => $order,
            ];
        }

        $this->insertBatch($rows);
    }
}
