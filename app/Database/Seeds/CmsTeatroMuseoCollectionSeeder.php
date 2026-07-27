<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\CollectionBlockPresets;
use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use App\Database\Seeds\Concerns\TeatroMuseoCollectionPresets;
use CodeIgniter\Database\Seeder;

/**
 * Creates the nine TeatroMuseo editorial collections without importing any
 * legacy rows. It is safe to run before or after the starter content seeders.
 */
final class CmsTeatroMuseoCollectionSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $langIds = $this->langIds(['es', 'en']);
        if (! isset($langIds['es'])) {
            echo "CmsTeatroMuseoCollectionSeeder: 'es' language not found; skipping.\n";
            return;
        }

        $presets = CollectionBlockPresets::all() + TeatroMuseoCollectionPresets::all();
        foreach ($this->definitions() as $definition) {
            $type = $definition['collection_type'];
            $preset = $presets[$type] ?? null;
            if (! is_array($preset)) {
                continue;
            }

            $collectionId = $this->upsertRecord('cms_collections', [
                'collection_key' => $definition['collection_key'],
            ], [
                'collection_type' => $type,
                'is_active' => 1,
                'requires_approval' => $definition['requires_approval'],
                'enables_categories' => 1,
                'enables_tags' => 1,
                'default_sitemap_priority' => $definition['sitemap_priority'],
                'default_changefreq' => $definition['changefreq'],
                'sort_order' => $definition['sort_order'],
                'block_template' => json_encode($preset['block_template'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'wizard_config' => json_encode($preset['wizard_config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($collectionId === null) {
                continue;
            }

            foreach ($definition['translations'] as $language => $translation) {
                $languageId = $langIds[$language] ?? null;
                if ($languageId === null) {
                    continue;
                }
                $this->upsertRecord('cms_collection_translations', [
                    'collection_id' => $collectionId,
                    'language_id' => $languageId,
                ], $translation);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            $this->definition('news', 'noticias', 'Noticias', 'News', 'Noticias y actualidad del museo.', 10, '0.7', 'weekly', false),
            $this->definition('companias', 'companias', 'Compañías', 'Companies', 'Compañías y colectivos artísticos.', 20, '0.6', 'monthly', false),
            $this->definition('personas', 'personas', 'Personas', 'People', 'Artistas, docentes, curadores y colaboradores.', 30, '0.6', 'monthly', false),
            $this->definition('obras', 'obras', 'Obras', 'Works', 'Obras y piezas del catálogo artístico.', 40, '0.8', 'monthly', false),
            $this->definition('videos', 'videos', 'Videos', 'Videos', 'Videos audiovisuales y registros.', 50, '0.6', 'monthly', false),
            $this->definition('festivales', 'festivales', 'Festivales', 'Festivals', 'Festivales y sus ediciones o programas.', 60, '0.8', 'monthly', false),
            $this->definition('exposiciones', 'exposiciones', 'Exposiciones', 'Exhibitions', 'Exposiciones vigentes e históricas.', 70, '0.8', 'monthly', false),
            $this->definition('cursos', 'cursos', 'Cursos', 'Courses', 'Cursos, talleres y actividades formativas.', 80, '0.7', 'weekly', false),
            $this->definition('publicaciones', 'publicaciones', 'Publicaciones', 'Publications', 'Publicaciones, prensa y documentos institucionales.', 90, '0.6', 'monthly', false),
        ];
    }

    /** @return array<string, mixed> */
    private function definition(
        string $type,
        string $key,
        string $esName,
        string $enName,
        string $description,
        int $sortOrder,
        string $priority,
        string $changefreq,
        bool $requiresApproval
    ): array {
        $slugEn = match ($key) {
            'noticias' => 'news',
            'companias' => 'companies',
            'personas' => 'people',
            'obras' => 'works',
            'videos' => 'videos',
            'festivales' => 'festivals',
            'exposiciones' => 'exhibitions',
            'cursos' => 'courses',
            'publicaciones' => 'publications',
            default => $key,
        };

        return [
            'collection_type' => $type,
            'collection_key' => $key,
            'requires_approval' => $requiresApproval ? 1 : 0,
            'sitemap_priority' => $priority,
            'changefreq' => $changefreq,
            'sort_order' => $sortOrder,
            'translations' => [
                'es' => [
                    'slug' => $key,
                    'name' => $esName,
                    'description' => $description,
                    'listing_title' => $esName,
                    'listing_intro' => 'Explora el catálogo de ' . strtolower($esName) . '.',
                    'default_meta_title' => $esName . ' | TeatroMuseo',
                    'default_meta_description' => $description,
                ],
                'en' => [
                    'slug' => $slugEn,
                    'name' => $enName,
                    'description' => $description,
                    'listing_title' => $enName,
                    'listing_intro' => 'Explore the ' . strtolower($enName) . ' catalog.',
                    'default_meta_title' => $enName . ' | TeatroMuseo',
                    'default_meta_description' => $description,
                ],
            ],
        ];
    }

    /** @param list<string> $codes @return array<string, int> */
    private function langIds(array $codes): array
    {
        $rows = $this->db->table('cms_languages')->whereIn('code', $codes)->get()->getResultArray();
        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }
}
