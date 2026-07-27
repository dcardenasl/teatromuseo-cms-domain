<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use App\Entities\EntryEntity;
use App\Libraries\Cms\EntryRelationSynchronizer;
use CodeIgniter\Database\Seeder;
use Config\Services;

/**
 * Creates synthetic pilot entries for the TeatroMuseo collections.
 *
 * The pilot is deliberately separate from the legacy ETL and from the generic
 * starter bootstrap. It provides one minimal and one complete entry per
 * collection so the team can verify templates, translations, references,
 * semantic relations, listing pages and detail routes before importing real
 * content.
 */
final class CmsTeatroMuseoPilotSeeder extends Seeder
{
    use IdempotentSeederSupport;

    /** @var array<string, int> */
    private array $entryIds = [];

    /** @var array<string, int> */
    private array $languages = [];

    /** @var array<string, int> */
    private array $blockIds = [];

    private EntryRelationSynchronizer $relationSynchronizer;

    public function run(): void
    {
        $this->relationSynchronizer = new EntryRelationSynchronizer($this->db);
        $this->languages = $this->languageIds();
        $collectionIds = $this->collectionIds();
        $this->blockIds = $this->blockIds([
            'compania_ficha',
            'persona_ficha',
            'obra_ficha',
            'video_ficha',
            'festival_ficha',
            'exposicion_ficha',
            'curso_ficha',
            'publicacion_metadata',
            'related_entries',
            'rich_text',
            'image',
        ]);

        if (! isset($this->languages['es'])) {
            echo "CmsTeatroMuseoPilotSeeder: missing Spanish language; skipping.\n";

            return;
        }

        foreach ($this->definitions() as $definition) {
            if (! isset($collectionIds[$definition['collection_key']])) {
                echo "CmsTeatroMuseoPilotSeeder: collection '{$definition['collection_key']}' not found; skipping.\n";

                continue;
            }

            $entryId = $this->seedEntry(
                $collectionIds[$definition['collection_key']],
                $definition['collection_key'],
                $definition['variant'],
                $definition['sort_order']
            );
            $this->entryIds[$this->entryKey($definition['collection_key'], $definition['variant'])] = $entryId;
        }

        foreach ($this->definitions() as $definition) {
            $entryId = $this->entryIds[$this->entryKey($definition['collection_key'], $definition['variant'])] ?? null;
            if ($entryId === null) {
                continue;
            }

            $this->seedEntryBlocks(
                $entryId,
                $definition['collection_key'],
                $definition['variant']
            );
        }

        Services::fileReferenceSynchronizer(false)->rebuildAll();

        echo "CmsTeatroMuseoPilotSeeder: seeded " . count($this->entryIds) . " pilot entries.\n";
    }

    /**
     * @return list<array{collection_key: string, variant: string, sort_order: int}>
     */
    private function definitions(): array
    {
        $definitions = [];
        $sortOrder = 1;

        foreach (['companias', 'personas', 'obras', 'videos', 'festivales', 'exposiciones', 'cursos', 'publicaciones', 'noticias'] as $collectionKey) {
            $definitions[] = [
                'collection_key' => $collectionKey,
                'variant' => 'minimal',
                'sort_order' => $sortOrder++,
            ];
            $definitions[] = [
                'collection_key' => $collectionKey,
                'variant' => 'complete',
                'sort_order' => $sortOrder++,
            ];
        }

        return $definitions;
    }

