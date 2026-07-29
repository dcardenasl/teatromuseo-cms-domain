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
        $blockIds = $this->blockIds([
            'page_header',
            'collection_listing',
            'catalog_item_header',
            'catalog_item_details',
            'catalog_item_content',
            'catalog_item_gallery',
            'event_item_header',
            'event_item_details',
            'event_item_content',
            'event_item_gallery',
        ]);

        if (! isset(
            $languages['es'],
            $languages['en'],
            $languages['fr'],
            $languages['pt'],
            $blockIds['page_header'],
            $blockIds['collection_listing'],
            $blockIds['catalog_item_header'],
            $blockIds['catalog_item_details'],
            $blockIds['catalog_item_content'],
            $blockIds['catalog_item_gallery'],
            $blockIds['event_item_header'],
            $blockIds['event_item_details'],
            $blockIds['event_item_content'],
            $blockIds['event_item_gallery']
        )) {
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
            $this->resetPageBlocks($pageId);
            $this->upsertPageBlocks($pageId, $collectionId, $definition, $languages, $blockIds);
        }

        foreach ($this->templateDefinitions() as $definition) {
            $pageId = $this->upsertTemplatePage($definition['page_type'], $definition['sort_order']);
            if ($pageId === null) {
                continue;
            }

            $this->upsertTemplatePageTranslations($pageId, $definition, $languages);
            $this->resetPageBlocks($pageId);
            $this->upsertTemplatePageBlocks($pageId, $definition, $languages, $blockIds);
        }
    }

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')->whereIn('code', ['es', 'en', 'fr', 'pt'])->get()->getResultArray();
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
            'fr' => [
                'slug' => $definition['fr_slug'],
                'title' => $definition['fr_name'],
                'excerpt' => 'Catalogue éditorial de ' . strtolower($definition['fr_name']) . '.',
                'meta_title' => $definition['fr_name'] . ' | TeatroMuseo',
                'meta_description' => 'Explorez le catalogue ' . strtolower($definition['fr_name']) . '.',
                'robots' => 'index, follow',
            ],
            'pt' => [
                'slug' => $definition['pt_slug'],
                'title' => $definition['pt_name'],
                'excerpt' => 'Catálogo editorial de ' . strtolower($definition['pt_name']) . '.',
                'meta_title' => $definition['pt_name'] . ' | TeatroMuseo',
                'meta_description' => 'Explore o catálogo de ' . strtolower($definition['pt_name']) . '.',
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
                'source_type' => 'cms_collection',
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
                'fallback_image_url' => 'https://images.unsplash.com/photo-1544928147-79a2dbc1f389?auto=format&fit=crop&w=600&q=80',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $blockTranslations = [
            'page_header' => [
                'es' => ['heading' => $definition['es_name'], 'subheading' => 'Catálogo editorial TeatroMuseo.'],
                'en' => ['heading' => $definition['en_name'], 'subheading' => 'TeatroMuseo editorial catalog.'],
                'fr' => ['heading' => $definition['fr_name'], 'subheading' => 'Catalogue éditorial TeatroMuseo.'],
                'pt' => ['heading' => $definition['pt_name'], 'subheading' => 'Catálogo editorial TeatroMuseo.'],
            ],
            'collection_listing' => [
                'es' => ['intro_title' => $definition['es_name'], 'empty_message' => 'No hay entradas publicadas todavía.'],
                'en' => ['intro_title' => $definition['en_name'], 'empty_message' => 'No published entries yet.'],
                'fr' => ['intro_title' => $definition['fr_name'], 'empty_message' => "Aucune entrée publiée pour l'instant."],
                'pt' => ['intro_title' => $definition['pt_name'], 'empty_message' => 'Ainda não há entradas publicadas.'],
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

    /**
     * @param array{page_type: string, es_name: string, en_name: string, fr_name: string, pt_name: string, es_slug: string, en_slug: string, fr_slug: string, pt_slug: string, es_excerpt: string, en_excerpt: string, fr_excerpt: string, pt_excerpt: string, sort_order: int, block_keys: list<string>} $definition
     * @param array<string, int> $languages
     * @param array<string, int> $blockIds
     */
    private function upsertTemplatePageBlocks(int $pageId, array $definition, array $languages, array $blockIds): void
    {
        $sortOrder = 1;

        foreach ($definition['block_keys'] as $blockKey) {
            $blockId = $blockIds[$blockKey] ?? null;
            if ($blockId === null) {
                continue;
            }

            $blockConfig = [];
            if ($blockKey === 'catalog_item_header') {
                $blockConfig['fallback_image_url'] = 'https://images.unsplash.com/photo-1544928147-79a2dbc1f389?auto=format&fit=crop&w=1600&q=80';
            } elseif ($blockKey === 'event_item_header') {
                $blockConfig['fallback_image_url'] = 'https://images.unsplash.com/photo-1507676184212-d0330a15183e?auto=format&fit=crop&w=1600&q=80';
            } elseif (in_array($blockKey, ['catalog_item_gallery', 'event_item_gallery'], true)) {
                $blockConfig['fallback_gallery_images'] = [
                    'https://images.unsplash.com/photo-1514306191717-452ec28c7814?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1478147424096-f60b45700874?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?auto=format&fit=crop&w=800&q=80',
                ];
            }

            $instanceId = $this->upsertRecord('cms_block_instances', [
                'block_id' => $blockId,
                'owner_type' => 'page',
                'owner_id' => $pageId,
                'parent_instance_id' => null,
                'sort_order' => $sortOrder++,
            ], [
                'column_index' => null,
                'is_active' => 1,
                'block_config' => json_encode(empty($blockConfig) ? new \stdClass() : $blockConfig, JSON_UNESCAPED_SLASHES),
            ]);

            if ($instanceId === null) {
                continue;
            }

            foreach ($languages as $languageCode => $languageId) {
                $fallbackTitle = match ($languageCode) {
                    'es' => $definition['es_name'],
                    'en' => $definition['en_name'],
                    'fr' => $definition['fr_name'],
                    'pt' => $definition['pt_name'],
                    default => $definition['es_name'],
                };

                $this->upsertRecord('cms_block_instance_translations', [
                    'instance_id' => $instanceId,
                    'language_id' => $languageId,
                ], [
                    'block_data' => json_encode(['fallback_title' => $fallbackTitle], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_published' => 1,
                ]);
            }
        }
    }

    /**
     * @param array{page_type: string, es_name: string, en_name: string, fr_name: string, pt_name: string, es_slug: string, en_slug: string, fr_slug: string, pt_slug: string, es_excerpt: string, en_excerpt: string, fr_excerpt: string, pt_excerpt: string, sort_order: int, block_keys: list<string>} $definition
     * @param array<string, int> $languages
     */
    private function upsertTemplatePageTranslations(int $pageId, array $definition, array $languages): void
    {
        $translations = [
            'es' => [
                'slug' => $definition['es_slug'],
                'title' => $definition['es_name'],
                'excerpt' => $definition['es_excerpt'],
                'meta_title' => $definition['es_name'],
                'meta_description' => $definition['es_excerpt'],
                'canonical_url' => null,
                'robots' => 'noindex, follow',
                'schema_data' => null,
            ],
            'en' => [
                'slug' => $definition['en_slug'],
                'title' => $definition['en_name'],
                'excerpt' => $definition['en_excerpt'],
                'meta_title' => $definition['en_name'],
                'meta_description' => $definition['en_excerpt'],
                'canonical_url' => null,
                'robots' => 'noindex, follow',
                'schema_data' => null,
            ],
            'fr' => [
                'slug' => $definition['fr_slug'],
                'title' => $definition['fr_name'],
                'excerpt' => $definition['fr_excerpt'],
                'meta_title' => $definition['fr_name'],
                'meta_description' => $definition['fr_excerpt'],
                'canonical_url' => null,
                'robots' => 'noindex, follow',
                'schema_data' => null,
            ],
            'pt' => [
                'slug' => $definition['pt_slug'],
                'title' => $definition['pt_name'],
                'excerpt' => $definition['pt_excerpt'],
                'meta_title' => $definition['pt_name'],
                'meta_description' => $definition['pt_excerpt'],
                'canonical_url' => null,
                'robots' => 'noindex, follow',
                'schema_data' => null,
            ],
        ];

        foreach ($translations as $language => $translation) {
            $languageId = $languages[$language] ?? null;
            if ($languageId === null) {
                continue;
            }

            $slug = (string) ($translation['slug'] ?? '');
            if ($slug !== '') {
                $conflict = $this->db->table('cms_page_translations')
                    ->where('language_id', $languageId)
                    ->where('slug', $slug)
                    ->get()
                    ->getRowArray();

                if ($conflict !== null && (int) $conflict['page_id'] !== $pageId) {
                    continue;
                }
            }

            $this->upsertRecord('cms_page_translations', [
                'page_id' => $pageId,
                'language_id' => $languageId,
            ], $translation);
        }
    }

    private function upsertTemplatePage(string $pageType, int $sortOrder): ?int
    {
        return $this->upsertRecord('cms_pages', [
            'page_type' => $pageType,
        ], [
            'parent_id' => null,
            'collection_id' => null,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
            'sort_order' => $sortOrder,
            'sitemap_priority' => '0.0',
            'sitemap_changefreq' => 'never',
            'is_in_sitemap' => 0,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return list<array{page_type: string, es_name: string, en_name: string, fr_name: string, pt_name: string, es_slug: string, en_slug: string, fr_slug: string, pt_slug: string, es_excerpt: string, en_excerpt: string, fr_excerpt: string, pt_excerpt: string, sort_order: int, block_keys: list<string>}>
     */
    private function templateDefinitions(): array
    {
        return [
            [
                'page_type' => 'template_catalog_item',
                'es_name' => 'Plantilla de ficha de catálogo',
                'en_name' => 'Catalog item template',
                'fr_name' => 'Modèle de fiche de catalogue',
                'pt_name' => 'Modelo de ficha de catálogo',
                'es_slug' => '__template_catalog_item',
                'en_slug' => '__template_catalog_item',
                'fr_slug' => '__template_catalog_item',
                'pt_slug' => '__template_catalog_item',
                'es_excerpt' => 'Plantilla interna para la ficha pública del catálogo.',
                'en_excerpt' => 'Internal template for the public catalog detail page.',
                'fr_excerpt' => 'Modèle interne pour la fiche publique du catalogue.',
                'pt_excerpt' => 'Modelo interno para a ficha pública do catálogo.',
                'sort_order' => 900,
                'block_keys' => ['catalog_item_header', 'catalog_item_details', 'catalog_item_content', 'catalog_item_gallery'],
            ],
            [
                'page_type' => 'template_event_item',
                'es_name' => 'Plantilla de ficha de evento',
                'en_name' => 'Event item template',
                'fr_name' => 'Modèle de fiche d’événement',
                'pt_name' => 'Modelo de ficha de evento',
                'es_slug' => '__template_event_item',
                'en_slug' => '__template_event_item',
                'fr_slug' => '__template_event_item',
                'pt_slug' => '__template_event_item',
                'es_excerpt' => 'Plantilla interna para la ficha pública de programación.',
                'en_excerpt' => 'Internal template for the public event detail page.',
                'fr_excerpt' => 'Modèle interne pour la fiche publique de programmation.',
                'pt_excerpt' => 'Modelo interno para a ficha pública de programação.',
                'sort_order' => 910,
                'block_keys' => ['event_item_header', 'event_item_details', 'event_item_content', 'event_item_gallery'],
            ],
        ];
    }

    /** @return list<array{collection_key: string, es_name: string, en_name: string, es_slug: string, en_slug: string, sort_order: int}> */
    private function definitions(): array
    {
        return [
            ['collection_key' => 'noticias', 'es_name' => 'Noticias', 'en_name' => 'News', 'fr_name' => 'Actualités', 'pt_name' => 'Notícias', 'es_slug' => 'noticias', 'en_slug' => 'news', 'fr_slug' => 'actualites', 'pt_slug' => 'noticias', 'sort_order' => 10],
            ['collection_key' => 'companias', 'es_name' => 'Compañías', 'en_name' => 'Companies', 'fr_name' => 'Compagnies', 'pt_name' => 'Companhias', 'es_slug' => 'companias', 'en_slug' => 'companies', 'fr_slug' => 'compagnies', 'pt_slug' => 'companhias', 'sort_order' => 20],
            ['collection_key' => 'personas', 'es_name' => 'Personas', 'en_name' => 'People', 'fr_name' => 'Personnes', 'pt_name' => 'Pessoas', 'es_slug' => 'personas', 'en_slug' => 'people', 'fr_slug' => 'personnes', 'pt_slug' => 'pessoas', 'sort_order' => 30],
            ['collection_key' => 'obras', 'es_name' => 'Obras', 'en_name' => 'Works', 'fr_name' => 'Oeuvres', 'pt_name' => 'Obras', 'es_slug' => 'obras', 'en_slug' => 'works', 'fr_slug' => 'oeuvres', 'pt_slug' => 'obras', 'sort_order' => 40],
            ['collection_key' => 'videos', 'es_name' => 'Videos', 'en_name' => 'Videos', 'fr_name' => 'Vidéos', 'pt_name' => 'Vídeos', 'es_slug' => 'videos', 'en_slug' => 'videos', 'fr_slug' => 'videos', 'pt_slug' => 'videos', 'sort_order' => 50],
            ['collection_key' => 'festivales', 'es_name' => 'Festivales', 'en_name' => 'Festivals', 'fr_name' => 'Festivals', 'pt_name' => 'Festivais', 'es_slug' => 'festivales', 'en_slug' => 'festivals', 'fr_slug' => 'festivals', 'pt_slug' => 'festivais', 'sort_order' => 60],
            ['collection_key' => 'exposiciones', 'es_name' => 'Exposiciones', 'en_name' => 'Exhibitions', 'fr_name' => 'Expositions', 'pt_name' => 'Exposições', 'es_slug' => 'exposiciones', 'en_slug' => 'exhibitions', 'fr_slug' => 'expositions', 'pt_slug' => 'exposicoes', 'sort_order' => 70],
            ['collection_key' => 'cursos', 'es_name' => 'Cursos', 'en_name' => 'Courses', 'fr_name' => 'Cours', 'pt_name' => 'Cursos', 'es_slug' => 'cursos', 'en_slug' => 'courses', 'fr_slug' => 'cours', 'pt_slug' => 'cursos', 'sort_order' => 80],
            ['collection_key' => 'publicaciones', 'es_name' => 'Publicaciones', 'en_name' => 'Publications', 'fr_name' => 'Publications', 'pt_name' => 'Publicações', 'es_slug' => 'publicaciones', 'en_slug' => 'publications', 'fr_slug' => 'publications', 'pt_slug' => 'publicacoes', 'sort_order' => 90],
        ];
    }
}
