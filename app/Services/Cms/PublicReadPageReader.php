<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\DTO\Request\Cms\PublicReadPageRequestDTO;
use App\Interfaces\Cms\PublicReadPageReaderInterface;
use App\Libraries\Cms\BlockInstanceSerializer;
use App\Modules\PublicRead\Support\PublicReadEnvelope;
use CodeIgniter\Database\BaseConnection;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

/** Set-based CMS page reader with batch block serialization. */
final class PublicReadPageReader implements PublicReadPageReaderInterface
{
    private const FALLBACK_LOCALE = 'es';

    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(
        private readonly BaseConnection $db,
        private readonly BlockInstanceSerializer $blockSerializer,
        private readonly string $fallbackLocale = self::FALLBACK_LOCALE,
    ) {
    }

    /** @param list<string> $fields */
    public function index(PublicReadPageRequestDTO $request, array $fields): ApiResult
    {
        [$pages, $translations, $languages, $defaultLocale] = $this->loadPublicGraph();
        $pathMap = $this->buildPathMap($pages, $translations, $languages, $request->locale, $defaultLocale);
        $data = [];
        foreach ($pages as $page) {
            $id = (int) $page['id'];
            if ((int) ($page['is_in_sitemap'] ?? 1) !== 1 || !isset($pathMap[$id])) {
                continue;
            }
            $translation = $this->resolveTranslation($translations[$id] ?? [], $request->locale, $defaultLocale);
            $data[] = $this->filterFields([
                'id' => $id,
                'page_type' => $page['page_type'],
                'title' => $translation['title'] ?? '',
                'excerpt' => $translation['excerpt'] ?? null,
                'slug' => $pathMap[$id]['path'],
                'localized_slugs' => $pathMap[$id]['localized'],
                'sitemap_priority' => $page['sitemap_priority'],
                'sitemap_changefreq' => $page['sitemap_changefreq'],
                'is_in_sitemap' => (bool) $page['is_in_sitemap'],
                'updated_at' => $page['updated_at'],
            ], $fields);
        }

        $total = count($data);
        $data = array_slice($data, ($request->page - 1) * $request->perPage, $request->perPage);

        return PublicReadEnvelope::success(
            locale: $request->locale,
            data: $data,
            sourceRevision: $this->revision($pages),
            page: $request->page,
            perPage: $request->perPage,
            total: $total,
            meta: [
                'fields' => $fields,
                'query' => ['page' => $request->page, 'per_page' => $request->perPage],
            ],
        );
    }

    /** @param list<string> $fields */
    public function show(string $locale, string $path, array $fields): ApiResult
    {
        [$pages, $translations, $languages, $defaultLocale] = $this->loadPublicGraph();
        $pathMap = $this->buildPathMap($pages, $translations, $languages, $locale, $defaultLocale);
        $normalized = trim($path, '/');
        if ($normalized === '') {
            $normalized = 'home';
        }

        $pageId = null;
        foreach ($pathMap as $id => $pathData) {
            if ($pathData['path'] === $normalized) {
                $pageId = $id;
                break;
            }
        }
        if ($pageId === null) {
            return $this->notFound($locale);
        }

        $page = null;
        foreach ($pages as $candidate) {
            if ((int) $candidate['id'] === $pageId) {
                $page = $candidate;
                break;
            }
        }
        if ($page === null) {
            return $this->notFound($locale);
        }

        $translation = $this->resolveTranslation($translations[$pageId] ?? [], $locale, $defaultLocale);
        $payload = [
            'id' => $pageId,
            'parent_id' => $page['parent_id'],
            'collection_id' => $page['collection_id'],
            'page_type' => $page['page_type'],
            'published_at' => $page['published_at'],
            'sort_order' => (int) $page['sort_order'],
            'sitemap_priority' => $page['sitemap_priority'],
            'sitemap_changefreq' => $page['sitemap_changefreq'],
            'is_in_sitemap' => (bool) $page['is_in_sitemap'],
            'title' => $translation['title'] ?? '',
            'excerpt' => $translation['excerpt'] ?? null,
            'meta_title' => $translation['meta_title'] ?? null,
            'meta_description' => $translation['meta_description'] ?? null,
            'canonical_url' => $translation['canonical_url'] ?? null,
            'robots' => $translation['robots'] ?? null,
            'localized_slugs' => $pathMap[$pageId]['localized'] ?? [],
            'blocks' => $this->blockSerializer->forContent('page', (int) $pageId, $locale),
            'updated_at' => $page['updated_at'],
        ];

        return PublicReadEnvelope::success(
            locale: $locale,
            data: $this->filterFields($payload, $fields),
            sourceRevision: $this->revision([$page]),
            meta: ['fields' => $fields, 'query' => ['path' => $normalized]],
        );
    }

