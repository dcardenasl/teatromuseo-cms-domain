<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\DTO\Request\Cms\PublicEntryIndexRequestDTO;
use App\DTO\Request\Cms\PublicEntryShowRequestDTO;
use App\Entities\EntryEntity;
use App\Libraries\Cms\BlockInstanceSerializer;
use App\Libraries\Cms\EntryListingContentResolver;
use App\Libraries\Cms\EntryTaxonomyPivotResolver;
use App\Libraries\Cms\FileUrlResolver;
use App\Libraries\Cms\PreviewToken;
use dcardenasl\Ci4ApiCore\Dto\Common\PayloadResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;

/**
 * Read-optimized public (unauthenticated) entry queries: listing and single-entry
 * lookup by collection + slug, with N+1-safe batch resolution of translations,
 * categories, and tags across the whole result set.
 *
 * Extracted from EntryService, which composes this class for listPublic()/showPublic().
 */
class PublicEntryReader
{
    private ?\App\Models\CollectionModel $collectionModel = null;

    private ?\App\Models\EntryModel $entryModel = null;

    private ?\App\Models\CategoryTranslationModel $categoryTranslationModel = null;

    private ?\App\Models\TagTranslationModel $tagTranslationModel = null;

    private ?\App\Models\EntryTranslationModel $entryTranslationModel = null;

    private ?\App\Models\BlockInstanceTranslationModel $blockInstanceTranslationModel = null;

    private ?\App\Models\LanguageModel $languageModel = null;

    public function __construct(
        private FileUrlResolver $fileUrlResolver,
        private EntryListingContentResolver $entryListingContentResolver,
        private BlockInstanceSerializer $blockInstanceSerializer,
        private EntryTaxonomyPivotResolver $taxonomyPivotResolver,
    ) {
    }

    private function collectionModel(): \App\Models\CollectionModel
    {
        if ($this->collectionModel === null) {
            /** @var \App\Models\CollectionModel $resolved */
            $resolved = model(\App\Models\CollectionModel::class);
            $this->collectionModel = $resolved;
        }

        return $this->collectionModel;
    }

    private function entryModel(): \App\Models\EntryModel
    {
        if ($this->entryModel === null) {
            /** @var \App\Models\EntryModel $resolved */
            $resolved = model(\App\Models\EntryModel::class);
            $this->entryModel = $resolved;
        }

        return $this->entryModel;
    }

    private function categoryTranslationModel(): \App\Models\CategoryTranslationModel
    {
        if ($this->categoryTranslationModel === null) {
            /** @var \App\Models\CategoryTranslationModel $resolved */
            $resolved = model(\App\Models\CategoryTranslationModel::class);
            $this->categoryTranslationModel = $resolved;
        }

        return $this->categoryTranslationModel;
    }

    private function tagTranslationModel(): \App\Models\TagTranslationModel
    {
        if ($this->tagTranslationModel === null) {
            /** @var \App\Models\TagTranslationModel $resolved */
            $resolved = model(\App\Models\TagTranslationModel::class);
            $this->tagTranslationModel = $resolved;
        }

        return $this->tagTranslationModel;
    }

    private function entryTranslationModel(): \App\Models\EntryTranslationModel
    {
        if ($this->entryTranslationModel === null) {
            /** @var \App\Models\EntryTranslationModel $resolved */
            $resolved = model(\App\Models\EntryTranslationModel::class);
            $this->entryTranslationModel = $resolved;
        }

        return $this->entryTranslationModel;
    }

    private function blockInstanceTranslationModel(): \App\Models\BlockInstanceTranslationModel
    {
        if ($this->blockInstanceTranslationModel === null) {
            /** @var \App\Models\BlockInstanceTranslationModel $resolved */
            $resolved = model(\App\Models\BlockInstanceTranslationModel::class);
            $this->blockInstanceTranslationModel = $resolved;
        }

        return $this->blockInstanceTranslationModel;
    }

