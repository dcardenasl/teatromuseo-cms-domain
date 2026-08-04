<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Seeds canonical public redirects for legacy public slugs.
 */
final class CmsTeatroMuseoRedirectSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $this->upsertRecord('cms_redirects', [
            'old_path' => 'obras',
        ], [
            'new_url' => config(\Config\Cms::class)->eventListingPath,
            'redirect_type' => 301,
            'is_active' => 1,
            'hit_count' => 0,
            'last_hit_at' => null,
            'note' => 'Legacy works slug now resolves to the event listing.',
        ]);

        foreach (['publicaciones', 'publications', 'publicacoes'] as $oldPath) {
            $this->upsertRecord('cms_redirects', [
                'old_path' => $oldPath,
            ], [
                'new_url' => 'editorial',
                'redirect_type' => 301,
                'is_active' => 1,
                'hit_count' => 0,
                'last_hit_at' => null,
                'note' => 'Editorial is the canonical public section URL.',
            ]);
        }

        $collection = $this->db->table('cms_collections')
            ->select('id')
            ->whereIn('collection_key', ['editoriales', 'editorial'])
            ->where('is_active', 1)
            ->get()
            ->getRowArray();
        if (! is_array($collection)) {
            return;
        }

        $legacyPrefixes = [
            'es' => 'publicaciones',
            'en' => 'publications',
            'fr' => 'publications',
            'pt' => 'publicacoes',
        ];
        $languages = $this->db->table('cms_languages')
            ->select('id, code')
            ->whereIn('code', array_keys($legacyPrefixes))
            ->get()
            ->getResultArray();
        foreach ($languages as $language) {
            $code = (string) ($language['code'] ?? '');
            $languageId = (int) ($language['id'] ?? 0);
            if ($languageId <= 0 || ! isset($legacyPrefixes[$code])) {
                continue;
            }

            $entries = $this->db->table('cms_entries e')
                ->select('et.slug')
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
                $this->upsertRecord('cms_redirects', [
                    'old_path' => $legacyPrefixes[$code] . '/' . $slug,
                ], [
                    'new_url' => 'editorial/' . $slug,
                    'redirect_type' => 301,
                    'is_active' => 1,
                    'hit_count' => 0,
                    'last_hit_at' => null,
                    'note' => 'Legacy Editorial entry URL.',
                ]);
            }
        }
    }
}
