<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use App\Models\CollectionModel;
use App\Models\PageModel;

/**
 * Resolves public navigation targets for content blocks.
 *
 * Navigation is deliberately derived from CMS relationships at read time. A
 * block stores what it displays, while pages and localized slugs remain the
 * source of truth for where the visitor navigates.
 */
final class BlockNavigationResolver
{
    /** @var array<string, array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}> */
    private array $resolvedPageCache = [];

    /** @var array<int|string, int> */
    private array $collectionIdsByKey = [];

    /** @var array<int|string, int> */
    private array $indexPageIdsByCollection = [];

    public function __construct(
        private readonly SlugRouter $slugRouter,
    ) {
    }

    /**
     * Resolve one block configuration for a language.
     *
     * @param array<string, mixed> $blockConfig
     * @param array<string, mixed> $navigationDefinition
     * @return array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}
     */
    public function resolve(
        array $blockConfig,
        string $lang,
        array $navigationDefinition = [],
        string $ownerType = '',
        int $ownerId = 0,
    ): array {
        if ($navigationDefinition === []) {
            return $this->unresolved('navigation_not_declared');
        }

        $sourceType = strtolower(trim((string) ($blockConfig['source_type'] ?? 'auto')));

        if ($sourceType === 'auto') {
            $sourceType = match (strtolower(trim((string) ($blockConfig['collection_key'] ?? '')))) {
                'cartelera', 'events', 'eventos' => 'event_items',
                'museo', 'catalogo', 'catalog', 'fichas', 'collection_items' => 'catalog_items',
                default => ! empty($blockConfig['collection_id']) || ! empty($blockConfig['collection_key'])
                    ? 'cms_collection'
                    : '',
            };
        }

        if (($navigationDefinition['source'] ?? '') === 'owner') {
            return $this->resolveOwnerParent($ownerType, $ownerId, $lang, $navigationDefinition);
        }

        return match ($sourceType) {
            'cms_collection', 'collection' => $this->resolveCmsCollection($blockConfig, $lang, $navigationDefinition),
            'event_items' => $this->resolvePageType((string) ($navigationDefinition['event_page_type'] ?? 'events'), $lang),
            'catalog_items' => $this->resolvePageType((string) ($navigationDefinition['catalog_page_type'] ?? 'catalog_listing'), $lang),
            default => $this->unresolved('unsupported_source'),
        };
    }

    /**
     * Resolve several configurations while reusing page and collection lookups.
     *
     * @param list<array<string, mixed>> $blockConfigs
     * @param list<array<string, mixed>> $navigationDefinitions
     * @param list<int> $ownerIds
     * @return list<array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}>
     */
    public function resolveMany(
        array $blockConfigs,
        string $lang,
        array $navigationDefinitions = [],
        string $ownerType = '',
        array $ownerIds = [],
    ): array {
        $this->warmCollectionIndexCache($blockConfigs);

        return array_map(
            fn (array $config, int $index): array => $this->resolve(
                $config,
                $lang,
                $navigationDefinitions[$index] ?? [],
                $ownerType,
                (int) ($ownerIds[$index] ?? 0),
            ),
            $blockConfigs,
            array_keys($blockConfigs),
        );
    }

