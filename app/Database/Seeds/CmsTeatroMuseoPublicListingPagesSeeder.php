<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use App\Database\Seeds\Concerns\TeatroMuseoPublicRoutes;
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

    private bool $replacedLegacyPage = false;

    public function run(): void
    {
        $languages = $this->languageIds();
        $blockIds = $this->blockIds(['page_header', 'collection_listing', 'collection_timeline', 'gallery', 'gallery_item']);

        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoPublicListingPagesSeeder: missing languages; skipping.\n";

            return;
        }

        if (! isset($blockIds['page_header'], $blockIds['collection_listing'], $blockIds['collection_timeline'], $blockIds['gallery'], $blockIds['gallery_item'])) {
            echo "CmsTeatroMuseoPublicListingPagesSeeder: missing block types; skipping.\n";

            return;
        }

        foreach ($this->pageDefinitions() as $definition) {
            $pageId = $this->upsertListingPage($definition);
            if ($pageId === null) {
                continue;
            }

            /*
             * Listing pages are canonical replacements for older generic
             * pages with the same localized slug. Their old block tree is
             * removed once, otherwise both the legacy rich text and the new
             * listing would render on the public page.
             */
            if ($this->replacedLegacyPage) {
                $this->clearPageBlocks($pageId);
            }

            /*
             * Re-seed the canonical block tree below. The page row itself is
             * updated by upsertListingPage, preserving its stable id.
             */
            $this->upsertPageTranslations($pageId, $definition['translations'], $languages);
            $this->upsertPageBlocks($pageId, $definition, $blockIds, $languages);
        }
    }

    /** @param array<string, mixed> $definition */
    private function upsertListingPage(array $definition): ?int
    {
        $this->replacedLegacyPage = false;
        $pageType = (string) $definition['page_type'];
        $existing = $this->db->table('cms_pages')
            ->select('cms_pages.id, cms_pages.page_type')
            ->where('cms_pages.page_type', $pageType)
            ->where('cms_pages.deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        if ($existing === null) {
            $slugs = [];
            foreach ((array) $definition['translations'] as $translation) {
                if (is_array($translation) && isset($translation['slug'])) {
                    $slugs[] = (string) $translation['slug'];
                }
            }
            if ($slugs !== []) {
                $existing = $this->db->table('cms_pages')
                    ->select('cms_pages.id, cms_pages.page_type')
                    ->join('cms_page_translations', 'cms_page_translations.page_id = cms_pages.id')
                    ->where('cms_pages.deleted_at IS NULL', null, false)
                    ->whereIn('cms_page_translations.slug', $slugs)
                    ->orderBy('cms_pages.id', 'ASC')
                    ->get()
                    ->getRowArray();
            }
        }

        $payload = [
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
        ];

        if ($existing !== null) {
            $pageId = (int) $existing['id'];
            $this->replacedLegacyPage = (string) ($existing['page_type'] ?? '') !== $pageType;
            $this->db->table('cms_pages')->where('id', $pageId)->update(array_merge(['page_type' => $pageType], $payload));

            return $pageId;
        }

        return $this->upsertRecord('cms_pages', ['page_type' => $pageType], $payload);
    }

    private function clearPageBlocks(int $pageId): void
    {
        $instances = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('owner_type', 'page')
            ->where('owner_id', $pageId)
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $instanceId = (int) $instance['id'];
            $this->db->table('cms_block_instance_translations')->where('instance_id', $instanceId)->delete();
            $this->db->table('cms_block_instances')->where('id', $instanceId)->delete();
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
        $collectionKey = (string) ($listingConfig['collection_key'] ?? '');
        if ($collectionKey !== '') {
            $collectionId = $this->collectionId($collectionKey);
            if ($collectionId !== null) {
                $listingConfig['collection_id'] = $collectionId;
            }
        }
        $categorySlug = (string) ($listingConfig['category_slug'] ?? '');
        if ($categorySlug !== '' && $collectionKey !== '') {
            $categoryId = $this->categoryId($collectionKey, $categorySlug);
            if ($categoryId !== null) {
                $listingConfig['category_id'] = $categoryId;
            }
        }
        $listingBlockKey = (string) ($definition['listing']['block_key'] ?? 'collection_listing');
        unset($listingConfig['category_slug']);
        if ($listingBlockKey === 'collection_listing') {
            unset($listingConfig['collection_key']);
        }
        $listingId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $blockIds[$listingBlockKey],
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
            $listingBlockKey => $definition['listing']['translations'],
        ];

        foreach ([
            'page_header' => $headerId,
            $listingBlockKey => $listingId,
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

        if ((string) ($definition['page_type'] ?? '') === 'press') {
            $this->upsertPressGallery($pageId, $blockIds, $languages);
        }
    }

    /** @param array<string, int> $blockIds @param array<string, int> $languages */
    private function upsertPressGallery(int $pageId, array $blockIds, array $languages): void
    {
        $galleryId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $blockIds['gallery'],
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => 3,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'presentation_mode' => 'modal_preview',
                'columns' => '4',
                'gap' => 'medium',
                'css_class' => 'public-press-gallery',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        if ($galleryId === null) {
            return;
        }

        $translations = [
            'es' => ['title' => 'Galería', 'description' => 'Conoce parte de la experiencia del TeatroMuseo en nuestras visitas guiadas.'],
            'en' => ['title' => 'Gallery', 'description' => 'Discover part of the TeatroMuseo experience through our guided tours.'],
            'fr' => ['title' => 'Galerie', 'description' => 'Découvrez une partie de l’expérience du TeatroMuseo à travers nos visites guidées.'],
            'pt' => ['title' => 'Galeria', 'description' => 'Conheça parte da experiência do TeatroMuseo em nossas visitas guiadas.'],
        ];
        foreach ($translations as $language => $data) {
            $languageId = $languages[$language] ?? null;
            if ($languageId === null) {
                continue;
            }
            $this->upsertRecord('cms_block_instance_translations', [
                'instance_id' => $galleryId,
                'language_id' => $languageId,
            ], [
                'block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => 1,
            ]);
        }

        $images = [];
        for ($index = 1; $index <= 8; $index++) {
            $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $images[] = [
                'url' => '/assets/images/press-gallery/visita-guiada-' . $number . '.jpg',
                'alt' => 'Visita guiada al TeatroMuseo, imagen ' . $index,
                'caption' => 'Visita guiada ' . $index,
            ];
        }

        foreach ($images as $index => $image) {
            $childId = $this->upsertRecord('cms_block_instances', [
                'block_id' => $blockIds['gallery_item'],
                'owner_type' => 'page',
                'owner_id' => $pageId,
                'parent_instance_id' => $galleryId,
                'sort_order' => $index + 1,
            ], [
                'column_index' => null,
                'is_active' => 1,
                'block_config' => json_encode([
                    'image' => ['source_kind' => 'external_url', 'file_id' => null, 'url' => $image['url']],
                ], JSON_UNESCAPED_SLASHES),
            ]);
            if ($childId === null) {
                continue;
            }

            foreach ($languages as $language => $languageId) {
                $this->upsertRecord('cms_block_instance_translations', [
                    'instance_id' => $childId,
                    'language_id' => $languageId,
                ], [
                    'block_data' => json_encode([
                        'alt' => $image['alt'],
                        'caption' => $image['caption'],
                        'link_url' => '',
                        'link_label' => '',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
 *     header_translations: array<string, array{heading: string, subheading: string, breadcrumb_label: string}>,
     *     listing: array{config: array<string, mixed>, translations: array<string, array<string, string>>}
     * }>
     */
    private function pageDefinitions(): array
    {
        $eventSlugs = TeatroMuseoPublicRoutes::pageSlugs('events');
        $catalogSlugs = TeatroMuseoPublicRoutes::pageSlugs('catalog_listing');

        return [
            [
                'page_type' => 'press',
                'sort_order' => 27,
                'translations' => $this->standardTranslations('prensa', 'press', 'presse', 'imprensa', 'Prensa', 'Press', 'Presse', 'Imprensa', 'comunicados y documentos para medios', 'press releases and media documents', 'communiqués et documents pour les médias', 'comunicados e documentos para a imprensa'),
                'header_translations' => $this->standardHeaders('Prensa', 'Press', 'Presse', 'Imprensa', 'Comunicados, documentos y material para medios.', 'Press releases, documents, and media resources.', 'Communiqués, documents et ressources pour les médias.', 'Comunicados, documentos e recursos para a imprensa.'),
                'listing' => [
                    'block_key' => 'collection_timeline',
                    'config' => ['collection_key' => 'prensa', 'items_limit' => 100, 'order_direction' => 'desc', 'layout' => 'alternating', 'show_excerpt' => true, 'show_documents' => true, 'show_entry_link' => false, 'open_in_new_tab' => true, 'css_class' => 'public-press-timeline'],
                    'translations' => ['es' => ['section_title' => 'Prensa', 'description' => 'Comunicados y documentos de prensa del TeatroMuseo.', 'empty_message' => 'No hay documentos de prensa disponibles todavía.', 'document_label' => 'Descargar documento', 'entry_label' => 'Ver ficha'], 'en' => ['section_title' => 'Press', 'description' => 'TeatroMuseo press releases and media documents.', 'empty_message' => 'No press documents are available yet.', 'document_label' => 'Download document', 'entry_label' => 'View sheet'], 'fr' => ['section_title' => 'Presse', 'description' => 'Communiqués et documents de presse de TeatroMuseo.', 'empty_message' => 'Aucun document de presse disponible pour le moment.', 'document_label' => 'Télécharger le document', 'entry_label' => 'Voir la fiche'], 'pt' => ['section_title' => 'Imprensa', 'description' => 'Comunicados e documentos de imprensa do TeatroMuseo.', 'empty_message' => 'Ainda não há documentos de imprensa disponíveis.', 'document_label' => 'Baixar documento', 'entry_label' => 'Ver ficha']],
                ],
            ],
            [
                'page_type' => 'publications',
                'sort_order' => 28,
                'translations' => $this->standardTranslations('publicaciones', 'publications', 'publications', 'publicacoes', 'Publicaciones', 'Publications', 'Publications', 'Publicações', 'editoriales y publicaciones del TeatroMuseo', 'TeatroMuseo editorials and publications', 'publications éditoriales de TeatroMuseo', 'publicações editoriais do TeatroMuseo'),
                'header_translations' => $this->standardHeaders('Publicaciones', 'Publications', 'Publications', 'Publicações', 'Explora nuestras publicaciones editoriales.', 'Explore our editorial publications.', 'Explorez nos publications éditoriales.', 'Explore nossas publicações editoriais.'),
                'listing' => [
                    'config' => ['source_type' => 'cms_collection', 'collection_key' => 'editoriales', 'per_page' => 12, 'order_by' => 'published_at', 'order_direction' => 'desc', 'layout_variant' => 'cards', 'show_search' => true, 'show_categories' => false, 'show_tags' => false, 'show_excerpt' => true, 'show_date' => true, 'show_button' => true, 'show_item_categories' => false, 'show_extra_richtext' => false, 'show_extra_link' => false, 'show_extra_image' => false, 'css_class' => 'public-listing public-listing--publications'],
                    'translations' => ['es' => ['intro_title' => 'Publicaciones', 'intro_text' => '<p>Explora nuestras publicaciones editoriales y sus documentos disponibles.</p>', 'section_label' => 'Editorial', 'item_label' => 'Publicación', 'featured_item_label' => 'Publicación destacada', 'count_label' => 'Mostrando {count} publicaciones', 'empty_message' => 'No hay publicaciones editoriales disponibles todavía.'], 'en' => ['intro_title' => 'Publications', 'intro_text' => '<p>Explore our editorial publications and their available documents.</p>', 'section_label' => 'Editorial', 'item_label' => 'Publication', 'featured_item_label' => 'Featured publication', 'count_label' => 'Showing {count} publications', 'empty_message' => 'No editorial publications are available yet.'], 'fr' => ['intro_title' => 'Publications', 'intro_text' => '<p>Explorez nos publications éditoriales et leurs documents disponibles.</p>', 'section_label' => 'Éditorial', 'item_label' => 'Publication', 'featured_item_label' => 'Publication à la une', 'count_label' => 'Affichage de {count} publications', 'empty_message' => 'Aucune publication éditoriale disponible pour le moment.'], 'pt' => ['intro_title' => 'Publicações', 'intro_text' => '<p>Explore nossas publicações editoriais e seus documentos disponíveis.</p>', 'section_label' => 'Editorial', 'item_label' => 'Publicação', 'featured_item_label' => 'Publicação em destaque', 'count_label' => 'Mostrando {count} publicações', 'empty_message' => 'Ainda não há publicações editoriais disponíveis.']],
                ],
            ],
            [
                'page_type' => 'transparency',
                'sort_order' => 29,
                'translations' => $this->standardTranslations('transparencia', 'transparency', 'transparence', 'transparencia', 'Transparencia', 'Transparency', 'Transparence', 'Transparência', 'información institucional y documentos de transparencia', 'institutional information and transparency documents', 'informations institutionnelles et documents de transparence', 'informações institucionais e documentos de transparência'),
                'header_translations' => $this->standardHeaders('Transparencia', 'Transparency', 'Transparence', 'Transparência', 'Información institucional y documentos públicos.', 'Institutional information and public documents.', 'Informations institutionnelles et documents publics.', 'Informações institucionais e documentos públicos.'),
                'listing' => [
                    'config' => ['source_type' => 'cms_collection', 'collection_key' => 'transparencia', 'per_page' => 12, 'order_by' => 'published_at', 'order_direction' => 'desc', 'layout_variant' => 'list', 'show_search' => true, 'show_categories' => false, 'show_tags' => false, 'show_excerpt' => true, 'show_date' => true, 'show_button' => true, 'show_item_categories' => false, 'show_extra_richtext' => false, 'show_extra_link' => false, 'show_extra_image' => false, 'css_class' => 'public-listing public-listing--transparency'],
                    'translations' => ['es' => ['intro_title' => 'Transparencia', 'intro_text' => '<p>Consulta la información institucional y los documentos públicos de TeatroMuseo.</p>', 'section_label' => 'Transparencia', 'item_label' => 'Documento', 'featured_item_label' => 'Documento destacado', 'count_label' => 'Mostrando {count} documentos', 'empty_message' => 'No hay documentos de transparencia disponibles todavía.'], 'en' => ['intro_title' => 'Transparency', 'intro_text' => '<p>Browse TeatroMuseo institutional information and public documents.</p>', 'section_label' => 'Transparency', 'item_label' => 'Document', 'featured_item_label' => 'Featured document', 'count_label' => 'Showing {count} documents', 'empty_message' => 'No transparency documents are available yet.'], 'fr' => ['intro_title' => 'Transparence', 'intro_text' => '<p>Consultez les informations institutionnelles et les documents publics de TeatroMuseo.</p>', 'section_label' => 'Transparence', 'item_label' => 'Document', 'featured_item_label' => 'Document à la une', 'count_label' => 'Affichage de {count} documents', 'empty_message' => 'Aucun document de transparence disponible pour le moment.'], 'pt' => ['intro_title' => 'Transparência', 'intro_text' => '<p>Consulte as informações institucionais e os documentos públicos do TeatroMuseo.</p>', 'section_label' => 'Transparência', 'item_label' => 'Documento', 'featured_item_label' => 'Documento em destaque', 'count_label' => 'Mostrando {count} documentos', 'empty_message' => 'Ainda não há documentos de transparência disponíveis.']],
                ],
            ],
            [
                'page_type' => 'events',
                'sort_order' => 35,
                'translations' => [
                    'es' => [
                        'slug' => $eventSlugs['es'],
                        'title' => 'Cartelera',
                        'excerpt' => 'Explora la programación pública y accede a los eventos disponibles.',
                        'meta_title' => 'Cartelera | TeatroMuseo',
                        'meta_description' => 'Consulta la cartelera pública y accede a las funciones, talleres y actividades.',
                    ],
                    'en' => [
                        'slug' => $eventSlugs['en'],
                        'title' => 'Programming',
                        'excerpt' => 'Explore the public program and access the available events.',
                        'meta_title' => 'Programming | TeatroMuseo',
                        'meta_description' => 'Browse the public program and access theatre events.',
                    ],
                    'fr' => [
                        'slug' => $eventSlugs['fr'],
                        'title' => 'Programmation',
                        'excerpt' => 'Découvrez la programmation publique et accédez aux événements disponibles.',
                        'meta_title' => 'Programmation | TeatroMuseo',
                        'meta_description' => 'Consultez la programmation publique et accédez aux spectacles, ateliers et activités.',
                    ],
                    'pt' => [
                        'slug' => $eventSlugs['pt'],
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
                    ],
                    'en' => [
                        'heading' => 'Programming',
                        'subheading' => 'Public shows, workshops, and activities available now.',
                        'breadcrumb_label' => 'Home',
                    ],
                    'fr' => [
                        'heading' => 'Programmation',
                        'subheading' => 'Spectacles, ateliers et activités publiques disponibles maintenant.',
                        'breadcrumb_label' => 'Accueil',
                    ],
                    'pt' => [
                        'heading' => 'Programação',
                        'subheading' => 'Espetáculos, oficinas e atividades públicas disponíveis agora.',
                        'breadcrumb_label' => 'Início',
                    ],
                ],
                'listing' => [
                    'config' => [
                        'source_type' => 'event_items',
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
                        'slug' => $catalogSlugs['es'],
                        'title' => 'Colección del museo',
                        'excerpt' => 'Explora la colección pública del museo con búsquedas, categorías y paginación.',
                        'meta_title' => 'Colección del museo | TeatroMuseo',
                        'meta_description' => 'Consulta la colección pública del museo y accede a sus obras, piezas y fichas.',
                    ],
                    'en' => [
                        'slug' => $catalogSlugs['en'],
                        'title' => 'Museum Collection',
                        'excerpt' => 'Explore the public museum collection with search, categories, and pagination.',
                        'meta_title' => 'Museum Collection | TeatroMuseo',
                        'meta_description' => 'Browse the public museum collection and access its works, pieces, and sheets.',
                    ],
                    'fr' => [
                        'slug' => $catalogSlugs['fr'],
                        'title' => 'Collection du musée',
                        'excerpt' => 'Explorez la collection publique du musée avec recherche, catégories et pagination.',
                        'meta_title' => 'Collection du musée | TeatroMuseo',
                        'meta_description' => 'Consultez la collection publique du musée et accédez à ses œuvres, pièces et fiches.',
                    ],
                    'pt' => [
                        'slug' => $catalogSlugs['pt'],
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
                    ],
                    'en' => [
                        'heading' => 'Museum Collection',
                        'subheading' => 'Works, pieces, and heritage sheets available for exploration.',
                        'breadcrumb_label' => 'Home',
                    ],
                    'fr' => [
                        'heading' => 'Collection du musée',
                        'subheading' => 'Œuvres, pièces et fiches patrimoniales à explorer.',
                        'breadcrumb_label' => 'Accueil',
                    ],
                    'pt' => [
                        'heading' => 'Coleção do museu',
                        'subheading' => 'Obras, peças e fichas patrimoniais disponíveis para explorar.',
                        'breadcrumb_label' => 'Início',
                    ],
                ],
                'listing' => [
                    'config' => [
                        'source_type' => 'catalog_items',
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

    private function collectionId(string $collectionKey): ?int
    {
        $row = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', $collectionKey)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    private function categoryId(string $collectionKey, string $slug): ?int
    {
        $collectionId = $this->collectionId($collectionKey);
        if ($collectionId === null) {
            return null;
        }

        $row = $this->db->table('cms_categories')
            ->select('cms_categories.id')
            ->join('cms_category_translations', 'cms_category_translations.category_id = cms_categories.id')
            ->where('cms_categories.collection_id', $collectionId)
            ->where('cms_category_translations.slug', $slug)
            ->where('cms_categories.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /** @return array<string, array<string, string>> */
    private function standardTranslations(
        string $esSlug,
        string $enSlug,
        string $frSlug,
        string $ptSlug,
        string $esTitle,
        string $enTitle,
        string $frTitle,
        string $ptTitle,
        string $esExcerpt,
        string $enExcerpt,
        ?string $frExcerpt = null,
        ?string $ptExcerpt = null
    ): array {
        return [
            'es' => ['slug' => $esSlug, 'title' => $esTitle, 'excerpt' => $esExcerpt, 'meta_title' => $esTitle . ' | TeatroMuseo', 'meta_description' => $esExcerpt],
            'en' => ['slug' => $enSlug, 'title' => $enTitle, 'excerpt' => $enExcerpt, 'meta_title' => $enTitle . ' | TeatroMuseo', 'meta_description' => $enExcerpt],
            'fr' => ['slug' => $frSlug, 'title' => $frTitle, 'excerpt' => $frExcerpt ?? $enExcerpt, 'meta_title' => $frTitle . ' | TeatroMuseo', 'meta_description' => $frExcerpt ?? $enExcerpt],
            'pt' => ['slug' => $ptSlug, 'title' => $ptTitle, 'excerpt' => $ptExcerpt ?? $enExcerpt, 'meta_title' => $ptTitle . ' | TeatroMuseo', 'meta_description' => $ptExcerpt ?? $enExcerpt],
        ];
    }

    /** @return array<string, array<string, string>> */
    private function standardHeaders(
        string $esHeading,
        string $enHeading,
        string $frHeading,
        string $ptHeading,
        string $esSubheading,
        string $enSubheading,
        ?string $frSubheading = null,
        ?string $ptSubheading = null
    ): array {
        return [
            'es' => ['heading' => $esHeading, 'subheading' => $esSubheading, 'breadcrumb_label' => 'Inicio'],
            'en' => ['heading' => $enHeading, 'subheading' => $enSubheading, 'breadcrumb_label' => 'Home'],
            'fr' => ['heading' => $frHeading, 'subheading' => $frSubheading ?? $enSubheading, 'breadcrumb_label' => 'Accueil'],
            'pt' => ['heading' => $ptHeading, 'subheading' => $ptSubheading ?? $enSubheading, 'breadcrumb_label' => 'Início'],
        ];
    }
}
