<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Separates the editorial, press and transparency domains without deleting
 * the original collection, entries, categories or file references.
 *
 * @cms-content-data-migration
 */
final class SplitPublicationCollections extends Migration
{
    public function down(): void
    {
        // This is a data-classification migration. The original collection
        // remains available as an inactive historical record, so rollback is
        // intentionally non-destructive and does not delete or merge data.
    }

    public function up(): void
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
        foreach ($this->definitions() as $definition) {
            $collectionIds[$definition['key']] = $this->ensureCollection($definition, $old);
        }

        $oldId = (int) $old['id'];
        $categoryMap = $this->categoryMap($oldId);

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

    /** @param array<string, mixed> $definition @param array<string, mixed> $old */
    private function ensureCollection(array $definition, array $old): int
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
            $translation = $this->translation($definition['key'], $code);
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
    private function categoryMap(int $oldCollectionId): array
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
    private function definitions(): array
    {
        return [
            ['key' => 'editoriales', 'type' => 'editoriales', 'priority' => '0.6', 'changefreq' => 'monthly', 'sort_order' => 90],
            ['key' => 'prensa', 'type' => 'prensa', 'priority' => '0.6', 'changefreq' => 'monthly', 'sort_order' => 91],
            ['key' => 'transparencia', 'type' => 'transparencia', 'priority' => '0.6', 'changefreq' => 'yearly', 'sort_order' => 92],
        ];
    }

    /** @return array<string, string>|null */
    private function translation(string $key, string $language): ?array
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
}
