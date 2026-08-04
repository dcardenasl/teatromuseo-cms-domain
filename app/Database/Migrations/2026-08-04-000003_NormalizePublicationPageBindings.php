<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ensures the three public pages use collection ownership, not old filters.
 *
 * @cms-content-data-migration
 */
final class NormalizePublicationPageBindings extends Migration
{
    public function up(): void
    {
        foreach ([
            ['slug' => 'publicaciones', 'block' => 'collection_listing', 'collection' => 'editoriales'],
            ['slug' => 'prensa', 'block' => 'collection_timeline', 'collection' => 'prensa'],
            ['slug' => 'transparencia', 'block' => 'collection_listing', 'collection' => 'transparencia'],
        ] as $binding) {
            $this->normalize($binding['slug'], $binding['block'], $binding['collection']);
        }
    }

    public function down(): void
    {
        // Non-destructive data normalization; the inactive source collection
        // remains available for historical inspection.
    }

    private function normalize(string $pageSlug, string $blockKey, string $collectionKey): void
    {
        $page = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->join('cms_languages l', 'l.id = pt.language_id AND l.code = \'es\'')
            ->where('pt.slug', $pageSlug)
            ->get()
            ->getRowArray();
        $collection = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', $collectionKey)
            ->get()
            ->getRowArray();
        $block = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', $blockKey)
            ->get()
            ->getRowArray();
        if ($page === null || $collection === null || $block === null) {
            return;
        }

        $instances = $this->db->table('cms_block_instances')
            ->where(['owner_type' => 'page', 'owner_id' => (int) $page['id'], 'block_id' => (int) $block['id']])
            ->get()
            ->getResultArray();
        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            $config = is_array($config) ? $config : [];
            $config['collection_id'] = (int) $collection['id'];
            if ($blockKey === 'collection_timeline') {
                $config['collection_key'] = $collectionKey;
            } else {
                unset($config['collection_key']);
            }
            unset($config['category_id'], $config['category_slug']);
            $this->db->table('cms_block_instances')
                ->where('id', (int) $instance['id'])
                ->update(['block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }
}
