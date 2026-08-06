<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes the public People collection page and main-menu link.
 *
 * @cms-content-data-migration
 */
final class RemovePeoplePublicNavigation extends Migration
{
    public function up(): void
    {
        $collection = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'personas')
            ->get()
            ->getRowArray();

        if (! is_array($collection)) {
            return;
        }

        $collectionId = (int) $collection['id'];
        $menuItems = $this->db->table('cms_menu_items mi')
            ->select('mi.id')
            ->join('cms_menus m', 'm.id = mi.menu_id')
            ->where('m.menu_key', 'main')
            ->where('mi.collection_id', $collectionId)
            ->get()
            ->getResultArray();

        foreach ($menuItems as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $this->db->table('cms_menu_item_translations')->where('menu_item_id', $itemId)->delete();
            $this->db->table('cms_menu_items')->where('id', $itemId)->delete();
        }

        $pages = $this->db->table('cms_pages')
            ->select('id')
            ->where('collection_id', $collectionId)
            ->get()
            ->getResultArray();

        foreach ($pages as $page) {
            $pageId = (int) ($page['id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }
            // Keep the collection and entries for internal editorial
            // references, but remove this index from the public site.
            $this->db->table('cms_pages')->where('id', $pageId)->update([
                'status' => 'draft',
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        // The public page and menu item remain intentionally removed.
    }
}
