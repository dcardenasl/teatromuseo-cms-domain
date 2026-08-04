<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Renames the public Editorial section without changing its stable slugs. */
final class RenamePublicationsToEditorial extends Migration
{
    /** @var array<string, string> */
    private array $labels = [
        'es' => 'Editorial',
        'en' => 'Editorial',
        'fr' => 'Éditorial',
        'pt' => 'Editorial',
    ];

    public function up(): void
    {
        $this->renameCollection();
        $this->renamePage();
        $this->renameMenus();
    }

    public function down(): void
    {
        // The public section is intentionally Editorial going forward.
    }

    private function renameCollection(): void
    {
        $collection = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'editoriales')
            ->get()
            ->getRowArray();

        if (! is_array($collection)) {
            return;
        }

        $this->updateTranslations('cms_collection_translations', 'collection_id', (int) $collection['id'], 'name', 'listing_title', 'default_meta_title');
    }

    private function renamePage(): void
    {
        $page = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'publications')
            ->get()
            ->getRowArray();

        if (! is_array($page)) {
            return;
        }

        $pageId = (int) $page['id'];
        $this->updateTranslations('cms_page_translations', 'page_id', $pageId, 'title', 'meta_title');

        $instances = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('owner_type', 'page')
            ->where('owner_id', $pageId)
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $instanceId = (int) ($instance['id'] ?? 0);
            if ($instanceId <= 0) {
                continue;
            }

            $translations = $this->db->table('cms_block_instance_translations')
                ->where('instance_id', $instanceId)
                ->get()
                ->getResultArray();

            foreach ($translations as $translation) {
                $language = $this->languageCode((int) ($translation['language_id'] ?? 0));
                if ($language === null || ! isset($this->labels[$language])) {
                    continue;
                }

                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data)) {
                    continue;
                }

                $changed = false;
                if (array_key_exists('heading', $data)) {
                    $data['heading'] = $this->labels[$language];
                    $changed = true;
                }
                if (array_key_exists('intro_title', $data)) {
                    $data['intro_title'] = $this->labels[$language];
                    $changed = true;
                }

                if ($changed) {
                    $this->db->table('cms_block_instance_translations')
                        ->where('id', (int) $translation['id'])
                        ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            }
        }
    }

    private function renameMenus(): void
    {
        $page = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'publications')
            ->get()
            ->getRowArray();

        if (! is_array($page)) {
            return;
        }

        $items = $this->db->table('cms_menu_items')
            ->select('id')
            ->where('page_id', (int) $page['id'])
            ->get()
            ->getResultArray();

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $translations = $this->db->table('cms_menu_item_translations')
                ->where('menu_item_id', $itemId)
                ->get()
                ->getResultArray();
            foreach ($translations as $translation) {
                $language = $this->languageCode((int) ($translation['language_id'] ?? 0));
                if ($language !== null && isset($this->labels[$language])) {
                    $this->db->table('cms_menu_item_translations')
                        ->where('id', (int) $translation['id'])
                        ->update(['label' => $this->labels[$language]]);
                }
            }
        }
    }

    private function updateTranslations(string $table, string $foreignKey, int $foreignId, string ...$fields): void
    {
        $translations = $this->db->table($table)
            ->where($foreignKey, $foreignId)
            ->get()
            ->getResultArray();

        foreach ($translations as $translation) {
            $language = $this->languageCode((int) ($translation['language_id'] ?? 0));
            if ($language === null || ! isset($this->labels[$language])) {
                continue;
            }
            $payload = [];
            foreach ($fields as $field) {
                $payload[$field] = $field === 'meta_title' || $field === 'default_meta_title'
                    ? $this->labels[$language] . ' | TeatroMuseo'
                    : $this->labels[$language];
            }
            $this->db->table($table)->where('id', (int) $translation['id'])->update($payload);
        }
    }

    private function languageCode(int $languageId): ?string
    {
        $row = $this->db->table('cms_languages')->select('code')->where('id', $languageId)->get()->getRowArray();
        return is_array($row) && is_string($row['code'] ?? null) ? $row['code'] : null;
    }
}
