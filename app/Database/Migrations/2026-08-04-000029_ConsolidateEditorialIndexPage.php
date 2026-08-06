<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes Editorial use the same collection index for every public entry point.
 *
 * A collection-backed section must not have one page for the menu and another
 * page for entry details. The collection index is the single navigational
 * owner; its localized slug is resolved dynamically by the CMS.
 *
 * @cms-content-data-migration
 */
final class ConsolidateEditorialIndexPage extends Migration
{
    public function up(): void
    {
        $collectionId = $this->normalizeCollectionKey();
        if ($collectionId === null) {
            return;
        }

        // A previous removal may have soft-deleted the collection index. It
        // is still the canonical owner and must be restored, not duplicated.
        $indexPage = $this->findPage('collection_index', $collectionId, true);
        $listingPage = $this->findPage('publications');

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
            $this->normalizeMenuItems($collectionId, $indexPage['id'] ?? null, $listingPage['id'] ?? null);
            return;
        }

        // Keep the old page and its editorial blocks recoverable, but remove it
        // from public resolution so it cannot compete with the collection index.
        $this->db->table('cms_pages')->where('id', (int) $listingPage['id'])->update([
            'status' => 'draft',
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->normalizeMenuItems($collectionId, (int) $indexPage['id'], (int) $listingPage['id']);
    }

    public function down(): void
    {
        // The collection index remains the canonical public owner.
    }

    private function normalizeCollectionKey(): ?int
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

    private function normalizeMenuItems(int $collectionId, ?int $indexPageId, ?int $legacyPageId): void
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
    private function findPage(string $pageType, ?int $collectionId = null, bool $includeDeleted = false): ?array
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
}
