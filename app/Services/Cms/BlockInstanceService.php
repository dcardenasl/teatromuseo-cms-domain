<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Entities\BlockInstanceEntity;
use App\Interfaces\Cms\BlockInstanceServiceInterface;
use App\Libraries\Cms\BlockReferenceValidator;
use App\Libraries\Cms\EntryRelationSynchronizer;
use App\Libraries\Cms\FileReferenceSynchronizer;
use App\Libraries\Cms\FileUrlResolver;
use App\Libraries\Cms\HtmlSanitizer;
use App\Traits\Services\HasDeferredTranslations;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

/**
 * @extends BaseCrudService<BlockInstanceEntity>
 */
class BlockInstanceService extends BaseCrudService implements BlockInstanceServiceInterface
{
    use HasDeferredTranslations;

    private ?string $filterOwnerType = null;
    private ?int $filterOwnerId = null;

    private FileUrlResolver $fileUrlResolver;

    private FileReferenceSynchronizer $fileReferenceSynchronizer;

    private \App\Libraries\Cms\CacheInvalidationClient $cacheInvalidator;

    private ?\App\Libraries\Cms\TranslationSynchronizer $translationSynchronizer;

    private ?BlockReferenceValidator $blockReferenceValidator;

    private ?EntryRelationSynchronizer $entryRelationSynchronizer;

    public function setOwnerContext(string $ownerType, int $ownerId): void
    {
        $this->filterOwnerType = $ownerType;
        $this->filterOwnerId   = $ownerId;
    }

    public function ownerTypeForInstance(int $id): ?string
    {
        $instance = $this->repository->find($id);
        if (! isset($instance->owner_type)) {
            return null;
        }

        return (string) $instance->owner_type;
    }

    /**
     * @param RepositoryInterface<BlockInstanceEntity> $blockInstanceRepository
     */
    public function __construct(
        RepositoryInterface $blockInstanceRepository,
        ResponseMapperInterface $responseMapper,
        FileUrlResolver $fileUrlResolver,
        FileReferenceSynchronizer $fileReferenceSynchronizer,
        \App\Libraries\Cms\CacheInvalidationClient $cacheInvalidator,
        ?\App\Libraries\Cms\TranslationSynchronizer $translationSynchronizer = null,
        ?BlockReferenceValidator $blockReferenceValidator = null,
        ?EntryRelationSynchronizer $entryRelationSynchronizer = null
    ) {
        parent::__construct($blockInstanceRepository, $responseMapper);
        $this->fileUrlResolver = $fileUrlResolver;
        $this->fileReferenceSynchronizer = $fileReferenceSynchronizer;
        $this->cacheInvalidator = $cacheInvalidator;
        $this->translationSynchronizer = $translationSynchronizer;
        $this->blockReferenceValidator = $blockReferenceValidator;
        $this->entryRelationSynchronizer = $entryRelationSynchronizer;
    }

    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        $data = parent::beforeStore($data, $context);
        $this->validateSlideNavigation($data);
        $data = $this->normalizeBlockConfig($data);
        $data = $this->normalizeEntryReferencesFromPayload($data);

