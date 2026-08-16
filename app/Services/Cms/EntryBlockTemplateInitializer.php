<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Libraries\Cms\EntryFacetValueSynchronizer;

/**
 * Transactionally creates BlockInstances (and their translations) for every
 * block defined in an entry's parent collection `block_template`, optionally
 * pre-filling block_data fields from a wizard_extra payload.
 *
 * Extracted from EntryService::afterStore()'s block-template auto-init logic.
 */
class EntryBlockTemplateInitializer
{
    public function __construct(private readonly EntryFacetValueSynchronizer $entryFacetValueSynchronizer)
    {
    }

    /**
     * @param array<string, mixed>|null $wizardExtra
     * @return list<string> wizard_extra keys that were consumed (mapped to block_data)
     * @throws \Exception
     */
    public function initialize(object $entry, ?array $wizardExtra): array
    {
        $collectionId = isset($entry->collection_id) ? (int) $entry->collection_id : null;
        if ($collectionId === null) {
            return [];
        }

        /** @var \App\Models\CollectionModel $collectionModel */
        $collectionModel = model(\App\Models\CollectionModel::class);
        $collection = $collectionModel->find($collectionId);

        if (!$collection instanceof \App\Entities\CollectionEntity) {
            return [];
        }

        $blocks = $collection->getBlocksArray();
        if ($blocks === null || $blocks === []) {
            return [];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        /** @var list<string> $consumedKeys */
        $consumedKeys = [];

        try {
            /** @var \App\Models\BlockTypeModel $blockTypeModel */
            $blockTypeModel = model(\App\Models\BlockTypeModel::class);

            /** @var \App\Models\BlockInstanceModel $blockInstanceModel */
            $blockInstanceModel = model(\App\Models\BlockInstanceModel::class);

            /** @var \App\Models\BlockInstanceTranslationModel $translationModel */
            $translationModel = model(\App\Models\BlockInstanceTranslationModel::class);

            /** @var \App\Models\LanguageModel $languageModel */
            $languageModel = model(\App\Models\LanguageModel::class);

            /** @var list<\App\Entities\LanguageEntity> $activeLanguages */
            $activeLanguages = $languageModel->where('is_active', 1)->findAll();
            $createdBlockCount = 0;
            // A required block created for an already-published entry is part of
            // that public entry. Draft and review entries remain private until
            // their workflow reaches `published`; optional blocks added later
            // keep their own publication decision through the normal block API.
            $initialBlockPublication = (string) ($entry->workflow_status ?? 'draft') === 'published' ? 1 : 0;

            foreach ($blocks as $blockDef) {
                // Legacy templates did not carry this flag and therefore keep
                // the historical behavior of creating every declared block.
                if (array_key_exists('auto_create', $blockDef) && ! (bool) $blockDef['auto_create']) {
                    continue;
                }

                $blockKey = (string) ($blockDef['block_key'] ?? '');
                $blockType = $blockTypeModel->where('block_key', $blockKey)->first();

                if (!$blockType instanceof \App\Entities\BlockTypeEntity) {
                    throw new \RuntimeException("Block type '{$blockKey}' not found during template initialization");
                }

                $blockConfigDefaults = $blockDef['block_config_defaults'] ?? [];
                $configJson = json_encode(is_array($blockConfigDefaults) ? $blockConfigDefaults : []);

                $instanceId = $blockInstanceModel->insert([
                    'block_id'     => (int) $blockType->id,
                    'owner_type'   => 'entry',
                    'owner_id'     => (int) $entry->id,
                    'sort_order'   => (int) ($blockDef['sort_order'] ?? 1),
                    'is_active'    => 1,
                    'block_config' => $configJson !== false ? $configJson : '{}',
                ]);

                if (!$instanceId) {
                    throw new \RuntimeException("Failed to insert block instance for '{$blockKey}'");
                }

                // Derive initial block_data from wizard_extra if provided (once per block, shared across languages)
                // `schema_definition` is cast as 'json' on BlockTypeEntity, which CI4 decodes to
                // stdClass (recursively) rather than array — go through the normalizer instead of
                // an `is_array()` check, which would always be false and silently drop every field.
                $schemaDef   = \dcardenasl\Ci4ApiCore\Support\JsonCastNormalizer::toArray($blockType->schema_definition ?? null);
                $schemaFields = is_array($schemaDef['fields'] ?? null) ? (array) $schemaDef['fields'] : [];

                $extraction   = $wizardExtra !== null
                    ? $this->extractBlockDataFromWizardExtra($schemaFields, $wizardExtra)
                    : ['data' => [], 'consumed' => []];

                $consumedKeys = array_merge($consumedKeys, $extraction['consumed']);
                $blockDataJson = !empty($extraction['data'])
                    ? (json_encode($extraction['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
                    : '{}';

                foreach ($activeLanguages as $language) {
                    if (!$language instanceof \App\Entities\LanguageEntity) {
                        continue;
                    }
                    $inserted = $translationModel->insert([
                        'instance_id'  => (int) $instanceId,
                        'language_id'  => (int) $language->id,
                        'block_data'   => $blockDataJson,
                        'is_published' => $initialBlockPublication,
                    ]);

                    if (!$inserted) {
                        throw new \RuntimeException(lang('Entries.block_translation_insert_failed', [$language->id]));
                    }

                    $this->entryFacetValueSynchronizer->sync(
                        (int) $entry->id,
                        (int) $instanceId,
                        $blockKey,
                        (int) $language->id,
                        $extraction['data'],
                        $schemaFields
                    );
                }

                $createdBlockCount++;
            }

            $db->transComplete();

            if (!$db->transStatus()) {
                throw new \RuntimeException(lang('Entries.block_template_init_tx_failed'));
            }

            log_message('info', "[EntryBlockTemplateInitializer] Initialized {$createdBlockCount} block(s) for entry {$entry->id} (collection {$collectionId}).");

            if ($wizardExtra !== null && $wizardExtra !== []) {
                $unconsumed = array_diff(array_keys($wizardExtra), $consumedKeys);
                if ($unconsumed !== []) {
                    log_message('warning', '[EntryBlockTemplateInitializer] wizard_extra key(s) with no matching block field for entry ' . $entry->id . ': ' . implode(', ', $unconsumed));
                }
            }
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', "[EntryBlockTemplateInitializer] Block template init failed for entry {$entry->id}: {$e->getMessage()}");
            throw $e;
        }

        return $consumedKeys;
    }

    /**
     * Matches wizard_extra keys against a block type's schema fields and returns the
     * block_data subset to pre-fill, plus the list of wizard_extra keys consumed.
     *
     * @param array<string, mixed> $schemaFields  schema_definition['fields'] from BlockTypeEntity
     * @param array<string, mixed> $wizardExtra
     * @return array{data: array<string, mixed>, consumed: list<string>}
     */
    private function extractBlockDataFromWizardExtra(array $schemaFields, array $wizardExtra): array
    {
        if ($schemaFields === [] || $wizardExtra === []) {
            return ['data' => [], 'consumed' => []];
        }

        $blockData = [];
        $consumed  = [];

        foreach (array_keys($schemaFields) as $fieldKey) {
            if (array_key_exists($fieldKey, $wizardExtra)) {
                $blockData[$fieldKey] = $wizardExtra[$fieldKey];
                $consumed[]           = $fieldKey;
            }
        }

        return ['data' => $blockData, 'consumed' => $consumed];
    }
}
