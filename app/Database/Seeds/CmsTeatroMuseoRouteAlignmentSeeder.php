<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use App\Database\Seeds\Concerns\TeatroMuseoPublicRoutes;
use CodeIgniter\Database\Seeder;

/**
 * Repairs the localized public URL contract after bootstrap or migration.
 *
 * Page and collection seeders create the records; this narrow pass guarantees
 * that an existing database and a fresh database expose the same URLs before
 * menus, caches, or public-read snapshots are built.
 */
final class CmsTeatroMuseoRouteAlignmentSeeder extends Seeder
{
    use IdempotentSeederSupport;

    /** @var list<string> */
    private const PAGE_TYPES = [
        'contact',
        'events',
        'catalog_listing',
        'press',
        'transparency',
    ];

    /** @var array<string, list<string>> */
    private const PAGE_LOOKUP_SLUGS = [
        'about' => ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'],
        'history' => ['historia', 'history', 'histoire', 'nossa-historia'],
    ];

    /** @var list<string> */
    private const COLLECTION_KEYS = [
        'noticias',
        'companias',
        'personas',
        'obras',
        'videos',
        'festivales',
        'exposiciones',
        'teatroescuela',
        'editoriales',
        'prensa',
        'transparencia',
    ];

    public function run(): void
    {
        $languages = $this->languageIds();
        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoRouteAlignmentSeeder: missing languages; skipping.\n";

            return;
        }

        foreach (self::PAGE_TYPES as $pageType) {
            $pageId = $this->pageIdByType($pageType);
            if ($pageId !== null) {
                $this->alignPageTranslations($pageId, TeatroMuseoPublicRoutes::pageSlugs($pageType), $languages);
            }
        }

        foreach (self::PAGE_LOOKUP_SLUGS as $routeKey => $lookupSlugs) {
            $pageId = $this->pageIdBySlug($lookupSlugs);
            if ($pageId !== null) {
                $this->alignPageTranslations($pageId, TeatroMuseoPublicRoutes::pageSlugs($routeKey), $languages);
            }
        }

        foreach (self::COLLECTION_KEYS as $collectionKey) {
            $collectionId = $this->collectionIdByKey($collectionKey);
            if ($collectionId === null) {
                continue;
            }

            $slugs = TeatroMuseoPublicRoutes::collectionSlugs($collectionKey);
            $this->alignCollectionTranslations($collectionId, $slugs, $languages);

            $pageId = $this->collectionIndexPageId($collectionId);
            if ($pageId !== null) {
                $this->alignPageTranslations($pageId, $slugs, $languages);
            }
        }
    }

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', ['es', 'en', 'fr', 'pt'])
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }

    private function pageIdByType(string $pageType): ?int
    {
        $row = $this->db->table('cms_pages')
            ->select('id')
            ->where('page_type', $pageType)
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /** @param list<string> $slugs */
    private function pageIdBySlug(array $slugs): ?int
    {
        $row = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('p.deleted_at IS NULL', null, false)
            ->whereIn('pt.slug', $slugs)
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function collectionIdByKey(string $collectionKey): ?int
    {
        $row = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', $collectionKey)
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function collectionIndexPageId(int $collectionId): ?int
    {
        $row = $this->db->table('cms_pages')
            ->select('id')
            ->where('collection_id', $collectionId)
            ->where('page_type', 'collection_index')
            ->where('deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /** @param array<string, string> $slugs @param array<string, int> $languages */
    private function alignPageTranslations(int $pageId, array $slugs, array $languages): void
    {
        $this->alignTranslations('cms_page_translations', 'page_id', $pageId, $slugs, $languages);
    }

    /** @param array<string, string> $slugs @param array<string, int> $languages */
    private function alignCollectionTranslations(int $collectionId, array $slugs, array $languages): void
    {
        $this->alignTranslations('cms_collection_translations', 'collection_id', $collectionId, $slugs, $languages);
    }

    /**
     * @param array<string, string> $slugs
     * @param array<string, int> $languages
     */
    private function alignTranslations(string $table, string $ownerColumn, int $ownerId, array $slugs, array $languages): void
    {
        foreach ($slugs as $languageCode => $slug) {
            $languageId = $languages[$languageCode] ?? null;
            if ($languageId === null) {
                continue;
            }

            $conflict = $this->db->table($table)
                ->select('id')
                ->where('language_id', $languageId)
                ->where('slug', $slug)
                ->where($ownerColumn . ' !=', $ownerId)
                ->get()
                ->getRowArray();
            if (is_array($conflict)) {
                throw new \RuntimeException(sprintf(
                    'CmsTeatroMuseoRouteAlignmentSeeder: slug "%s" for %s conflicts with record %d.',
                    $slug,
                    $table,
                    (int) $conflict['id'],
                ));
            }

            $this->db->table($table)
                ->where($ownerColumn, $ownerId)
                ->where('language_id', $languageId)
                ->update(['slug' => $slug]);
        }
    }
}