    /** @return array{0: list<array<string, mixed>>, 1: array<int, array<string, array<string, mixed>>>, 2: list<array<string, mixed>>, 3: string} */
    private function loadPublicGraph(): array
    {
        $languageQuery = $this->db->table('cms_languages')
            ->select('id, code, is_default')->where('is_active', 1)->get();
        $languageRows = $languageQuery !== false ? array_values($languageQuery->getResultArray()) : [];
        $codeById = [];
        $defaultLocale = strtolower($this->fallbackLocale);
        foreach ($languageRows as $language) {
            $code = strtolower((string) $language['code']);
            $codeById[(int) $language['id']] = $code;
            if ((int) $language['is_default'] === 1) {
                $defaultLocale = $code;
            }
        }
        $languageIds = array_values(array_map(static fn (array $language): int => (int) $language['id'], $languageRows));
        $pageQuery = $this->db->table('cms_pages')
            ->select('id, parent_id, collection_id, page_type, status, published_at, sort_order, sitemap_priority, sitemap_changefreq, is_in_sitemap, created_at, updated_at')
            ->where('status', 'published')->where('deleted_at', null)
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
        $pages = $pageQuery !== false ? array_values($pageQuery->getResultArray()) : [];
        if ($pages === []) {
            return [[], [], $languageRows, $defaultLocale];
        }

        $pageIds = array_map(static fn (array $page): int => (int) $page['id'], $pages);
        $translationBuilder = $this->db->table('cms_page_translations')
            ->select('page_id, language_id, slug, title, excerpt, meta_title, meta_description, canonical_url, robots, updated_at')
            ->whereIn('page_id', $pageIds);
        if ($languageIds !== []) {
            $translationBuilder->whereIn('language_id', $languageIds);
        }
        // Load all active-language slugs in this single set query. The requested
        // and default locales still control the content fallback, while the
        // complete map lets the response expose stable localized_slugs without
        // querying a translation inside the hierarchy loop.
        $translations = [];
        $translationQuery = $translationBuilder->get();
        $translationRows = $translationQuery !== false ? $translationQuery->getResultArray() : [];
        foreach ($translationRows as $translation) {
            $translation['locale'] = $codeById[(int) $translation['language_id']] ?? '';
            $translations[(int) $translation['page_id']][(string) $translation['locale']] = $translation;
        }

        return [$pages, $translations, $languageRows, $defaultLocale];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param array<int, array<string, array<string, mixed>>> $translations
     * @param list<array<string, mixed>> $languages
     * @return array<int, array{path: string, localized: array<string, string>}>
     */
    private function buildPathMap(array $pages, array $translations, array $languages, string $locale, string $defaultLocale): array
    {
        $pageMap = [];
        foreach ($pages as $page) {
            $pageMap[(int) $page['id']] = $page;
        }
        $codes = [$locale, $defaultLocale];
        foreach ($languages as $language) {
            $codes[] = strtolower((string) $language['code']);
        }
        $result = [];
        foreach ($pageMap as $id => $page) {
            $localized = [];
            foreach (array_values(array_unique($codes)) as $code) {
                $segments = [];
                $current = $id;
                $visited = [];
                while ($current !== null && !isset($visited[$current])) {
                    $visited[$current] = true;
                    $pageRow = $pageMap[$current] ?? null;
                    if ($pageRow === null) {
                        $segments = [];
                        break;
                    }
                    $translation = $this->resolveTranslation($translations[$current] ?? [], $code, $defaultLocale);
                    $slug = trim((string) ($translation['slug'] ?? ''));
                    if ($slug === '') {
                        $segments = [];
                        break;
                    }
                    array_unshift($segments, $slug);
                    $current = $pageRow['parent_id'] !== null ? (int) $pageRow['parent_id'] : null;
                }
                if ($segments !== []) {
                    $localized[$code] = implode('/', $segments);
                }
            }
            $path = $localized[$locale] ?? $localized[$defaultLocale] ?? '';
            if ($path !== '') {
                $result[$id] = ['path' => $path, 'localized' => $localized];
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $translations
     * @return array<string, mixed>
     */
    private function resolveTranslation(array $translations, string $locale, string $defaultLocale): array
    {
        return $translations[$locale] ?? $translations[$defaultLocale] ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function filterFields(array $payload, array $fields): array
    {
        return $fields === [] ? $payload : array_intersect_key($payload, array_flip($fields));
    }

    /** @param list<array<string, mixed>> $rows */
    private function revision(array $rows): string
    {
        $updated = '';
        $maxId = 0;
        foreach ($rows as $row) {
            $updated = max($updated, (string) ($row['updated_at'] ?? ''));
            $maxId = max($maxId, (int) ($row['id'] ?? 0));
        }

        return 'cms:' . ($updated !== '' ? $updated : 'empty') . ':' . $maxId;
    }

    private function notFound(string $locale): ApiResult
    {
        return new ApiResult([
            'version' => 1,
            'ok' => false,
            'data' => null,
            'meta' => ['locale' => $locale, 'source_revision' => 'cms:empty'],
            'source' => ['domain' => 'cms', 'state' => 'unavailable', 'stale' => false],
            'messages' => ['Page not found.'],
        ], 404);
    }
}
