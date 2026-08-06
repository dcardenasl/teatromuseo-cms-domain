<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Consolidates duplicate team grids created by older idempotent seed runs.
 *
 * The canonical block is the one at the configured institutional position
 * (the current database uses 3024). Existing children are moved there before
 * the duplicate root is removed, so the admin child listing and public page
 * use the same source of truth.
 *
 * @cms-content-data-migration
 */
final class ConsolidateAboutTeamBlocks extends Migration
{
    public function up(): void
    {
        $pageId = $this->pageId();
        $teamTypeId = $this->teamTypeId();
        if ($pageId === null || $teamTypeId === null) {
            return;
        }

        $roots = $this->db->table('cms_block_instances')
            ->where('block_id', $teamTypeId)
            ->where('owner_type', 'page')
            ->where('owner_id', $pageId)
            ->where('parent_instance_id IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (count($roots) < 2) {
            return;
        }

        $target = null;
        foreach ($roots as $root) {
            if ((int) ($root['id'] ?? 0) === 3024) {
                $target = $root;
                break;
            }
        }
        $target ??= $roots[0];
        $targetId = (int) $target['id'];

        foreach ($roots as $root) {
            $sourceId = (int) ($root['id'] ?? 0);
            if ($sourceId <= 0 || $sourceId === $targetId) {
                continue;
            }

            $this->db->table('cms_block_instances')
                ->where('parent_instance_id', $sourceId)
                ->update(['parent_instance_id' => $targetId]);
            $this->db->table('cms_block_instance_translations')
                ->where('instance_id', $sourceId)
                ->delete();
            $this->db->table('cms_block_instances')
                ->where('id', $sourceId)
                ->delete();
        }

        $this->db->table('cms_block_instances')
            ->where('id', $targetId)
            ->update(['sort_order' => 8, 'is_active' => 1]);
    }

    public function down(): void
    {
        // Editorial consolidation is intentionally not reversed.
    }

    private function pageId(): ?int
    {
        $row = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->whereIn('pt.slug', ['quienes-somos', 'nosotros', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function teamTypeId(): ?int
    {
        $row = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'team_grid')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }
}
