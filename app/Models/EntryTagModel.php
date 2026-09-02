<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pivot table for `cms_entries` <-> `cms_tags` (composite PK
 * `[entry_id, tag_id]`, no autoincrement `id` — see
 * 2026-06-11-070007_CreateCmsEntryRelations.php). Plain `Model`, not
 * `BaseAuditableModel`: individual pivot-row churn isn't meaningful audit
 * trail on its own, the entry-level change is what's audited.
 *
 * Extracted from EntryService::replaceEntryTags() (LAYER-03), which used to
 * run this delete+insertBatch pair directly against `Database::connect()`.
 */
class EntryTagModel extends Model
{
    protected $table = 'cms_entry_tags';
    protected $primaryKey = 'entry_id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;

    protected $allowedFields = ['entry_id', 'tag_id'];

    /**
     * Replace every tag pivot row for an entry with exactly the given set
     * of tag ids.
     *
     * @param list<int> $tagIds
     */
    public function replaceForEntry(int $entryId, array $tagIds): void
    {
        $this->where('entry_id', $entryId)->delete();

        if ($tagIds === []) {
            return;
        }

        $rows = [];
        foreach ($tagIds as $tagId) {
            $rows[] = ['entry_id' => $entryId, 'tag_id' => $tagId];
        }

        $this->insertBatch($rows);
    }
}
