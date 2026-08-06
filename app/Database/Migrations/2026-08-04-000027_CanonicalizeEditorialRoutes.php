<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives Editorial one canonical public URL and preserves legacy URLs.
 *
 * The public listing page and collection index are the same navigational
 * concept. Keeping their localized slugs aligned prevents entry details from
 * generating a breadcrumb URL different from the menu URL.
 *
 * @cms-content-data-migration
 */
final class CanonicalizeEditorialRoutes extends Migration
{
    /** @var list<string> */
    private array $legacyPaths = ['publicaciones', 'publications', 'publicacoes'];

    public function up(): void
    {
        $this->createLegacyRedirects();
        $this->renameListingPage();
        $this->renameCollectionIndexPages();
        $this->renameActiveEditorialCollectionSlugs();
    }

    public function down(): void
    {
        // Canonical public URLs are intentionally not reverted.
    }

    private function createLegacyRedirects(): void
    {
        foreach ($this->legacyPaths as $oldPath) {
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

    private function renameListingPage(): void
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

    private function renameCollectionIndexPages(): void
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

    private function renameActiveEditorialCollectionSlugs(): void
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
}
