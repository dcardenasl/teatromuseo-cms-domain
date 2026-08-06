<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Moves Press from About into the Press & Media menu group.
 *
 * @cms-content-data-migration
 */
final class MovePressMenuItem extends Migration
{
    /** @var array<string, string> */
    private array $labels = [
        'es' => 'Prensa',
        'en' => 'Press',
        'fr' => 'Presse',
        'pt' => 'Imprensa',
    ];

    public function up(): void
    {
        $page = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'press')
            ->get()
            ->getRowArray();

        if (! is_array($page)) {
            return;
        }

        foreach (['main', 'footer'] as $menuKey) {
            $this->moveOrCreateItem($menuKey, (int) $page['id']);
        }
    }

    public function down(): void
    {
        // The Press item remains grouped under Press & Media intentionally.
    }

    private function moveOrCreateItem(string $menuKey, int $pageId): void
    {
        $menu = $this->db->table('cms_menus')
            ->select('id')
            ->where('menu_key', $menuKey)
            ->get()
            ->getRowArray();
        if (! is_array($menu)) {
            return;
        }

        $menuId = (int) $menu['id'];
        $group = $this->db->table('cms_menu_items mi')
            ->select('mi.id')
            ->join('cms_menu_item_translations mit', 'mit.menu_item_id = mi.id')
            ->join('cms_languages l', 'l.id = mit.language_id')
            ->where('mi.menu_id', $menuId)
            ->where('mi.parent_id IS NULL', null, false)
            ->where('l.code', 'es')
            ->where('mit.label', 'Prensa y Medios')
            ->get()
            ->getRowArray();
        if (! is_array($group)) {
            return;
        }

        $groupId = (int) $group['id'];
        $item = $this->db->table('cms_menu_items')
            ->select('id')
            ->where('menu_id', $menuId)
            ->where('page_id', $pageId)
            ->get()
            ->getRowArray();

        if (is_array($item)) {
            $itemId = (int) $item['id'];
            $this->db->table('cms_menu_items')->where('id', $itemId)->update([
                'parent_id' => $groupId,
                'sort_order' => 4,
                'is_active' => 1,
            ]);
        } else {
            $this->db->table('cms_menu_items')->insert([
                'menu_id' => $menuId,
                'parent_id' => $groupId,
                'link_type' => 'page',
                'page_id' => $pageId,
                'link_target' => '_self',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $itemId = (int) $this->db->insertID();
        }

        if ($itemId <= 0) {
            return;
        }

        $languages = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', array_keys($this->labels))
            ->get()
            ->getResultArray();
        foreach ($languages as $language) {
            $languageId = (int) ($language['id'] ?? 0);
            $code = (string) ($language['code'] ?? '');
            if ($languageId <= 0 || ! isset($this->labels[$code])) {
                continue;
            }
            $translation = $this->db->table('cms_menu_item_translations')
                ->select('id')
                ->where('menu_item_id', $itemId)
                ->where('language_id', $languageId)
                ->get()
                ->getRowArray();
            $payload = [
                'menu_item_id' => $itemId,
                'language_id' => $languageId,
                'label' => $this->labels[$code],
                'custom_url' => null,
            ];
            if (is_array($translation)) {
                $this->db->table('cms_menu_item_translations')
                    ->where('id', (int) $translation['id'])
                    ->update($payload);
            } else {
                $this->db->table('cms_menu_item_translations')->insert($payload);
            }
        }
    }
}
