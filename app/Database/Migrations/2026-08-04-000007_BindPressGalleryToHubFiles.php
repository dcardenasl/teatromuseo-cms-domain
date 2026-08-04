<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Binds the press gallery to Hub originals so the public serializer can expose
 * the generated responsive variants (thumb, sm, md and lg).
 *
 * @cms-content-data-migration
 */
final class BindPressGalleryToHubFiles extends Migration
{
    /** @var list<int> */
    private const HUB_FILE_IDS = [1764, 1765, 1766, 1767, 1768, 1769, 1770, 1771];

    public function up(): void
    {
        $page = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->join('cms_languages l', 'l.id = pt.language_id AND l.code = \'es\'')
            ->where('pt.slug', 'prensa')
            ->get()
            ->getRowArray();
        if ($page === null) {
            return;
        }

        $gallery = $this->db->table('cms_block_instances parent')
            ->select('parent.id')
            ->join('cms_content_blocks block', 'block.id = parent.block_id AND block.block_key = \'gallery\'')
            ->where(['parent.owner_type' => 'page', 'parent.owner_id' => (int) $page['id'], 'parent.parent_instance_id' => null])
            ->get()
            ->getRowArray();
        if ($gallery === null) {
            return;
        }

        $children = $this->db->table('cms_block_instances child')
            ->select('child.id, child.sort_order')
            ->join('cms_content_blocks block', 'block.id = child.block_id AND block.block_key = \'gallery_item\'')
            ->where(['child.owner_type' => 'page', 'child.owner_id' => (int) $page['id'], 'child.parent_instance_id' => (int) $gallery['id']])
            ->orderBy('child.sort_order', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($children as $index => $child) {
            $hubFileId = self::HUB_FILE_IDS[$index] ?? null;
            if ($hubFileId === null) {
                continue;
            }

            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $fallbackUrl = '/assets/images/press-gallery/visita-guiada-' . $number . '.jpg';
            $instanceId = (int) $child['id'];
            $this->db->table('cms_block_instances')
                ->where('id', $instanceId)
                ->update([
                    'block_config' => json_encode([
                        'image' => [
                            'source_kind' => 'hub_file',
                            'file_id' => $hubFileId,
                            'url' => $fallbackUrl,
                        ],
                    ], JSON_UNESCAPED_SLASHES),
                ]);

            if (! $this->db->tableExists('cms_file_references')) {
                continue;
            }

            $this->db->table('cms_file_references')
                ->where(['resource_type' => 'block_instance', 'resource_id' => $instanceId, 'role' => 'block_config.image'])
                ->delete();
            $this->db->table('cms_file_references')->insert([
                'hub_file_id' => $hubFileId,
                'resource_type' => 'block_instance',
                'resource_id' => $instanceId,
                'block_instance_id' => $instanceId,
                'role' => 'block_config.image',
                'label' => 'Galería de Prensa - imagen ' . ($index + 1),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the Hub files and restore no external dependency on rollback.
    }
}
