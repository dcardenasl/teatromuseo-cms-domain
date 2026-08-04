<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Seeds\Concerns\CollectionBlockPresets;
use CodeIgniter\Database\Migration;

/**
 * Aligns the Noticias collection with the public media contract.
 *
 * The old template created an optional `image` block with no image. The cover
 * is already stored canonically on cms_entry_translations, so the public
 * reader projects it into the gallery instead of duplicating the file
 * reference in cms_block_instances.
 *
 * @cms-content-data-migration
 */
final class NormalizeNewsCoverGallery extends Migration
{
    public function up(): void
    {
        $collectionResult = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'noticias')
            ->get();
        $collection = $collectionResult !== false ? $collectionResult->getRowArray() : null;

        if (! is_array($collection)) {
            return;
        }

        $template = CollectionBlockPresets::news()['block_template'];
        $this->db->table('cms_collections')
            ->where('id', (int) $collection['id'])
            ->update([
                'block_template' => json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

        $legacyImageBlocksResult = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_config')
            ->join('cms_entries e', "e.id = i.owner_id AND i.owner_type = 'entry'")
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('e.collection_id', (int) $collection['id'])
            ->where('b.block_key', 'image')
            ->where('i.is_active', 1)
            ->get();
        $legacyImageBlocks = $legacyImageBlocksResult !== false ? $legacyImageBlocksResult->getResultArray() : [];

        $ids = [];
        foreach ($legacyImageBlocks as $block) {
            $config = is_string($block['block_config'] ?? null)
                ? json_decode((string) $block['block_config'], true)
                : $block['block_config'];
            $image = is_array($config['image'] ?? null) ? $config['image'] : [];
            $hasFile = is_numeric($image['file_id'] ?? null) && (int) $image['file_id'] > 0;
            $hasUrl = trim((string) ($image['url'] ?? '')) !== '';

            if (! $hasFile && ! $hasUrl) {
                $ids[] = (int) $block['id'];
            }
        }

        if ($ids !== []) {
            $this->db->table('cms_block_instances')->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void
    {
        // Forward-only: restoring empty legacy image blocks would recreate the
        // placeholder that this migration removes.
    }
}