    private function seedEntry(int $collectionId, string $collectionKey, string $variant, int $sortOrder): int
    {
        $slug = $this->entrySlug($collectionKey, $variant, 'es');
        $entryId = $this->findEntryIdBySlug($collectionId, $slug, $this->languages['es']);
        $payload = [
            'collection_id' => $collectionId,
            'author_id' => null,
            'workflow_status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
            'is_featured' => $variant === 'complete' ? 1 : 0,
            'view_count' => 0,
            'sort_order' => $sortOrder,
            'wizard_extra' => null,
            'sitemap_priority' => $variant === 'complete' ? '0.8' : '0.5',
            'sitemap_changefreq' => 'monthly',
            'is_in_sitemap' => 1,
            'deleted_at' => null,
        ];

        if ($entryId === null) {
            $entryId = $this->createRecord('cms_entries', $payload);
        } else {
            $this->db->table('cms_entries')->where('id', $entryId)->update($payload);
        }

        if ($entryId === null) {
            throw new \RuntimeException(sprintf('Unable to create pilot entry for %s/%s.', $collectionKey, $variant));
        }

        $this->resetEntryEditorialData($entryId);
        $this->seedEntryTranslations($entryId, $collectionKey, $variant);

        $entry = model(\App\Models\EntryModel::class)->find($entryId);
        if (! $entry instanceof EntryEntity) {
            throw new \RuntimeException(sprintf('Unable to reload pilot entry %d.', $entryId));
        }

        Services::entryBlockTemplateInitializer(false)->initialize($entry, null);

        return $entryId;
    }

    private function seedEntryTranslations(int $entryId, string $collectionKey, string $variant): void
    {
        foreach ($this->configuredLanguageCodes() as $language) {
            $title = $this->entryTitle($collectionKey, $variant, $language);
            $imageUrl = $variant === 'complete' ? $this->mediaUrl($collectionKey, $variant) : null;

            $this->upsertRecord('cms_entry_translations', [
                'entry_id' => $entryId,
                'language_id' => $this->languages[$language],
            ], [
                'slug' => $this->entrySlug($collectionKey, $variant, $language),
                'title' => $title,
                'excerpt' => $this->entryExcerpt($collectionKey, $variant, $language),
                'featured_file_id' => null,
                'featured_image_url' => $imageUrl,
                'meta_title' => $title . ' | TeatroMuseo',
                'meta_description' => $this->entryExcerpt($collectionKey, $variant, $language),
                'og_image_file_id' => null,
                'og_type' => 'article',
                'canonical_url' => null,
                'robots' => 'index, follow',
                'schema_data' => null,
            ]);
        }
    }

