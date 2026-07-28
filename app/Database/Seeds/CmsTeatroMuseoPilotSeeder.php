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

        if (! isset($this->languages['es'], $this->languages['en'], $this->languages['fr'], $this->languages['pt'])) {
            echo "CmsTeatroMuseoPilotSeeder: missing one or more required languages; skipping.\n";

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
        $description = $this->localeText($contentLanguage, [
            'es' => '<p>Este bloque piloto sintético verifica la estructura editorial completa antes de migrar el contenido real.</p>',
            'en' => '<p>This synthetic pilot block validates the full editorial structure before migrating real content.</p>',
            'fr' => '<p>Ce bloc pilote synthétique valide la structure éditoriale complète avant la migration du contenu réel.</p>',
            'pt' => '<p>Este bloco piloto sintético valida a estrutura editorial completa antes de migrar o conteúdo real.</p>',
        ]);

        if ($collectionKey === 'noticias') {
            return match ($blockKey) {
                'rich_text' => ['content' => $description . $this->localeText($contentLanguage, [
                    'es' => '<p>Es contenido de prueba reemplazable.</p>',
                    'en' => '<p>It is intentionally replaceable test content.</p>',
                    'fr' => '<p>Il s’agit volontairement d’un contenu de test remplaçable.</p>',
                    'pt' => '<p>É um conteúdo de teste substituível de forma intencional.</p>',
                ])],
                'image' => ['alt' => $title, 'caption' => $this->localeText($contentLanguage, [
                    'es' => 'Imagen de portada piloto',
                    'en' => 'Pilot cover image',
                    'fr' => 'Image de couverture pilote',
                    'pt' => 'Imagem de capa piloto',
                ])],
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
                'summary' => $this->localeText($contentLanguage, [
                    'es' => 'Ficha sintética de compañía para validar el template.',
                    'en' => 'Synthetic company profile for template validation.',
                    'fr' => 'Fiche synthétique de compagnie pour valider le modèle.',
                    'pt' => 'Ficha sintética de companhia para validar o template.',
                ]),
                'description' => $description,
                'website' => 'https://example.test/companias/' . $variant,
            ],
            'personas' => [
                'name' => $title,
                'role' => $this->localeText($contentLanguage, [
                    'es' => 'Artista y docente',
                    'en' => 'Artist and educator',
                    'fr' => 'Artiste et enseignant',
                    'pt' => 'Artista e docente',
                ]),
                'bio' => $description,
                'website' => 'https://example.test/personas/' . $variant,
            ],
            'obras' => [
                'subtitle' => $this->localeText($contentLanguage, [
                    'es' => 'Registro sintético de obra',
                    'en' => 'A synthetic work record',
                    'fr' => 'Fiche synthétique d’œuvre',
                    'pt' => 'Registro sintético de obra',
                ]),
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
                'credit' => $this->localeText($contentLanguage, [
                    'es' => 'Archivo piloto TeatroMuseo',
                    'en' => 'TeatroMuseo pilot archive',
                    'fr' => 'Archives pilotes TeatroMuseo',
                    'pt' => 'Arquivo piloto TeatroMuseo',
                ]),
                'related_works' => $variant === 'complete' ? $this->references([
                    ['collection_key' => 'obras', 'variant' => 'complete'],
                ]) : [],
            ],
            'festivales' => [
                'edition' => '2026',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-10',
                'venue' => $this->localeText($contentLanguage, [
                    'es' => 'Espacios TeatroMuseo',
                    'en' => 'TeatroMuseo venues',
                    'fr' => 'Espaces TeatroMuseo',
                    'pt' => 'Espaços TeatroMuseo',
                ]),
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
                'venue' => $this->localeText($contentLanguage, [
                    'es' => 'Galería del museo',
                    'en' => 'Museum gallery',
                    'fr' => 'Galerie du musée',
                    'pt' => 'Galeria do museu',
                ]),
                'description' => $description,
            ],
            'cursos' => [
                'modality' => 'hibrido',
                'start_date' => '2026-04-05',
                'end_date' => '2026-05-05',
                'schedule' => $this->localeText($contentLanguage, [
                    'es' => 'Martes, 18:00',
                    'en' => 'Tuesdays, 18:00',
                    'fr' => 'Mardis, 18h00',
                    'pt' => 'Terças-feiras, 18h00',
                ]),
                'venue' => $this->localeText($contentLanguage, [
                    'es' => 'Sala y online',
                    'en' => 'Classroom and online',
                    'fr' => 'En salle et en ligne',
                    'pt' => 'Sala e online',
                ]),
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
     * The pilot ships with explicit copy for every configured locale so the
     * content matrix can be validated end to end without fallback text.
     */
    private function contentLanguage(string $language): string
    {
        return in_array($language, ['es', 'en', 'fr', 'pt'], true) ? $language : 'es';
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
        $baseSlug = $this->collectionSlug($collectionKey, $language);
        $suffix = $variant === 'minimal' ? 'pilot-minimal' : 'pilot-complete';

        return $baseSlug . '-' . $suffix;
    }

    private function entryTitle(string $collectionKey, string $variant, string $language): string
    {
        $language = $this->contentLanguage($language);
        $names = [
            'companias' => ['es' => 'Compañía piloto', 'en' => 'Pilot company', 'fr' => 'Compagnie pilote', 'pt' => 'Companhia piloto'],
            'personas' => ['es' => 'Persona piloto', 'en' => 'Pilot person', 'fr' => 'Personne pilote', 'pt' => 'Pessoa piloto'],
            'obras' => ['es' => 'Obra piloto', 'en' => 'Pilot work', 'fr' => 'Œuvre pilote', 'pt' => 'Obra piloto'],
            'videos' => ['es' => 'Video piloto', 'en' => 'Pilot video', 'fr' => 'Vidéo pilote', 'pt' => 'Vídeo piloto'],
            'festivales' => ['es' => 'Festival piloto', 'en' => 'Pilot festival', 'fr' => 'Festival pilote', 'pt' => 'Festival piloto'],
            'exposiciones' => ['es' => 'Exposición piloto', 'en' => 'Pilot exhibition', 'fr' => 'Exposition pilote', 'pt' => 'Exposição piloto'],
            'cursos' => ['es' => 'Curso piloto', 'en' => 'Pilot course', 'fr' => 'Cours pilote', 'pt' => 'Curso piloto'],
            'publicaciones' => ['es' => 'Publicación piloto', 'en' => 'Pilot publication', 'fr' => 'Publication pilote', 'pt' => 'Publicação piloto'],
            'noticias' => ['es' => 'Noticia piloto', 'en' => 'Pilot news', 'fr' => 'Actualité pilote', 'pt' => 'Notícia piloto'],
        ];
        $base = $names[$collectionKey][$language] ?? ucfirst($collectionKey);

        return $base . ($variant === 'minimal'
            ? $this->localeText($language, ['es' => ' mínima', 'en' => ' minimal', 'fr' => ' minimale', 'pt' => ' mínima'])
            : $this->localeText($language, ['es' => ' completa', 'en' => ' complete', 'fr' => ' complète', 'pt' => ' completa']));
    }

    private function entryExcerpt(string $collectionKey, string $variant, string $language): string
    {
        $language = $this->contentLanguage($language);
        $collectionLabel = $this->collectionLabel($collectionKey, $language);

        return match ($language) {
            'en' => sprintf('%s pilot %s entry for validating the CMS structure.', $collectionLabel, $variant),
            'fr' => sprintf('Entrée pilote %s de %s pour valider la structure du CMS.', $variant === 'minimal' ? 'minimale' : 'complète', $collectionLabel),
            'pt' => sprintf('Entrada piloto %s de %s para validar a estrutura do CMS.', $variant === 'minimal' ? 'mínima' : 'completa', $collectionLabel),
            default => sprintf('Entrada piloto %s de %s para validar la estructura del CMS.', $variant === 'minimal' ? 'mínima' : 'completa', $collectionLabel),
        };
    }

    private function mediaUrl(string $collectionKey, string $variant): string
    {
        return sprintf('https://picsum.photos/seed/teatromuseo-%s-%s/1200/800', $collectionKey, $variant);
    }

    /**
     * @param array{es: string, en: string, fr: string, pt: string} $texts
     */
    private function localeText(string $language, array $texts): string
    {
        $language = $this->contentLanguage($language);

        return $texts[$language] ?? $texts['es'];
    }

    private function collectionSlug(string $collectionKey, string $language): string
    {
        $slugs = [
            'companias' => ['es' => 'companias', 'en' => 'companies', 'fr' => 'compagnies', 'pt' => 'companhias'],
            'personas' => ['es' => 'personas', 'en' => 'people', 'fr' => 'personnes', 'pt' => 'pessoas'],
            'obras' => ['es' => 'obras', 'en' => 'works', 'fr' => 'oeuvres', 'pt' => 'obras'],
            'videos' => ['es' => 'videos', 'en' => 'videos', 'fr' => 'videos', 'pt' => 'videos'],
            'festivales' => ['es' => 'festivales', 'en' => 'festivals', 'fr' => 'festivals', 'pt' => 'festivais'],
            'exposiciones' => ['es' => 'exposiciones', 'en' => 'exhibitions', 'fr' => 'expositions', 'pt' => 'exposicoes'],
            'cursos' => ['es' => 'cursos', 'en' => 'courses', 'fr' => 'cours', 'pt' => 'cursos'],
            'publicaciones' => ['es' => 'publicaciones', 'en' => 'publications', 'fr' => 'publications', 'pt' => 'publicacoes'],
            'noticias' => ['es' => 'noticias', 'en' => 'news', 'fr' => 'actualites', 'pt' => 'noticias'],
        ];

        $language = $this->contentLanguage($language);

        return $slugs[$collectionKey][$language] ?? $collectionKey;
    }

    private function collectionLabel(string $collectionKey, string $language): string
    {
        $labels = [
            'companias' => ['es' => 'compañías', 'en' => 'companies', 'fr' => 'compagnies', 'pt' => 'companhias'],
            'personas' => ['es' => 'personas', 'en' => 'people', 'fr' => 'personnes', 'pt' => 'pessoas'],
            'obras' => ['es' => 'obras', 'en' => 'works', 'fr' => 'œuvres', 'pt' => 'obras'],
            'videos' => ['es' => 'videos', 'en' => 'videos', 'fr' => 'vidéos', 'pt' => 'vídeos'],
            'festivales' => ['es' => 'festivales', 'en' => 'festivals', 'fr' => 'festivals', 'pt' => 'festivais'],
            'exposiciones' => ['es' => 'exposiciones', 'en' => 'exhibitions', 'fr' => 'expositions', 'pt' => 'exposições'],
            'cursos' => ['es' => 'cursos', 'en' => 'courses', 'fr' => 'cours', 'pt' => 'cursos'],
            'publicaciones' => ['es' => 'publicaciones', 'en' => 'publications', 'fr' => 'publications', 'pt' => 'publicações'],
            'noticias' => ['es' => 'noticias', 'en' => 'news', 'fr' => 'actualités', 'pt' => 'notícias'],
        ];

        $language = $this->contentLanguage($language);

        return $labels[$collectionKey][$language] ?? $collectionKey;
    }
}
