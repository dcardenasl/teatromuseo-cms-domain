<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\CollectionBlockPresets;
use App\Database\Seeds\Concerns\TeatroMuseoPublicRoutes;
use CodeIgniter\Database\Seeder;

/**
 * Consolidated Cms Content Sanitization Seeder.
 * Ported from the 41 content migrations (2026-08-03 to 2026-08-05).
 */
class CmsContentSanitizationSeeder extends Seeder
{
    public function run(): void
    {
        $this->sanitize_NormalizeBlockNavigationSchemas();
        $this->sanitize_SyncBlockInstanceNavigationSchemas();
        $this->sanitize_NormalizeTheatreSchoolLabels();
        $this->sanitize_NormalizeTheatreSchoolPageTitles();
        $this->sanitize_NormalizeTheatreSchoolCollectionKey();
        $this->sanitize_NormalizeTheatreSchoolCanonicalLabels();
        $this->sanitize_NormalizeNewsCoverGallery();
        $this->sanitize_NormalizeHomeHeroSliderNavigation();
        $this->sanitize_AlignHomeHeroSliderWithPublishedEventSlugs();
        $this->sanitize_AddPublicationPageTypes();
        $this->sanitize_SplitPublicationCollections();
        $this->sanitize_NormalizePublicationPageBindings();
        $this->sanitize_LabelPressDocumentSemesters();
        $this->sanitize_AddPressGallery();
        $this->sanitize_EnhanceGalleryBlockFields();
        $this->sanitize_BindPressGalleryToHubFiles();
        $this->sanitize_IntroduceSemanticSlideNavigation();
        $this->sanitize_NormalizeTeatroEscuelaIdentifiers();
        // Apply canonical localized URLs after legacy collection identifiers
        // have been merged. This keeps fresh and upgraded databases aligned
        // before public-read consumers build their URL index.
        $this->sanitize_NormalizePublicNavigationSlugs();
        $this->sanitize_UnifyAboutPageLocales();
        $this->sanitize_PersistAboutSpanishEditorialContent();
        $this->sanitize_CreateAboutTeamChildren();
        $this->sanitize_CompleteAboutTeamPrimaryMedia();
        $this->sanitize_AddListingFieldProjection();
        $this->sanitize_BackfillListingProjections();
        $this->sanitize_NormalizeListingProjectionReferences();
        $this->sanitize_BackfillPublishedAtForPublishedEntries();
        $this->sanitize_ClarifyCollectionListingTaxonomyLabels();
        $this->sanitize_ConsolidateAboutTeamBlocks();
        $this->sanitize_SyncAboutTeamEditorialData();
        $this->sanitize_RestoreAboutTeamBlockCompatibilityId();
        $this->sanitize_RemovePeoplePublicNavigation();
        $this->sanitize_RenamePublicationsToEditorial();
        $this->sanitize_MovePressMenuItem();
        $this->sanitize_CanonicalizeEditorialRoutes();
        $this->sanitize_PreserveEditorialEntryRoutes();
        $this->sanitize_ConsolidateEditorialIndexPage();
        $this->sanitize_NormalizeAboutTeamAdditionalRoles();
        $this->sanitize_NormalizeLegacyCollectionIndexPages();
        $this->sanitize_RetireObrasCollection();
        $this->sanitize_NormalizeSiteSettings();
        $this->sanitize_NormalizeStoredMediaUrls();
        $this->sanitize_NormalizePublishedVideoBlocks();
    }

    private function sanitize_NormalizeStoredMediaUrls(): void
    {
        foreach ([
            ['cms_entry_translations', 'featured_image_url'],
            ['cms_entry_translations', 'og_image_url'],
            ['cms_page_translations', 'og_image_url'],
        ] as [$table, $column]) {
            if (! $this->db->fieldExists('id', $table) || ! $this->db->fieldExists($column, $table)) {
                continue;
            }

            $rows = $this->db->table($table)->select("id, {$column}")->get()->getResultArray();
            foreach ($rows as $row) {
                $current = $row[$column] ?? null;
                $normalized = $this->Sanitize_NormalizeStoredMediaUrls_portableUrl($current);
                if ($normalized === $current) {
                    continue;
                }

                $this->db->table($table)->where('id', (int) $row['id'])->update([
                    $column => $normalized,
                ]);
            }
        }

        foreach ([
            'cms_block_instances' => 'block_config',
            'cms_block_instance_translations' => 'block_data',
        ] as $table => $column) {
            if (! $this->db->fieldExists('id', $table) || ! $this->db->fieldExists($column, $table)) {
                continue;
            }

            $rows = $this->db->table($table)->select("id, {$column}")->get()->getResultArray();
            foreach ($rows as $row) {
                $decoded = json_decode((string) ($row[$column] ?? ''), true);
                if (! is_array($decoded)) {
                    continue;
                }

                $normalized = $this->Sanitize_NormalizeStoredMediaUrls_value($decoded);
                if ($normalized === $decoded) {
                    continue;
                }

                $this->db->table($table)->where('id', (int) $row['id'])->update([
                    $column => json_encode(
                        $normalized,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                    ),
                ]);
            }
        }
    }

