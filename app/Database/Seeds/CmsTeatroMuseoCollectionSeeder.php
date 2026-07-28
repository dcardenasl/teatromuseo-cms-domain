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
        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
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
            $this->definition('news', 'noticias', 'Noticias', 'News', 'Actualités', 'Notícias', 'Noticias y actualidad del museo.', 'Museum news and current affairs.', 'Actualités et vie culturelle du musée.', 'Notícias e atualidades do museu.', 10, '0.7', 'weekly', false),
            $this->definition('companias', 'companias', 'Compañías', 'Companies', 'Compagnies', 'Companhias', 'Compañías y colectivos artísticos.', 'Companies and artistic collectives.', 'Compagnies et collectifs artistiques.', 'Companhias e coletivos artísticos.', 20, '0.6', 'monthly', false),
            $this->definition('personas', 'personas', 'Personas', 'People', 'Personnes', 'Pessoas', 'Artistas, docentes, curadores y colaboradores.', 'Artists, teachers, curators, and collaborators.', 'Artistes, enseignants, commissaires et collaborateurs.', 'Artistas, docentes, curadores e colaboradores.', 30, '0.6', 'monthly', false),
            $this->definition('obras', 'obras', 'Obras', 'Works', 'Oeuvres', 'Obras', 'Obras y piezas del catálogo artístico.', 'Works and pieces from the artistic catalog.', 'Œuvres et pièces du catalogue artistique.', 'Obras e peças do catálogo artístico.', 40, '0.8', 'monthly', false),
            $this->definition('videos', 'videos', 'Videos', 'Videos', 'Vidéos', 'Vídeos', 'Videos audiovisuales y registros.', 'Audiovisual videos and recordings.', 'Vidéos audiovisuelles et enregistrements.', 'Vídeos audiovisuais e registros.', 50, '0.6', 'monthly', false),
            $this->definition('festivales', 'festivales', 'Festivales', 'Festivals', 'Festivals', 'Festivais', 'Festivales y sus ediciones o programas.', 'Festivals and their editions or programs.', 'Festivals et leurs éditions ou programmes.', 'Festivais e suas edições ou programações.', 60, '0.8', 'monthly', false),
            $this->definition('exposiciones', 'exposiciones', 'Exposiciones', 'Exhibitions', 'Expositions', 'Exposições', 'Exposiciones vigentes e históricas.', 'Current and historical exhibitions.', 'Expositions actuelles et historiques.', 'Exposições atuais e históricas.', 70, '0.8', 'monthly', false),
            $this->definition('cursos', 'cursos', 'Cursos', 'Courses', 'Cours', 'Cursos', 'Cursos, talleres y actividades formativas.', 'Courses, workshops, and learning activities.', 'Cours, ateliers et activités de formation.', 'Cursos, oficinas e atividades formativas.', 80, '0.7', 'weekly', false),
            $this->definition('publicaciones', 'publicaciones', 'Publicaciones', 'Publications', 'Publications', 'Publicações', 'Publicaciones, prensa y documentos institucionales.', 'Publications, press, and institutional documents.', 'Publications, presse et documents institutionnels.', 'Publicações, imprensa e documentos institucionais.', 90, '0.6', 'monthly', false),
        ];
    }

    /** @return array<string, mixed> */
    private function definition(
        string $type,
        string $key,
        string $esName,
        string $enName,
        string $frName,
        string $ptName,
        string $description,
        string $enDescription,
        string $frDescription,
        string $ptDescription,
        int $sortOrder,
        string $priority,
        string $changefreq,
        bool $requiresApproval
    ): array {
        $slugs = $this->slugs($key);

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
                    'slug' => $slugs['en'],
                    'name' => $enName,
                    'description' => $enDescription,
                    'listing_title' => $enName,
                    'listing_intro' => 'Explore the ' . strtolower($enName) . ' catalog.',
                    'default_meta_title' => $enName . ' | TeatroMuseo',
                    'default_meta_description' => $enDescription,
                ],
                'fr' => [
                    'slug' => $slugs['fr'],
                    'name' => $frName,
                    'description' => $frDescription,
                    'listing_title' => $frName,
                    'listing_intro' => 'Explorez le catalogue ' . strtolower($frName) . '.',
                    'default_meta_title' => $frName . ' | TeatroMuseo',
                    'default_meta_description' => $frDescription,
                ],
                'pt' => [
                    'slug' => $slugs['pt'],
                    'name' => $ptName,
                    'description' => $ptDescription,
                    'listing_title' => $ptName,
                    'listing_intro' => 'Explore o catálogo de ' . strtolower($ptName) . '.',
                    'default_meta_title' => $ptName . ' | TeatroMuseo',
                    'default_meta_description' => $ptDescription,
                ],
            ],
        ];
    }

    /** @return array{en: string, fr: string, pt: string} */
    private function slugs(string $key): array
    {
        return match ($key) {
            'noticias' => ['en' => 'news', 'fr' => 'actualites', 'pt' => 'noticias'],
            'companias' => ['en' => 'companies', 'fr' => 'compagnies', 'pt' => 'companhias'],
            'personas' => ['en' => 'people', 'fr' => 'personnes', 'pt' => 'pessoas'],
            'obras' => ['en' => 'works', 'fr' => 'oeuvres', 'pt' => 'obras'],
            'videos' => ['en' => 'videos', 'fr' => 'videos', 'pt' => 'videos'],
            'festivales' => ['en' => 'festivals', 'fr' => 'festivals', 'pt' => 'festivais'],
            'exposiciones' => ['en' => 'exhibitions', 'fr' => 'expositions', 'pt' => 'exposicoes'],
            'cursos' => ['en' => 'courses', 'fr' => 'cours', 'pt' => 'cursos'],
            'publicaciones' => ['en' => 'publications', 'fr' => 'publications', 'pt' => 'publicacoes'],
            default => ['en' => $key, 'fr' => $key, 'pt' => $key],
        };
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
