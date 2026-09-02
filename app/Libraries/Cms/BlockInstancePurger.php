<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Hard-deletes every block instance (and its translations) owned by a page
 * or entry.
 *
 * `cms_block_instances` has no soft-delete column (`BlockInstanceModel::
 * $useSoftDeletes = false`), so once an owner is gone its blocks must be
 * purged outright — leaving them behind turns them into orphans that still
 * count as "in use" for any Hub file they reference, blocking
 * `DELETE /files/{id}` with a 409 indefinitely for a file nothing can reach
 * anymore.
 *
 * `cms_file_references.block_instance_id` and `cms_block_instance_translations
 * .instance_id` are both declared `ON DELETE CASCADE` to `cms_block_instances.id`
 * (2026-06-11-070008_CreateCmsBlocks.php), so deleting the instance row alone
 * is enough to free any file it referenced — this class still deletes
 * translations explicitly first, mirroring the established convention in
 * `App\Database\Seeds\Concerns\IdempotentSeederSupport::resetPageBlocks()`,
 * rather than depending solely on the FK cascade.
 */
class BlockInstancePurger
{
    /** @var BaseConnection<mixed, mixed> */
    private BaseConnection $db;

    /**
     * @param BaseConnection<mixed, mixed>|null $db
     */
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @return int number of block instances purged
     */
    public function purgeForOwner(string $ownerType, int $ownerId): int
    {
        $result = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->get();
        $rows = $result ? $result->getResultArray() : [];

        if ($rows === []) {
            return 0;
        }

        $instanceIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);

        $this->db->table('cms_block_instance_translations')->whereIn('instance_id', $instanceIds)->delete();
        $this->db->table('cms_block_instances')->whereIn('id', $instanceIds)->delete();

        log_message(
            'info',
            '[BlockInstancePurger] Purged ' . count($instanceIds) . " block instance(s) for owner_type={$ownerType} owner_id={$ownerId}."
        );

        return count($instanceIds);
    }
}