        return $this->deferTranslationsFromUpdate($data);
    }

    protected function afterStore(object $entity, ?SecurityContext $context): void
    {
        parent::afterStore($entity, $context);
        $this->flushDeferredTranslations(fn (array $t) => $this->saveTranslations((int) $entity->id, $t));
        $this->fileReferenceSynchronizer->syncBlockInstance((int) $entity->id);
        $this->cacheInvalidator->invalidate($this->cacheScopesForEntity($entity));
    }

    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $data = parent::beforeUpdate($id, $data, $context);

        // Both helpers below may need the persisted row for fields the payload
        // omits (block_id, owner_type, owner_id). `$instance` is passed by
        // reference so whichever one needs it first loads it and the other
        // reuses it — a payload carrying those fields loads nothing at all.
        //
        // This still leaves one read more than strictly necessary:
        // BaseCrudService::update() already loaded the same row before invoking
        // this hook, but hands it only to setEntityContext(), which forwards it
        // to the audit trail without exposing it. Removing that third SELECT
        // means passing the loaded entity into beforeUpdate() — a ci4-api-core
        // signature change, tracked under CORE-02.
        $instance = null;

        $this->validateSlideNavigation($data, $id, $instance);
        $data = $this->normalizeBlockConfig($data, $instance !== null ? (int) ($instance->block_id ?? 0) : null);
        $data = $this->normalizeEntryReferencesFromPayload($data, $id, $instance);

        return $this->deferTranslationsFromUpdate($data);
    }

    protected function afterUpdate(object $entity, ?SecurityContext $context): void
    {
        parent::afterUpdate($entity, $context);
        $this->flushDeferredTranslations(fn (array $t) => $this->saveTranslations((int) $entity->id, $t));
        $this->fileReferenceSynchronizer->syncBlockInstance((int) $entity->id);
        $this->cacheInvalidator->invalidate($this->cacheScopesForEntity($entity));
    }

    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
        parent::beforeDelete($id, $context);
        $this->assertBlockNotLocked($id);
    }

    protected function afterDelete(object $entity, ?SecurityContext $context): void
    {
        parent::afterDelete($entity, $context);
        $this->cacheInvalidator->invalidate($this->cacheScopesForEntity($entity));
    }

    /**
     * Throws AuthorizationException when the block instance is locked by its
     * collection's block_template. Only applies to entry-owned instances.
     * destroy() already guarantees the instance exists before this hook runs.
     *
     * @throws \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException
     */
    private function assertBlockNotLocked(int $instanceId): void
    {
        /** @var BlockInstanceEntity|null $instance */
        $instance = $this->repository->find($instanceId);
        if (!$instance instanceof BlockInstanceEntity || $instance->owner_type !== 'entry') {
            return;
        }

        /** @var \App\Models\EntryModel $entryModel */
        $entryModel = model(\App\Models\EntryModel::class);
        $entry = $entryModel->find((int) $instance->owner_id);
        if (!$entry instanceof \App\Entities\EntryEntity) {
            return;
        }

        /** @var \App\Models\CollectionModel $collectionModel */
        $collectionModel = model(\App\Models\CollectionModel::class);
        $collection = $collectionModel->find((int) $entry->collection_id);
        if (!$collection instanceof \App\Entities\CollectionEntity) {
            return;
        }

        $blockType = $this->blockTypeById((int) $instance->block_id);
        if ($blockType === null) {
            return;
        }

        if ($collection->isBlockLocked((string) $blockType->block_key)) {
            throw new \dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException(
                lang('BlockInstances.locked_by_template')
            );
        }
    }

    protected function enrichEntities(array $entities): array
    {
        if (empty($entities)) {
            return $entities;
        }

        // ── Translations ───────────────────────────────────────────────────────
        $instanceIds = array_map(fn ($entity) => (int) $entity->id, $entities);

        /** @var \App\Models\BlockInstanceTranslationModel $translationModel */
        $translationModel = model(\App\Models\BlockInstanceTranslationModel::class);
        $translations = $translationModel->whereIn('instance_id', $instanceIds)->findAll();

        $translationsGrouped = [];
        foreach ($translations as $translation) {
            /** @var \App\Entities\BlockInstanceTranslationEntity $translation */
            $translationsGrouped[$translation->instance_id][] = [
                'language_id'  => (int) $translation->language_id,
                'block_data'   => $translation->block_data,
                'is_published' => (bool) $translation->is_published,
            ];
        }

        // ── Block type meta (block_key) ─────────────────────────────────────────
        // Merges block_key into block_config so consumers can identify the block
        // type without a separate lookup, even when block_config was created before
        // block_key was stored explicitly.
        $uniqueBlockIds = array_unique(array_map(fn ($entity) => (int) $entity->block_id, $entities));

        /** @var \App\Models\BlockTypeModel $blockTypeModel */
        $blockTypeModel = model(\App\Models\BlockTypeModel::class);
        /** @var list<\App\Entities\BlockTypeEntity> $blockTypeEntities */
        $blockTypeEntities = $blockTypeModel->whereIn('id', $uniqueBlockIds)->findAll();

        /** @var array<int, string> $blockKeyById  id → block_key */
        $blockKeyById = [];
        foreach ($blockTypeEntities as $bt) {
            $blockKeyById[(int) $bt->id] = (string) $bt->block_key;
        }

        // ── Apply to entities ──────────────────────────────────────────────────
        foreach ($entities as $entity) {
            $entity->translations = $translationsGrouped[$entity->id] ?? [];

            $bid = (int) $entity->block_id;
            if (isset($blockKeyById[$bid])) {
                $existing = is_array($entity->block_config) ? $entity->block_config : [];
                // block_key from the block type is authoritative — always in position
                $entity->block_config = array_merge(['block_key' => $blockKeyById[$bid]], $existing);
            } else {
                $entity->block_config = is_array($entity->block_config) ? $entity->block_config : [];
            }

        }

        return $entities;
    }

    /**
     * @param array<mixed> $translations
     */
    private function saveTranslations(int $instanceId, array $translations): void
    {
        /** @var \App\Models\BlockInstanceTranslationModel $translationModel */
        $translationModel = model(\App\Models\BlockInstanceTranslationModel::class);
        $blockSchemaFields = $this->blockSchemaFields($instanceId);
        $ownerEntryId = $this->blockOwnerEntryId($instanceId);

        $rows = [];
        $normalizedTranslations = [];
        foreach ($translations as $translation) {
            $blockData = $translation['block_data'] ?? [];
            if (! is_array($blockData)) {
                $blockData = [];
            } else {
                $blockData = $this->sanitizeBlockData($blockData);
                if ($this->blockReferenceValidator !== null) {
                    $blockData = $this->blockReferenceValidator->normalizeBlockData(
                        $blockData,
                        $blockSchemaFields,
                        $ownerEntryId
                    );
                }
                if ($blockSchemaFields !== []) {
                    $blockData = $this->fileUrlResolver->normalizeBlockData(
                        $blockData,
                        $blockSchemaFields,
                        'storage'
                    );
                }
            }

            $rows[] = [
                'language_id'  => (int) $translation['language_id'],
                'block_data'   => json_encode($blockData),
                'is_published' => (bool) ($translation['is_published'] ?? true),
            ];
            $normalizedTranslations[] = [
                'block_data' => $blockData,
            ];
        }

        ($this->translationSynchronizer ?? throw new \LogicException(lang('Api.translationSynchronizerRequired')))->replace(
            $translationModel,
            'instance_id',
            $instanceId,
            $rows,
            static fn (array $row): array => $row,
        );
        $this->syncSemanticRelations($instanceId, $normalizedTranslations);
    }

    /** @param array<mixed> $translations */
    private function syncSemanticRelations(int $instanceId, array $translations): void
    {
        if ($this->entryRelationSynchronizer === null) {
            return;
        }

        $instance = $this->repository->find($instanceId);
        if (! isset($instance->owner_type, $instance->owner_id, $instance->block_id)
            || (string) $instance->owner_type !== 'entry') {
            return;
        }

        $blockType = $this->blockTypeById((int) $instance->block_id);
        if ($blockType === null || (string) $blockType->block_key !== 'related_entries') {
            return;
        }

        $references = [];
        $relationType = 'related';
        foreach ($translations as $translation) {
            $data = is_array($translation['block_data'] ?? null) ? $translation['block_data'] : [];
            if (in_array((string) ($data['relation_type'] ?? ''), ['related', 'recommended', 'prerequisite', 'sequel'], true)) {
                $relationType = (string) $data['relation_type'];
            }
            foreach ((array) ($data['entries'] ?? []) as $reference) {
                if (is_array($reference) && isset($reference['entry_id'], $reference['collection_key'])) {
                    $references[] = [
                        'entry_id' => (int) $reference['entry_id'],
                        'collection_key' => (string) $reference['collection_key'],
                    ];
                }
            }
        }

        $unique = [];
        foreach ($references as $reference) {
            $unique[$reference['collection_key'] . ':' . $reference['entry_id']] = $reference;
        }
        $this->entryRelationSynchronizer->sync(
            (int) $instance->owner_id,
            $instanceId,
            $relationType,
            array_values($unique)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeBlockConfig(array $data, ?int $persistedBlockId = null): array
    {
        if (! array_key_exists('block_config', $data)) {
            return $data;
        }

        $blockConfig = $data['block_config'];
        if (is_string($blockConfig) && trim($blockConfig) !== '') {
            $decoded = json_decode($blockConfig, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $blockConfig = $decoded;
            }
        }

        if (is_array($blockConfig)) {
            $blockId = isset($data['block_id'])
                ? (int) $data['block_id']
                : (int) ($persistedBlockId ?? 0);
            if ($blockId > 0) {
                $schemaDefinition = $this->blockSchemaDefinition($blockId);
                $configFields = is_array($schemaDefinition['config_fields'] ?? null)
                    ? (array) $schemaDefinition['config_fields']
                    : [];
                if ($configFields !== []) {
                    $blockConfig = $this->fileUrlResolver->normalizeBlockConfig(
                        $blockConfig,
                        $configFields,
                        'storage'
                    );
                }
            }
            $blockConfig = $this->decodeJsonConfigFields($blockConfig, $configFields ?? []);

            $data['block_config'] = json_encode($blockConfig);
        } elseif ($blockConfig === null || $blockConfig === '') {
            $data['block_config'] = null;
        }

        return $data;
    }

    /**
     * Admin forms submit schema fields of type json through hidden inputs.
     * Decode those values at the domain boundary so every writer persists the
     * same structured contract, regardless of which Admin client submitted it.
     *
     * @param array<string, mixed> $blockConfig
     * @param array<string, mixed> $configFields
     * @return array<string, mixed>
     */
    private function decodeJsonConfigFields(array $blockConfig, array $configFields): array
    {
        $jsonFields = $configFields;
        // `listing_projection` was introduced as a hidden JSON form field
        // before every deployed block schema exposed its `json` definition.
        // Keep accepting that transport shape while normalizing it to the
        // same structured value as newer schema-driven clients.
        $jsonFields['listing_projection'] ??= ['type' => 'json'];

        foreach ($jsonFields as $fieldKey => $fieldDefinition) {
            if (! is_array($fieldDefinition) || strtolower((string) ($fieldDefinition['type'] ?? '')) !== 'json') {
                continue;
            }

            $value = $blockConfig[$fieldKey] ?? null;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $blockConfig[$fieldKey] = $decoded;
            }
        }

        return $blockConfig;
    }

    /** @param array<string, mixed> $data */
    private function validateSlideNavigation(array $data, ?int $instanceId = null, ?object &$instance = null): void
    {
        $blockId = (int) ($data['block_id'] ?? 0);
        if ($blockId <= 0 && $instanceId !== null) {
            $instance ??= $this->repository->find($instanceId);
            $blockId = (int) ($instance->block_id ?? 0);
        }
        $type = $this->blockTypeById($blockId);
        if ($type === null || (string) $type->block_key !== 'slide_banner') {
            return;
        }

        $config = $data['block_config'] ?? [];
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        $config = is_array($config) ? $config : [];
        $mode = strtolower(trim((string) ($config['navigation_mode'] ?? 'none')));
        if (! in_array($mode, ['none', 'internal', 'external'], true)) {
            throw new \InvalidArgumentException(lang('BlockInstances.invalid_slide_navigation_mode'));
        }
        if ($mode === 'internal' && ! in_array((string) ($config['navigation_target_type'] ?? ''), ['page', 'event_listing', 'catalog_listing', 'collection_index'], true)) {
            throw new \InvalidArgumentException(lang('BlockInstances.invalid_slide_internal_target'));
        }
        $targetType = (string) ($config['navigation_target_type'] ?? '');
        if ($mode === 'internal' && $targetType === 'page' && (int) ($config['page_id'] ?? 0) <= 0) {
            throw new \InvalidArgumentException(lang('BlockInstances.invalid_slide_internal_target'));
        }
        if ($mode === 'internal' && $targetType === 'collection_index' && (int) ($config['collection_id'] ?? 0) <= 0) {
            throw new \InvalidArgumentException(lang('BlockInstances.invalid_slide_internal_target'));
        }

        foreach ((array) ($data['translations'] ?? []) as $translation) {
            if (! is_array($translation)) {
                continue;
            }
            $blockData = is_array($translation['block_data'] ?? null) ? $translation['block_data'] : [];
            $externalUrl = trim((string) ($blockData['external_url'] ?? ''));
            if ($mode === 'external' && $externalUrl !== '' && ! preg_match('#^https?://[^\s]+$#i', $externalUrl)) {
                throw new \InvalidArgumentException(lang('BlockInstances.invalid_slide_external_url'));
            }
            if ($mode !== 'external' && $externalUrl !== '') {
                throw new \InvalidArgumentException(lang('BlockInstances.external_url_without_external_mode'));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function cacheScopesForEntity(object $entity): array
    {
        $ownerType = (string) ($entity->owner_type ?? 'page');

        return $ownerType === 'entry' ? ['entries'] : ['pages', 'collections'];
    }

    /**
     * Recursively sanitize any string values in block_data that look like HTML,
     * so rich-text content is safe when rendered unescaped in public views.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function sanitizeBlockData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && str_contains($value, '<')) {
                $data[$key] = HtmlSanitizer::clean($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeBlockData($value);
            }
        }

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function blockSchemaFields(int $instanceId): array
    {
        $schemaDefinition = $this->blockSchemaDefinitionByInstance($instanceId);
        $fields = $schemaDefinition['fields'] ?? [];

        return is_array($fields) ? $fields : [];
    }

    /**
     * Validate references before the parent row is written. Translation rows
     * are persisted in afterStore/afterUpdate by design, so doing this early
     * prevents an invalid reference from leaving a partially updated block.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeEntryReferencesFromPayload(array $data, ?int $instanceId = null, ?object &$instance = null): array
    {
        if ($this->blockReferenceValidator === null || ! array_key_exists('translations', $data)) {
            return $data;
        }

        $translations = $data['translations'];
        if (! is_array($translations)) {
            return $data;
        }

        $blockId = isset($data['block_id']) ? (int) $data['block_id'] : 0;
        $ownerType = (string) ($data['owner_type'] ?? '');
        $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : null;
        if ($instanceId !== null) {
            $instance ??= $this->repository->find($instanceId);
            if ($blockId <= 0 && isset($instance->block_id)) {
                $blockId = (int) $instance->block_id;
            }
            if ($ownerType === '' && isset($instance->owner_type)) {
                $ownerType = (string) $instance->owner_type;
            }
            if ($ownerId === null && isset($instance->owner_id)) {
                $ownerId = (int) $instance->owner_id;
            }
        }

        $fields = $blockId > 0 ? $this->blockSchemaDefinition($blockId)['fields'] ?? [] : [];
        if (! is_array($fields)) {
            return $data;
        }

        $ownerEntryId = $ownerType === 'entry' ? $ownerId : null;
        foreach ($translations as $index => $translation) {
            if (! is_array($translation) || ! is_array($translation['block_data'] ?? null)) {
                continue;
            }
            $translations[$index]['block_data'] = $this->blockReferenceValidator->normalizeBlockData(
                $translation['block_data'],
                $fields,
                $ownerEntryId
            );
        }
        $data['translations'] = $translations;

        return $data;
    }

    private function blockOwnerEntryId(int $instanceId): ?int
    {
        $instance = $this->repository->find($instanceId);
        if (! isset($instance->owner_type, $instance->owner_id) || $instance->owner_type !== 'entry') {
            return null;
        }

        return (int) $instance->owner_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function blockSchemaDefinition(int $blockId): array
    {
        $blockType = $this->blockTypeById($blockId);
        if ($blockType === null) {
            return [];
        }

        $schemaDefinition = $blockType->schema_definition ?? null;
        if (is_string($schemaDefinition) && trim($schemaDefinition) !== '') {
            $decoded = json_decode($schemaDefinition, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($schemaDefinition) ? $schemaDefinition : [];
    }

    private function blockTypeById(int $blockId): ?\App\Entities\BlockTypeEntity
    {
        if ($blockId <= 0) {
            return null;
        }

        $blockType = (new \App\Models\BlockTypeModel())->find($blockId);

        return $blockType instanceof \App\Entities\BlockTypeEntity ? $blockType : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function blockSchemaDefinitionByInstance(int $instanceId): array
    {
        $row = $this->repository->find($instanceId);
        $blockId = isset($row->block_id) ? (int) $row->block_id : null;

        return $blockId !== null ? $this->blockSchemaDefinition($blockId) : [];
    }

    protected function applyQueryOptions(array $criteria): array
    {
        $criteria = parent::applyQueryOptions($criteria);

        if (empty($criteria['sort'])) {
            $criteria['sort'] = 'sort_order';
        }

        return $criteria;
    }

    protected function applyBaseCriteria(object $builder): void
    {
        if ($this->filterOwnerType !== null && $this->filterOwnerId !== null) {
            $builder->where('owner_type', $this->filterOwnerType)
                    ->where('owner_id', $this->filterOwnerId);

            // Consume — reset so a shared service instance doesn't leak state.
            $this->filterOwnerType = null;
            $this->filterOwnerId   = null;
        }
    }
}