    private function languageModel(): \App\Models\LanguageModel
    {
        if ($this->languageModel === null) {
            /** @var \App\Models\LanguageModel $resolved */
            $resolved = model(\App\Models\LanguageModel::class);
            $this->languageModel = $resolved;
        }

        return $this->languageModel;
    }

    public function listPublic(PublicEntryIndexRequestDTO $dto): DataTransferObjectInterface
    {
        $collection = $this->collectionModel()
            ->where('collection_key', $dto->collection_key)
            ->where('is_active', 1)
            ->first();

        if (!$collection instanceof \App\Entities\CollectionEntity) {
            throw new NotFoundException(lang('Collections.not_found'));
        }

        ['langId' => $langId, 'defaultLangId' => $defaultLangId] = $this->resolveLanguageIds($dto->lang);

        // The reader is shared by the service container. Start every public
        // request from a clean builder so an early return or failed query can
        // never leak joins, groups, or projections into the next request.
        // Keep the projection rooted in cms_entries so countAllResults() never
        // wraps duplicate joined column names.
        $entryModel = $this->entryModel();
        $entryModel->builder()->resetQuery();
        $entryModel->select('cms_entries.*');

        if ($dto->category !== null) {
            $catTrans = $this->categoryTranslationModel()->where('slug', $dto->category)->first();
            if (!$catTrans instanceof \App\Entities\CategoryTranslationEntity) {
                return PaginatedResponseDTO::fromArray([
                    'data'     => [],
                    'total'    => 0,
                    'page'     => $dto->page,
                    'per_page' => $dto->per_page,
                ]);
            }
            $entryModel->join('cms_entry_categories', 'cms_entry_categories.entry_id = cms_entries.id')
                ->where('cms_entry_categories.category_id', (int) $catTrans->category_id);
        }

        if ($dto->tag !== null) {
            $tagTrans = $this->tagTranslationModel()->where('slug', $dto->tag)->first();
            if (!$tagTrans instanceof \App\Entities\TagTranslationEntity) {
                return PaginatedResponseDTO::fromArray([
                    'data'     => [],
                    'total'    => 0,
                    'page'     => $dto->page,
                    'per_page' => $dto->per_page,
                ]);
            }
            $entryModel->join('cms_entry_tags', 'cms_entry_tags.entry_id = cms_entries.id')
                ->where('cms_entry_tags.tag_id', (int) $tagTrans->tag_id);
        }

        if ($dto->q !== null) {
            $search = trim($dto->q);
            if ($search !== '') {
                $searchLangIds = [(int) $langId];
                if ($defaultLangId !== $langId) {
                    $searchLangIds[] = (int) $defaultLangId;
                }
                $entryModel->join(
                    'cms_entry_translations search_trans',
                    'search_trans.entry_id = cms_entries.id AND search_trans.language_id IN (' . implode(', ', $searchLangIds) . ')',
                    'left'
                );
                $entryModel->groupStart()
                    ->where('MATCH(search_trans.title, search_trans.excerpt) AGAINST(' . $entryModel->db->escape($search) . ' IN BOOLEAN MODE)', null, false)
                    ->orLike('search_trans.title', $search)
                    ->orLike('search_trans.excerpt', $search)
                ->groupEnd();
                $entryModel->groupBy('cms_entries.id');
            }
        }

        $now    = date('Y-m-d H:i:s');
        $offset = ($dto->page - 1) * $dto->per_page;

        $builder = $entryModel
            ->where('collection_id', (int) $collection->id)
            ->where('workflow_status', 'published')
            ->groupStart()
                ->where('published_at IS NULL')
                ->orWhere('published_at <=', $now)
            ->groupEnd()
            ->groupStart()
                ->where('scheduled_at IS NULL')
                ->orWhere('scheduled_at <=', $now)
            ->groupEnd();

        $total = (int) $builder->countAllResults(false);

        // Populated only for the 'cursos' branch below — carries each entry's real
        // start_date so it can be exposed as `display_date` on the response without a
        // second batch query (the sort below already resolves it for every entry).
        $cursoStartDates = [];

        if ($dto->collection_key === 'cursos') {
            // "Cursos" reads as a program calendar, not an article feed: upcoming courses
            // first (soonest first), then past ones most-recent-first — the same reasoning
            // as the public Cartelera listing (event-domain EVT-DOM-007). start_date lives
            // inside the curso_ficha block's translated block_data, not a cms_entries column,
            // so a single ORDER BY can't express it — walk every matching row (bounded by
            // this collection's real size, a single institution's course catalog), resolve
            // each entry's start_date in one batched query, sort in PHP, then paginate.
            $entries = $this->sortCursosUpcomingFirst($builder, $langId, $defaultLangId, $dto->page, $dto->per_page, $cursoStartDates);
        } else {
            $orderColumn = match ($dto->order_by) {
                'published_at' => 'cms_entries.published_at',
                'created_at'   => 'cms_entries.created_at',
                'title'        => 'entry_title_order',
                default        => 'cms_entries.sort_order',
            };

            if ($dto->order_by === 'title') {
                $builder->join(
                    'cms_entry_translations title_trans',
                    'title_trans.entry_id = cms_entries.id AND title_trans.language_id = ' . (int) $langId,
                    'left'
                )
                    ->select('cms_entries.*')
                    ->select('title_trans.title AS entry_title_order', false);
            }

            $entries = $builder
                ->orderBy($orderColumn, $dto->order_direction)
                ->orderBy('cms_entries.created_at', 'DESC')
                ->findAll($dto->per_page, $offset);
        }

        if (empty($entries)) {
            return PaginatedResponseDTO::fromArray([
                'data'     => [],
                'total'    => $total,
                'page'     => $dto->page,
                'per_page' => $dto->per_page,
            ]);
        }

        $entryIds = [];
        foreach ($entries as $e) {
            if ($e instanceof EntryEntity) {
                $entryIds[] = (int) $e->id;
            }
        }

        $entryTransMap  = $this->batchResolveEntryTranslations($entryIds, $langId, $defaultLangId);
        $categoriesMap  = $this->taxonomyPivotResolver->resolveLocalizedCategories($entryIds, $langId, $defaultLangId);
        $tagsMap        = $this->taxonomyPivotResolver->resolveLocalizedTags($entryIds, $langId, $defaultLangId);

        $data = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof EntryEntity) {
                continue;
            }
            $entryId = (int) $entry->id;
            $item    = array_merge($entry->toArray(), $entryTransMap[$entryId] ?? []);
            $item['categories'] = $categoriesMap[$entryId] ?? [];
            $item['tags']       = $tagsMap[$entryId] ?? [];
            unset($item['entry_title_order']);
            // Normalize featured/OG images into canonical nested objects.
            $item = $this->normalizeEntryMedia($item);
            if ($dto->collection_key === 'cursos') {
                // The course's real start_date, not published_at — the public listing
                // template prefers this for the card's date badge and already sorts by it.
                $item['display_date'] = $cursoStartDates[$entryId] ?? null;
            }
            $data[] = $item;
        }

        if ($dto->include_listing_content) {
            $listingContentByEntry = $this->entryListingContentResolver->resolveBatch($data, $dto->lang);

            foreach ($data as &$item) {
                $entryId = (int) ($item['id'] ?? 0);
                $item['listing_content'] = $listingContentByEntry[$entryId] ?? [
                    'rich_text' => '',
                    'image' => null,
                    'secondary_action' => null,
                ];
            }
            unset($item);
        }

        return PaginatedResponseDTO::fromArray([
            'data'     => $data,
            'total'    => (int) $total,
            'page'     => $dto->page,
            'per_page' => $dto->per_page,
        ]);
    }

    /**
     * @param array<int, string> $startDatesOut Populated with entry_id => start_date so the
     *      caller can expose the same resolved dates as `display_date` without re-querying.
     * @return list<EntryEntity>
     */
    private function sortCursosUpcomingFirst(\App\Models\EntryModel $builder, int $langId, int $defaultLangId, int $page, int $perPage, array &$startDatesOut): array
    {
        $allEntries = array_values(array_filter(
            $builder->findAll(),
            static fn (mixed $entry): bool => $entry instanceof EntryEntity
        ));

        $entryIds = [];
        foreach ($allEntries as $entry) {
            $entryIds[] = (int) $entry->id;
        }

        $startDates    = $this->batchResolveCursoStartDates($entryIds, $langId, $defaultLangId);
        $startDatesOut = $startDates;
        $today         = date('Y-m-d');

        usort($allEntries, static function (EntryEntity $a, EntryEntity $b) use ($startDates, $today): int {
            $aDate   = $startDates[(int) $a->id] ?? '';
            $bDate   = $startDates[(int) $b->id] ?? '';
            $aFuture = $aDate !== '' && $aDate >= $today;
            $bFuture = $bDate !== '' && $bDate >= $today;

            if ($aFuture !== $bFuture) {
                return $aFuture ? -1 : 1;
            }
            // A course with no start_date at all can't be placed on the timeline — push it
            // to the very end rather than letting empty-string comparisons sort it first.
            if ($aDate === '' || $bDate === '') {
                return match (true) {
                    $aDate === '' && $bDate === '' => 0,
                    $aDate === ''                  => 1,
                    default                        => -1,
                };
            }

            return $aFuture ? $aDate <=> $bDate : $bDate <=> $aDate;
        });

        return array_slice($allEntries, ($page - 1) * $perPage, $perPage);
    }

    /**
     * Batch-resolves the `curso_ficha` block's `start_date` for a set of entries in one
     * query (N+1-safe, matching the batch-resolve pattern used elsewhere in this reader) —
     * the date lives in `cms_block_instance_translations.block_data`, not a queryable
     * `cms_entries` column.
     *
     * @param list<int> $entryIds
     * @return array<int, string> entry_id => start_date (Y-m-d), only entries that have one
     */
    private function batchResolveCursoStartDates(array $entryIds, int $langId, int $defaultLangId): array
    {
        if ($entryIds === []) {
            return [];
        }

        $languageIds = $langId === $defaultLangId ? [$langId] : [$langId, $defaultLangId];

        // Routed through the injected BlockInstanceTranslationModel (not Database::connect())
        // to stay within this Service layer's model-coupling guardrail
        // (ServiceModelDependencyConventionsTest) — CI4 Models proxy join()/where()/etc.
        // straight to their query builder, same as the entryModel() joins above. The model
        // is shared/memoized across requests, so reset its builder first — same reasoning
        // as the entryModel() reset at the top of listPublic().
        $this->blockInstanceTranslationModel()->builder()->resetQuery();

        $rows = $this->blockInstanceTranslationModel()
            ->select('cms_block_instances.owner_id AS entry_id, cms_block_instance_translations.language_id, cms_block_instance_translations.block_data')
            ->join('cms_block_instances', 'cms_block_instances.id = cms_block_instance_translations.instance_id')
            ->join('cms_content_blocks', 'cms_content_blocks.id = cms_block_instances.block_id')
            ->where('cms_block_instances.owner_type', 'entry')
            ->where('cms_content_blocks.block_key', 'curso_ficha')
            ->whereIn('cms_block_instance_translations.language_id', $languageIds)
            ->whereIn('cms_block_instances.owner_id', $entryIds)
            ->findAll();

        // Requested language wins over the default-language fallback when both exist.
        $byEntryAndLang = [];
        foreach ($rows as $row) {
            if (!$row instanceof \App\Entities\BlockInstanceTranslationEntity) {
                continue;
            }
            // owner_id was selected under an alias (entry_id) — it isn't a declared
            // attribute on the entity, so it comes back as a dynamic property.
            $entryId    = (int) $row->entry_id;
            $languageId = (int) $row->language_id;
            $byEntryAndLang[$entryId][$languageId] = $row->block_data;
        }

        $map = [];
        foreach ($byEntryAndLang as $entryId => $byLang) {
            $raw = $byLang[$langId] ?? $byLang[$defaultLangId] ?? null;
            if ($raw === null) {
                continue;
            }
            $decoded   = \App\Libraries\Cms\JsonCastNormalizer::toArray($raw);
            $startDate = trim((string) ($decoded['start_date'] ?? ''));
            if ($startDate !== '') {
                $map[$entryId] = $startDate;
            }
        }

        return $map;
    }

    public function showPublic(PublicEntryShowRequestDTO $dto): DataTransferObjectInterface
    {
        $collection = $this->collectionModel()
            ->where('collection_key', $dto->collection_key)
            ->where('is_active', 1)
            ->first();

        if (!$collection instanceof \App\Entities\CollectionEntity) {
            throw new NotFoundException(lang('Collections.not_found'));
        }

        ['langId' => $langId, 'defaultLangId' => $defaultLangId] = $this->resolveLanguageIds($dto->lang);

        $translationModel = $this->entryTranslationModel();
        $langModel = $this->languageModel();
        $activeLanguages = $langModel->where('is_active', 1)->findAll();
        $activeLanguageIds = [];
        foreach ($activeLanguages as $activeLanguage) {
            if ($activeLanguage instanceof \App\Entities\LanguageEntity) {
                $activeLanguageIds[] = (int) $activeLanguage->id;
            }
        }

        $entryTranslation = null;
        if ($activeLanguageIds !== []) {
            $entryTranslation = $translationModel
                ->where('slug', $dto->slug)
                ->whereIn('language_id', $activeLanguageIds)
                ->first();
        }

        if (!$entryTranslation instanceof \App\Entities\EntryTranslationEntity
            && $defaultLangId !== $langId
        ) {
            $entryTranslation = $translationModel
                ->where('slug', $dto->slug)
                ->where('language_id', $defaultLangId)
                ->first();
        }

        if (!$entryTranslation instanceof \App\Entities\EntryTranslationEntity) {
            throw new NotFoundException(lang('Entries.not_found'));
        }

        $entryId = (int) $entryTranslation->entry_id;
        $preview = (bool) ($dto->preview ?? false)
            && PreviewToken::verify('entry', (string) $entryId, $dto->preview_expires, $dto->preview_sig);

        $now    = date('Y-m-d H:i:s');
        $query = $this->entryModel()
            ->where('id', $entryId)
            ->where('collection_id', (int) $collection->id);

        if (!$preview) {
            $query->where('workflow_status', 'published')
                ->groupStart()
                    ->where('published_at IS NULL')
                    ->orWhere('published_at <=', $now)
                ->groupEnd()
                ->groupStart()
                    ->where('scheduled_at IS NULL')
                    ->orWhere('scheduled_at <=', $now)
                ->groupEnd();
        }

        $entry = $query->first();

        if (!$entry instanceof EntryEntity) {
            throw new NotFoundException(lang('Entries.not_found'));
        }

        $entryTransMap = $this->batchResolveEntryTranslations([$entryId], $langId, $defaultLangId);
        $categoriesMap = $this->taxonomyPivotResolver->resolveLocalizedCategories([$entryId], $langId, $defaultLangId);
        $tagsMap       = $this->taxonomyPivotResolver->resolveLocalizedTags([$entryId], $langId, $defaultLangId);

        $blocks = $this->blockInstanceSerializer->forContent('entry', $entryId, $dto->lang);

        $data               = array_merge($entry->toArray(), $entryTransMap[$entryId] ?? []);
        $data['categories'] = $categoriesMap[$entryId] ?? [];
        $data['tags']       = $tagsMap[$entryId] ?? [];
        $data['blocks']     = $blocks;

        // Normalize featured/OG images into canonical nested objects.
        $data = $this->normalizeEntryMedia($data);

        // Get all translations of this entry to construct localized slugs
        /** @var list<\App\Entities\EntryTranslationEntity> $allTranslations */
        $allTranslations = $translationModel->where('entry_id', $entryId)->findAll();
        /** @var list<\App\Entities\LanguageEntity> $activeLanguages */
        $activeLanguages = $langModel->where('is_active', 1)->findAll();
        $langCodeMap = [];
        foreach ($activeLanguages as $al) {
            $langCodeMap[$al->id] = $al->code;
        }

        $localizedSlugs = [];
        foreach ($allTranslations as $at) {
            $code = $langCodeMap[$at->language_id] ?? null;
            $slug = trim((string) $at->slug);
            if ($code !== null && $slug !== '') {
                $localizedSlugs[$code] = $slug;
            }
        }
        $data['localized_slugs'] = $localizedSlugs;

        return PayloadResponseDTO::fromArray($data);
    }

    /**
     * Resolves target and default language IDs in a single query.
     *
     * @return array{langId: int, defaultLangId: int}
     */
    private function resolveLanguageIds(string $langCode): array
    {
        $rows = $this->languageModel()
            ->groupStart()
                ->where('code', $langCode)
                ->orWhere('is_default', 1)
            ->groupEnd()
            ->where('is_active', 1)
            ->findAll();

        $langId        = null;
        $defaultLangId = null;

        foreach ($rows as $row) {
            if (!$row instanceof \App\Entities\LanguageEntity) {
                continue;
            }
            if ($row->code === $langCode) {
                $langId = (int) $row->id;
            }
            if ((int) $row->is_default === 1) {
                $defaultLangId = (int) $row->id;
            }
        }

        if ($langId === null && $defaultLangId === null) {
            throw new NotFoundException(lang('Entries.language_not_found'));
        }

        $resolvedLangId    = $langId ?? $defaultLangId;
        $resolvedDefaultId = $defaultLangId ?? $langId;

        return ['langId' => $resolvedLangId, 'defaultLangId' => $resolvedDefaultId];
    }

    /**
     * @param  list<int>  $entryIds
     * @return array<int, array<string, mixed>>
     */
    private function batchResolveEntryTranslations(array $entryIds, int $langId, int $defaultLangId): array
    {
        if (empty($entryIds)) {
            return [];
        }

        /** @var list<\App\Entities\LanguageEntity> $activeLanguages */
        $activeLanguages = $this->languageModel()->where('is_active', 1)->findAll();

        $activeLanguageIds = [];
        $activeLanguageCodes = [];
        foreach ($activeLanguages as $activeLanguage) {
            if (! $activeLanguage instanceof \App\Entities\LanguageEntity) {
                continue;
            }

            $activeLanguageIds[] = (int) $activeLanguage->id;
            $activeLanguageCodes[(int) $activeLanguage->id] = (string) $activeLanguage->code;
        }

        if ($activeLanguageIds === []) {
            return [];
        }

        $rows = $this->entryTranslationModel()
            ->whereIn('entry_id', $entryIds)
            ->whereIn('language_id', $activeLanguageIds)
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            if (! $row instanceof \App\Entities\EntryTranslationEntity) {
                continue;
            }

            $entryId = (int) $row->entry_id;
            $languageId = (int) $row->language_id;
            $slug = trim((string) $row->slug);

            $normalizedMedia = $this->fileUrlResolver->normalizeEntryTranslation([
                'featured_image' => $row->featured_image ?? null,
                'featured_file_id' => $row->featured_file_id !== null ? (int) $row->featured_file_id : null,
                'featured_image_url' => $row->featured_image_url,
                'og_image' => $row->og_image ?? null,
                'og_image_file_id' => $row->og_image_file_id !== null ? (int) $row->og_image_file_id : null,
                'og_image_url' => $row->og_image_url ?? null,
            ]);

            $grouped[$entryId]['translations'][$languageId] = [
                'slug'               => $slug,
                'title'              => $row->title,
                'excerpt'            => $row->excerpt,
                'featured_image'     => $normalizedMedia['featured_image'] ?? null,
                'meta_title'         => $row->meta_title,
                'meta_description'   => $row->meta_description,
                'og_image'           => $normalizedMedia['og_image'] ?? null,
                'og_type'            => $row->og_type,
                'canonical_url'      => $row->canonical_url,
                'robots'             => $row->robots,
                'schema_data'        => $row->schema_data,
            ];

            if ($slug !== '' && isset($activeLanguageCodes[$languageId])) {
                $grouped[$entryId]['localized_slugs'][$activeLanguageCodes[$languageId]] = $slug;
            }
        }

        $map = [];
        foreach ($grouped as $entryId => $bundle) {
            $translations   = $bundle['translations'];
            $localizedSlugs = $bundle['localized_slugs'] ?? [];

            if (isset($translations[$langId])) {
                $selectedLanguageId = $langId;
            } elseif (isset($translations[$defaultLangId])) {
                $selectedLanguageId = $defaultLangId;
            } else {
                // Neither the requested nor the default language exists:
                // prefer the first translation with a usable slug.
                $selectedLanguageId = (int) array_key_first($translations);
                foreach ($translations as $languageId => $candidate) {
                    if ($candidate['slug'] !== '') {
                        $selectedLanguageId = (int) $languageId;
                        break;
                    }
                }
            }

            $selected = $translations[$selectedLanguageId];

            if ($selected['slug'] === '') {
                // Borrow a slug from the default language first, then any translation.
                if (isset($translations[$defaultLangId]) && $translations[$defaultLangId]['slug'] !== '') {
                    $selected['slug'] = $translations[$defaultLangId]['slug'];
                } else {
                    foreach ($translations as $candidate) {
                        if ($candidate['slug'] !== '') {
                            $selected['slug'] = $candidate['slug'];
                            break;
                        }
                    }
                }
            }

            $map[$entryId] = $selected + [
                'is_fallback'     => $selectedLanguageId !== $langId,
                'localized_slugs' => $localizedSlugs,
            ];
        }

        return $map;
    }

    /**
     * Normalize entry-level media references into canonical nested objects.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeEntryMedia(array $item): array
    {
        $featuredImage = $item['featured_image'] ?? null;
        if (!is_array($featuredImage) || $featuredImage === []) {
            $normalized = $this->fileUrlResolver->normalizeEntryTranslation([
                'featured_image' => $item['featured_image'] ?? null,
                'featured_file_id' => $item['featured_file_id'] ?? null,
                'featured_image_url' => $item['featured_image_url'] ?? null,
            ]);

            $featuredImage = $normalized['featured_image'] ?? null;
        }

        $ogImage = $item['og_image'] ?? null;
        if (!is_array($ogImage) || $ogImage === []) {
            $normalized = $this->fileUrlResolver->normalizeEntryTranslation([
                'og_image' => $item['og_image'] ?? null,
                'og_image_file_id' => $item['og_image_file_id'] ?? null,
                'og_image_url' => $item['og_image_url'] ?? null,
            ]);

            $ogImage = $normalized['og_image'] ?? null;
        }

        $item['featured_image'] = $featuredImage;
        $item['og_image'] = $ogImage;
        unset(
            $item['featured_file_id'],
            $item['featured_image_url'],
            $item['og_image_file_id'],
            $item['og_image_url']
        );

        return $item;
    }
}
