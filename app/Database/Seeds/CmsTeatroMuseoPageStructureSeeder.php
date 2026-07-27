<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Creates collection index pages for the TeatroMuseo catalog.
 *
 * This is structure only: it creates published index pages and their listing
 * blocks, but never creates entries or imports legacy content.
 */
final class CmsTeatroMuseoPageStructureSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $languages = $this->languageIds();
        $blockIds = $this->blockIds(['page_header', 'collection_listing']);

        if (! isset($languages['es'], $languages['en'], $blockIds['page_header'], $blockIds['collection_listing'])) {
            echo "CmsTeatroMuseoPageStructureSeeder: missing languages or block types; skipping.\n";
            return;
        }

        foreach ($this->definitions() as $definition) {
            $collectionId = $this->collectionId($definition['collection_key']);
            if ($collectionId === null) {
                echo "CmsTeatroMuseoPageStructureSeeder: collection '{$definition['collection_key']}' not found; skipping.\n";
                continue;
            }

            $pageId = $this->upsertCollectionIndexPage($collectionId, $definition['sort_order']);
            if ($pageId === null) {
                continue;
            }

            $this->upsertPageTranslations($pageId, $definition, $languages);
            $this->upsertPageBlocks($pageId, $collectionId, $definition, $languages, $blockIds);
        }
    }

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')->whereIn('code', ['es', 'en'])->get()->getResultArray();
        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }

    /** @param list<string> $keys @return array<string, int> */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->whereIn('block_key', $keys)->get()->getResultArray();
        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row['block_key']] = (int) $row['id'];
        }

        return $ids;
    }

    private function collectionId(string $key): ?int
    {
        $row = $this->db->table('cms_collections')->select('id')->where('collection_key', $key)->get()->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function upsertCollectionIndexPage(int $collectionId, int $sortOrder): ?int
    {
        return $this->upsertCollectionIndexPageRecord($collectionId, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
            'sort_order' => $sortOrder,
            'sitemap_priority' => '0.8',
            'sitemap_changefreq' => 'monthly',
            'is_in_sitemap' => 1,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array{collection_key: string, es_name: string, en_name: string, es_slug: string, en_slug: string, sort_order: int} $definition
     * @param array<string, int> $languages
     */
    private function upsertPageTranslations(int $pageId, array $definition, array $languages): void
    {
        $translations = [
            'es' => [
                'slug' => $definition['es_slug'],
                'title' => $definition['es_name'],
                'excerpt' => 'Catálogo editorial de ' . strtolower($definition['es_name']) . '.',
                'meta_title' => $definition['es_name'] . ' | TeatroMuseo',
                'meta_description' => 'Explora el catálogo de ' . strtolower($definition['es_name']) . '.',
                'robots' => 'index, follow',
            ],
            'en' => [
                'slug' => $definition['en_slug'],
                'title' => $definition['en_name'],
                'excerpt' => 'Editorial catalog of ' . strtolower($definition['en_name']) . '.',
                'meta_title' => $definition['en_name'] . ' | TeatroMuseo',
                'meta_description' => 'Explore the ' . strtolower($definition['en_name']) . ' catalog.',
                'robots' => 'index, follow',
            ],
        ];

        foreach ($translations as $language => $translation) {
            $languageId = $languages[$language] ?? null;
            if ($languageId === null) {
                continue;
            }

            $this->upsertRecord('cms_page_translations', [
                'page_id' => $pageId,
                'language_id' => $languageId,
            ], $translation);
        }
    }

    /**
     * @param array{collection_key: string, es_name: string, en_name: string, es_slug: string, en_slug: string, sort_order: int} $definition
     * @param array<string, int> $languages
     * @param array<string, int> $blockIds
     */
    private function upsertPageBlocks(int $pageId, int $collectionId, array $definition, array $languages, array $blockIds): void
    {
        $headerId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $blockIds['page_header'],
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => 1,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode(['bg_color' => 'bg-slate-100', 'css_class' => ''], JSON_UNESCAPED_SLASHES),
        ]);

        $listingId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $blockIds['collection_listing'],
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => 2,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'collection_id' => $collectionId,
                'per_page' => 12,
                'order_by' => 'published_at',
                'order_direction' => 'desc',
                'layout_variant' => 'cards',
                'show_search' => 1,
                'show_categories' => 1,
                'show_tags' => 0,
                'show_excerpt' => 1,
                'show_date' => 1,
                'show_button' => 1,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $blockTranslations = [
            'page_header' => [
                'es' => ['heading' => $definition['es_name'], 'subheading' => 'Catálogo editorial TeatroMuseo.'],
                'en' => ['heading' => $definition['en_name'], 'subheading' => 'TeatroMuseo editorial catalog.'],
            ],
            'collection_listing' => [
                'es' => ['intro_title' => $definition['es_name'], 'empty_message' => 'No hay entradas publicadas todavía.'],
                'en' => ['intro_title' => $definition['en_name'], 'empty_message' => 'No published entries yet.'],
            ],
        ];

        foreach (['page_header' => $headerId, 'collection_listing' => $listingId] as $blockKey => $instanceId) {
            if ($instanceId === null) {
                continue;
            }

            foreach ($blockTranslations[$blockKey] as $language => $data) {
                $languageId = $languages[$language] ?? null;
                if ($languageId === null) {
                    continue;
                }

                $this->upsertRecord('cms_block_instance_translations', [
                    'instance_id' => $instanceId,
                    'language_id' => $languageId,
                ], [
                    'block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_published' => 1,
                ]);
            }
        }
    }

    /** @return list<array{collection_key: string, es_name: string, en_name: string, es_slug: string, en_slug: string, sort_order: int}> */
    private function definitions(): array
    {
        return [
            ['collection_key' => 'noticias', 'es_name' => 'Noticias', 'en_name' => 'News', 'es_slug' => 'noticias', 'en_slug' => 'news', 'sort_order' => 10],
            ['collection_key' => 'companias', 'es_name' => 'Compañías', 'en_name' => 'Companies', 'es_slug' => 'companias', 'en_slug' => 'companies', 'sort_order' => 20],
            ['collection_key' => 'personas', 'es_name' => 'Personas', 'en_name' => 'People', 'es_slug' => 'personas', 'en_slug' => 'people', 'sort_order' => 30],
            ['collection_key' => 'obras', 'es_name' => 'Obras', 'en_name' => 'Works', 'es_slug' => 'obras', 'en_slug' => 'works', 'sort_order' => 40],
            ['collection_key' => 'videos', 'es_name' => 'Videos', 'en_name' => 'Videos', 'es_slug' => 'videos', 'en_slug' => 'videos', 'sort_order' => 50],
            ['collection_key' => 'festivales', 'es_name' => 'Festivales', 'en_name' => 'Festivals', 'es_slug' => 'festivales', 'en_slug' => 'festivals', 'sort_order' => 60],
            ['collection_key' => 'exposiciones', 'es_name' => 'Exposiciones', 'en_name' => 'Exhibitions', 'es_slug' => 'exposiciones', 'en_slug' => 'exhibitions', 'sort_order' => 70],
            ['collection_key' => 'cursos', 'es_name' => 'Cursos', 'en_name' => 'Courses', 'es_slug' => 'cursos', 'en_slug' => 'courses', 'sort_order' => 80],
            ['collection_key' => 'publicaciones', 'es_name' => 'Publicaciones', 'en_name' => 'Publications', 'es_slug' => 'publicaciones', 'en_slug' => 'publications', 'sort_order' => 90],
        ];
    }
}