    /** @param list<array<string, mixed>> $blockConfigs */
    private function warmCollectionIndexCache(array $blockConfigs): void
    {
        $ids = [];
        $keys = [];
        foreach ($blockConfigs as $config) {
            $rawId = $config['collection_id'] ?? null;
            if (is_scalar($rawId) && (int) $rawId > 0) {
                $ids[] = (int) $rawId;
                continue;
            }

            $key = trim((string) ($config['collection_key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        if ($keys !== []) {
            $collections = (new CollectionModel())
                ->whereIn('collection_key', array_values(array_unique($keys)))
                ->where('is_active', 1)
                ->findAll();
            foreach ($collections as $collection) {
                $collectionId = $this->entityId($collection);
                if ($collectionId > 0) {
                    $key = is_object($collection)
                        ? (string) ($collection->collection_key ?? '')
                        : (string) ($collection['collection_key'] ?? '');
                    if ($key !== '') {
                        $this->collectionIdsByKey[$key] = $collectionId;
                        $ids[] = $collectionId;
                    }
                }
            }
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $pages = (new PageModel())
            ->whereIn('collection_id', $ids)
            ->where('page_type', 'collection_index')
            ->where('status', 'published')
            ->where('deleted_at IS NULL', null, false)
            ->findAll();
        foreach ($pages as $page) {
            $collectionId = is_object($page)
                ? (int) ($page->collection_id ?? 0)
                : (int) ($page['collection_id'] ?? 0);
            if ($collectionId > 0) {
                $this->indexPageIdsByCollection[$collectionId] = $this->entityId($page);
            }
        }
    }

    /**
     * @param array<string, mixed> $blockConfig
     * @param array<string, mixed> $navigationDefinition
     * @return array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}
     */
    private function resolveCmsCollection(array $blockConfig, string $lang, array $navigationDefinition): array
    {
        if (($navigationDefinition['target'] ?? 'collection_index') !== 'collection_index') {
            return $this->unresolved('unsupported_target');
        }

        $collectionId = $this->collectionId($blockConfig);
        if ($collectionId === null) {
            return $this->unresolved('collection_not_found');
        }

        $pageId = $this->indexPageIdsByCollection[$collectionId] ?? null;
        if ($pageId === null) {
            $page = (new PageModel())
                ->where('collection_id', $collectionId)
                ->where('page_type', 'collection_index')
                ->where('status', 'published')
                ->where('deleted_at IS NULL', null, false)
                ->first();

            $pageId = $this->entityId($page);
            $this->indexPageIdsByCollection[$collectionId] = $pageId;
        }

        if ($pageId <= 0) {
            return $this->unresolved('index_page_not_published', 'collection_index');
        }

        return $this->resolvePage($pageId, 'collection_index', $lang);
    }

    /**
     * @return array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}
     */
    private function resolvePageType(string $pageType, string $lang): array
    {
        $cacheKey = $pageType . ':' . $lang;
        if (isset($this->resolvedPageCache[$cacheKey])) {
            return $this->resolvedPageCache[$cacheKey];
        }

        $page = (new PageModel())
            ->where('page_type', $pageType)
            ->where('status', 'published')
            ->where('deleted_at IS NULL', null, false)
            ->first();

        if ($page === null) {
            return $this->resolvedPageCache[$cacheKey] = $this->unresolved('listing_page_not_published', $pageType);
        }

        $routeKey = match ($pageType) {
            'events' => 'events',
            'catalog_listing' => 'catalog',
            default => null,
        };

        return $this->resolvedPageCache[$cacheKey] = $this->resolvePage(
            $this->entityId($page),
            $pageType,
            $lang,
            $routeKey,
        );
    }

    /**
     * @param array<string, mixed> $navigationDefinition
     * @return array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}
     */
    private function resolveOwnerParent(
        string $ownerType,
        int $ownerId,
        string $lang,
        array $navigationDefinition,
    ): array {
        if ($ownerType !== 'page' || $ownerId <= 0 || ($navigationDefinition['target'] ?? '') !== 'parent_page') {
            return $this->unresolved('unsupported_owner_target');
        }

        $page = (new PageModel())->where('id', $ownerId)->where('deleted_at IS NULL', null, false)->first();
        if ($page === null) {
            return $this->unresolved('owner_page_not_found');
        }

        $parentId = (int) ($page->parent_id ?? 0);
        if ($parentId > 0) {
            return $this->resolvePage($parentId, 'parent_page', $lang);
        }

        return $this->resolvePageType('home', $lang);
    }

    /**
     * @return array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null}
     */
    private function resolvePage(int $pageId, string $targetType, string $lang, ?string $routeKey = null): array
    {
        $cacheKey = $targetType . ':' . $pageId . ':' . $lang . ':' . ($routeKey ?? '');
        if (isset($this->resolvedPageCache[$cacheKey])) {
            return $this->resolvedPageCache[$cacheKey];
        }

        $slug = $this->slugRouter->resolveSlug($lang, 'page', $pageId);
        if ($slug === null || trim($slug, '/') === '') {
            if ($routeKey === null) {
                return $this->resolvedPageCache[$cacheKey] = $this->unresolved('slug_not_translated', $targetType, $pageId);
            }

            return $this->resolvedPageCache[$cacheKey] = [
                'status' => 'resolved',
                'target_type' => $targetType,
                'target_id' => $pageId,
                'route_key' => $routeKey,
                'url' => null,
            ];
        }

        if ($routeKey !== null) {
            return $this->resolvedPageCache[$cacheKey] = [
                'status' => 'resolved',
                'target_type' => $targetType,
                'target_id' => $pageId,
                'route_key' => $routeKey,
                'url' => null,
            ];
        }

        $path = trim($slug, '/') === 'home'
            ? '/' . trim($lang, '/')
            : '/' . trim($lang, '/') . '/' . ltrim($slug, '/');

        return $this->resolvedPageCache[$cacheKey] = [
            'status' => 'resolved',
            'target_type' => $targetType,
            'target_id' => $pageId,
            'route_key' => null,
            'url' => $path,
        ];
    }

    /** @param array<string, mixed> $blockConfig */
    private function collectionId(array $blockConfig): ?int
    {
        $rawId = $blockConfig['collection_id'] ?? null;
        if (is_scalar($rawId) && (int) $rawId > 0) {
            return (int) $rawId;
        }

        $key = trim((string) ($blockConfig['collection_key'] ?? ''));
        if ($key === '') {
            return null;
        }

        if (isset($this->collectionIdsByKey[$key])) {
            return $this->collectionIdsByKey[$key];
        }

        $collection = (new CollectionModel())->where('collection_key', $key)->first();
        if ($collection === null || (int) ($collection->is_active ?? 0) !== 1) {
            return null;
        }

        return $this->collectionIdsByKey[$key] = $this->entityId($collection);
    }

    private function entityId(mixed $entity): int
    {
        if (is_object($entity)) {
            return (int) ($entity->id ?? 0);
        }

        return is_array($entity) ? (int) ($entity['id'] ?? 0) : 0;
    }

    /** @return array{status: string, target_type: string|null, target_id: int|null, route_key: string|null, url: string|null} */
    private function unresolved(string $status, ?string $targetType = null, ?int $targetId = null): array
    {
        return [
            'status' => $status,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'route_key' => null,
            'url' => null,
        ];
    }
}