    private function seedEntryBlocks(int $entryId, string $collectionKey, string $variant): void
    {
        $instances = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_id, b.block_key')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'entry')
            ->where('i.owner_id', $entryId)
            ->orderBy('i.sort_order', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($instances as $instance) {
            $blockKey = (string) $instance['block_key'];

            foreach ($this->configuredLanguageCodes() as $language) {
                $blockData = $this->blockData($collectionKey, $variant, $blockKey, $language);

                $this->upsertRecord('cms_block_instance_translations', [
                    'instance_id' => (int) $instance['id'],
                    'language_id' => $this->languages[$language],
                ], [
                    'block_data' => json_encode($blockData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_published' => 1,
                ]);
            }

            $blockConfig = $this->blockConfig($collectionKey, $variant, $blockKey);
            if ($blockConfig !== null) {
                $this->db->table('cms_block_instances')
                    ->where('id', (int) $instance['id'])
                    ->update([
                        'block_config' => json_encode($blockConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
            }

            if ($blockKey === 'related_entries') {
                $this->relationSynchronizer->sync(
                    $entryId,
                    (int) $instance['id'],
                    'recommended',
                    $this->relatedReferences($collectionKey)
                );
            }
        }

        $this->seedOptionalRelatedBlock($entryId, $collectionKey, $variant);
    }

    private function seedOptionalRelatedBlock(int $entryId, string $collectionKey, string $variant): void
    {
        $references = $this->relatedReferences($collectionKey);
        if ($variant !== 'complete' || $references === [] || ! isset($this->blockIds['related_entries'])) {
            return;
        }

        $instanceId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $this->blockIds['related_entries'],
            'owner_type' => 'entry',
            'owner_id' => $entryId,
            'parent_instance_id' => null,
            'sort_order' => 20,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => '{}',
        ]);

        if ($instanceId === null) {
            throw new \RuntimeException(sprintf('Unable to add related pilot block to entry %d.', $entryId));
        }

        foreach ($this->configuredLanguageCodes() as $language) {
            $this->upsertRecord('cms_block_instance_translations', [
                'instance_id' => $instanceId,
                'language_id' => $this->languages[$language],
            ], [
                'block_data' => json_encode([
                    'relation_type' => 'recommended',
                    'entries' => $references,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_published' => 1,
            ]);
        }

        $this->relationSynchronizer->sync($entryId, $instanceId, 'recommended', $references);
    }

    /** @return array<string, mixed> */
    private function blockData(string $collectionKey, string $variant, string $blockKey, string $language): array
    {
        $contentLanguage = $this->contentLanguage($language);
        $title = $this->entryTitle($collectionKey, $variant, $contentLanguage);
        $isEnglish = $contentLanguage === 'en';
        $description = $isEnglish
            ? '<p>This synthetic pilot block verifies the complete editorial shape before legacy migration.</p>'
            : '<p>Este bloque piloto sintético verifica la estructura editorial completa antes de migrar el legacy.</p>';

        if ($collectionKey === 'noticias') {
            return match ($blockKey) {
                'rich_text' => ['content' => $description . ($isEnglish ? '<p>It is intentionally replaceable test content.</p>' : '<p>Es contenido de prueba reemplazable.</p>')],
                'image' => ['alt' => $title, 'caption' => $isEnglish ? 'Pilot cover image' : 'Imagen de portada piloto'],
                default => [],
            };
        }

        if ($blockKey === 'related_entries') {
            return [
                'relation_type' => 'recommended',
                'entries' => $this->relatedReferences($collectionKey),
            ];
        }

        return match ($collectionKey) {
            'companias' => [
                'name' => $title,
                'summary' => $isEnglish ? 'Synthetic company profile for template validation.' : 'Ficha sintética de compañía para validar el template.',
                'description' => $description,
                'website' => 'https://example.test/companias/' . $variant,
            ],
            'personas' => [
                'name' => $title,
                'role' => $isEnglish ? 'Artist and educator' : 'Artista y docente',
                'bio' => $description,
                'website' => 'https://example.test/personas/' . $variant,
            ],
            'obras' => [
                'subtitle' => $isEnglish ? 'A synthetic work record' : 'Registro sintético de obra',
                'synopsis' => $description,
                'duration' => '90 min',
                'premiere_date' => '2026-01-15',
                'company' => $variant === 'complete' ? $this->reference('companias', 'complete') : null,
                'people' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'personas', 'variant' => 'complete'],
                    ['collection_key' => 'personas', 'variant' => 'minimal'],
                ]) : [],
            ],
            'videos' => [
                'provider' => 'youtube',
                'video_id' => 'pilot-' . $variant,
                'video_url' => 'https://www.youtube.com/watch?v=pilot-' . $variant,
                'duration' => '08:30',
                'credit' => $isEnglish ? 'TeatroMuseo pilot archive' : 'Archivo piloto TeatroMuseo',
                'related_works' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'obras', 'variant' => 'complete'],
                ]) : [],
            ],
            'festivales' => [
                'edition' => '2026',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-10',
                'venue' => $isEnglish ? 'TeatroMuseo venues' : 'Espacios TeatroMuseo',
                'status' => 'upcoming',
                'works' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'obras', 'variant' => 'complete'],
                    ['collection_key' => 'obras', 'variant' => 'minimal'],
                ]) : [],
                'videos' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'videos', 'variant' => 'complete'],
                ]) : [],
            ],
            'exposiciones' => [
                'author' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'personas', 'variant' => 'complete'],
                ]) : [],
                'curator' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'personas', 'variant' => 'minimal'],
                ]) : [],
                'opening_date' => '2026-05-01',
                'closing_date' => '2026-11-30',
                'venue' => $isEnglish ? 'Museum gallery' : 'Galería del museo',
                'description' => $description,
            ],
            'cursos' => [
                'modality' => 'hibrido',
                'start_date' => '2026-04-05',
                'end_date' => '2026-05-05',
                'schedule' => $isEnglish ? 'Tuesdays, 18:00' : 'Martes, 18:00',
                'venue' => $isEnglish ? 'Classroom and online' : 'Sala y online',
                'capacity' => 20,
                'price' => 35000,
                'instructors' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'personas', 'variant' => 'complete'],
                ]) : [],
                'registration_url' => 'https://example.test/cursos/' . $variant . '/inscripcion',
            ],
            'publicaciones' => [
                'publication_type' => 'editorial',
                'authors' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'personas', 'variant' => 'complete'],
                ]) : [],
                'publication_date' => '2026-03-20',
                'publisher' => 'TeatroMuseo',
                'document_link' => 'https://example.test/publicaciones/' . $variant . '.pdf',
            ],
            default => [],
        };
    }

    /** @return array<string, mixed>|null */
    private function blockConfig(string $collectionKey, string $variant, string $blockKey): ?array
    {
        $configKey = match ($blockKey) {
            'image' => 'image',
            'compania_ficha' => 'logo',
            'persona_ficha' => 'portrait',
            'obra_ficha' => 'featured_media',
            'video_ficha' => 'thumbnail',
            'festival_ficha', 'exposicion_ficha', 'curso_ficha', 'publicacion_metadata' => 'cover',
            default => null,
        };

        if ($configKey === null) {
            return null;
        }

        return [
            $configKey => $this->mediaReference($this->mediaUrl($collectionKey, $variant)),
        ];
    }

    /** @return list<array{entry_id: int, collection_key: string}> */
    private function relatedReferences(string $collectionKey): array
    {
        $targets = match ($collectionKey) {
            'personas' => [['collection_key' => 'companias', 'variant' => 'complete']],
            'obras' => [
                ['collection_key' => 'companias', 'variant' => 'complete'],
                ['collection_key' => 'personas', 'variant' => 'complete'],
            ],
            'videos' => [['collection_key' => 'obras', 'variant' => 'complete']],
            'festivales' => [
                ['collection_key' => 'obras', 'variant' => 'complete'],
                ['collection_key' => 'videos', 'variant' => 'complete'],
            ],
            'exposiciones', 'cursos', 'publicaciones' => [['collection_key' => 'personas', 'variant' => 'complete']],
            default => [],
        };

        return $this->references($targets);
    }

    /**
     * @param list<array{collection_key: string, variant: string}> $targets
     * @return list<array{entry_id: int, collection_key: string}>
     */
    private function references(array $targets): array
    {
        $references = [];
        foreach ($targets as $target) {
            $reference = $this->reference($target['collection_key'], $target['variant']);
            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /** @return array{entry_id: int, collection_key: string}|null */
    private function reference(string $collectionKey, string $variant): ?array
    {
        $entryId = $this->entryIds[$this->entryKey($collectionKey, $variant)] ?? null;
        if ($entryId === null) {
            return null;
        }

        return ['entry_id' => $entryId, 'collection_key' => $collectionKey];
    }

    private function resetEntryEditorialData(int $entryId): void
    {
        $this->db->table('cms_entry_related')
            ->groupStart()
            ->where('entry_id', $entryId)
            ->orWhere('related_entry_id', $entryId)
            ->groupEnd()
            ->delete();

        $instanceRows = $this->db->table('cms_block_instances')
            ->select('id')
            ->where('owner_type', 'entry')
            ->where('owner_id', $entryId)
            ->get()
            ->getResultArray();
        $instanceIds = array_map(static fn (array $row): int => (int) $row['id'], $instanceRows);

        if ($instanceIds !== []) {
            $this->db->table('cms_block_instance_translations')->whereIn('instance_id', $instanceIds)->delete();
            $this->db->table('cms_file_references')
                ->where('resource_type', 'block_instance')
                ->whereIn('resource_id', $instanceIds)
                ->delete();
            $this->db->table('cms_block_instances')->whereIn('id', $instanceIds)->delete();
        }

        $this->db->table('cms_file_references')
            ->where('resource_type', 'entry')
            ->where('resource_id', $entryId)
            ->delete();
        $this->db->table('cms_entry_versions')->where('entry_id', $entryId)->delete();
    }

    /** @return array<string, int> */
    private function languageIds(): array
    {
        $rows = $this->db->table('cms_languages')
            ->select('code, id')
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }

    /** @return list<string> */
    private function configuredLanguageCodes(): array
    {
        return array_keys($this->languages);
    }

    /**
     * The pilot has only Spanish and English fixture text. Other configured
     * locales still receive rows so the locale matrix is exercised, while
     * Spanish remains the explicit source/fallback instead of inventing copy.
     */
    private function contentLanguage(string $language): string
    {
        return $language === 'en' ? 'en' : 'es';
    }

    /** @return array<string, int> */
    private function collectionIds(): array
    {
        $rows = $this->db->table('cms_collections')->select('id, collection_key')->get()->getResultArray();
        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['collection_key']] = (int) $row['id'];
        }

        return $ids;
    }

    /** @param list<string> $keys @return array<string, int> */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->whereIn('block_key', $keys)->get()->getResultArray();
        $ids = [];
        foreach ($rows as $row) {
            $ids[(string) $row['block_key']] = (int) $row['id'];
        }

        return $ids;
    }

    private function findEntryIdBySlug(int $collectionId, string $slug, int $languageId): ?int
    {
        $row = $this->db->table('cms_entries e')
            ->select('e.id')
            ->join('cms_entry_translations et', 'et.entry_id = e.id')
            ->where('e.collection_id', $collectionId)
            ->where('et.language_id', $languageId)
            ->where('et.slug', $slug)
            ->where('e.deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    private function entryKey(string $collectionKey, string $variant): string
    {
        return $collectionKey . ':' . $variant;
    }

    private function entrySlug(string $collectionKey, string $variant, string $language): string
    {
        $language = $this->contentLanguage($language);
        $englishKey = $collectionKey === 'noticias' ? 'news' : match ($collectionKey) {
            'companias' => 'companies',
            'personas' => 'people',
            'obras' => 'works',
            'festivales' => 'festivals',
            'exposiciones' => 'exhibitions',
            'cursos' => 'courses',
            'publicaciones' => 'publications',
            default => $collectionKey,
        };
        $suffix = $variant === 'minimal' ? 'pilot-minimal' : 'pilot-complete';

        return ($language === 'es' ? $collectionKey : $englishKey) . '-' . $suffix;
    }

    private function entryTitle(string $collectionKey, string $variant, string $language): string
    {
        $language = $this->contentLanguage($language);
        $names = [
            'companias' => ['es' => 'Compañía piloto', 'en' => 'Pilot company'],
            'personas' => ['es' => 'Persona piloto', 'en' => 'Pilot person'],
            'obras' => ['es' => 'Obra piloto', 'en' => 'Pilot work'],
            'videos' => ['es' => 'Video piloto', 'en' => 'Pilot video'],
            'festivales' => ['es' => 'Festival piloto', 'en' => 'Pilot festival'],
            'exposiciones' => ['es' => 'Exposición piloto', 'en' => 'Pilot exhibition'],
            'cursos' => ['es' => 'Curso piloto', 'en' => 'Pilot course'],
            'publicaciones' => ['es' => 'Publicación piloto', 'en' => 'Pilot publication'],
            'noticias' => ['es' => 'Noticia piloto', 'en' => 'Pilot news'],
        ];
        $base = $names[$collectionKey][$language] ?? ucfirst($collectionKey);

        return $base . ($variant === 'minimal'
            ? ($language === 'es' ? ' mínima' : ' minimal')
            : ($language === 'es' ? ' completa' : ' complete'));
    }

    private function entryExcerpt(string $collectionKey, string $variant, string $language): string
    {
        $language = $this->contentLanguage($language);
        return $language === 'es'
            ? sprintf('Entrada piloto %s de %s para validar la estructura del CMS.', $variant === 'minimal' ? 'mínima' : 'completa', $collectionKey)
            : sprintf('%s pilot %s entry for validating the CMS structure.', ucfirst($collectionKey), $variant);
    }

    private function mediaUrl(string $collectionKey, string $variant): string
    {
        return sprintf('https://picsum.photos/seed/teatromuseo-%s-%s/1200/800', $collectionKey, $variant);
    }
}
