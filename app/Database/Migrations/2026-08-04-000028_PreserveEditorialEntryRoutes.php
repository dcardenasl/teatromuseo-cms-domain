<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Preserves old Editorial entry URLs after the canonical prefix changes. */
final class PreserveEditorialEntryRoutes extends Migration
{
    /** @var array<string, string> */
    private array $legacyPrefixes = [
        'es' => 'publicaciones',
        'en' => 'publications',
        'fr' => 'publications',
        'pt' => 'publicacoes',
    ];

    public function up(): void
    {
        $collections = $this->db->table('cms_collections')
            ->select('id')
            ->whereIn('collection_key', ['editoriales', 'editorial'])
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
        $languages = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', array_keys($this->legacyPrefixes))
            ->get()
            ->getResultArray();

        foreach ($collections as $collection) {
            foreach ($languages as $language) {
                $code = (string) ($language['code'] ?? '');
                $languageId = (int) ($language['id'] ?? 0);
                if ($languageId <= 0 || ! isset($this->legacyPrefixes[$code])) {
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
                    $this->upsertRedirect(
                        $this->legacyPrefixes[$code] . '/' . $slug,
                        'editorial/' . $slug
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Historical redirects are intentionally retained.
    }

    private function upsertRedirect(string $oldPath, string $newUrl): void
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
}
