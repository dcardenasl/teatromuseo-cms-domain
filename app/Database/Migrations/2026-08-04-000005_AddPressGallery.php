<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the local legacy press gallery to the canonical Prensa page.
 *
 * @cms-content-data-migration
 */
final class AddPressGallery extends Migration
{
    public function up(): void
    {
        $page = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->join('cms_languages l', 'l.id = pt.language_id AND l.code = \'es\'')
            ->where('pt.slug', 'prensa')
            ->get()
            ->getRowArray();
        $gallery = $this->blockId('gallery');
        $galleryItem = $this->blockId('gallery_item');
        if ($page === null || $gallery === null || $galleryItem === null) {
            return;
        }

        $pageId = (int) $page['id'];
        $existing = $this->db->table('cms_block_instances')
            ->where(['owner_type' => 'page', 'owner_id' => $pageId, 'block_id' => $gallery, 'parent_instance_id' => null])
            ->get()
            ->getRowArray();
        if ($existing !== null) {
            return;
        }

        $this->db->transStart();
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $gallery,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => 3,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'presentation_mode' => 'modal_preview',
                'columns' => '4',
                'gap' => 'medium',
                'css_class' => 'public-press-gallery',
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $galleryId = (int) $this->db->insertID();

        $languages = $this->db->table('cms_languages')->get()->getResultArray();
        $galleryTranslations = [
            'es' => ['title' => 'Galería', 'description' => 'Conoce parte de la experiencia del TeatroMuseo en nuestras visitas guiadas.'],
            'en' => ['title' => 'Gallery', 'description' => 'Discover part of the TeatroMuseo experience through our guided tours.'],
            'fr' => ['title' => 'Galerie', 'description' => 'Découvrez une partie de l’expérience du TeatroMuseo à travers nos visites guidées.'],
            'pt' => ['title' => 'Galeria', 'description' => 'Conheça parte da experiência do TeatroMuseo em nossas visitas guiadas.'],
        ];
        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            if (! isset($galleryTranslations[$code])) {
                continue;
            }
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $galleryId,
                'language_id' => (int) $language['id'],
                'block_data' => json_encode($galleryTranslations[$code], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => 1,
            ]);
        }

        for ($index = 1; $index <= 8; $index++) {
            $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $url = '/assets/images/press-gallery/visita-guiada-' . $number . '.jpg';
            $this->db->table('cms_block_instances')->insert([
                'block_id' => $galleryItem,
                'owner_type' => 'page',
                'owner_id' => $pageId,
                'parent_instance_id' => $galleryId,
                'sort_order' => $index,
                'column_index' => null,
                'is_active' => 1,
                'block_config' => json_encode([
                    'image' => ['source_kind' => 'external_url', 'file_id' => null, 'url' => $url],
                ], JSON_UNESCAPED_SLASHES),
            ]);
            $childId = (int) $this->db->insertID();
            foreach ($languages as $language) {
                $this->db->table('cms_block_instance_translations')->insert([
                    'instance_id' => $childId,
                    'language_id' => (int) $language['id'],
                    'block_data' => json_encode([
                        'alt' => 'Visita guiada al TeatroMuseo, imagen ' . $index,
                        'caption' => 'Visita guiada ' . $index,
                        'link_url' => '',
                        'link_label' => '',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_published' => 1,
                ]);
            }
        }
        $this->db->transComplete();
    }

    public function down(): void
    {
        // Intentionally non-destructive: the gallery is content and the local
        // image assets must not be removed by a migration rollback.
    }

    private function blockId(string $key): ?int
    {
        $row = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $key)->get()->getRowArray();
        return $row === null ? null : (int) $row['id'];
    }
}
