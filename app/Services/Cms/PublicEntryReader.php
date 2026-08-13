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

    private function languageModel(): \App\Models\LanguageModel
    {
        if ($this->languageModel === null) {
            /** @var \App\Models\LanguageModel $resolved */
            $resolved = model(\App\Models\LanguageModel::class);
            $this->languageModel = $resolved;
        }

        return $this->languageModel;
    }

    /**
     * `entry.*` fields safe to filter/order by directly on `cms_entries`
     * (real, indexed columns — never require a join).
     */
    private const ENTRY_DIRECT_COLUMNS = ['published_at', 'created_at', 'sort_order'];

    /**
     * `entry.*` fields safe to filter/order by via `cms_entry_translations`
     * (plain scalar columns only — excludes `schema_data` (JSON) and the two
     * file-id foreign keys, which were never meaningful facet/order targets).
     */
    private const ENTRY_TRANSLATION_FIELDS = [
        'slug', 'title', 'excerpt', 'meta_title', 'meta_description',
        'canonical_url', 'robots', 'og_type', 'featured_image_url',
    ];

    /**
     * Every real cms_entries column the public contract can expose, minus
     * `wizard_extra` — a transient write-time payload (cleared once the
     * block-template initializer consumes it, see EntryBlockTemplateInitializer)
     * that is never read anywhere in this file and can be an arbitrarily
     * large JSON blob. `select('cms_entries.*')` was paying to read and
     * hydrate it on every public listing/detail request for nothing.
     */
    private const ENTRY_PUBLIC_COLUMNS = 'cms_entries.id, cms_entries.collection_id, cms_entries.author_id, '
        . 'cms_entries.workflow_status, cms_entries.published_at, cms_entries.scheduled_at, '
        . 'cms_entries.is_featured, cms_entries.view_count, cms_entries.sort_order, '
        . 'cms_entries.sitemap_priority, cms_entries.sitemap_changefreq, cms_entries.is_in_sitemap, '
        . 'cms_entries.deleted_at, cms_entries.created_at, cms_entries.updated_at';

    /** @param array<string, mixed> $options */
    public function listPublic(PublicEntryIndexRequestDTO $dto, array $options = []): DataTransferObjectInterface
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
        $entryModel->select(self::ENTRY_PUBLIC_COLUMNS);

        if ($dto->category_id !== null) {
            $entryModel->join('cms_entry_categories', 'cms_entry_categories.entry_id = cms_entries.id')
                ->where('cms_entry_categories.category_id', $dto->category_id);
        } elseif ($dto->category !== null) {
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

        // --- Faceted filter (filter_by/filter_value) — real WHERE against an
        // indexed column or an indexed cms_entry_facet_values lookup, never a
        // full-table load. See docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.A.
        $filterField = null;
        if ($dto->filter_by !== null && $dto->filter_value !== null) {
            $filterField = $this->classifyField($dto->filter_by);
            $this->applyFieldFilter($builder, $filterField, $dto->filter_by, $langId, $defaultLangId, $dto->filter_operator, $dto->filter_value);
        }

        // Count against the filtered-but-not-yet-ordered builder — mirrors
        // the pre-existing default branch, which already did this correctly.
        $total = (int) $builder->countAllResults(false);

        $displayValueExpr = null; // SQL fragment selected as entry_listing_field_value, or null if not applicable
        $displayEntryColumn = null; // set instead of the above when the field is a direct cms_entries column

        if ($dto->listing_field !== null) {
            $orderField = $this->classifyField($dto->listing_field);
            $reuseFilterJoin = $filterField !== null
                && $dto->filter_by === $dto->listing_field
                && $filterField['mode'] === $orderField['mode']
                && $orderField['mode'] !== 'entry_column';

            switch ($orderField['mode']) {
                case 'entry_column':
                    $column = 'cms_entries.' . $orderField['column'];
                    $this->applyFieldOrder($builder, $column, 'string', $dto->order_direction, $now);
                    $displayEntryColumn = $orderField['column'];
                    break;

                case 'entry_translation':
                    $alias = $reuseFilterJoin ? 'filter_trans' : 'order_trans';
                    if (! $reuseFilterJoin) {
                        $this->joinTranslationValueSubquery($builder, (string) $orderField['column'], $langId, $defaultLangId, $alias, 'left');
                    }
                    $this->applyFieldOrder($builder, "{$alias}.resolved_value", 'string', $dto->order_direction, $now);
                    $displayValueExpr = "{$alias}.resolved_value";
                    break;

                case 'facet':
                    $alias = $reuseFilterJoin ? 'filter_facet' : 'order_facet';
                    if (! $reuseFilterJoin) {
                        $this->joinFacetSubquery($builder, $dto->listing_field, $langId, $defaultLangId, $alias, 'left');
                    }
                    // A field_key's value_type is resolved once per request
                    // (not per row) from whichever row currently carries it.
                    // If a block field's declared type changed after some
                    // rows were materialized under the old type, those rows'
                    // non-matching typed column is NULL and they naturally
                    // sort after any row of the now-current type — a self-
                    // correcting edge case, not a lasting inconsistency (the
                    // next save of that block re-materializes it under the
                    // current type).
                    $valueType = $this->resolveFacetValueType($dto->listing_field);
                    $orderColumn = match ($valueType) {
                        'date'    => "{$alias}.value_date",
                        'numeric' => "{$alias}.value_numeric",
                        default   => "{$alias}.value_string",
                    };
                    $this->applyFieldOrder($builder, $orderColumn, $valueType, $dto->order_direction, $now);
                    // value_string always holds the verbatim original value
                    // (EntryFacetValueSynchronizer populates it regardless of
                    // type) — used here instead of value_date/value_numeric
                    // so `display_date` matches the source block_data string
                    // exactly (e.g. "2026-08-13", not a normalized
                    // "2026-08-13 00:00:00" DATETIME rendering).
                    $displayValueExpr = "{$alias}.value_string";
                    break;

                default:
                    // Unresolvable field reference (e.g. an unsupported
                    // entry.* column, or the still-dead taxonomy.* prefix —
                    // see PublicEntryReader's original audit note). No
                    // primary ORDER BY is added; the tie-breakers below apply
                    // uniformly, matching the legacy behavior where an
                    // unresolved field produced identical (empty) values for
                    // every entry and every comparison fell through to them.
                    break;
            }

            // Same tie-breakers the already-correct default branch below
            // uses: published_at desc, created_at desc, then id desc for a
            // fully stable order across pages.
            $builder->orderBy('cms_entries.published_at', 'DESC')
                ->orderBy('cms_entries.created_at', 'DESC')
                ->orderBy('cms_entries.id', 'DESC');

            if ($displayValueExpr !== null) {
                $builder->select("{$displayValueExpr} AS entry_listing_field_value", false);
            }

            $entries = $builder->findAll($dto->per_page, $offset);
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
                    ->select(self::ENTRY_PUBLIC_COLUMNS)
                    ->select('title_trans.title AS entry_title_order', false);
            }

            $databaseDirection = $dto->order_direction === 'DESC' ? 'DESC' : 'ASC';
            $entries = $builder
                ->orderBy($orderColumn, $databaseDirection)
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

        $fields = $this->projectionFields($options['fields'] ?? null);
        $fullProjection = $fields === [];
        $needsTranslationBundle = $fullProjection || $dto->order_by === 'title' || $this->hasAnyField($fields, [
            'title', 'slug', 'excerpt', 'featured_image', 'og_image', 'meta_title',
            'meta_description', 'canonical_url', 'robots', 'schema_data', 'localized_slugs', 'translations',
        ]);
        $needsMedia = $fullProjection || $this->hasAnyField($fields, ['featured_image', 'og_image']);
        $entryTranslations = $needsTranslationBundle
            ? $this->batchResolveEntryTranslations($entryIds, $langId, $defaultLangId, $needsMedia)
            : ['translations' => [], 'media' => []];
        $entryTransMap = $entryTranslations['translations'];
        $entryMediaMap = $entryTranslations['media'];
        $needsTaxonomy = $fullProjection || $this->hasAnyField($fields, ['categories', 'tags']);
        $categoriesMap  = $needsTaxonomy ? $this->taxonomyPivotResolver->resolveLocalizedCategories($entryIds, $langId, $defaultLangId) : [];
        $tagsMap        = $needsTaxonomy ? $this->taxonomyPivotResolver->resolveLocalizedTags($entryIds, $langId, $defaultLangId) : [];

        $data = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof EntryEntity) {
                continue;
            }
            $entryId = (int) $entry->id;
            $item    = array_merge($entry->toArray(), $entryTransMap[$entryId] ?? []);
            $item['categories'] = $categoriesMap[$entryId] ?? [];
            $item['tags']       = $tagsMap[$entryId] ?? [];
            unset($item['entry_title_order'], $item['entry_listing_field_value']);
            // Normalize featured/OG images into canonical nested objects.
            if ($needsMedia) {
                $item = $this->normalizeEntryMedia($item, $entryMediaMap);
            }
            if ($dto->listing_field !== null) {
                if ($displayEntryColumn !== null) {
                    $raw = $entry->{$displayEntryColumn} ?? null;
                } else {
                    $raw = $entry->entry_listing_field_value ?? null;
                }
                $item['display_date'] = is_scalar($raw) && trim((string) $raw) !== '' ? trim((string) $raw) : null;
            }
            $data[] = $item;
        }

        if ($dto->include_listing_content) {
            // Empty $listing_content_fields means "no sub-selection requested" —
            // resolveBatch() then computes/returns every key, same contract as
            // before sub-field selection existed.
            $listingContentByEntry = $this->entryListingContentResolver->resolveBatch(
                $data,
                $dto->lang,
                $dto->projection_fields,
                $dto->listing_content_fields
            );

            foreach ($data as &$item) {
                $entryId = (int) ($item['id'] ?? 0);
                $item['listing_content'] = $listingContentByEntry[$entryId] ?? [
                    'rich_text' => '',
                    'image' => null,
                    'secondary_action' => null,
                    'date_fields' => [],
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
     * Classifies a `filter_by`/`order_by=field:...` reference into how it
     * must be resolved at the SQL layer:
     *   - `entry_column`   — a real, indexed cms_entries column.
     *   - `entry_translation` — a plain scalar cms_entry_translations column.
     *   - `facet`          — a `block.<block_key>.<field>` or bare `<field>`
     *     reference, resolved against the materialized cms_entry_facet_values
     *     table (see EntryFacetValueSynchronizer).
     *   - `none`           — unresolvable (e.g. an entry.* field outside the
     *     supported list, or the still-unsupported taxonomy.* prefix). Callers
     *     treat this as "no entries match"/"no ordering effect", matching the
     *     legacy PHP-side resolver's behavior for the same inputs.
     *
     * @return array{mode: string, column: string}
     */
    private function classifyField(string $field): array
    {
        if (str_starts_with($field, 'entry.')) {
            $entryField = substr($field, 6);
            if (in_array($entryField, self::ENTRY_DIRECT_COLUMNS, true)) {
                return ['mode' => 'entry_column', 'column' => $entryField];
            }
            if (in_array($entryField, self::ENTRY_TRANSLATION_FIELDS, true)) {
                return ['mode' => 'entry_translation', 'column' => $entryField];
            }

            return ['mode' => 'none', 'column' => ''];
        }

        if (str_starts_with($field, 'taxonomy.')) {
            return ['mode' => 'none', 'column' => ''];
        }

        // `block.<block_key>.<field>` or the bare legacy `<field>` form —
        // both are written to cms_entry_facet_values by
        // EntryFacetValueSynchronizer under the same field_key the caller
        // passed here, so no further parsing is needed.
        return ['mode' => 'facet', 'column' => ''];
    }

    /**
     * @param array{mode: string, column: string} $field
     */
    private function applyFieldFilter(
        \App\Models\EntryModel $builder,
        array $field,
        string $rawField,
        int $langId,
        int $defaultLangId,
        string $operator,
        string $value
    ): void {
        $column = match ($field['mode']) {
            'entry_column' => 'cms_entries.' . $field['column'],
            'entry_translation' => (function () use ($builder, $field, $langId, $defaultLangId): string {
                $this->joinTranslationValueSubquery($builder, (string) $field['column'], $langId, $defaultLangId, 'filter_trans', 'inner');

                return 'filter_trans.resolved_value';
            })(),
            'facet' => (function () use ($builder, $rawField, $langId, $defaultLangId): string {
                $this->joinFacetSubquery($builder, $rawField, $langId, $defaultLangId, 'filter_facet', 'inner');

                return 'filter_facet.value_string';
            })(),
            default => null,
        };

        if ($column === null) {
            // Unresolvable field reference: no entry can match, exactly like
            // the legacy resolver returning an empty candidate-value map.
            $builder->where('1 = 0', null, false);

            return;
        }

        if ($operator === 'contains') {
            $builder->like($column, $value);
        } else {
            $builder->where($column, $value);
        }
    }

    /**
     * Applies ORDER BY for a resolved field column, always pushing NULLs
     * last regardless of direction (matching compareNullableValues()'s prior
     * semantics) and supporting the two-bucket `UPCOMING` date order
     * ("próximos primero, luego histórico descendente") for date-typed
     * fields only — the same restriction the previous PHP-side sorter had in
     * practice, since UPCOMING only ever made sense against a parseable date.
     */
    private function applyFieldOrder(\App\Models\EntryModel $builder, string $column, string $valueType, string $direction, string $now): void
    {
        if ($direction === 'UPCOMING' && $valueType === 'date') {
            $quotedNow = $builder->db->escape($now);
            $builder->orderBy("CASE WHEN {$column} IS NULL THEN 2 WHEN {$column} >= {$quotedNow} THEN 0 ELSE 1 END", 'ASC', false);
            $builder->orderBy("CASE WHEN {$column} >= {$quotedNow} THEN {$column} END", 'ASC', false);
            $builder->orderBy("CASE WHEN {$column} < {$quotedNow} THEN {$column} END", 'DESC', false);

            return;
        }

        $sqlDirection = $direction === 'DESC' ? 'DESC' : 'ASC';
        $builder->orderBy("({$column} IS NULL)", 'ASC', false);
        $builder->orderBy($column, $sqlDirection, false);
    }

    /**
     * Joins a derived, per-entry-resolved value from cms_entry_facet_values
     * for one field_key, with the requested language winning over the
     * default-language fallback when both exist — same precedence the old
     * PHP-side `byEntryAndLang[$entryId][$langId] ?? [...][$defaultLangId]`
     * lookup had, just computed in SQL instead of after loading every row.
     */
    private function joinFacetSubquery(\App\Models\EntryModel $builder, string $fieldKey, int $langId, int $defaultLangId, string $alias, string $joinType): void
    {
        $db = $builder->db;
        $escapedField = $db->escape($fieldKey);
        $sql = "(SELECT entry_id, "
            . "COALESCE(MAX(CASE WHEN language_id = {$langId} THEN value_string END), MAX(CASE WHEN language_id = {$defaultLangId} THEN value_string END)) AS value_string, "
            . "COALESCE(MAX(CASE WHEN language_id = {$langId} THEN value_date END), MAX(CASE WHEN language_id = {$defaultLangId} THEN value_date END)) AS value_date, "
            . "COALESCE(MAX(CASE WHEN language_id = {$langId} THEN value_numeric END), MAX(CASE WHEN language_id = {$defaultLangId} THEN value_numeric END)) AS value_numeric "
            . "FROM cms_entry_facet_values WHERE field_key = {$escapedField} AND language_id IN ({$langId}, {$defaultLangId}) "
            . "GROUP BY entry_id) {$alias}";

        $builder->join($sql, "{$alias}.entry_id = cms_entries.id", $joinType);
    }

    /**
     * Same per-entry, language-fallback resolution as joinFacetSubquery(),
     * but against cms_entry_translations for `entry.*` fields that live on
     * the translation row (title, excerpt, slug, ...) rather than as a
     * materialized facet. $column is only ever called with a value from
     * self::ENTRY_TRANSLATION_FIELDS — never raw user input — so it is safe
     * to interpolate as an identifier.
     */
    private function joinTranslationValueSubquery(\App\Models\EntryModel $builder, string $column, int $langId, int $defaultLangId, string $alias, string $joinType): void
    {
        $db = $builder->db;
        $quotedColumn = $db->escapeIdentifiers($column);
        $sql = "(SELECT entry_id, "
            . "COALESCE(MAX(CASE WHEN language_id = {$langId} THEN {$quotedColumn} END), MAX(CASE WHEN language_id = {$defaultLangId} THEN {$quotedColumn} END)) AS resolved_value "
            . "FROM cms_entry_translations "
            . "WHERE {$quotedColumn} IS NOT NULL AND {$quotedColumn} <> '' AND language_id IN ({$langId}, {$defaultLangId}) "
            . "GROUP BY entry_id) {$alias}";

        $builder->join($sql, "{$alias}.entry_id = cms_entries.id", $joinType);
    }

    /**
     * One cheap, field_key-indexed lookup to learn whether a facet field
     * should sort as a date, a number, or a plain string — decided once per
     * request, not per candidate row.
     */
    private function resolveFacetValueType(string $fieldKey): string
    {
        $result = $this->entryModel()->db->table('cms_entry_facet_values')
            ->select('value_type')
            ->where('field_key', $fieldKey)
            ->limit(1)
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (string) ($row['value_type'] ?? 'string') : 'string';
    }

    /** @param array<string, mixed> $options */
    public function showPublic(PublicEntryShowRequestDTO $dto, array $options = []): DataTransferObjectInterface
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
        $entryModel = $this->entryModel();
        $entryModel->builder()->resetQuery();
        $query = $entryModel
            ->select(self::ENTRY_PUBLIC_COLUMNS)
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

        $fields = $this->projectionFields($options['fields'] ?? null);
        $fullProjection = $fields === [];
        $needsTranslationBundle = $fullProjection || $this->hasAnyField($fields, [
            'title', 'slug', 'excerpt', 'featured_image', 'og_image', 'meta_title',
            'meta_description', 'canonical_url', 'robots', 'schema_data', 'localized_slugs', 'translations',
        ]);
        $needsMedia = $fullProjection || $this->hasAnyField($fields, ['featured_image', 'og_image']);
        $needsTaxonomy = $fullProjection || $this->hasAnyField($fields, ['categories', 'tags']);
        $needsBlocks = $fullProjection || in_array('blocks', $fields, true);
        $entryTranslations = $needsTranslationBundle
            ? $this->batchResolveEntryTranslations([$entryId], $langId, $defaultLangId, $needsMedia)
            : ['translations' => [], 'media' => []];
        $entryTransMap = $entryTranslations['translations'];
        $entryMediaMap = $entryTranslations['media'];
        $categoriesMap = $needsTaxonomy ? $this->taxonomyPivotResolver->resolveLocalizedCategories([$entryId], $langId, $defaultLangId) : [];
        $tagsMap       = $needsTaxonomy ? $this->taxonomyPivotResolver->resolveLocalizedTags([$entryId], $langId, $defaultLangId) : [];

        $data               = array_merge($entry->toArray(), $entryTransMap[$entryId] ?? []);
        $data['categories'] = $categoriesMap[$entryId] ?? [];
        $data['tags']       = $tagsMap[$entryId] ?? [];

        if ($needsMedia) {
            // Normalize featured/OG images into canonical nested objects.
            $data = $this->normalizeEntryMedia($data, $entryMediaMap);
        }

        if ($needsBlocks) {
            $blocks = $this->blockInstanceSerializer->forContent('entry', $entryId, $dto->lang);
            $data['blocks'] = $this->composeNewsGallery(
                $dto->collection_key,
                $blocks,
                is_array($data['featured_image'] ?? null) ? $data['featured_image'] : [],
                (string) ($data['title'] ?? '')
            );
        }

        if ($needsTranslationBundle) {
            $data['localized_slugs'] = $entryTransMap[$entryId]['localized_slugs'] ?? [];
        }

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
     * @return array{translations: array<int, array<string, mixed>>, media: array<int, array<string, mixed>>}
     */
    private function batchResolveEntryTranslations(array $entryIds, int $langId, int $defaultLangId, bool $includeMedia = true): array
    {
        if (empty($entryIds)) {
            return ['translations' => [], 'media' => []];
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
            return ['translations' => [], 'media' => []];
        }

        $rows = $this->entryTranslationModel()
            ->whereIn('entry_id', $entryIds)
            ->whereIn('language_id', $activeLanguageIds)
            ->findAll();

        $mediaMap = [];
        if ($includeMedia) {
            $fileIds = [];
            foreach ($rows as $row) {
                if (! $row instanceof \App\Entities\EntryTranslationEntity) {
                    continue;
                }

                foreach (['featured_file_id', 'og_image_file_id'] as $field) {
                    $id = $row->{$field} ?? null;
                    if (is_numeric($id) && (int) $id > 0) {
                        $fileIds[] = (int) $id;
                    }
                }
                foreach (['featured_image', 'og_image'] as $field) {
                    $id = $this->fileUrlResolver->resolveMediaReferenceFileId($row->{$field} ?? null);
                    if ($id !== null) {
                        $fileIds[] = $id;
                    }
                }
            }
            $mediaMap = $this->fileUrlResolver->resolveManyMeta(array_values(array_unique($fileIds)), 'public');
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (! $row instanceof \App\Entities\EntryTranslationEntity) {
                continue;
            }

            $entryId = (int) $row->entry_id;
            $languageId = (int) $row->language_id;
            $slug = trim((string) $row->slug);

            $normalizedMedia = $includeMedia ? $this->fileUrlResolver->normalizeEntryTranslation([
                'featured_image' => $row->featured_image ?? null,
                'featured_file_id' => $row->featured_file_id !== null ? (int) $row->featured_file_id : null,
                'featured_image_url' => $row->featured_image_url,
                'og_image' => $row->og_image ?? null,
                'og_image_file_id' => $row->og_image_file_id !== null ? (int) $row->og_image_file_id : null,
                'og_image_url' => $row->og_image_url ?? null,
            ], 'public', $mediaMap) : ['featured_image' => null, 'og_image' => null];

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

        return ['translations' => $map, 'media' => $mediaMap];
    }

    /**
     * Normalize entry-level media references into canonical nested objects.
     *
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $mediaMap
     * @return array<string, mixed>
     */
    private function normalizeEntryMedia(array $item, array $mediaMap): array
    {
        $featuredImage = $item['featured_image'] ?? null;
        if (!is_array($featuredImage) || $featuredImage === []) {
            $normalized = $this->fileUrlResolver->normalizeEntryTranslation([
                'featured_image' => $item['featured_image'] ?? null,
                'featured_file_id' => $item['featured_file_id'] ?? null,
                'featured_image_url' => $item['featured_image_url'] ?? null,
            ], 'public', $mediaMap);

            $featuredImage = $normalized['featured_image'] ?? null;
        }

        $ogImage = $item['og_image'] ?? null;
        if (!is_array($ogImage) || $ogImage === []) {
            $normalized = $this->fileUrlResolver->normalizeEntryTranslation([
                'og_image' => $item['og_image'] ?? null,
                'og_image_file_id' => $item['og_image_file_id'] ?? null,
                'og_image_url' => $item['og_image_url'] ?? null,
            ], 'public', $mediaMap);

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

    /**
     * @param list<string> $fields
     * @param list<string> $wanted
     */
    private function hasAnyField(array $fields, array $wanted): bool
    {
        return $fields !== [] && array_intersect($fields, $wanted) !== [];
    }

    /** @return list<string> */
    private function projectionFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $field): string => (string) $field, $fields),
            static fn (string $field): bool => $field !== '',
        ));
    }

    /**
     * The public contract for Noticias exposes the entry cover in its gallery.
     * The cover remains owned by the entry translation; the gallery item is a
     * virtual projection so changing the cover can never leave a duplicated,
     * stale media reference in CMS storage.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $featuredImage
     * @return array<int, array<string, mixed>>
     */
    private function composeNewsGallery(string $collectionKey, array $blocks, array $featuredImage, string $title): array
    {
        if (strtolower(trim($collectionKey)) !== 'noticias' || ! $this->mediaReferenceIsUsable($featuredImage)) {
            return $blocks;
        }

        $hasGallery = false;
        $normalizedBlocks = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            // The old Noticias template created an optional image block with
            // no configured image. It is not editorial content and must not be
            // exposed as a public placeholder.
            if (($block['block_key'] ?? '') === 'image' && ! $this->blockConfigHasImage($block)) {
                continue;
            }

            $children = is_array($block['children'] ?? null)
                ? array_values(array_filter($block['children'], static fn (mixed $child): bool => is_array($child)))
                : [];
            $block['children'] = $this->composeNewsGalleryChildren($children, $featuredImage, $title, $hasGallery);

            if (($block['block_key'] ?? '') === 'gallery') {
                $hasGallery = true;
                if (! $this->galleryContainsImage($block['children'], $featuredImage)) {
                    array_unshift($block['children'], $this->virtualGalleryItem($featuredImage, $title));
                }
            }

            $normalizedBlocks[] = $block;
        }

        if (! $hasGallery) {
            $normalizedBlocks[] = $this->virtualGallery($featuredImage, $title);
        }

        return $normalizedBlocks;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @param array<string, mixed> $featuredImage
     * @param-out bool $hasGallery
     * @return list<array<string, mixed>>
     */
    private function composeNewsGalleryChildren(array $children, array $featuredImage, string $title, bool &$hasGallery): array
    {
        $normalizedChildren = [];
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['block_key'] ?? '') === 'image' && ! $this->blockConfigHasImage($child)) {
                continue;
            }

            $nested = is_array($child['children'] ?? null)
                ? array_values(array_filter($child['children'], static fn (mixed $nestedChild): bool => is_array($nestedChild)))
                : [];
            $child['children'] = $this->composeNewsGalleryChildren($nested, $featuredImage, $title, $hasGallery);
            if (($child['block_key'] ?? '') === 'gallery') {
                $hasGallery = true;
                if (! $this->galleryContainsImage($child['children'], $featuredImage)) {
                    array_unshift($child['children'], $this->virtualGalleryItem($featuredImage, $title));
                }
            }
            $normalizedChildren[] = $child;
        }

        return $normalizedChildren;
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockConfigHasImage(array $block): bool
    {
        $image = is_array($block['block_config']['image'] ?? null) ? $block['block_config']['image'] : [];

        return $this->mediaReferenceIsUsable($image);
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @param array<string, mixed> $featuredImage
     */
    private function galleryContainsImage(array $children, array $featuredImage): bool
    {
        foreach ($children as $child) {
            if (! is_array($child) || ($child['block_key'] ?? '') !== 'gallery_item') {
                continue;
            }
            $image = is_array($child['block_config']['image'] ?? null) ? $child['block_config']['image'] : [];
            if ($this->mediaReferencesMatch($image, $featuredImage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function mediaReferencesMatch(array $left, array $right): bool
    {
        $leftFileId = (int) ($left['file_id'] ?? 0);
        $rightFileId = (int) ($right['file_id'] ?? 0);
        if ($leftFileId > 0 && $rightFileId > 0) {
            return $leftFileId === $rightFileId;
        }

        $leftUrl = trim((string) ($left['url'] ?? ''));
        $rightUrl = trim((string) ($right['url'] ?? ''));

        return $leftUrl !== '' && $leftUrl === $rightUrl;
    }

    /**
     * @param array<string, mixed> $image
     */
    private function mediaReferenceIsUsable(array $image): bool
    {
        return (int) ($image['file_id'] ?? 0) > 0 || trim((string) ($image['url'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>
     */
    private function virtualGalleryItem(array $image, string $title): array
    {
        return [
            'id' => null,
            'block_key' => 'gallery_item',
            'sort_order' => 0,
            'column_index' => null,
            'parent_instance_id' => null,
            'block_config' => ['image' => $image],
            'block_data' => ['alt' => $title, 'caption' => ''],
            'is_fallback' => false,
            'is_virtual' => true,
            'children' => [],
        ];
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>
     */
    private function virtualGallery(array $image, string $title): array
    {
        return [
            'id' => null,
            'block_key' => 'gallery',
            'sort_order' => PHP_INT_MAX,
            'column_index' => null,
            'parent_instance_id' => null,
            'block_config' => [],
            'block_data' => [],
            'is_fallback' => false,
            'is_virtual' => true,
            'children' => [$this->virtualGalleryItem($image, $title)],
        ];
    }
}