    /**
     * The primary `video_ficha` block is required and locked for the `videos`
     * collection. Older imports created the entries as published but left the
     * auto-created block translations private, so public readers discarded the
     * YouTube identity before the listing projection could expose it.
     *
     * Align only valid video data belonging to published, active entries. Draft
     * entries, inactive blocks, incomplete payloads, and unrelated collections
     * remain untouched.
     */
    private function sanitize_NormalizePublishedVideoBlocks(): void
    {
        $rows = $this->db->table('cms_block_instance_translations t')
            ->select('t.id, t.block_data')
            ->join('cms_block_instances i', 'i.id = t.instance_id')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->join('cms_entries e', "e.id = i.owner_id AND i.owner_type = 'entry'")
            ->join('cms_collections c', 'c.id = e.collection_id')
            ->where('b.block_key', 'video_ficha')
            ->where('c.collection_key', 'videos')
            ->where('e.workflow_status', 'published')
            ->where('e.deleted_at IS NULL', null, false)
            ->where('i.is_active', 1)
            ->where('t.is_published', 0)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $data = json_decode((string) ($row['block_data'] ?? '{}'), true);
            if (! is_array($data) || ! $this->Sanitize_NormalizePublishedVideoBlocks_isValidVideo($data)) {
                continue;
            }

            $this->db->table('cms_block_instance_translations')
                ->where('id', (int) $row['id'])
                ->update(['is_published' => 1]);
        }
    }

    /** @param array<string, mixed> $data */
    private function Sanitize_NormalizePublishedVideoBlocks_isValidVideo(array $data): bool
    {
        $provider = strtolower(trim((string) ($data['provider'] ?? '')));
        $videoId = trim((string) ($data['video_id'] ?? ''));
        $videoUrl = trim((string) ($data['video_url'] ?? ''));

        if ($provider === 'youtube') {
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1;
        }

        return $provider === 'vimeo'
            && preg_match('/^\d+$/', $videoId) === 1
            && preg_match('#^https://(?:www\.)?vimeo\.com/#i', $videoUrl) === 1;
    }

    private function Sanitize_NormalizeStoredMediaUrls_portableUrl(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $url = trim($value);
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return $value;
        }

        $path = '/' . ltrim($path, '/');
        if (! str_starts_with($path, '/uploads/')) {
            return $value;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        $suffix = is_string($query) && $query !== '' ? '?' . $query : '';
        $suffix .= is_string($fragment) && $fragment !== '' ? '#' . $fragment : '';

        return $path . $suffix;
    }

    private function Sanitize_NormalizeStoredMediaUrls_value(mixed $value, string $sourceKind = ''): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $currentSourceKind = strtolower(trim((string) ($value['source_kind'] ?? $sourceKind)));
        foreach ($value as $key => $item) {
            if (is_string($item) && ($key === 'url' || str_ends_with((string) $key, '_url'))) {
                $normalized = $this->Sanitize_NormalizeStoredMediaUrls_portableUrl($item);
                if ($key === 'url' && $currentSourceKind === 'hub_file') {
                    $path = '/' . ltrim((string) $normalized, '/');
                    if (str_starts_with($path, '/files/')) {
                        $normalized = '';
                    }
                }
                $value[$key] = $normalized;
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->Sanitize_NormalizeStoredMediaUrls_value($item, $currentSourceKind);
            }
        }

        return $value;
    }

    private function sanitize_NormalizeBlockNavigationSchemas(): void
    {
        $table = $this->db->table('cms_content_blocks');

        foreach (['collection_grid', 'collection_listing', 'page_header'] as $blockKey) {
            $row = $table->where('block_key', $blockKey)->get()->getRowArray();
            if (! is_array($row)) {
                continue;
            }

            $schema = json_decode((string) ($row['schema_definition'] ?? ''), true);
            if (! is_array($schema)) {
                continue;
            }

            $changed = false;
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            if ($blockKey === 'collection_grid' && array_key_exists('view_all_url', $fields)) {
                unset($fields['view_all_url']);
                $changed = true;
            }
            if ($fields !== ($schema['fields'] ?? null)) {
                $schema['fields'] = $fields;
                $changed = true;
            }

            $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
            if ($blockKey === 'collection_listing' && array_key_exists('source_path', $configFields)) {
                unset($configFields['source_path']);
                $changed = true;
            }
            if ($configFields !== ($schema['config_fields'] ?? null)) {
                $schema['config_fields'] = $configFields;
                $changed = true;
            }

            if ($blockKey === 'page_header' && array_key_exists('breadcrumb_url', $fields)) {
                unset($fields['breadcrumb_url']);
                $schema['fields'] = $fields;
                $changed = true;
            }

            $navigation = $blockKey === 'collection_grid'
                ? [
                    'source' => 'block_config',
                    'target' => 'collection_index',
                    'required' => false,
                ]
                : ($blockKey === 'collection_listing'
                ? [
                    'source' => 'block_config',
                    'target' => 'listing_page',
                    'required' => false,
                    'event_page_type' => 'events',
                    'catalog_page_type' => 'catalog_listing',
                ]
                : [
                    'source' => 'owner',
                    'target' => 'parent_page',
                    'required' => false,
                ]);
            if (($schema['navigation'] ?? null) !== $navigation) {
                $schema['navigation'] = $navigation;
                $changed = true;
            }

            if ($changed) {
                $table->where('id', (int) $row['id'])->update([
                    'schema_definition' => json_encode(
                        $schema,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                    ),
                ]);
            }
        }
    }

    private function sanitize_SyncBlockInstanceNavigationSchemas(): void
    {
        $rows = $this->db->table('cms_content_blocks')
                    ->select('id, block_key, schema_definition')
                    ->whereIn('block_key', self::Sanitize_SyncBlockInstanceNavigationSchemas_BLOCK_KEYS)
                    ->get()
                    ->getResultArray();

        $table = $this->db->table('cms_content_blocks');
        foreach ($rows as $row) {
            $blockKey = (string) ($row['block_key'] ?? '');
            $schema = json_decode((string) ($row['schema_definition'] ?? ''), true);
            if (! in_array($blockKey, self::Sanitize_SyncBlockInstanceNavigationSchemas_BLOCK_KEYS, true) || ! is_array($schema)) {
                continue;
            }

            $normalized = $this->Sanitize_SyncBlockInstanceNavigationSchemas_normalizeSchema($schema, $blockKey);
            if ($normalized === $schema) {
                continue;
            }

            $table->where('id', (int) $row['id'])->update([
                'schema_definition' => json_encode(
                    $normalized,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
            ]);
        }
    }

    // Helpers/properties from class:
    private const Sanitize_SyncBlockInstanceNavigationSchemas_BLOCK_KEYS = ['collection_grid', 'collection_listing', 'page_header'];





    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function Sanitize_SyncBlockInstanceNavigationSchemas_normalizeSchema(array $schema, string $blockKey): array
    {
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        if ($blockKey === 'collection_grid') {
            unset($fields['view_all_url']);
        }
        if ($blockKey === 'page_header') {
            unset($fields['breadcrumb_url']);
        }
        $schema['fields'] = $fields;

        $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
        if ($blockKey === 'collection_listing') {
            unset($configFields['source_path']);
        }
        $schema['config_fields'] = $configFields;

        $schema['navigation'] = match ($blockKey) {
            'collection_grid' => [
                'source' => 'block_config',
                'target' => 'collection_index',
                'required' => false,
            ],
            'collection_listing' => [
                'source' => 'block_config',
                'target' => 'listing_page',
                'required' => false,
                'event_page_type' => 'events',
                'catalog_page_type' => 'catalog_listing',
            ],
            default => [
                'source' => 'owner',
                'target' => 'parent_page',
                'required' => false,
            ],
        };

        return $schema;
    }

    private function sanitize_NormalizePublicNavigationSlugs(): void
    {
        $this->Sanitize_NormalizePublicNavigationSlugs_normalizePageSlugs();
        $this->Sanitize_NormalizePublicNavigationSlugs_normalizeCourseSlugs();
    }

    private function Sanitize_NormalizePublicNavigationSlugs_normalizePageSlugs(): void
    {
        foreach (['events', 'catalog_listing'] as $pageType) {
            $slugs = TeatroMuseoPublicRoutes::pageSlugs($pageType);
            foreach ($slugs as $languageCode => $slug) {
                $this->db->query(
                    'UPDATE cms_page_translations t '
                    . 'INNER JOIN cms_pages p ON p.id = t.page_id '
                    . 'INNER JOIN cms_languages l ON l.id = t.language_id '
                    . 'SET t.slug = ? '
                    . 'WHERE p.page_type = ? AND l.code = ?',
                    [$slug, $pageType, $languageCode],
                );
            }
        }
    }

    private function Sanitize_NormalizePublicNavigationSlugs_normalizeCourseSlugs(): void
    {
        foreach (TeatroMuseoPublicRoutes::collectionSlugs('teatroescuela') as $languageCode => $slug) {
            $this->db->query(
                'UPDATE cms_collection_translations t '
                    . 'INNER JOIN cms_collections c ON c.id = t.collection_id '
                    . 'INNER JOIN cms_languages l ON l.id = t.language_id '
                    . 'SET t.slug = ? '
                    . 'WHERE c.collection_key = ? AND l.code = ?',
                [$slug, 'teatroescuela', $languageCode],
            );

            $this->db->query(
                'UPDATE cms_page_translations t '
                    . 'INNER JOIN cms_pages p ON p.id = t.page_id '
                    . 'INNER JOIN cms_collections c ON c.id = p.collection_id '
                    . 'INNER JOIN cms_languages l ON l.id = t.language_id '
                    . 'SET t.slug = ? '
                    . 'WHERE p.page_type = ? AND p.deleted_at IS NULL '
                    . 'AND c.collection_key = ? AND l.code = ?',
                [$slug, 'collection_index', 'teatroescuela', $languageCode],
            );
        }
    }

    private function sanitize_NormalizeTheatreSchoolLabels(): void
    {
        $this->db->query(
            'UPDATE cms_collection_translations t '
                    . 'INNER JOIN cms_collections c ON c.id = t.collection_id '
                    . 'SET t.name = ?, t.listing_title = ?, t.default_meta_title = ? '
                    . 'WHERE c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'cursos'],
        );
    }

    private function sanitize_NormalizeTheatreSchoolPageTitles(): void
    {
        $this->db->query(
            'UPDATE cms_page_translations t '
                    . 'INNER JOIN cms_pages p ON p.id = t.page_id '
                    . 'INNER JOIN cms_collections c ON c.id = p.collection_id '
                    . 'SET t.title = ?, t.meta_title = ? '
                    . 'WHERE p.page_type = ? AND c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'collection_index', 'cursos'],
        );
    }

    private function sanitize_NormalizeTheatreSchoolCollectionKey(): void
    {
        $existing = $this->db->table('cms_collections')
                    ->select('id')
                    ->where('collection_key', 'cursos')
                    ->get()
                    ->getRowArray();

        if ($existing !== null) {
            // A prior partial seed may have created the canonical row already.
            // Keep it and remove the duplicate legacy-key row only when it is
            // safe to do so; normally this branch is never reached.
            $legacy = $this->db->table('cms_collections')
                ->select('id')
                ->where('collection_key', 'TeatroEscuela')
                ->get()
                ->getRowArray();

            if ($legacy !== null && (int) $legacy['id'] !== (int) $existing['id']) {
                throw new \RuntimeException('Cannot normalize TeatroEscuela collection key: both cursos and TeatroEscuela exist.');
            }

            return;
        }

        $this->db->query(
            'UPDATE cms_collections SET collection_key = ? WHERE collection_key = ?',
            ['cursos', 'TeatroEscuela'],
        );

        $this->db->query(
            'UPDATE cms_page_translations t '
            . 'INNER JOIN cms_pages p ON p.id = t.page_id '
            . 'INNER JOIN cms_collections c ON c.id = p.collection_id '
            . 'SET t.title = ?, t.meta_title = ? '
            . 'WHERE p.page_type = ? AND c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'collection_index', 'cursos'],
        );
    }

    private function sanitize_NormalizeTheatreSchoolCanonicalLabels(): void
    {
        $this->db->query(
            'UPDATE cms_collection_translations t '
                    . 'INNER JOIN cms_collections c ON c.id = t.collection_id '
                    . 'SET t.name = ?, t.listing_title = ?, t.default_meta_title = ? '
                    . 'WHERE c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'cursos'],
        );

        $this->db->query(
            'UPDATE cms_page_translations t '
            . 'INNER JOIN cms_pages p ON p.id = t.page_id '
            . 'INNER JOIN cms_collections c ON c.id = p.collection_id '
            . 'SET t.title = ?, t.meta_title = ? '
            . 'WHERE p.page_type = ? AND c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'collection_index', 'cursos'],
        );
    }

    private function sanitize_NormalizeNewsCoverGallery(): void
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

    private function sanitize_NormalizeHomeHeroSliderNavigation(): void
    {
        $home = $this->db->table('cms_pages')
                    ->select('id')
                    ->where('page_type', 'home')
                    ->where('deleted_at IS NULL', null, false)
                    ->get()
                    ->getRowArray();
        if (! is_array($home)) {
            return;
        }

        $slide = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'slide_banner')
            ->get()
            ->getRowArray();
        if (! is_array($slide)) {
            return;
        }

        $languages = [];
        foreach ($this->db->table('cms_languages')->select('id, code')->get()->getResultArray() as $language) {
            $languages[(int) $language['id']] = (string) $language['code'];
        }

        $instances = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('block_id', (int) $slide['id'])
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $home['id'])
            ->where('parent_instance_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $translations = $this->db->table('cms_block_instance_translations')
                ->where('instance_id', (int) $instance['id'])
                ->get()
                ->getResultArray();

            foreach ($translations as $translation) {
                $locale = $languages[(int) $translation['language_id']] ?? '';
                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data)) {
                    continue;
                }

                $destination = $this->Sanitize_NormalizeHomeHeroSliderNavigation_canonicalDestination((string) ($data['cta_url'] ?? ''), $locale);
                if ($destination === null || $destination === ($data['cta_url'] ?? null)) {
                    continue;
                }

                $data['cta_url'] = $destination;
                $this->db->table('cms_block_instance_translations')
                    ->where('id', (int) $translation['id'])
                    ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            }
        }
    }

    // Helpers/properties from class:
    /** @var array<string, array<string, string>> */
    private const Sanitize_NormalizeHomeHeroSliderNavigation_DESTINATIONS = [
        'contact' => [
            'es' => '/contacto',
            'en' => '/contact',
            'fr' => '/contact',
            'pt' => '/contato',
        ],
        'events' => [
            'es' => '/cartelera',
            'en' => '/programming',
            'fr' => '/programmation',
            'pt' => '/programacao',
        ],
        'theatre_school' => [
            'es' => '/teatroescuela',
            'en' => '/theaterschool',
            'fr' => '/theatreecole',
            'pt' => '/escola-de-teatro',
        ],
    ];





    private function Sanitize_NormalizeHomeHeroSliderNavigation_canonicalDestination(string $url, string $locale): ?string
    {
        $path = trim((string) (parse_url(trim($url), PHP_URL_PATH) ?? ''), '/');
        if ($path === '') {
            return $url === '/' ? '/' : null;
        }

        $aliases = [
            'contacto' => 'contact', 'contact' => 'contact', 'contato' => 'contact',
            'cartelera' => 'events', 'events' => 'events', 'programme' => 'events', 'eventos' => 'events',
            'cursos' => 'theatre_school', 'teatroescuela' => 'theatre_school',
            'theaterschool' => 'theatre_school', 'theatreecole' => 'theatre_school', 'escola-de-teatro' => 'theatre_school',
        ];
        $key = $aliases[$path] ?? null;

        return $key !== null ? (self::Sanitize_NormalizeHomeHeroSliderNavigation_DESTINATIONS[$key][$locale] ?? self::Sanitize_NormalizeHomeHeroSliderNavigation_DESTINATIONS[$key]['es']) : null;
    }

    private function sanitize_AlignHomeHeroSliderWithPublishedEventSlugs(): void
    {
        $home = $this->db->table('cms_pages')
                    ->select('id')
                    ->where('page_type', 'home')
                    ->get()
                    ->getRowArray();
        $slide = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'slide_banner')
            ->get()
            ->getRowArray();
        if (! is_array($home) || ! is_array($slide)) {
            return;
        }

        $languages = [];
        foreach ($this->db->table('cms_languages')->select('id, code')->get()->getResultArray() as $language) {
            $languages[(int) $language['id']] = (string) $language['code'];
        }

        $instances = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('block_id', (int) $slide['id'])
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $home['id'])
            ->where('parent_instance_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $translations = $this->db->table('cms_block_instance_translations')
                ->where('instance_id', (int) $instance['id'])
                ->get()
                ->getResultArray();
            foreach ($translations as $translation) {
                $locale = $languages[(int) $translation['language_id']] ?? '';
                if (! isset(self::Sanitize_AlignHomeHeroSliderWithPublishedEventSlugs_EVENT_SLUGS[$locale])) {
                    continue;
                }

                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data) || ! $this->Sanitize_AlignHomeHeroSliderWithPublishedEventSlugs_isEventDestination((string) ($data['cta_url'] ?? ''))) {
                    continue;
                }

                $data['cta_url'] = self::Sanitize_AlignHomeHeroSliderWithPublishedEventSlugs_EVENT_SLUGS[$locale];
                $this->db->table('cms_block_instance_translations')
                    ->where('id', (int) $translation['id'])
                    ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            }
        }
    }

    // Helpers/properties from class:
    /** @var array<string, string> */
    private const Sanitize_AlignHomeHeroSliderWithPublishedEventSlugs_EVENT_SLUGS = [
        'es' => '/cartelera',
        'en' => '/programming',
        'fr' => '/programmation',
        'pt' => '/programacao',
    ];





    private function Sanitize_AlignHomeHeroSliderWithPublishedEventSlugs_isEventDestination(string $url): bool
    {
        $path = trim((string) (parse_url(trim($url), PHP_URL_PATH) ?? ''), '/');

        return in_array($path, [
            'cartelera', 'events', 'programme', 'eventos',
            'programming', 'programmation', 'programacao',
        ], true);
    }

    /**
     * The page-type enum is prepared before bootstrap listing seeders by
     * CmsPageTypeSeeder. Keep this traceable sanitizer step for the migrated
     * content operation without repeating the schema alteration here.
     */
    private function sanitize_AddPublicationPageTypes(): void
    {
        if (! $this->db->tableExists('cms_pages')) {
            return;
        }
    }

    private function sanitize_SplitPublicationCollections(): void
    {
        if (! $this->db->tableExists('cms_collections')) {
            return;
        }

        $old = $this->db->table('cms_collections')
            ->where('collection_key', 'publicaciones')
            ->get()
            ->getRowArray();
        if ($old === null) {
            return;
        }

        // Free the public slug before creating the canonical editorial
        // collection. This also makes a partially retried migration safe.
        $this->db->table('cms_collection_translations')
            ->where('collection_id', (int) $old['id'])
            ->set('slug', 'publicaciones-archivo')
            ->update();

        $collectionIds = [];
        foreach ($this->Sanitize_SplitPublicationCollections_definitions() as $definition) {
            $collectionIds[$definition['key']] = $this->Sanitize_SplitPublicationCollections_ensureCollection($definition, $old);
        }

        $oldId = (int) $old['id'];
        $categoryMap = $this->Sanitize_SplitPublicationCollections_categoryMap($oldId);

        foreach ($categoryMap as $categoryId => $targetKey) {
            $targetId = $collectionIds[$targetKey] ?? null;
            if ($targetId === null) {
                continue;
            }

            $entryRows = $this->db->table('cms_entry_categories')
                ->select('entry_id')
                ->where('category_id', $categoryId)
                ->get()
                ->getResultArray();
            $entryIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['entry_id'] ?? 0),
                $entryRows
            )));
            if ($entryIds !== []) {
                $this->db->table('cms_entries')
                    ->where('collection_id', $oldId)
                    ->whereIn('id', $entryIds)
                    ->set('collection_id', $targetId)
                    ->update();
            }

            // Keep the existing category and its entry pivots, but move the
            // category to the collection that now owns those entries.
            $this->db->table('cms_categories')
                ->where('id', $categoryId)
                ->set('collection_id', $targetId)
                ->update();
        }

        // The previous collection remains as an inactive historical record;
        // nothing is deleted and no file/block reference is rewritten.
        $this->db->table('cms_collections')
            ->where('id', $oldId)
            ->set(['is_active' => 0, 'sort_order' => 999])
            ->update();
    }

    // Helpers/properties from class:
    /** @param array<string, mixed> $definition @param array<string, mixed> $old */
    private function Sanitize_SplitPublicationCollections_ensureCollection(array $definition, array $old): int
    {
        $existing = $this->db->table('cms_collections')
            ->where('collection_key', $definition['key'])
            ->get()
            ->getRowArray();

        $payload = [
            'collection_type' => $definition['type'],
            'is_active' => 1,
            'requires_approval' => (int) ($old['requires_approval'] ?? 0),
            'enables_categories' => 1,
            'enables_tags' => 1,
            'default_sitemap_priority' => $definition['priority'],
            'default_changefreq' => $definition['changefreq'],
            'block_template' => $old['block_template'] ?? null,
            'wizard_config' => $old['wizard_config'] ?? null,
            'sort_order' => $definition['sort_order'],
        ];

        if ($existing === null) {
            $this->db->table('cms_collections')->insert(['collection_key' => $definition['key'], ...$payload]);
            $id = (int) $this->db->insertID();
        } else {
            $id = (int) $existing['id'];
            $this->db->table('cms_collections')->where('id', $id)->update($payload);
        }

        $languages = $this->db->table('cms_languages')->get()->getResultArray();
        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            $translation = $this->Sanitize_SplitPublicationCollections_translation($definition['key'], $code);
            if ($translation === null) {
                continue;
            }
            $translationRow = [
                'collection_id' => $id,
                'language_id' => (int) $language['id'],
                ...$translation,
            ];
            $existingTranslation = $this->db->table('cms_collection_translations')
                ->where('collection_id', $id)
                ->where('language_id', (int) $language['id'])
                ->get()
                ->getRowArray();
            if ($existingTranslation === null) {
                $this->db->table('cms_collection_translations')->insert($translationRow);
            } else {
                $this->db->table('cms_collection_translations')
                    ->where('id', (int) $existingTranslation['id'])
                    ->update($translation);
            }
        }

        return $id;
    }

    /** @return array<int, string> */
    private function Sanitize_SplitPublicationCollections_categoryMap(int $oldCollectionId): array
    {
        $rows = $this->db->table('cms_categories cat')
            ->select('cat.id, trans.slug')
            ->join('cms_category_translations trans', 'trans.category_id = cat.id')
            ->join('cms_languages lang', 'lang.id = trans.language_id AND lang.code = \'es\'')
            ->where('cat.collection_id', $oldCollectionId)
            ->get()
            ->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $target = match (strtolower((string) $row['slug'])) {
                'editorial' => 'editoriales',
                'prensa' => 'prensa',
                'transparencia' => 'transparencia',
                default => null,
            };
            if ($target !== null) {
                $map[(int) $row['id']] = $target;
            }
        }
        return $map;
    }

    /** @return list<array{key: string, type: string, priority: string, changefreq: string, sort_order: int}> */
    private function Sanitize_SplitPublicationCollections_definitions(): array
    {
        return [
            ['key' => 'editoriales', 'type' => 'editoriales', 'priority' => '0.6', 'changefreq' => 'monthly', 'sort_order' => 90],
            ['key' => 'prensa', 'type' => 'prensa', 'priority' => '0.6', 'changefreq' => 'monthly', 'sort_order' => 91],
            ['key' => 'transparencia', 'type' => 'transparencia', 'priority' => '0.6', 'changefreq' => 'yearly', 'sort_order' => 92],
        ];
    }

    /** @return array<string, string>|null */
    private function Sanitize_SplitPublicationCollections_translation(string $key, string $language): ?array
    {
        $labels = [
            'editoriales' => ['es' => ['slug' => 'publicaciones', 'name' => 'Publicaciones'], 'en' => ['slug' => 'publications', 'name' => 'Publications'], 'fr' => ['slug' => 'publications', 'name' => 'Publications'], 'pt' => ['slug' => 'publicacoes', 'name' => 'Publicações']],
            'prensa' => ['es' => ['slug' => 'prensa', 'name' => 'Prensa'], 'en' => ['slug' => 'press', 'name' => 'Press'], 'fr' => ['slug' => 'presse', 'name' => 'Presse'], 'pt' => ['slug' => 'imprensa', 'name' => 'Imprensa']],
            'transparencia' => ['es' => ['slug' => 'transparencia', 'name' => 'Transparencia'], 'en' => ['slug' => 'transparency', 'name' => 'Transparency'], 'fr' => ['slug' => 'transparence', 'name' => 'Transparence'], 'pt' => ['slug' => 'transparencia', 'name' => 'Transparência']],
        ];
        $value = $labels[$key][$language] ?? null;
        if ($value === null) {
            return null;
        }
        return [
            ...$value,
            'description' => $value['name'],
            'listing_title' => $value['name'],
            'listing_intro' => $value['name'],
            'default_meta_title' => $value['name'] . ' | TeatroMuseo',
            'default_meta_description' => $value['name'] . ' | TeatroMuseo',
        ];
    }

    private function sanitize_NormalizePublicationPageBindings(): void
    {
        foreach ([
                    ['slug' => 'publicaciones', 'block' => 'collection_listing', 'collection' => 'editoriales'],
                    ['slug' => 'prensa', 'block' => 'collection_timeline', 'collection' => 'prensa'],
                    ['slug' => 'transparencia', 'block' => 'collection_listing', 'collection' => 'transparencia'],
                ] as $binding) {
            $this->Sanitize_NormalizePublicationPageBindings_normalize($binding['slug'], $binding['block'], $binding['collection']);
        }
    }

    // Helpers/properties from class:
    private function Sanitize_NormalizePublicationPageBindings_normalize(string $pageSlug, string $blockKey, string $collectionKey): void
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

    private function sanitize_LabelPressDocumentSemesters(): void
    {
        $entries = $this->db->table('cms_entries e')
                    ->select('e.id, et.title')
                    ->join('cms_collections c', 'c.id = e.collection_id AND c.collection_key = \'prensa\'')
                    ->join('cms_entry_translations et', 'et.entry_id = e.id')
                    ->join('cms_languages l', 'l.id = et.language_id AND l.code = \'es\'')
                    ->get()
                    ->getResultArray();

        foreach ($entries as $entry) {
            $year = $this->Sanitize_LabelPressDocumentSemesters_yearFromTitle((string) ($entry['title'] ?? ''));
            if ($year === '') {
                continue;
            }

            $blocks = $this->db->table('cms_block_instances bi')
                ->select('bi.id, bi.block_config')
                ->join('cms_content_blocks cb', 'cb.id = bi.block_id AND cb.block_key = \'document_download\'')
                ->where(['bi.owner_type' => 'entry', 'bi.owner_id' => (int) $entry['id']])
                ->orderBy('bi.id', 'ASC')
                ->get()
                ->getResultArray();

            $fileIds = [];
            foreach ($blocks as $block) {
                $config = json_decode((string) ($block['block_config'] ?? '{}'), true);
                $fileId = is_array($config) && is_array($config['document'] ?? null)
                    ? (int) ($config['document']['file_id'] ?? 0)
                    : 0;
                if ($fileId > 0) {
                    $fileIds[(int) $block['id']] = $fileId;
                }
            }

            if (count($fileIds) !== 2) {
                continue;
            }

            // The imported file sequence identifies the first and second
            // semester pairs. Persist the result in editorial data so future
            // rendering never depends on insertion order or technical IDs.
            $orderedFileIds = $this->Sanitize_LabelPressDocumentSemesters_firstSemesterFileIds($year, $fileIds);
            foreach ($fileIds as $blockId => $fileId) {
                $semester = $fileId === $orderedFileIds[0] ? 1 : 2;
                $this->Sanitize_LabelPressDocumentSemesters_updateTranslations((int) $blockId, $semester);
            }
        }
    }

    // Helpers/properties from class:
    private function Sanitize_LabelPressDocumentSemesters_updateTranslations(int $instanceId, int $semester): void
    {
        $labels = [
            'es' => $semester === 1 ? 'Primer semestre' : 'Segundo semestre',
            'en' => $semester === 1 ? 'First semester' : 'Second semester',
            'fr' => $semester === 1 ? 'Premier semestre' : 'Deuxième semestre',
            'pt' => $semester === 1 ? 'Primeiro semestre' : 'Segundo semestre',
        ];

        $rows = $this->db->table('cms_block_instance_translations bit')
            ->select('bit.id, bit.block_data, l.code')
            ->join('cms_languages l', 'l.id = bit.language_id')
            ->where('bit.instance_id', $instanceId)
            ->get()
            ->getResultArray();
        foreach ($rows as $row) {
            $data = json_decode((string) ($row['block_data'] ?? '{}'), true);
            $data = is_array($data) ? $data : [];
            $data['title'] = $labels[(string) ($row['code'] ?? '')] ?? $labels['es'];
            $this->db->table('cms_block_instance_translations')
                ->where('id', (int) $row['id'])
                ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    /** @param array<int, int> $blockFileIds @return array{0: int, 1: int} */
    private function Sanitize_LabelPressDocumentSemesters_firstSemesterFileIds(string $year, array $blockFileIds): array
    {
        $fileIds = array_values($blockFileIds);
        $knownPairs = [
            '2016' => [139, 102],
            '2017' => [103, 104],
            '2018' => [140, 105],
            '2019' => [141, 106],
        ];
        if (isset($knownPairs[$year]) && count(array_diff($knownPairs[$year], $fileIds)) === 0) {
            return $knownPairs[$year];
        }

        sort($fileIds, SORT_NUMERIC);

        return [$fileIds[0], $fileIds[1]];
    }

    private function Sanitize_LabelPressDocumentSemesters_yearFromTitle(string $title): string
    {
        return preg_match('/\b(?:19|20)\d{2}\b/', $title, $match) === 1 ? $match[0] : '';
    }

    private function sanitize_AddPressGallery(): void
    {
        $page = $this->db->table('cms_pages p')
                    ->select('p.id')
                    ->join('cms_page_translations pt', 'pt.page_id = p.id')
                    ->join('cms_languages l', 'l.id = pt.language_id AND l.code = \'es\'')
                    ->where('pt.slug', 'prensa')
                    ->get()
                    ->getRowArray();
        $gallery = $this->Sanitize_AddPressGallery_blockId('gallery');
        $galleryItem = $this->Sanitize_AddPressGallery_blockId('gallery_item');
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

    // Helpers/properties from class:
    private function Sanitize_AddPressGallery_blockId(string $key): ?int
    {
        $row = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $key)->get()->getRowArray();
        return $row === null ? null : (int) $row['id'];
    }

    private function sanitize_EnhanceGalleryBlockFields(): void
    {
        $schema = [
                    'fields' => [
                        'title' => ['type' => 'string', 'label' => 'Título', 'required' => false],
                        'description' => ['type' => 'textarea', 'label' => 'Descripción', 'required' => false],
                    ],
                    'config_fields' => [
                        'presentation_mode' => ['type' => 'select', 'label' => 'Modo de Presentación', 'options' => ['grid', 'inline_preview', 'modal_preview'], 'default' => 'modal_preview', 'required' => false],
                        'columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2', '3', '4', '6'], 'default' => '3', 'required' => false],
                        'gap' => ['type' => 'select', 'label' => 'Espaciado', 'options' => ['small', 'medium', 'large', 'none'], 'default' => 'medium', 'required' => false],
                        'css_class' => ['type' => 'string', 'label' => 'Clase CSS', 'required' => false, 'default' => ''],
                    ],
                    'allowed_children' => ['gallery_item'],
                ];
        $this->db->table('cms_content_blocks')
            ->where('block_key', 'gallery')
            ->update(['schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    private function sanitize_BindPressGalleryToHubFiles(): void
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
            $hubFileId = self::Sanitize_BindPressGalleryToHubFiles_HUB_FILE_IDS[$index] ?? null;
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

    // Helpers/properties from class:
    /** @var list<int> */
    private const Sanitize_BindPressGalleryToHubFiles_HUB_FILE_IDS = [1764, 1765, 1766, 1767, 1768, 1769, 1770, 1771];

    private function sanitize_IntroduceSemanticSlideNavigation(): void
    {
        $type = $this->db->table('cms_content_blocks')->where('block_key', 'slide_banner')->get()->getRowArray();
        if (! is_array($type)) {
            return;
        }

        $schema = json_decode((string) ($type['schema_definition'] ?? ''), true);
        if (! is_array($schema)) {
            return;
        }
        $schema['fields']['external_url'] = ['type' => 'url', 'label' => 'URL externa', 'required' => false];
        unset($schema['fields']['cta_url']);
        $schema['config_fields'] = [
            'navigation_mode' => ['type' => 'select', 'label' => 'Destino del slide', 'options' => ['none', 'internal', 'external'], 'default' => 'internal', 'required' => true],
            'navigation_target_type' => ['type' => 'select', 'label' => 'Tipo de destino interno', 'options' => ['page', 'event_listing', 'catalog_listing', 'collection_index'], 'default' => 'event_listing', 'required' => false],
            'page_id' => ['type' => 'select', 'label' => 'Página de destino', 'required' => false],
            'collection_id' => ['type' => 'select', 'label' => 'Colección de destino', 'required' => false],
            'external_target' => ['type' => 'select', 'label' => 'Abrir URL externa', 'options' => ['_self', '_blank'], 'default' => '_self', 'required' => false],
        ] + (array) ($schema['config_fields'] ?? []);
        $schema['navigation'] = ['source' => 'block_config', 'target' => 'slide_destination'];
        $this->db->table('cms_content_blocks')->where('id', (int) $type['id'])->update([
            'schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        $instances = $this->db->table('cms_block_instances')->where('block_id', (int) $type['id'])->get()->getResultArray();
        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? ''), true);
            $config = is_array($config) ? $config : [];
            $urls = [];
            foreach ($this->db->table('cms_block_instance_translations')->where('instance_id', (int) $instance['id'])->get()->getResultArray() as $translation) {
                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data)) {
                    continue;
                }
                $url = trim((string) ($data['cta_url'] ?? ''));
                if ($url !== '') {
                    $urls[] = $url;
                }
                if ($this->Sanitize_IntroduceSemanticSlideNavigation_isAbsoluteExternal($url)) {
                    $data['external_url'] = $url;
                }
                unset($data['cta_url']);
                $this->db->table('cms_block_instance_translations')->where('id', (int) $translation['id'])->update([
                    'block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
            }

            $destination = $this->Sanitize_IntroduceSemanticSlideNavigation_destinationFor($urls);
            $config['navigation_mode'] = $destination['mode'];
            $config['navigation_target_type'] = $destination['type'];
            if ($destination['page_id'] !== null) {
                $config['page_id'] = $destination['page_id'];
            }
            if ($destination['collection_id'] !== null) {
                $config['collection_id'] = $destination['collection_id'];
            }
            $config['external_target'] = $config['external_target'] ?? '_self';
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    // Helpers/properties from class:
    /** @param list<string> $urls @return array{mode:string,type:string,page_id:int|null,collection_id:int|null} */
    private function Sanitize_IntroduceSemanticSlideNavigation_destinationFor(array $urls): array
    {
        $url = strtolower(trim((string) ($urls[0] ?? '')));
        if ($this->Sanitize_IntroduceSemanticSlideNavigation_isAbsoluteExternal($url)) {
            return ['mode' => 'external', 'type' => 'page', 'page_id' => null, 'collection_id' => null];
        }
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? $url), '/');
        $segments = explode('/', $path);
        $lastSegment = (string) ($segments[count($segments) - 1] ?? '');
        if (in_array($lastSegment, ['cartelera', 'programming', 'programmation', 'programacao', 'events', 'programme', 'eventos'], true)) {
            return ['mode' => 'internal', 'type' => 'event_listing', 'page_id' => null, 'collection_id' => null];
        }
        $page = $this->db->table('cms_pages')->whereIn('page_type', ['home', 'contact'])->where('status', 'published')->where('deleted_at IS NULL', null, false)->get()->getResultArray();
        foreach ($page as $row) {
            if ($row['page_type'] === 'home' && ($path === '' || $path === 'home')) {
                return ['mode' => 'internal', 'type' => 'page', 'page_id' => (int) $row['id'], 'collection_id' => null];
            }
            if ($row['page_type'] === 'contact' && in_array($path, ['contacto', 'contact', 'contato', 'contactez-nous'], true)) {
                return ['mode' => 'internal', 'type' => 'page', 'page_id' => (int) $row['id'], 'collection_id' => null];
            }
        }
        return ['mode' => 'none', 'type' => 'page', 'page_id' => null, 'collection_id' => null];
    }

    private function Sanitize_IntroduceSemanticSlideNavigation_isAbsoluteExternal(string $url): bool
    {
        return (bool) preg_match('#^https?://[^\s]+$#i', trim($url));
    }

    private function sanitize_NormalizeTeatroEscuelaIdentifiers(): void
    {
        if (! $this->db->tableExists('cms_collections')) {
            return;
        }

        $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_renameUniqueValue('cms_collections', 'collection_key', 'cursos', 'teatroescuela');
        $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_renameUniqueValue('cms_content_blocks', 'block_key', 'curso_ficha', 'teatroescuela_ficha');
        $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceJsonReferences('cms_collections', ['block_template', 'wizard_config']);
        $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceJsonReferences('cms_block_instances', ['block_config']);
        $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceJsonReferences('cms_page_block_instances', ['block_config']);
    }

    // Helpers/properties from class:
    private function Sanitize_NormalizeTeatroEscuelaIdentifiers_renameUniqueValue(string $table, string $column, string $old, string $new): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        $oldRow = $this->db->table($table)->where($column, $old)->get()->getRowArray();
        if ($oldRow === null) {
            return;
        }
        $newRow = $this->db->table($table)->where($column, $new)->get()->getRowArray();
        if ($newRow !== null && (int) ($newRow['id'] ?? 0) !== (int) ($oldRow['id'] ?? 0)) {
            if ($table === 'cms_collections' && $column === 'collection_key') {
                $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_mergeCollections((int) $oldRow['id'], (int) $newRow['id']);
                return;
            }

            throw new \RuntimeException(sprintf('Cannot rename %s.%s: both %s and %s exist.', $table, $column, $old, $new));
        }
        $this->db->table($table)->where('id', (int) $oldRow['id'])->update([$column => $new]);
    }

    private function Sanitize_NormalizeTeatroEscuelaIdentifiers_mergeCollections(int $legacyId, int $canonicalId): void
    {
        $this->db->table('cms_entries')->where('collection_id', $legacyId)->update(['collection_id' => $canonicalId]);

        foreach ($this->db->table('cms_pages')->where('collection_id', $legacyId)->get()->getResultArray() as $page) {
            if (($page['deleted_at'] ?? null) !== null) {
                $this->db->table('cms_pages')->where('id', (int) $page['id'])->delete();
                continue;
            }

            $this->db->table('cms_pages')->where('id', (int) $page['id'])->update(['collection_id' => $canonicalId]);
        }

        foreach ($this->db->table('cms_collection_translations')->where('collection_id', $legacyId)->get()->getResultArray() as $translation) {
            $exists = $this->db->table('cms_collection_translations')
                ->where('collection_id', $canonicalId)
                ->where('language_id', (int) $translation['language_id'])
                ->get()
                ->getRowArray();
            if ($exists === null) {
                $this->db->table('cms_collection_translations')->where('id', (int) $translation['id'])->update(['collection_id' => $canonicalId]);
                continue;
            }

            $this->db->table('cms_collection_translations')->where('id', (int) $translation['id'])->delete();
        }

        $this->db->table('cms_collections')->where('id', $legacyId)->delete();
    }

    /** @param list<string> $columns */
    private function Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceJsonReferences(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        foreach ($this->db->table($table)->get()->getResultArray() as $row) {
            $updates = [];
            foreach ($columns as $column) {
                if (! is_string($row[$column] ?? null) || trim($row[$column]) === '') {
                    continue;
                }
                $decoded = json_decode($row[$column], true);
                if (! is_array($decoded)) {
                    continue;
                }
                $normalized = $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceRecursive($decoded);
                if ($normalized !== $decoded) {
                    $updates[$column] = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
            }
            if ($updates !== [] && isset($row['id'])) {
                $this->db->table($table)->where('id', (int) $row['id'])->update($updates);
            }
        }
    }

    /** @return array<string|int, mixed> */
    private function Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = is_array($item) ? $this->Sanitize_NormalizeTeatroEscuelaIdentifiers_replaceRecursive($item) : (is_string($item) ? match ($item) {
                'cursos' => 'teatroescuela', 'curso_ficha' => 'teatroescuela_ficha', default => $item,
            } : $item);
        }
        return $value;
    }

    private function sanitize_UnifyAboutPageLocales(): void
    {
        $pageId = $this->Sanitize_UnifyAboutPageLocales_pageId();
        if ($pageId === null) {
            return;
        }

        $instances = $this->Sanitize_UnifyAboutPageLocales_rootInstances($pageId);
        $kept = [];
        $seen = [];
        foreach ($instances as $instance) {
            $key = (string) ($instance['block_key'] ?? '');
            $id = (int) ($instance['id'] ?? 0);
            if ($id <= 0 || in_array($key, self::Sanitize_UnifyAboutPageLocales_IMPORTED_ROOT_BLOCKS, true) || ! in_array($key, self::Sanitize_UnifyAboutPageLocales_CANONICAL_ROOT_BLOCKS, true) || isset($seen[$key])) {
                if ($id > 0) {
                    $this->db->table('cms_block_instances')->where('id', $id)->delete();
                }
                continue;
            }

            $seen[$key] = true;
            $kept[$key] = $id;
        }

        if (isset($kept['rich_text'])) {
            $this->Sanitize_UnifyAboutPageLocales_ensureSpanishHeading((int) $kept['rich_text']);
            $this->Sanitize_UnifyAboutPageLocales_writeTranslatedRichText((int) $kept['rich_text']);
        }

        if (isset($kept['cards_grid'])) {
            $this->Sanitize_UnifyAboutPageLocales_normalizeCards((int) $kept['cards_grid']);
        }

        if (isset($kept['team_grid'])) {
            $this->Sanitize_UnifyAboutPageLocales_mergeConfig((int) $kept['team_grid'], ['columns' => '3']);
        }
    }

    // Helpers/properties from class:
    /** @var list<string> */
    private const Sanitize_UnifyAboutPageLocales_PAGE_SLUGS = ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'];

    /** @var list<string> */
    private const Sanitize_UnifyAboutPageLocales_CANONICAL_ROOT_BLOCKS = ['page_header', 'hero_slider', 'rich_text', 'cards_grid', 'team_grid', 'cta'];

    /** @var list<string> */
    private const Sanitize_UnifyAboutPageLocales_IMPORTED_ROOT_BLOCKS = ['hero_banner', 'cards_slider', 'asset_showcase', 'accordion'];





    private function Sanitize_UnifyAboutPageLocales_pageId(): ?int
    {
        $result = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('p.deleted_at IS NULL', null, false)
            ->whereIn('pt.slug', self::Sanitize_UnifyAboutPageLocales_PAGE_SLUGS)
            ->orderBy('p.id', 'ASC')
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /** @return list<array<string, mixed>> */
    private function Sanitize_UnifyAboutPageLocales_rootInstances(int $pageId): array
    {
        $result = $this->db->table('cms_block_instances i')
            ->select('i.id, b.block_key')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'page')
            ->where('i.owner_id', $pageId)
            ->where('i.parent_instance_id IS NULL', null, false)
            ->orderBy('i.sort_order', 'ASC')
            ->orderBy('i.id', 'ASC')
            ->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    private function Sanitize_UnifyAboutPageLocales_ensureSpanishHeading(int $instanceId): void
    {
        $translation = $this->Sanitize_UnifyAboutPageLocales_translation($instanceId, 'es');
        if ($translation === null) {
            return;
        }

        $content = (string) ($translation['content'] ?? '');
        if (str_contains($content, '<h2>Sobre Nosotros</h2>')) {
            return;
        }

        $this->Sanitize_UnifyAboutPageLocales_saveTranslation($instanceId, 'es', ['content' => '<h2>Sobre Nosotros</h2>' . $content]);
    }

    private function Sanitize_UnifyAboutPageLocales_writeTranslatedRichText(int $instanceId): void
    {
        $translations = [
            'en' => '<h2>About Us</h2><p>Since 2007, the Teatromuseo Puppet and Clown Foundation has promoted, disseminated, and professionalized these performing arts in Chile through a national and international training school, a specialized museum, and a theatre with a permanent family programme.</p><p>We are a team of artists and cultural-management professionals who believe in life and laughter as tools for human development.</p>',
            'fr' => '<h2>À propos de nous</h2><p>Depuis 2007, la Fondation Teatromuseo de la marionnette et du clown promeut, diffuse et professionnalise ces arts de la scène au Chili grâce à une école de formation nationale et internationale, un musée spécialisé et une salle de théâtre proposant une programmation familiale permanente.</p><p>Nous sommes une équipe d’artistes et de professionnels de la gestion culturelle qui croyons en la vie et au rire comme outils de développement humain.</p>',
            'pt' => '<h2>Sobre Nós</h2><p>Desde 2007, a Fundação Teatromuseo do teatro de bonecos e do palhaço promove, difunde e profissionaliza essas artes da representação no Chile por meio de uma escola de formação nacional e internacional, um museu especializado e uma sala de teatro com programação familiar permanente.</p><p>Somos uma equipe de artistas e profissionais da gestão cultural que acredita na vida e no riso como ferramentas de desenvolvimento humano.</p>',
        ];

        foreach ($translations as $language => $content) {
            $this->Sanitize_UnifyAboutPageLocales_saveTranslation($instanceId, $language, ['content' => $content]);
        }
    }

    private function Sanitize_UnifyAboutPageLocales_normalizeCards(int $instanceId): void
    {
        $cardTypeResult = $this->db->table('cms_content_blocks')->select('id')->where('block_key', 'card_item')->get();
        $cardType = $cardTypeResult === false ? null : $cardTypeResult->getRowArray();
        if (! is_array($cardType)) {
            return;
        }

        $childrenResult = $this->db->table('cms_block_instances')
            ->where('parent_instance_id', $instanceId)
            ->where('block_id', (int) $cardType['id'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        $children = $childrenResult === false ? [] : $childrenResult->getResultArray();

        foreach (array_slice($children, 2) as $child) {
            $this->db->table('cms_block_instances')->where('id', (int) $child['id'])->delete();
        }

        $this->Sanitize_UnifyAboutPageLocales_mergeConfig($instanceId, ['columns_desktop' => '2', 'variant' => 'institutional']);
        $copy = [
            1 => [
                'en' => ['title' => 'Mission', 'description' => 'Strengthen, disseminate, and develop puppet and clown arts, enriching Chile’s cultural heritage and training new exponents through networks, schools, encounters, publications, and theatres.'],
                'fr' => ['title' => 'Mission', 'description' => 'Renforcer, diffuser et développer les arts de la marionnette et du clown, en enrichissant le patrimoine culturel du Chili et en formant de nouveaux artistes par des réseaux, écoles, rencontres, publications et salles de théâtre.'],
                'pt' => ['title' => 'Missão', 'description' => 'Fortalecer, difundir e desenvolver a arte do teatro de bonecos e do palhaço, enriquecendo o patrimônio cultural do Chile e formando novos artistas por meio de redes, escolas, encontros, publicações e salas de teatro.'],
            ],
            2 => [
                'en' => ['title' => 'Vision', 'description' => 'Establish the Teatromuseo Foundation as a space for research and development in these arts, so Valparaíso is recognized nationally and internationally as the cultural capital of puppetry and clowning.'],
                'fr' => ['title' => 'Vision', 'description' => 'Consolider la Fondation Teatromuseo comme un espace de recherche et de développement de ces arts, afin que Valparaíso soit reconnue nationalement et internationalement comme la capitale culturelle de la marionnette et du clown.'],
                'pt' => ['title' => 'Visão', 'description' => 'Consolidar a Fundação Teatromuseo como um espaço de pesquisa e desenvolvimento dessas artes, fazendo com que Valparaíso seja reconhecida nacional e internacionalmente como a capital cultural do teatro de bonecos e do palhaço.'],
            ],
        ];

        foreach (array_slice($children, 0, 2) as $index => $child) {
            foreach ($copy[$index + 1] ?? [] as $language => $data) {
                $this->Sanitize_UnifyAboutPageLocales_saveTranslation((int) $child['id'], $language, $data);
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function Sanitize_UnifyAboutPageLocales_translation(int $instanceId, string $language): ?array
    {
        $result = $this->db->table('cms_block_instance_translations t')
            ->select('t.id, t.block_data')
            ->join('cms_languages l', 'l.id = t.language_id')
            ->where('t.instance_id', $instanceId)
            ->where('l.code', $language)
            ->get();
        $row = $result === false ? null : $result->getRowArray();
        if (! is_array($row)) {
            return null;
        }

        $data = json_decode((string) ($row['block_data'] ?? '{}'), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function Sanitize_UnifyAboutPageLocales_saveTranslation(int $instanceId, string $language, array $data): void
    {
        $languageResult = $this->db->table('cms_languages')->select('id')->where('code', $language)->get();
        $languageRow = $languageResult === false ? null : $languageResult->getRowArray();
        if (! is_array($languageRow)) {
            return;
        }

        $languageId = (int) $languageRow['id'];
        $existingResult = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', $instanceId)
            ->where('language_id', $languageId)
            ->get();
        $existing = $existingResult === false ? null : $existingResult->getRowArray();
        $current = is_array($existing) ? json_decode((string) ($existing['block_data'] ?? '{}'), true) : [];
        $payload = [
            'block_data' => json_encode(array_merge(is_array($current) ? $current : [], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'is_published' => 1,
        ];

        if (is_array($existing)) {
            $this->db->table('cms_block_instance_translations')->where('id', (int) $existing['id'])->update($payload);
        } else {
            $this->db->table('cms_block_instance_translations')->insert($payload + ['instance_id' => $instanceId, 'language_id' => $languageId]);
        }
    }

    /** @param array<string, mixed> $config */
    private function Sanitize_UnifyAboutPageLocales_mergeConfig(int $instanceId, array $config): void
    {
        $result = $this->db->table('cms_block_instances')->select('block_config')->where('id', $instanceId)->get();
        $row = $result === false ? null : $result->getRowArray();
        $current = is_array($row) ? json_decode((string) ($row['block_config'] ?? '{}'), true) : [];
        $this->db->table('cms_block_instances')->where('id', $instanceId)->update([
            'block_config' => json_encode(array_merge(is_array($current) ? $current : [], $config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private function sanitize_PersistAboutSpanishEditorialContent(): void
    {
        $pageId = $this->Sanitize_PersistAboutSpanishEditorialContent_pageId();
        if ($pageId === null) {
            return;
        }

        $richText = $this->Sanitize_PersistAboutSpanishEditorialContent_instanceId($pageId, 'rich_text');
        if ($richText !== null) {
            $this->Sanitize_PersistAboutSpanishEditorialContent_mergeTranslation($richText, 'es', [
                'content' => '<h2>Sobre Nosotros</h2><p>Desde el año 2007, la Fundación Teatromuseo del títere y el payaso se ha dedicado a promover, difundir y profesionalizar estas artes de la representación en nuestro país. A través de una escuela de formación nacional e internacional, un museo especializado y una sala de teatro con cartelera familiar permanente.</p><p>Somos un equipo de artistas y profesionales de la gestión cultural que creemos en la vida y la risa como herramientas de desarrollo humano.</p>',
            ]);
        }

        $cardsGrid = $this->Sanitize_PersistAboutSpanishEditorialContent_instanceId($pageId, 'cards_grid');
        $cardType = $this->Sanitize_PersistAboutSpanishEditorialContent_blockTypeId('card_item');
        if ($cardsGrid === null || $cardType === null) {
            return;
        }

        $children = $this->db->table('cms_block_instances')
            ->where('parent_instance_id', $cardsGrid)
            ->where('block_id', $cardType)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        if ($children === false) {
            return;
        }

        $copy = [
            ['title' => 'Nuestra Misión', 'description' => 'Fortalecer, difundir y desarrollar el arte del títere y el payaso, enriqueciendo el patrimonio cultural de nuestro país y formando nuevos exponentes mediante redes, escuelas, encuentros, publicaciones y salas de teatro.'],
            ['title' => 'Nuestra Visión', 'description' => 'Consolidar a la Fundación Teatromuseo como un espacio de investigación y desarrollo de estas artes, logrando que Valparaíso sea reconocido nacional e internacionalmente como la capital cultural del títere y el payaso.'],
        ];

        foreach (array_values($children->getResultArray()) as $index => $child) {
            if (! isset($copy[$index])) {
                break;
            }

            $this->Sanitize_PersistAboutSpanishEditorialContent_mergeTranslation((int) $child['id'], 'es', $copy[$index]);
        }
    }

    // Helpers/properties from class:
    private function Sanitize_PersistAboutSpanishEditorialContent_pageId(): ?int
    {
        $result = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations t', 't.page_id = p.id')
            ->whereIn('t.slug', ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('p.id', 'ASC')
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function Sanitize_PersistAboutSpanishEditorialContent_blockTypeId(string $blockKey): ?int
    {
        $result = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $blockKey)->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function Sanitize_PersistAboutSpanishEditorialContent_instanceId(int $pageId, string $blockKey): ?int
    {
        $result = $this->db->table('cms_block_instances i')
            ->select('i.id')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'page')
            ->where('i.owner_id', $pageId)
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', $blockKey)
            ->orderBy('i.sort_order', 'ASC')
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /** @param array<string, mixed> $data */
    private function Sanitize_PersistAboutSpanishEditorialContent_mergeTranslation(int $instanceId, string $language, array $data): void
    {
        $languageResult = $this->db->table('cms_languages')->select('id')->where('code', $language)->get();
        $languageRow = $languageResult === false ? null : $languageResult->getRowArray();
        if (! is_array($languageRow)) {
            return;
        }

        $existingResult = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', $instanceId)
            ->where('language_id', (int) $languageRow['id'])
            ->get();
        $existing = $existingResult === false ? null : $existingResult->getRowArray();
        $current = is_array($existing) ? json_decode((string) ($existing['block_data'] ?? '{}'), true) : [];
        $payload = [
            'block_data' => json_encode(array_merge(is_array($current) ? $current : [], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'is_published' => 1,
        ];

        if (is_array($existing)) {
            $this->db->table('cms_block_instance_translations')->where('id', (int) $existing['id'])->update($payload);
            return;
        }

        $this->db->table('cms_block_instance_translations')->insert($payload + [
            'instance_id' => $instanceId,
            'language_id' => (int) $languageRow['id'],
        ]);
    }

    private function sanitize_CreateAboutTeamChildren(): void
    {
        $this->Sanitize_CreateAboutTeamChildren_ensureTeamMemberSchema();
        $pageId = $this->Sanitize_CreateAboutTeamChildren_pageId();
        $parentId = $pageId !== null ? $this->Sanitize_CreateAboutTeamChildren_instanceId($pageId, 'team_grid') : null;
        $teamMemberType = $this->Sanitize_CreateAboutTeamChildren_blockTypeId('team_member');
        $collectionId = $this->Sanitize_CreateAboutTeamChildren_collectionId('personas');
        $esId = $this->Sanitize_CreateAboutTeamChildren_languageId('es');
        if ($parentId === null || $teamMemberType === null || $collectionId === null || $esId === null) {
            return;
        }

        if ($this->db->table('cms_block_instances')->where('parent_instance_id', $parentId)->countAllResults() > 0) {
            return;
        }

        $parent = $this->db->table('cms_block_instances')->select('block_config')->where('id', $parentId)->get()->getRowArray();
        $config = is_array($parent) ? json_decode((string) ($parent['block_config'] ?? '{}'), true) : [];
        $names = array_values(array_filter(array_map('trim', explode(',', (string) ($config['filter_names'] ?? '')))));
        if ($names === []) {
            return;
        }

        $rows = $this->db->table('cms_entries e')
            ->select('e.id, t.title, t.excerpt, t.featured_image_url, e.wizard_extra')
            ->join('cms_entry_translations t', 't.entry_id=e.id AND t.language_id=' . $esId, 'left')
            ->where('e.collection_id', $collectionId)
            ->where('e.workflow_status', 'published')
            ->where('e.deleted_at IS NULL', null, false)
            ->get()->getResultArray();
        $byName = [];
        foreach ($rows as $row) {
            $byName[$this->Sanitize_CreateAboutTeamChildren_normalize((string) ($row['title'] ?? ''))] = $row;
        }

        foreach ($names as $order => $name) {
            $entry = $byName[$this->Sanitize_CreateAboutTeamChildren_normalize($name)] ?? null;
            if (! is_array($entry)) {
                continue;
            }
            $extra = json_decode((string) ($entry['wizard_extra'] ?? '{}'), true);
            $extra = is_array($extra) ? $extra : [];
            $this->Sanitize_CreateAboutTeamChildren_createChild(
                $parentId,
                $teamMemberType,
                $pageId,
                $order + 1,
                (string) ($entry['featured_image_url'] ?? ''),
                $extra,
                (string) ($entry['title'] ?? $name),
                (string) ($entry['excerpt'] ?? '')
            );
        }
    }

    // Helpers/properties from class:
    private const Sanitize_CreateAboutTeamChildren_PAGE_SLUGS = ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'];





    private function Sanitize_CreateAboutTeamChildren_createChild(int $parentId, int $typeId, ?int $ownerId, int $sort, string $photo, array $extra, string $name, string $excerpt): void
    {
        $photo = $this->Sanitize_CreateAboutTeamChildren_portableImageUrl($photo);
        $hover = $this->Sanitize_CreateAboutTeamChildren_hoverUrl($name);
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $typeId,
            'owner_type' => 'page',
            'owner_id' => $ownerId ?? 0,
            'parent_instance_id' => $parentId,
            'sort_order' => $sort,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'photo' => ['source_kind' => 'external_url', 'file_id' => null, 'url' => $photo],
                'hover_photo' => ['source_kind' => 'external_url', 'file_id' => null, 'url' => $hover],
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $id = $this->db->insertID();
        if ($id <= 0) {
            return;
        }
        foreach ($this->db->table('cms_languages')->get()->getResultArray() as $language) {
            $code = (string) $language['code'];
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $id,
                'language_id' => (int) $language['id'],
                'block_data' => json_encode([
                    'name' => $name,
                    'position' => (string) ($extra['position'] ?? $excerpt),
                    'profession' => (string) ($extra['profession'] ?? ''),
                    'email' => (string) ($extra['email'] ?? ''),
                    'bio' => $excerpt,
                    // Roles are additional responsibilities only. Profession
                    // and primary position have their own dedicated fields.
                    'roles' => [],
                    'linkedin_url' => '',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => 1,
            ]);
        }
    }

    private function Sanitize_CreateAboutTeamChildren_hoverUrl(string $name): string
    {
        $base = '/images/team/';
        $files = [
            'Víctor Quiroga' => 'victor-quiroga-01.png', 'Diego Zuñiga' => '6713f9c476bd5.png',
            'Paulina Beltrán' => 'paulina-beltran-01.png', 'Constanza Valenzuela' => 'constanza-valenzuela-01.png',
            'Felipe Lira' => 'felipe-lira-01.png', 'Claudio Palacios' => 'claudio-palacios-01.png',
            'Kevin Zamora' => '6713f9d87d5d7.png', 'Barbara Quiroga' => 'barbara-quiroga-01.png',
            'Javiera Silva' => '67128c39380a9.png', 'Tomás Arce' => '6713faca5094d.png',
        ];
        return $base . ($files[$name] ?? '');
    }

    private function Sanitize_CreateAboutTeamChildren_portableImageUrl(string $url): string
    {
        $url = trim($url);
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/images/team/') ? $path : $url;
    }

    private function Sanitize_CreateAboutTeamChildren_pageId(): ?int
    {
        $r = $this->db->table('cms_pages p')->select('p.id')->join('cms_page_translations t', 't.page_id=p.id')->whereIn('t.slug', self::Sanitize_CreateAboutTeamChildren_PAGE_SLUGS)->where('p.deleted_at IS NULL', null, false)->orderBy('p.id', 'ASC')->get()->getRowArray();
        return is_array($r) ? (int) $r['id'] : null;
    }
    private function Sanitize_CreateAboutTeamChildren_instanceId(int $pageId, string $key): ?int
    {
        $r = $this->db->table('cms_block_instances i')->select('i.id')->join('cms_content_blocks b', 'b.id=i.block_id')->where(['i.owner_type' => 'page','i.owner_id' => $pageId,'b.block_key' => $key])->where('i.parent_instance_id IS NULL', null, false)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function Sanitize_CreateAboutTeamChildren_blockTypeId(string $key): ?int
    {
        $r = $this->db->table('cms_content_blocks')->select('id')->where('block_key', $key)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function Sanitize_CreateAboutTeamChildren_collectionId(string $key): ?int
    {
        $r = $this->db->table('cms_collections')->select('id')->where('collection_key', $key)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function Sanitize_CreateAboutTeamChildren_languageId(string $code): ?int
    {
        $r = $this->db->table('cms_languages')->select('id')->where('code', $code)->get()->getRowArray();
        return is_array($r) ? (int)$r['id'] : null;
    }
    private function Sanitize_CreateAboutTeamChildren_normalize(string $value): string
    {
        return mb_strtolower(strtr(trim($value), ['á' => 'a','é' => 'e','í' => 'i','ó' => 'o','ú' => 'u','ñ' => 'n']));
    }

    private function Sanitize_CreateAboutTeamChildren_ensureTeamMemberSchema(): void
    {
        $row = $this->db->table('cms_content_blocks')->select('schema_definition')->where('block_key', 'team_member')->get()->getRowArray();
        if (! is_array($row)) {
            return;
        }
        $schema = json_decode((string) ($row['schema_definition'] ?? '{}'), true);
        if (! is_array($schema)) {
            return;
        }
        $schema['fields']['profession'] = ['type' => 'string', 'label' => 'Profesión', 'required' => false];
        $schema['fields']['email'] = ['type' => 'email', 'label' => 'Correo público', 'required' => false];
        $schema['fields']['roles'] = ['type' => 'repeater', 'label' => 'Roles adicionales', 'required' => false, 'item_fields' => ['label' => ['type' => 'string', 'label' => 'Rol adicional', 'required' => false]]];
        $schema['config_fields']['hover_photo'] = ['type' => 'media_reference', 'label' => 'Foto al pasar el cursor', 'accept' => 'image', 'required' => false];
        $this->db->table('cms_content_blocks')->where('block_key', 'team_member')->update([
            'schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function sanitize_CompleteAboutTeamPrimaryMedia(): void
    {
        $parentId = $this->Sanitize_CompleteAboutTeamPrimaryMedia_parentId();
        if ($parentId === null) {
            return;
        }
        $children = $this->db->table('cms_block_instances c')
            ->select('c.id, c.block_config, t.block_data')
            ->join('cms_content_blocks b', 'b.id=c.block_id')
            ->join('cms_block_instance_translations t', 't.instance_id=c.id')
            ->join('cms_languages l', 'l.id=t.language_id AND l.code="es"')
            ->where('c.parent_instance_id', $parentId)
            ->where('b.block_key', 'team_member')
            ->get();
        if ($children === false) {
            return;
        }

        foreach ($children->getResultArray() as $child) {
            $data = json_decode((string) ($child['block_data'] ?? '{}'), true);
            $name = is_array($data) ? (string) ($data['name'] ?? '') : '';
            $photo = self::Sanitize_CompleteAboutTeamPrimaryMedia_PRIMARY_MEDIA[$name] ?? null;
            if ($photo === null) {
                continue;
            }

            $config = json_decode((string) ($child['block_config'] ?? '{}'), true);
            $config = is_array($config) ? $config : [];
            $current = is_array($config['photo'] ?? null) ? $config['photo'] : [];
            if (trim((string) ($current['url'] ?? '')) !== '') {
                continue;
            }
            $config['photo'] = ['source_kind' => 'external_url', 'file_id' => null, 'url' => $photo];
            $this->db->table('cms_block_instances')->where('id', (int) $child['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    // Helpers/properties from class:
    /** @var array<string, string> */
    private const Sanitize_CompleteAboutTeamPrimaryMedia_PRIMARY_MEDIA = [
        'Víctor Quiroga' => '/images/team/victor-quiroga.png',
        'Paulina Beltrán' => '/images/team/paulina-beltran.png',
        'Tomás Arce' => '/images/team/6713faca50701.png',
        'Kevin Zamora' => '/images/team/6713f9d87ce79.png',
        'Javiera Silva' => '/images/team/67128c3937d9c.png',
    ];





    private function Sanitize_CompleteAboutTeamPrimaryMedia_parentId(): ?int
    {
        $row = $this->db->table('cms_block_instances i')
            ->select('i.id')
            ->join('cms_content_blocks b', 'b.id=i.block_id')
            ->join('cms_pages p', 'p.id=i.owner_id')
            ->join('cms_page_translations pt', 'pt.page_id=p.id')
            ->where('i.owner_type', 'page')
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', 'team_grid')
            ->whereIn('pt.slug', ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('i.id', 'ASC')
            ->get()->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function sanitize_AddListingFieldProjection(): void
    {
        $rows = $this->db->table('cms_content_blocks')->select('id, block_key, schema_definition')->where('is_active', 1)->get()->getResultArray();
        foreach ($rows as $row) {
            $schema = json_decode((string) ($row['schema_definition'] ?? '{}'), true);
            if (! is_array($schema)) {
                continue;
            }
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $listingFields = [];
            foreach ($fields as $key => $field) {
                if (is_array($field) && ($field['type'] ?? '') === 'date') {
                    $listingFields[(string) $key] = ['label' => (string) ($field['label'] ?? $key), 'type' => 'date'];
                }
            }
            if (in_array((string) ($row['block_key'] ?? ''), ['collection_grid', 'collection_listing'], true)) {
                $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
                $configFields['date_field'] = [
                    'type' => 'select',
                    'label' => 'Fecha visible en tarjeta',
                    'description' => 'Usa una fecha declarada por la ficha o una fecha editorial estándar.',
                    'options' => ['auto', 'published_at', 'created_at', 'listing.publication_date', 'listing.start_date', 'listing.end_date', 'listing.opening_date', 'listing.closing_date', 'listing.premiere_date', 'listing.performance_date', 'listing.recorded_at'],
                    'default' => 'auto',
                    'required' => false,
                ];
                $schema['config_fields'] = $configFields;
            }
            if ($listingFields === [] && ! isset($schema['listing_fields']) && ! in_array((string) ($row['block_key'] ?? ''), ['collection_grid', 'collection_listing'], true)) {
                continue;
            }
            $schema['listing_fields'] = $listingFields;
            $this->db->table('cms_content_blocks')->where('id', (int) $row['id'])->update([
                'schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        $blockIds = $this->Sanitize_AddListingFieldProjection_blockIds(['collection_grid', 'collection_listing']);
        if ($blockIds === []) {
            return;
        }
        $instances = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_config, c.collection_key')
            ->join('cms_collections c', "c.id = JSON_UNQUOTE(JSON_EXTRACT(i.block_config, '$.collection_id'))", 'left', false)
            ->whereIn('i.block_id', $blockIds)
            ->get()
            ->getResultArray();
        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config)) {
                continue;
            }
            $collectionKey = strtolower((string) ($config['collection_key'] ?? $instance['collection_key'] ?? ''));
            if (! in_array($collectionKey, ['teatroescuela', 'cursos'], true)) {
                continue;
            }

            $projection = $this->Sanitize_ListingProjectionFromConfig($config);
            $projectionVersion = (int) ($projection['version'] ?? 0);
            $configuredDirection = strtolower(trim((string) ($config['order_direction'] ?? '')));
            $projectionOrder = is_array($projection['order'] ?? null) ? $projection['order'] : [];
            $projectionDirection = strtolower(trim((string) ($projectionOrder['direction'] ?? '')));
            $legacyAgendaConfiguration = $projectionVersion < 2
                && in_array($configuredDirection, ['', 'asc'], true)
                && in_array($projectionDirection, ['', 'asc'], true);

            // This is a one-time upgrade from the original ascending date
            // default. Once projection version 2 exists, editors can choose
            // asc/desc/upcoming without the bootstrap seeder overwriting it.
            if ($projectionVersion < 2 && ($legacyAgendaConfiguration || $configuredDirection === '')) {
                $config['date_field'] = 'listing.start_date';
                $config['order_by'] = 'field:start_date';
                $config['order_direction'] = 'upcoming';
            }
            if ($projection !== null) {
                $projection['version'] = max(2, $projectionVersion);
                $projection['order'] = $projectionOrder;
                if (in_array($collectionKey, ['teatroescuela', 'cursos'], true)) {
                    $projection['slots'] = is_array($projection['slots'] ?? null) ? $projection['slots'] : [];
                    $projection['order'] = is_array($projection['order'] ?? null) ? $projection['order'] : [];
                    $projection['slots']['date'] = $this->Sanitize_NormalizeTheatreSchoolProjectionField(
                        (string) ($projection['slots']['date'] ?? '')
                    );
                    $projection['order']['field'] = $this->Sanitize_NormalizeTheatreSchoolProjectionField(
                        (string) ($projection['order']['field'] ?? '')
                    );
                }
                if ($legacyAgendaConfiguration || $projectionDirection === '') {
                    $projection['order']['field'] = 'block.teatroescuela_ficha.start_date';
                    $projection['order']['direction'] = 'upcoming';
                }
                $config['listing_projection'] = $projection;
            }
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    // Helpers/properties from class:
    /** @param list<string> $keys @return list<int> */
    private function Sanitize_AddListingFieldProjection_blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->select('id')->whereIn('block_key', $keys)->get()->getResultArray();
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    private function sanitize_BackfillListingProjections(): void
    {
        $blockIds = $this->Sanitize_BackfillListingProjections_blockIds(['collection_grid', 'collection_listing']);
        if ($blockIds === []) {
            return;
        }

        $instances = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_config, c.block_template, c.collection_key')
            ->join('cms_collections c', "c.id = JSON_UNQUOTE(JSON_EXTRACT(i.block_config, '$.collection_id'))", 'left', false)
            ->whereIn('i.block_id', $blockIds)
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config)) {
                continue;
            }
            $existingProjection = $this->Sanitize_ListingProjectionFromConfig($config);
            if ($existingProjection !== null) {
                if (is_string($config['listing_projection'] ?? null)) {
                    $config['listing_projection'] = $existingProjection;
                    $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                        'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
                continue;
            }

            $template = json_decode((string) ($instance['block_template'] ?? '{}'), true);
            $primaryBlockKey = $this->Sanitize_BackfillListingProjections_primaryBlockKey($template);
            $collectionKey = strtolower(trim((string) ($config['collection_key'] ?? $instance['collection_key'] ?? '')));
            $dateField = trim((string) ($config['date_field'] ?? ''));
            $orderField = trim((string) ($config['order_by'] ?? ''));
            $dateSource = $this->Sanitize_BackfillListingProjections_canonicalSource($dateField, $primaryBlockKey);
            $orderSource = $this->Sanitize_BackfillListingProjections_canonicalSource(str_starts_with($orderField, 'field:') ? substr($orderField, 6) : $orderField, $primaryBlockKey);

            $config['listing_projection'] = [
                'version' => in_array($collectionKey, ['teatroescuela', 'cursos'], true) ? 2 : 1,
                'slots' => [
                    'title' => 'entry.title',
                    'subtitle' => '',
                    'summary' => 'entry.excerpt',
                    'date' => $dateSource,
                    'image' => '',
                ],
                'extras' => [],
                'order' => [
                    'field' => $orderSource,
                    'direction' => match (strtolower((string) ($config['order_direction'] ?? 'desc'))) {
                        'asc', 'desc', 'upcoming' => strtolower((string) $config['order_direction']),
                        default => 'desc',
                    },
                ],
                'filters' => [],
            ];
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    // Helpers/properties from class:
    /** @param list<string> $keys @return list<int> */
    private function Sanitize_BackfillListingProjections_blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->select('id')->whereIn('block_key', $keys)->get()->getResultArray();
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    private function Sanitize_NormalizeTheatreSchoolProjectionField(string $field): string
    {
        return in_array($field, ['start_date', 'listing.start_date', 'field:start_date'], true)
            ? 'block.teatroescuela_ficha.start_date'
            : $field;
    }

    /**
     * Decode the JSON projection emitted by older Admin clients before the
     * idempotent content pass decides whether a projection needs backfilling.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    private function Sanitize_ListingProjectionFromConfig(array $config): ?array
    {
        $projection = $config['listing_projection'] ?? null;
        if (is_array($projection)) {
            return $projection;
        }
        if (! is_string($projection) || trim($projection) === '') {
            return null;
        }

        $decoded = json_decode($projection, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function Sanitize_BackfillListingProjections_primaryBlockKey(mixed $template): string
    {
        $blocks = is_array($template) && is_array($template['blocks'] ?? null) ? $template['blocks'] : [];
        foreach ($blocks as $block) {
            if (is_array($block) && trim((string) ($block['block_key'] ?? '')) !== '') {
                return trim((string) $block['block_key']);
            }
        }

        return '';
    }

    private function Sanitize_BackfillListingProjections_canonicalSource(string $source, string $primaryBlockKey): string
    {
        if ($source === '' || $source === 'auto') {
            return '';
        }
        if (str_starts_with($source, 'listing.') && $primaryBlockKey !== '') {
            return 'block.' . $primaryBlockKey . '.' . substr($source, 8);
        }
        if (in_array($source, ['published_at', 'created_at', 'title'], true)) {
            return 'entry.' . $source;
        }
        return $source;
    }

    private function sanitize_NormalizeListingProjectionReferences(): void
    {
        $blockIds = $this->Sanitize_NormalizeListingProjectionReferences_blockIds(['collection_grid', 'collection_listing']);
        if ($blockIds === []) {
            return;
        }

        $instances = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_config, c.block_template')
            ->join('cms_collections c', "c.id = JSON_UNQUOTE(JSON_EXTRACT(i.block_config, '$.collection_id'))", 'left', false)
            ->whereIn('i.block_id', $blockIds)
            ->get()->getResultArray();

        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config) || ! is_array($config['listing_projection'] ?? null)) {
                continue;
            }
            $template = json_decode((string) ($instance['block_template'] ?? '{}'), true);
            $blockKey = $this->Sanitize_NormalizeListingProjectionReferences_primaryBlockKey($template);
            $projection = $config['listing_projection'];
            $projection['slots'] = is_array($projection['slots'] ?? null) ? $projection['slots'] : [];
            $projection['order'] = is_array($projection['order'] ?? null) ? $projection['order'] : [];
            foreach (['date'] as $slot) {
                $projection['slots'][$slot] = $this->Sanitize_NormalizeListingProjectionReferences_normalize((string) ($projection['slots'][$slot] ?? ''), $blockKey);
            }
            $projection['order']['field'] = $this->Sanitize_NormalizeListingProjectionReferences_normalize((string) ($projection['order']['field'] ?? ''), $blockKey);
            $config['listing_projection'] = $projection;
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    // Helpers/properties from class:
    /** @param list<string> $keys @return list<int> */
    private function Sanitize_NormalizeListingProjectionReferences_blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->select('id')->whereIn('block_key', $keys)->get()->getResultArray();
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    private function Sanitize_NormalizeListingProjectionReferences_primaryBlockKey(mixed $template): string
    {
        $blocks = is_array($template) && is_array($template['blocks'] ?? null) ? $template['blocks'] : [];
        foreach ($blocks as $block) {
            if (is_array($block) && trim((string) ($block['block_key'] ?? '')) !== '') {
                return trim((string) $block['block_key']);
            }
        }
        return '';
    }

    private function Sanitize_NormalizeListingProjectionReferences_normalize(string $source, string $blockKey): string
    {
        if ($source === '' || $blockKey === '' || str_contains($source, '.')) {
            return $source;
        }
        return 'block.' . $blockKey . '.' . $source;
    }

    private function sanitize_BackfillPublishedAtForPublishedEntries(): void
    {
        $this->db->table('cms_entries')
                    ->set('published_at', 'created_at', false)
                    ->where('workflow_status', 'published')
                    ->where('published_at IS NULL', null, false)
                    ->update();
    }

    private function sanitize_ClarifyCollectionListingTaxonomyLabels(): void
    {
        $rows = $this->db->table('cms_content_blocks')
                    ->select('id, schema_definition')
                    ->where('block_key', 'collection_listing')
                    ->get()
                    ->getResultArray();

        foreach ($rows as $row) {
            $schema = json_decode((string) ($row['schema_definition'] ?? '{}'), true);
            if (! is_array($schema)) {
                continue;
            }

            $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
            if (is_array($configFields['show_tags'] ?? null)) {
                $configFields['show_tags']['label'] = 'Mostrar filtro por etiquetas o tipo';
                $configFields['show_tags']['description'] = 'Muestra chips de filtro sobre el listado. No muestra la etiqueta dentro de cada tarjeta.';
            }
            if (is_array($configFields['show_item_categories'] ?? null)) {
                $configFields['show_item_categories']['label'] = 'Mostrar clasificación en cada tarjeta';
                $configFields['show_item_categories']['description'] = 'Muestra la categoría o el tipo del elemento dentro de su tarjeta. No crea filtros.';
            }

            $schema['config_fields'] = $configFields;
            $this->db->table('cms_content_blocks')
                ->where('id', (int) ($row['id'] ?? 0))
                ->update(['schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    private function sanitize_ConsolidateAboutTeamBlocks(): void
    {
        $pageId = $this->Sanitize_ConsolidateAboutTeamBlocks_pageId();
        $teamTypeId = $this->Sanitize_ConsolidateAboutTeamBlocks_teamTypeId();
        if ($pageId === null || $teamTypeId === null) {
            return;
        }

        $roots = $this->db->table('cms_block_instances')
            ->where('block_id', $teamTypeId)
            ->where('owner_type', 'page')
            ->where('owner_id', $pageId)
            ->where('parent_instance_id IS NULL', null, false)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if (count($roots) < 2) {
            return;
        }

        $target = null;
        foreach ($roots as $root) {
            if ((int) ($root['id'] ?? 0) === 3024) {
                $target = $root;
                break;
            }
        }
        $target ??= $roots[0];
        $targetId = (int) $target['id'];

        foreach ($roots as $root) {
            $sourceId = (int) ($root['id'] ?? 0);
            if ($sourceId <= 0 || $sourceId === $targetId) {
                continue;
            }

            $this->db->table('cms_block_instances')
                ->where('parent_instance_id', $sourceId)
                ->update(['parent_instance_id' => $targetId]);
            $this->db->table('cms_block_instance_translations')
                ->where('instance_id', $sourceId)
                ->delete();
            $this->db->table('cms_block_instances')
                ->where('id', $sourceId)
                ->delete();
        }

        $this->db->table('cms_block_instances')
            ->where('id', $targetId)
            ->update(['sort_order' => 8, 'is_active' => 1]);
    }

    // Helpers/properties from class:
    private function Sanitize_ConsolidateAboutTeamBlocks_pageId(): ?int
    {
        $row = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->whereIn('pt.slug', ['quienes-somos', 'nosotros', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function Sanitize_ConsolidateAboutTeamBlocks_teamTypeId(): ?int
    {
        $row = $this->db->table('cms_content_blocks')
            ->select('id')
            ->where('block_key', 'team_grid')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function sanitize_SyncAboutTeamEditorialData(): void
    {
        $parentId = $this->Sanitize_SyncAboutTeamEditorialData_parentId();
        $languageId = $this->Sanitize_SyncAboutTeamEditorialData_languageId('es');
        if ($parentId === null || $languageId === null) {
            return;
        }

        $children = $this->db->table('cms_block_instances c')
            ->select('c.id, t.id AS translation_id, t.block_data')
            ->join('cms_content_blocks b', 'b.id = c.block_id')
            ->join('cms_block_instance_translations t', 't.instance_id = c.id AND t.language_id = ' . $languageId, 'left')
            ->where('c.parent_instance_id', $parentId)
            ->where('b.block_key', 'team_member')
            ->get()
            ->getResultArray();

        foreach ($children as $child) {
            $current = json_decode((string) ($child['block_data'] ?? '{}'), true);
            $name = is_array($current) ? trim((string) ($current['name'] ?? '')) : '';
            $person = self::Sanitize_SyncAboutTeamEditorialData_TEAM[$name] ?? null;
            if ($person === null) {
                continue;
            }

            $data = is_array($current) ? $current : [];
            $data['profession'] = $person['profession'];
            $data['position'] = $person['position'];
            $data['email'] = $person['email'];
            // Keep profession and primary position out of the additional
            // roles repeater; they already have dedicated fields.
            $data['roles'] = [];

            $payload = ['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            if (! empty($child['translation_id'])) {
                $this->db->table('cms_block_instance_translations')
                    ->where('id', (int) $child['translation_id'])
                    ->update($payload);
            } else {
                $this->db->table('cms_block_instance_translations')->insert($payload + [
                    'instance_id' => (int) $child['id'],
                    'language_id' => $languageId,
                    'is_published' => 1,
                ]);
            }
        }
    }

    // Helpers/properties from class:
    /** @var array<string, array{profession: string, position: string, email: string}> */
    private const Sanitize_SyncAboutTeamEditorialData_TEAM = [
        'Víctor Quiroga' => ['profession' => 'Payaso', 'position' => 'Presidente fundación', 'email' => 'direccion@teatromuseo.cl'],
        'Paulina Beltrán' => ['profession' => 'Titiritera', 'position' => 'Encargada de proyectos', 'email' => 'proyectos@teatromuseo.cl'],
        'Constanza Valenzuela' => ['profession' => 'Diseñadora', 'position' => 'Encargada de difusión', 'email' => 'diseno@teatromuseo.cl'],
        'Diego Zuñiga' => ['profession' => 'Actor, payaso', 'position' => 'Encargado de extensión y ventas', 'email' => 'extension@teatromuseo.cl'],
        'Claudio Palacios' => ['profession' => 'Payaso', 'position' => 'Secretario Academico', 'email' => 'teatroescuela@teatromuseo.cl'],
        'Felipe Lira' => ['profession' => 'Bailarín titiritero', 'position' => 'Encargado de programación', 'email' => 'programacion@teatromuseo.cl'],
        'Tomás Arce' => ['profession' => 'Gestor cultural', 'position' => 'Encargado de comunicaciones', 'email' => 'difusion@teatromuseo.cl'],
        'Barbara Quiroga' => ['profession' => 'Secretaria', 'position' => 'Encargada de sala y museo', 'email' => 'sala@teatromuseo.cl'],
        'Kevin Zamora' => ['profession' => 'Técnico', 'position' => 'Jefe técnico', 'email' => 'tecnico@teatromuseo.cl'],
        'Javiera Silva' => ['profession' => 'Periodista', 'position' => 'Editora Revista 795', 'email' => 'editorial@teatromuseo.cl'],
    ];





    private function Sanitize_SyncAboutTeamEditorialData_parentId(): ?int
    {
        $row = $this->db->table('cms_block_instances i')
            ->select('i.id')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->join('cms_pages p', 'p.id = i.owner_id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('i.owner_type', 'page')
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', 'team_grid')
            ->whereIn('pt.slug', ['quienes-somos', 'nosotros', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function Sanitize_SyncAboutTeamEditorialData_languageId(string $code): ?int
    {
        $row = $this->db->table('cms_languages')->select('id')->where('code', $code)->get()->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function sanitize_RestoreAboutTeamBlockCompatibilityId(): void
    {
        $source = $this->db->table('cms_block_instances')
                    ->where('id', self::Sanitize_RestoreAboutTeamBlockCompatibilityId_COMPATIBILITY_ID)
                    ->get()
                    ->getRowArray();

        if (is_array($source)) {
            return;
        }

        $source = $this->db->table('cms_block_instances i')
            ->select('i.*')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'page')
            ->where('i.owner_id', 17)
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', 'team_grid')
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getRowArray();

        if (! is_array($source)) {
            return;
        }

        $copy = $source;
        $copy['id'] = self::Sanitize_RestoreAboutTeamBlockCompatibilityId_COMPATIBILITY_ID;
        $copy['sort_order'] = 8;
        $this->db->table('cms_block_instances')->insert($copy);

        $translations = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', (int) $source['id'])
            ->get()
            ->getResultArray();
        foreach ($translations as $translation) {
            unset($translation['id']);
            $translation['instance_id'] = self::Sanitize_RestoreAboutTeamBlockCompatibilityId_COMPATIBILITY_ID;
            $this->db->table('cms_block_instance_translations')->insert($translation);
        }

        $this->db->table('cms_block_instances')
            ->where('parent_instance_id', (int) $source['id'])
            ->update(['parent_instance_id' => self::Sanitize_RestoreAboutTeamBlockCompatibilityId_COMPATIBILITY_ID]);
        $this->db->table('cms_block_instance_translations')
            ->where('instance_id', (int) $source['id'])
            ->delete();
        $this->db->table('cms_block_instances')
            ->where('id', (int) $source['id'])
            ->delete();
    }

    // Helpers/properties from class:
    private const Sanitize_RestoreAboutTeamBlockCompatibilityId_COMPATIBILITY_ID = 3024;

    private function sanitize_RemovePeoplePublicNavigation(): void
    {
        // People remains an internal editorial collection. Remove stale
        // generic index pages left by earlier bootstrap versions as well as
        // collection-bound indexes, otherwise an old localized shell can win
        // public page resolution even after the navigation item is removed.
        $peopleSlugs = array_values(TeatroMuseoPublicRoutes::collectionSlugs('personas'));
        $genericPages = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('p.page_type', 'generic')
            ->whereIn('pt.slug', $peopleSlugs)
            ->where('p.deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($genericPages as $page) {
            $pageId = (int) ($page['id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }
            $this->db->table('cms_pages')->where('id', $pageId)->update([
                'status' => 'draft',
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $collection = $this->db->table('cms_collections')
                    ->select('id')
                    ->where('collection_key', 'personas')
                    ->get()
                    ->getRowArray();

        if (! is_array($collection)) {
            return;
        }

        $collectionId = (int) $collection['id'];
        // Keep the editorial data and relations available to the admin while
        // preventing the web fallback resolver from exposing a collection
        // index when the dedicated CMS page is intentionally unpublished.
        $this->db->table('cms_collections')->where('id', $collectionId)->update([
            'is_active' => 0,
        ]);
        $menuItems = $this->db->table('cms_menu_items mi')
            ->select('mi.id')
            ->join('cms_menus m', 'm.id = mi.menu_id')
            ->where('m.menu_key', 'main')
            ->where('mi.collection_id', $collectionId)
            ->get()
            ->getResultArray();

        foreach ($menuItems as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $this->db->table('cms_menu_item_translations')->where('menu_item_id', $itemId)->delete();
            $this->db->table('cms_menu_items')->where('id', $itemId)->delete();
        }

        $pages = $this->db->table('cms_pages')
            ->select('id')
            ->where('collection_id', $collectionId)
            ->get()
            ->getResultArray();

        foreach ($pages as $page) {
            $pageId = (int) ($page['id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }
            // Keep the collection and entries for internal editorial
            // references, but remove this index from the public site.
            $this->db->table('cms_pages')->where('id', $pageId)->update([
                'status' => 'draft',
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function sanitize_RenamePublicationsToEditorial(): void
    {
        $this->Sanitize_RenamePublicationsToEditorial_renameCollection();
        $this->Sanitize_RenamePublicationsToEditorial_renamePage();
        $this->Sanitize_RenamePublicationsToEditorial_renameMenus();
    }

    // Helpers/properties from class:
    /** @var array<string, string> */
    private array $Sanitize_RenamePublicationsToEditorial_labels = [
        'es' => 'Editorial',
        'en' => 'Editorial',
        'fr' => 'Éditorial',
        'pt' => 'Editorial',
    ];





    private function Sanitize_RenamePublicationsToEditorial_renameCollection(): void
    {
        $collection = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'editoriales')
            ->get()
            ->getRowArray();

        if (! is_array($collection)) {
            return;
        }

        $this->Sanitize_RenamePublicationsToEditorial_updateTranslations('cms_collection_translations', 'collection_id', (int) $collection['id'], 'name', 'listing_title', 'default_meta_title');
    }

    private function Sanitize_RenamePublicationsToEditorial_renamePage(): void
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
        $this->Sanitize_RenamePublicationsToEditorial_updateTranslations('cms_page_translations', 'page_id', $pageId, 'title', 'meta_title');

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
                $language = $this->Sanitize_RenamePublicationsToEditorial_languageCode((int) ($translation['language_id'] ?? 0));
                if ($language === null || ! isset($this->Sanitize_RenamePublicationsToEditorial_labels[$language])) {
                    continue;
                }

                $data = json_decode((string) ($translation['block_data'] ?? ''), true);
                if (! is_array($data)) {
                    continue;
                }

                $changed = false;
                if (array_key_exists('heading', $data)) {
                    $data['heading'] = $this->Sanitize_RenamePublicationsToEditorial_labels[$language];
                    $changed = true;
                }
                if (array_key_exists('intro_title', $data)) {
                    $data['intro_title'] = $this->Sanitize_RenamePublicationsToEditorial_labels[$language];
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

    private function Sanitize_RenamePublicationsToEditorial_renameMenus(): void
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
                $language = $this->Sanitize_RenamePublicationsToEditorial_languageCode((int) ($translation['language_id'] ?? 0));
                if ($language !== null && isset($this->Sanitize_RenamePublicationsToEditorial_labels[$language])) {
                    $this->db->table('cms_menu_item_translations')
                        ->where('id', (int) $translation['id'])
                        ->update(['label' => $this->Sanitize_RenamePublicationsToEditorial_labels[$language]]);
                }
            }
        }
    }

    private function Sanitize_RenamePublicationsToEditorial_updateTranslations(string $table, string $foreignKey, int $foreignId, string ...$fields): void
    {
        $translations = $this->db->table($table)
            ->where($foreignKey, $foreignId)
            ->get()
            ->getResultArray();

        foreach ($translations as $translation) {
            $language = $this->Sanitize_RenamePublicationsToEditorial_languageCode((int) ($translation['language_id'] ?? 0));
            if ($language === null || ! isset($this->Sanitize_RenamePublicationsToEditorial_labels[$language])) {
                continue;
            }
            $payload = [];
            foreach ($fields as $field) {
                $payload[$field] = $field === 'meta_title' || $field === 'default_meta_title'
                    ? $this->Sanitize_RenamePublicationsToEditorial_labels[$language] . ' | TeatroMuseo'
                    : $this->Sanitize_RenamePublicationsToEditorial_labels[$language];
            }
            $this->db->table($table)->where('id', (int) $translation['id'])->update($payload);
        }
    }

    private function Sanitize_RenamePublicationsToEditorial_languageCode(int $languageId): ?string
    {
        $row = $this->db->table('cms_languages')->select('code')->where('id', $languageId)->get()->getRowArray();
        return is_array($row) && is_string($row['code'] ?? null) ? $row['code'] : null;
    }

    private function sanitize_MovePressMenuItem(): void
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
            $this->Sanitize_MovePressMenuItem_moveOrCreateItem($menuKey, (int) $page['id']);
        }
    }

    // Helpers/properties from class:
    /** @var array<string, string> */
    private array $Sanitize_MovePressMenuItem_labels = [
        'es' => 'Prensa',
        'en' => 'Press',
        'fr' => 'Presse',
        'pt' => 'Imprensa',
    ];





    private function Sanitize_MovePressMenuItem_moveOrCreateItem(string $menuKey, int $pageId): void
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
            ->whereIn('code', array_keys($this->Sanitize_MovePressMenuItem_labels))
            ->get()
            ->getResultArray();
        foreach ($languages as $language) {
            $languageId = (int) ($language['id'] ?? 0);
            $code = (string) ($language['code'] ?? '');
            if ($languageId <= 0 || ! isset($this->Sanitize_MovePressMenuItem_labels[$code])) {
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
                'label' => $this->Sanitize_MovePressMenuItem_labels[$code],
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

    private function sanitize_CanonicalizeEditorialRoutes(): void
    {
        $this->Sanitize_CanonicalizeEditorialRoutes_createLegacyRedirects();
        $this->Sanitize_CanonicalizeEditorialRoutes_renameListingPage();
        $this->Sanitize_CanonicalizeEditorialRoutes_renameCollectionIndexPages();
        $this->Sanitize_CanonicalizeEditorialRoutes_renameActiveEditorialCollectionSlugs();
    }

    // Helpers/properties from class:
    /** @var list<string> */
    private array $Sanitize_CanonicalizeEditorialRoutes_legacyPaths = ['publicaciones', 'publications', 'publicacoes'];





    private function Sanitize_CanonicalizeEditorialRoutes_createLegacyRedirects(): void
    {
        foreach ($this->Sanitize_CanonicalizeEditorialRoutes_legacyPaths as $oldPath) {
            $existing = $this->db->table('cms_redirects')
                ->select('id')
                ->where('old_path', $oldPath)
                ->get()
                ->getRowArray();
            $payload = [
                'new_url' => 'editorial',
                'redirect_type' => 301,
                'is_active' => 1,
                'hit_count' => 0,
                'last_hit_at' => null,
                'note' => 'Editorial is the canonical public section URL.',
            ];

            if (is_array($existing)) {
                $this->db->table('cms_redirects')->where('id', (int) $existing['id'])->update($payload);
            } else {
                $this->db->table('cms_redirects')->insert(['old_path' => $oldPath, ...$payload]);
            }
        }
    }

    private function Sanitize_CanonicalizeEditorialRoutes_renameListingPage(): void
    {
        $page = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'publications')
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();
        if (! is_array($page)) {
            return;
        }

        $this->db->table('cms_page_translations')
            ->where('page_id', (int) $page['id'])
            ->update(['slug' => 'editorial']);
    }

    private function Sanitize_CanonicalizeEditorialRoutes_renameCollectionIndexPages(): void
    {
        $collections = $this->db->table('cms_collections')
            ->select('id')
            ->whereIn('collection_key', ['editoriales', 'editorial'])
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
        foreach ($collections as $collection) {
            $pages = $this->db->table('cms_pages')
                ->select('id')
                ->where('collection_id', (int) $collection['id'])
                ->where('page_type', 'collection_index')
                ->where('deleted_at IS NULL', null, false)
                ->get()
                ->getResultArray();
            foreach ($pages as $page) {
                $this->db->table('cms_page_translations')
                    ->where('page_id', (int) $page['id'])
                    ->update(['slug' => 'editorial']);
            }
        }
    }

    private function Sanitize_CanonicalizeEditorialRoutes_renameActiveEditorialCollectionSlugs(): void
    {
        $collections = $this->db->table('cms_collections')
            ->select('id')
            ->whereIn('collection_key', ['editoriales', 'editorial'])
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
        foreach ($collections as $collection) {
            $this->db->table('cms_collection_translations')
                ->where('collection_id', (int) $collection['id'])
                ->update(['slug' => 'editorial']);
        }
    }

    private function sanitize_PreserveEditorialEntryRoutes(): void
    {
        $collections = $this->db->table('cms_collections')
                    ->select('id')
                    ->whereIn('collection_key', ['editoriales', 'editorial'])
                    ->where('is_active', 1)
                    ->get()
                    ->getResultArray();
        $languages = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', array_keys($this->Sanitize_PreserveEditorialEntryRoutes_legacyPrefixes))
            ->get()
            ->getResultArray();

        foreach ($collections as $collection) {
            foreach ($languages as $language) {
                $code = (string) ($language['code'] ?? '');
                $languageId = (int) ($language['id'] ?? 0);
                if ($languageId <= 0 || ! isset($this->Sanitize_PreserveEditorialEntryRoutes_legacyPrefixes[$code])) {
                    continue;
                }

                $entries = $this->db->table('cms_entries e')
                    ->select('e.id, et.slug')
                    ->join('cms_entry_translations et', 'et.entry_id = e.id')
                    ->where('e.collection_id', (int) $collection['id'])
                    ->where('et.language_id', $languageId)
                    ->get()
                    ->getResultArray();
                foreach ($entries as $entry) {
                    $slug = trim((string) ($entry['slug'] ?? ''), '/');
                    if ($slug === '') {
                        continue;
                    }
                    $this->Sanitize_PreserveEditorialEntryRoutes_upsertRedirect(
                        $this->Sanitize_PreserveEditorialEntryRoutes_legacyPrefixes[$code] . '/' . $slug,
                        'editorial/' . $slug
                    );
                }
            }
        }
    }

    // Helpers/properties from class:
    /** @var array<string, string> */
    private array $Sanitize_PreserveEditorialEntryRoutes_legacyPrefixes = [
        'es' => 'publicaciones',
        'en' => 'publications',
        'fr' => 'publications',
        'pt' => 'publicacoes',
    ];





    private function Sanitize_PreserveEditorialEntryRoutes_upsertRedirect(string $oldPath, string $newUrl): void
    {
        $existing = $this->db->table('cms_redirects')
            ->select('id')
            ->where('old_path', $oldPath)
            ->get()
            ->getRowArray();
        $payload = [
            'new_url' => $newUrl,
            'redirect_type' => 301,
            'is_active' => 1,
            'hit_count' => 0,
            'last_hit_at' => null,
            'note' => 'Legacy Editorial entry URL.',
        ];
        if (is_array($existing)) {
            $this->db->table('cms_redirects')->where('id', (int) $existing['id'])->update($payload);
        } else {
            $this->db->table('cms_redirects')->insert(['old_path' => $oldPath, ...$payload]);
        }
    }

    private function sanitize_ConsolidateEditorialIndexPage(): void
    {
        $collectionId = $this->Sanitize_ConsolidateEditorialIndexPage_normalizeCollectionKey();
        if ($collectionId === null) {
            return;
        }

        // A previous removal may have soft-deleted the collection index. It
        // is still the canonical owner and must be restored, not duplicated.
        $indexPage = $this->Sanitize_ConsolidateEditorialIndexPage_findPage('collection_index', $collectionId, true);
        $listingPage = $this->Sanitize_ConsolidateEditorialIndexPage_findPage('publications');

        if ($indexPage !== null) {
            $this->db->table('cms_pages')->where('id', (int) $indexPage['id'])->update([
                'status' => 'published',
                'deleted_at' => null,
            ]);
            $this->db->table('cms_page_translations')
                ->where('page_id', (int) $indexPage['id'])
                ->update(['slug' => 'editorial']);
        } elseif ($listingPage !== null) {
            $this->db->table('cms_pages')->where('id', (int) $listingPage['id'])->update([
                'page_type' => 'collection_index',
                'collection_id' => $collectionId,
                'status' => 'published',
                'deleted_at' => null,
            ]);
            $indexPage = $listingPage;
        }

        if ($indexPage === null || $listingPage === null || (int) $indexPage['id'] === (int) $listingPage['id']) {
            $this->Sanitize_ConsolidateEditorialIndexPage_normalizeMenuItems($collectionId, $indexPage['id'] ?? null, $listingPage['id'] ?? null);
            return;
        }

        // Keep the old page and its editorial blocks recoverable, but remove it
        // from public resolution so it cannot compete with the collection index.
        $this->db->table('cms_pages')->where('id', (int) $listingPage['id'])->update([
            'status' => 'draft',
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->Sanitize_ConsolidateEditorialIndexPage_normalizeMenuItems($collectionId, (int) $indexPage['id'], (int) $listingPage['id']);
    }

    // Helpers/properties from class:
    private function Sanitize_ConsolidateEditorialIndexPage_normalizeCollectionKey(): ?int
    {
        $canonical = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'editoriales')
            ->get()
            ->getRowArray();
        if (is_array($canonical)) {
            return (int) $canonical['id'];
        }

        $legacy = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'editorial')
            ->get()
            ->getRowArray();
        if (! is_array($legacy)) {
            return null;
        }

        $collectionId = (int) $legacy['id'];
        $this->db->table('cms_collections')
            ->where('id', $collectionId)
            ->update(['collection_key' => 'editoriales']);

        return $collectionId;
    }

    private function Sanitize_ConsolidateEditorialIndexPage_normalizeMenuItems(int $collectionId, ?int $indexPageId, ?int $legacyPageId): void
    {
        $builder = $this->db->table('cms_menu_items');
        if ($legacyPageId !== null) {
            $builder->groupStart()
                ->where('page_id', $legacyPageId)
                ->orWhere('collection_id', $collectionId)
                ->groupEnd();
        } else {
            $builder->where('collection_id', $collectionId);
        }

        $items = $builder->get()->getResultArray();
        foreach ($items as $item) {
            $this->db->table('cms_menu_items')
                ->where('id', (int) $item['id'])
                ->update([
                    'link_type' => 'collection_listing',
                    'page_id' => null,
                    'entry_id' => null,
                    'collection_id' => $collectionId,
                    'is_active' => 1,
                ]);
        }
    }

    /** @return array{id: int}|null */
    private function Sanitize_ConsolidateEditorialIndexPage_findPage(string $pageType, ?int $collectionId = null, bool $includeDeleted = false): ?array
    {
        $builder = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', $pageType);
        if (! $includeDeleted) {
            $builder->where('deleted_at IS NULL', null, false);
        }
        if ($collectionId !== null) {
            $builder->where('collection_id', $collectionId);
        }

        $row = $builder->get()->getRowArray();

        return is_array($row) ? ['id' => (int) $row['id']] : null;
    }

    private function sanitize_NormalizeAboutTeamAdditionalRoles(): void
    {
        $rows = $this->db->table('cms_block_instance_translations t')
                    ->select('t.id, t.block_data')
                    ->join('cms_block_instances i', 'i.id = t.instance_id')
                    ->join('cms_content_blocks b', 'b.id = i.block_id')
                    ->where('b.block_key', 'team_member')
                    ->get()
                    ->getResultArray();

        foreach ($rows as $row) {
            $data = json_decode((string) ($row['block_data'] ?? '{}'), true);
            if (! is_array($data)) {
                continue;
            }

            $excluded = array_filter([
                trim((string) ($data['position'] ?? '')),
                trim((string) ($data['profession'] ?? '')),
            ]);
            $excludedKeys = array_map([$this, 'Sanitize_NormalizeAboutTeamAdditionalRoles_normalize'], $excluded);
            $seen = [];
            $roles = [];

            foreach (is_array($data['roles'] ?? null) ? $data['roles'] : [] as $role) {
                $label = is_array($role)
                    ? trim((string) ($role['label'] ?? $role['name'] ?? ''))
                    : (is_scalar($role) ? trim((string) $role) : '');
                $key = $this->Sanitize_NormalizeAboutTeamAdditionalRoles_normalize($label);
                if ($label === '' || in_array($key, $excludedKeys, true) || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $roles[] = ['label' => $label];
            }

            $data['roles'] = $roles;
            $this->db->table('cms_block_instance_translations')
                ->where('id', (int) ($row['id'] ?? 0))
                ->update(['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        }
    }

    // Helpers/properties from class:
    private function Sanitize_NormalizeAboutTeamAdditionalRoles_normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function sanitize_NormalizeLegacyCollectionIndexPages(): void
    {
        $this->Sanitize_NormalizeLegacyCollectionIndexPages_freeObsoleteEditorialSlugs();
        $this->Sanitize_NormalizeLegacyCollectionIndexPages_repairEditorialIndex();
        $this->Sanitize_NormalizeLegacyCollectionIndexPages_removeLegacyIndexPages();
    }

    // Helpers/properties from class:
    /** @var array<string, array{title: string, slug: string}> */
    private const Sanitize_NormalizeLegacyCollectionIndexPages_EDITORIAL_TRANSLATIONS = [
        'es' => ['title' => 'Editorial', 'slug' => 'editorial'],
        'en' => ['title' => 'Editorial', 'slug' => 'editorial'],
        'fr' => ['title' => 'Éditorial', 'slug' => 'editorial'],
        'pt' => ['title' => 'Editorial', 'slug' => 'editorial'],
    ];





    private function Sanitize_NormalizeLegacyCollectionIndexPages_repairEditorialIndex(): void
    {
        $collection = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'editoriales')
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (! is_array($collection)) {
            return;
        }

        $page = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'collection_index')
            ->where('collection_id', (int) $collection['id'])
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        if (! is_array($page)) {
            return;
        }

        $pageId = (int) $page['id'];
        $this->db->table('cms_pages')->where('id', $pageId)->update([
            'status' => 'published',
            'deleted_at' => null,
        ]);

        $languages = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', array_keys(self::Sanitize_NormalizeLegacyCollectionIndexPages_EDITORIAL_TRANSLATIONS))
            ->get()
            ->getResultArray();

        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            $languageId = (int) ($language['id'] ?? 0);
            $translation = self::Sanitize_NormalizeLegacyCollectionIndexPages_EDITORIAL_TRANSLATIONS[$code] ?? null;
            if ($languageId <= 0 || $translation === null) {
                continue;
            }

            $payload = [
                'slug' => $translation['slug'],
                'title' => $translation['title'],
                'excerpt' => 'Publicaciones editoriales del TeatroMuseo.',
                'meta_title' => $translation['title'] . ' | TeatroMuseo',
                'meta_description' => 'Publicaciones editoriales del TeatroMuseo.',
                'robots' => 'index, follow',
            ];
            $existing = $this->db->table('cms_page_translations')
                ->select('id')
                ->where('page_id', $pageId)
                ->where('language_id', $languageId)
                ->get()
                ->getRowArray();

            if (is_array($existing)) {
                $this->db->table('cms_page_translations')
                    ->where('id', (int) $existing['id'])
                    ->update($payload);
            } else {
                $this->db->table('cms_page_translations')->insert([
                    'page_id' => $pageId,
                    'language_id' => $languageId,
                    ...$payload,
                ]);
            }
        }
    }

    private function Sanitize_NormalizeLegacyCollectionIndexPages_freeObsoleteEditorialSlugs(): void
    {
        $legacyPages = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'publications')
            ->where('deleted_at IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($legacyPages as $page) {
            $pageId = (int) ($page['id'] ?? 0);
            if ($pageId <= 0) {
                continue;
            }

            $translations = $this->db->table('cms_page_translations t')
                ->select('t.id, l.code')
                ->join('cms_languages l', 'l.id = t.language_id')
                ->where('t.page_id', $pageId)
                ->get()
                ->getResultArray();
            foreach ($translations as $translation) {
                $code = (string) ($translation['code'] ?? 'xx');
                $this->db->table('cms_page_translations')
                    ->where('id', (int) $translation['id'])
                    ->update(['slug' => '__archived_publications_' . $code]);
            }
        }
    }

    private function Sanitize_NormalizeLegacyCollectionIndexPages_removeLegacyIndexPages(): void
    {
        $legacyCollections = $this->db->table('cms_collections')
            ->select('id')
            ->whereIn('collection_key', ['cartelera', 'obras'])
            ->get()
            ->getResultArray();

        $collectionIds = array_values(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $legacyCollections
        ));
        if ($collectionIds === []) {
            return;
        }

        $pages = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'collection_index')
            ->whereIn('collection_id', $collectionIds)
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getResultArray();
        $pageIds = array_values(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $pages
        ));

        if ($pageIds !== []) {
            $this->db->table('cms_pages')->whereIn('id', $pageIds)->update([
                'status' => 'draft',
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->table('cms_collections')
            ->where('collection_key', 'obras')
            ->update(['is_active' => 0]);

        $eventsPage = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', 'events')
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();
        if (! is_array($eventsPage)) {
            return;
        }

        $menuBuilder = $this->db->table('cms_menu_items');
        $menuBuilder->groupStart();
        if ($pageIds !== []) {
            $menuBuilder->whereIn('page_id', $pageIds);
        }
        $menuBuilder->orWhereIn('collection_id', $collectionIds);
        $menuBuilder->groupEnd();
        $menuBuilder->update([
            'link_type' => 'page',
            'page_id' => (int) $eventsPage['id'],
            'entry_id' => null,
            'collection_id' => null,
            'is_active' => 1,
        ]);
    }

    private function sanitize_RetireObrasCollection(): void
    {
        $this->db->table('cms_collections')
                    ->where('collection_key', 'obras')
                    ->update(['is_active' => 0]);
    }

    private function sanitize_NormalizeSiteSettings(): void
    {
        if (! $this->db->tableExists('cms_settings')) {
            return;
        }

        $this->db->table('cms_settings')
            ->whereIn('setting_key', $this->Sanitize_NormalizeSiteSettings_retiredKeys)
            ->delete();

        $this->db->table('cms_settings')
            ->where('setting_key', 'analytics_provider')
            ->update([
                'input_type' => 'select',
                'options_json' => json_encode([
                    ['value' => 'none', 'label' => 'None'],
                    ['value' => 'ga4', 'label' => 'Google Analytics 4'],
                    ['value' => 'plausible', 'label' => 'Plausible'],
                    ['value' => 'fathom', 'label' => 'Fathom'],
                ], JSON_UNESCAPED_UNICODE),
                'description' => 'Proveedor de analytics: none | ga4 | plausible | fathom',
            ]);
    }

    // Helpers/properties from class:
    /** @var list<string> */
    private array $Sanitize_NormalizeSiteSettings_retiredKeys = [
        'site_title',
        'footer_bg_color',
        'footer_text_color',
        'footer_border_color',
        'contact_admin_email',
        'contact_from_email',
        'contact_site_name',
        'contact_autoreply_message',
        'social_twitter',
        'social_linkedin',
        'social_tiktok',
        'social_pinterest',
        'social_github',
    ];

}
