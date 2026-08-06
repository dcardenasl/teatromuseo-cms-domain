<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Keeps the existing admin deep link /17/blocks/3024/edit valid.
 *
 * @cms-content-data-migration
 */
final class RestoreAboutTeamBlockCompatibilityId extends Migration
{
    private const COMPATIBILITY_ID = 3024;

    public function up(): void
    {
        $source = $this->db->table('cms_block_instances')
            ->where('id', self::COMPATIBILITY_ID)
            ->get()
            ->getRowArray();

        if (is_array($source)) {
            return;
        }

        $source = $this->db->table('cms_block_instances i')
            ->select('i.*')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'page')
            ->where('i.owner_id', 17)
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', 'team_grid')
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getRowArray();

        if (! is_array($source)) {
            return;
        }

        $copy = $source;
        $copy['id'] = self::COMPATIBILITY_ID;
        $copy['sort_order'] = 8;
        $this->db->table('cms_block_instances')->insert($copy);

        $translations = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', (int) $source['id'])
            ->get()
            ->getResultArray();
        foreach ($translations as $translation) {
            unset($translation['id']);
            $translation['instance_id'] = self::COMPATIBILITY_ID;
            $this->db->table('cms_block_instance_translations')->insert($translation);
        }

        $this->db->table('cms_block_instances')
            ->where('parent_instance_id', (int) $source['id'])
            ->update(['parent_instance_id' => self::COMPATIBILITY_ID]);
        $this->db->table('cms_block_instance_translations')
            ->where('instance_id', (int) $source['id'])
            ->delete();
        $this->db->table('cms_block_instances')
            ->where('id', (int) $source['id'])
            ->delete();
    }

    public function down(): void
    {
        // Preserve the compatibility ID and editorial content on rollback.
    }
}
