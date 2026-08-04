<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes obsolete collection index pages and repairs the Editorial index.
 *
 * Cartelera is represented by the canonical `events` page. The old
 * collection-backed pages for Cartelera/Obras must not compete with it.
 * Their records remain recoverable through soft deletion.
 */
final class NormalizeLegacyCollectionIndexPages extends Migration
{
    /** @var array<string, array{title: string, slug: string}> */
    private const EDITORIAL_TRANSLATIONS = [
        'es' => ['title' => 'Editorial', 'slug' => 'editorial'],
        'en' => ['title' => 'Editorial', 'slug' => 'editorial'],
        'fr' => ['title' => 'Éditorial', 'slug' => 'editorial'],
        'pt' => ['title' => 'Editorial', 'slug' => 'editorial'],
    ];

    public function up(): void
    {
        $this->freeObsoleteEditorialSlugs();
        $this->repairEditorialIndex();
        $this->removeLegacyIndexPages();
    }

    public function down(): void
    {
        // Legacy pages remain soft-deleted and are intentionally not restored.
    }

    private function repairEditorialIndex(): void
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
            ->whereIn('code', array_keys(self::EDITORIAL_TRANSLATIONS))
            ->get()
            ->getResultArray();

        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            $languageId = (int) ($language['id'] ?? 0);
            $translation = self::EDITORIAL_TRANSLATIONS[$code] ?? null;
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

    private function freeObsoleteEditorialSlugs(): void
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

    private function removeLegacyIndexPages(): void
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
}
