<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Seeds the canonical public listing pages used by the site navigation.
 *
 * The pages are CMS-owned, but their block payloads point to external public
 * sources when needed so the same listing component can render museum items and
 * event items without legacy fallbacks.
 */
final class CmsTeatroMuseoPublicListingPagesSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $languages = $this->languageIds();
        $blockIds = $this->blockIds(['page_header', 'collection_listing']);

        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoPublicListingPagesSeeder: missing languages; skipping.\n";

            return;
        }

        if (! isset($blockIds['page_header'], $blockIds['collection_listing'])) {
            echo "CmsTeatroMuseoPublicListingPagesSeeder: missing block types; skipping.\n";

            return;
        }

        foreach ($this->pageDefinitions() as $definition) {
            $pageId = $this->upsertRecord('cms_pages', [
                'page_type' => $definition['page_type'],
            ], [
                'parent_id' => null,
                'collection_id' => null,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
                'scheduled_at' => null,
                'sort_order' => $definition['sort_order'],
                'sitemap_priority' => '0.8',
                'sitemap_changefreq' => 'monthly',
                'is_in_sitemap' => 1,
                'deleted_at' => null,
            ]);

            if ($pageId === null) {
                continue;
            }

            $this->upsertPageTranslations($pageId, $definition['translations'], $languages);
            $this->upsertPageBlocks($pageId, $definition, $blockIds, $languages);
        }
    }

    /**
     * @return array<string, int>
     */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', ['es', 'en', 'fr', 'pt'])
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @param list<string> $keys
     * @return array<string, int>
     */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')
            ->whereIn('block_key', $keys)
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['block_key']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     * @param array<string, int> $languages
     */
    private function upsertPageTranslations(int $pageId, array $translations, array $languages): void
    {
        foreach ($translations as $language => $translation) {
            $languageId = $languages[$language] ?? null;
            if ($languageId === null) {
                continue;
            }

            $this->upsertRecord('cms_page_translations', [
                'page_id' => $pageId,
                'language_id' => $languageId,
            ], [
                'slug' => $translation['slug'],
                'title' => $translation['title'],
                'excerpt' => $translation['excerpt'],
                'meta_title' => $translation['meta_title'],
                'meta_description' => $translation['meta_description'],
                'canonical_url' => null,
                'robots' => 'index, follow',
                'schema_data' => null,
                'og_image_file_id' => null,
                'og_image_url' => null,
                'og_type' => null,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, int> $blockIds
     * @param array<string, int> $languages
     */
    private function upsertPageBlocks(int $pageId, array $definition, array $blockIds, array $languages): void
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
            'block_config' => json_encode([
                'bg_color' => 'bg-slate-100',
                'css_class' => '',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $listingConfig = $definition['listing']['config'];
        $listingId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $blockIds['collection_listing'],
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => 2,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode($listingConfig, JSON_UNESCAPED_SLASHES),
        ]);

        $translations = [
            'page_header' => $definition['header_translations'],
            'collection_listing' => $definition['listing']['translations'],
        ];

        foreach ([
            'page_header' => $headerId,
            'collection_listing' => $listingId,
        ] as $blockKey => $instanceId) {
            if ($instanceId === null) {
                continue;
            }

            foreach ($translations[$blockKey] as $language => $data) {
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
     * @return list<array{
     *     page_type: string,
     *     sort_order: int,
     *     translations: array<string, array{slug: string, title: string, excerpt: string, meta_title: string, meta_description: string}>,
     *     header_translations: array<string, array{heading: string, subheading: string, breadcrumb_label: string, breadcrumb_url: string}>,
     *     listing: array{config: array<string, mixed>, translations: array<string, array<string, string>>}
     * }>
     */
    private function pageDefinitions(): array
    {
        return [
            [
                'page_type' => 'events',
                'sort_order' => 35,
                'translations' => [
                    'es' => [
                        'slug' => 'cartelera',
                        'title' => 'Cartelera',
                        'excerpt' => 'Explora la programación pública y accede a los eventos disponibles.',
                        'meta_title' => 'Cartelera | TeatroMuseo',
                        'meta_description' => 'Consulta la cartelera pública y accede a las funciones, talleres y actividades.',
                    ],
                    'en' => [
                        'slug' => 'cartelera',
                        'title' => 'Programming',
                        'excerpt' => 'Explore the public program and access the available events.',
                        'meta_title' => 'Programming | TeatroMuseo',
                        'meta_description' => 'Browse the public program and access theatre events.',
                    ],
                    'fr' => [
                        'slug' => 'cartelera',
                        'title' => 'Programmation',
                        'excerpt' => 'Découvrez la programmation publique et accédez aux événements disponibles.',
                        'meta_title' => 'Programmation | TeatroMuseo',
                        'meta_description' => 'Consultez la programmation publique et accédez aux spectacles, ateliers et activités.',
                    ],
                    'pt' => [
                        'slug' => 'cartelera',
                        'title' => 'Programação',
                        'excerpt' => 'Explore a programação pública e acesse os eventos disponíveis.',
                        'meta_title' => 'Programação | TeatroMuseo',
                        'meta_description' => 'Consulte a programação pública e acesse espetáculos, oficinas e atividades.',
                    ],
                ],
                'header_translations' => [
                    'es' => [
                        'heading' => 'Cartelera',
                        'subheading' => 'Funciones, talleres y actividades públicas disponibles ahora.',
                        'breadcrumb_label' => 'Inicio',
                        'breadcrumb_url' => '/',
                    ],
                    'en' => [
                        'heading' => 'Programming',
                        'subheading' => 'Public shows, workshops, and activities available now.',
                        'breadcrumb_label' => 'Home',
                        'breadcrumb_url' => '/',
                    ],
                    'fr' => [
                        'heading' => 'Programmation',
                        'subheading' => 'Spectacles, ateliers et activités publiques disponibles maintenant.',
                        'breadcrumb_label' => 'Accueil',
                        'breadcrumb_url' => '/',
                    ],
                    'pt' => [
                        'heading' => 'Programação',
                        'subheading' => 'Espetáculos, oficinas e atividades públicas disponíveis agora.',
                        'breadcrumb_label' => 'Início',
                        'breadcrumb_url' => '/',
                    ],
                ],
                'listing' => [
                    'config' => [
                        'source_type' => 'event_items',
                        'source_path' => 'cartelera',
                        'per_page' => 12,
                        'order_by' => 'start_time',
                        'order_direction' => 'asc',
                        'layout_variant' => 'cards',
                        'show_search' => true,
                        'show_categories' => false,
                        'show_tags' => true,
                        'show_excerpt' => true,
                        'show_date' => true,
                        'show_button' => true,
                        'show_item_categories' => true,
                        'show_extra_richtext' => false,
                        'show_extra_link' => false,
                        'show_extra_image' => false,
                        'css_class' => 'public-listing public-listing--event',
                    ],
                    'translations' => [
                        'es' => [
                            'intro_title' => 'Cartelera',
                            'intro_text' => '<p>Explora la cartelera pública y descubre funciones, talleres y actividades.</p>',
                            'section_label' => 'Programación',
                            'item_label' => 'Evento',
                            'featured_item_label' => 'Evento destacado',
                            'count_label' => 'Mostrando {count} eventos',
                            'empty_message' => 'No hay eventos disponibles todavía.',
                        ],
                        'en' => [
                            'intro_title' => 'Programming',
                            'intro_text' => '<p>Explore the public program and discover shows, workshops, and activities.</p>',
                            'section_label' => 'Events',
                            'item_label' => 'Event',
                            'featured_item_label' => 'Featured event',
                            'count_label' => 'Showing {count} events',
                            'empty_message' => 'No events are available yet.',
                        ],
                        'fr' => [
                            'intro_title' => 'Programmation',
                            'intro_text' => '<p>Explorez la programmation publique et découvrez spectacles, ateliers et activités.</p>',
                            'section_label' => 'Programmation',
                            'item_label' => 'Événement',
                            'featured_item_label' => 'Événement à la une',
                            'count_label' => 'Affichage de {count} événements',
                            'empty_message' => "Aucun événement n'est disponible pour le moment.",
                        ],
                        'pt' => [
                            'intro_title' => 'Programação',
                            'intro_text' => '<p>Explore a programação pública e descubra espetáculos, oficinas e atividades.</p>',
                            'section_label' => 'Programação',
                            'item_label' => 'Evento',
                            'featured_item_label' => 'Evento em destaque',
                            'count_label' => 'Mostrando {count} eventos',
                            'empty_message' => 'Ainda não há eventos disponíveis.',
                        ],
                    ],
                ],
            ],
            [
                'page_type' => 'catalog_listing',
                'sort_order' => 36,
                'translations' => [
                    'es' => [
                        'slug' => 'museo/coleccion',
                        'title' => 'Colección del museo',
                        'excerpt' => 'Explora la colección pública del museo con búsquedas, categorías y paginación.',
                        'meta_title' => 'Colección del museo | TeatroMuseo',
                        'meta_description' => 'Consulta la colección pública del museo y accede a sus obras, piezas y fichas.',
                    ],
                    'en' => [
                        'slug' => 'museo/coleccion',
                        'title' => 'Museum Collection',
                        'excerpt' => 'Explore the public museum collection with search, categories, and pagination.',
                        'meta_title' => 'Museum Collection | TeatroMuseo',
                        'meta_description' => 'Browse the public museum collection and access its works, pieces, and sheets.',
                    ],
                    'fr' => [
                        'slug' => 'museo/coleccion',
                        'title' => 'Collection du musée',
                        'excerpt' => 'Explorez la collection publique du musée avec recherche, catégories et pagination.',
                        'meta_title' => 'Collection du musée | TeatroMuseo',
                        'meta_description' => 'Consultez la collection publique du musée et accédez à ses œuvres, pièces et fiches.',
                    ],
                    'pt' => [
                        'slug' => 'museo/coleccion',
                        'title' => 'Coleção do museu',
                        'excerpt' => 'Explore a coleção pública do museu com busca, categorias e paginação.',
                        'meta_title' => 'Coleção do museu | TeatroMuseo',
                        'meta_description' => 'Consulte a coleção pública do museu e acesse suas obras, peças e fichas.',
                    ],
                ],
                'header_translations' => [
                    'es' => [
                        'heading' => 'Colección del museo',
                        'subheading' => 'Obras, piezas y fichas patrimoniales disponibles para explorar.',
                        'breadcrumb_label' => 'Inicio',
                        'breadcrumb_url' => '/',
                    ],
                    'en' => [
                        'heading' => 'Museum Collection',
                        'subheading' => 'Works, pieces, and heritage sheets available for exploration.',
                        'breadcrumb_label' => 'Home',
                        'breadcrumb_url' => '/',
                    ],
                    'fr' => [
                        'heading' => 'Collection du musée',
                        'subheading' => 'Œuvres, pièces et fiches patrimoniales à explorer.',
                        'breadcrumb_label' => 'Accueil',
                        'breadcrumb_url' => '/',
                    ],
                    'pt' => [
                        'heading' => 'Coleção do museu',
                        'subheading' => 'Obras, peças e fichas patrimoniais disponíveis para explorar.',
                        'breadcrumb_label' => 'Início',
                        'breadcrumb_url' => '/',
                    ],
                ],
                'listing' => [
                    'config' => [
                        'source_type' => 'catalog_items',
                        'source_path' => 'museo/coleccion',
                        'per_page' => 12,
                        'order_by' => 'name',
                        'order_direction' => 'asc',
                        'layout_variant' => 'cards',
                        'show_search' => true,
                        'show_categories' => true,
                        'show_tags' => false,
                        'show_excerpt' => true,
                        'show_date' => false,
                        'show_button' => true,
                        'show_item_categories' => true,
                        'show_extra_richtext' => false,
                        'show_extra_link' => false,
                        'show_extra_image' => false,
                        'css_class' => 'public-listing public-listing--museum',
                    ],
                    'translations' => [
                        'es' => [
                            'intro_title' => 'Colección del museo',
                            'intro_text' => '<p>Explora la colección pública del museo con filtros, etiquetas y paginación.</p>',
                            'section_label' => 'Museo',
                            'item_label' => 'Obra',
                            'featured_item_label' => 'Obra destacada',
                            'count_label' => 'Mostrando {count} obras',
                            'empty_message' => 'No hay obras disponibles todavía.',
                        ],
                        'en' => [
                            'intro_title' => 'Museum Collection',
                            'intro_text' => '<p>Explore the public museum collection with filters, tags, and pagination.</p>',
                            'section_label' => 'Museum',
                            'item_label' => 'Work',
                            'featured_item_label' => 'Featured work',
                            'count_label' => 'Showing {count} works',
                            'empty_message' => 'No works are available yet.',
                        ],
                        'fr' => [
                            'intro_title' => 'Collection du musée',
                            'intro_text' => '<p>Explorez la collection publique du musée avec filtres, étiquettes et pagination.</p>',
                            'section_label' => 'Musée',
                            'item_label' => 'Œuvre',
                            'featured_item_label' => 'Œuvre à la une',
                            'count_label' => 'Affichage de {count} œuvres',
                            'empty_message' => "Aucune œuvre n'est disponible pour le moment.",
                        ],
                        'pt' => [
                            'intro_title' => 'Coleção do museu',
                            'intro_text' => '<p>Explore a coleção pública do museu com filtros, etiquetas e paginação.</p>',
                            'section_label' => 'Museu',
                            'item_label' => 'Obra',
                            'featured_item_label' => 'Obra em destaque',
                            'count_label' => 'Mostrando {count} obras',
                            'empty_message' => 'Ainda não há obras disponíveis.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
