<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Libraries\Cms\TranslationAuditSupport;
use App\Libraries\Cms\TranslationResourceCatalog;

/**
 * Audits translation completeness for CMS block instances specifically —
 * schema-driven translatable fields nested inside `block_data` JSON, as
 * opposed to the flat-column translations the other resource types use.
 *
 * Extracted from TranslationAuditService, which composes this class.
 */
class BlockInstanceTranslationAuditor
{
    protected \App\Models\BlockInstanceTranslationModel $blockInstanceTranslationModel;

    protected \App\Models\BlockInstanceModel $blockInstanceModel;

    public function __construct(private TranslationAuditSupport $support)
    {
        $this->blockInstanceTranslationModel = model(\App\Models\BlockInstanceTranslationModel::class);
        $this->blockInstanceModel = model(\App\Models\BlockInstanceModel::class);
    }

    /**
     * @param list<object> $activeLanguages
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function audit(array $activeLanguages, array $filters, ?int $defaultLanguageId = null): array
    {
        $instances = $this->getBlockInstancesWithTypes();
        $translationsByInstance = $this->support->groupTranslationsByResource(
            $this->blockInstanceTranslationModel->findAll(),
            'instance_id'
        );

        $issues = [];
        foreach ($instances as $instance) {
            $instanceId = (int) ($instance['id'] ?? 0);
            if ($instanceId <= 0) {
                continue;
            }

            $translatableFields = $this->getTranslatableBlockFieldDefinitions($instance['schema_definition'] ?? null);
            if ($translatableFields === []) {
                continue;
            }

            $translations = $translationsByInstance[$instanceId] ?? [];
            foreach ($activeLanguages as $lang) {
                $langId = (int) $lang->id;
                if (! $this->support->languageFilterAllows($filters, $langId)) {
                    continue;
                }

                $translation = $translations[$langId] ?? null;
                $valueResolver = function (array $row, string $fieldKey, array $fieldDefinition): mixed {
                    return $this->extractBlockFieldValue($row, $fieldKey, $fieldDefinition);
                };
                [$status, $detail] = $translation === null
                    && ! $this->shouldReportMissing($translations, $translatableFields, $valueResolver)
                    ? ['complete', '']
                    : $this->support->evaluateTranslationState(
                        $translation,
                        $translations,
                        $translatableFields,
                        $langId,
                        $valueResolver,
                        $langId === $defaultLanguageId
                            ? null
                            : (isset($instance['updated_at']) ? (string) $instance['updated_at'] : null),
                        $defaultLanguageId
                    );
                if ($status === 'complete') {
                    continue;
                }

                $issues[] = $this->support->buildIssue(
                    'block_instance',
                    $instanceId,
                    'Block Instance #' . $instanceId . ' (' . (string) ($instance['block_key'] ?? '') . ')',
                    $langId,
                    (string) ($lang->code ?? ''),
                    $status,
                    $detail,
                    [
                        'owner_type' => (string) ($instance['owner_type'] ?? ''),
                        'owner_id' => (int) ($instance['owner_id'] ?? 0),
                        'block_key' => (string) ($instance['block_key'] ?? ''),
                    ]
                );
            }
        }

        return $issues;
    }

    /**
     * Per-instance audit used by TranslationAuditService::auditResource() for the
     * 'block_instance' resource type: resolves the instance + its schema + its
     * translations, ready for the caller to run through evaluateTranslationState().
     *
     * @return array{0: array<string, mixed>, 1: array<string, array{required: bool, type: string, data_key: string, compareToSource: bool}>, 2: array<int, array<string, mixed>>, 3: callable(array<string, mixed>, string, array<string, mixed>): mixed}|null
     */
    public function resolveForResource(int $resourceId): ?array
    {
        $instance = $this->getBlockInstanceWithType($resourceId);
        if ($instance === null) {
            return null;
        }

        $translations = $this->support->groupTranslationsByResource(
            $this->blockInstanceTranslationModel->where('instance_id', $resourceId)->findAll(),
            'instance_id'
        )[$resourceId] ?? [];

        $fieldDefinitions = $this->getTranslatableBlockFieldDefinitions($instance['schema_definition'] ?? null);
        $valueResolver = function (array $row, string $fieldKey, array $fieldDefinition): mixed {
            return $this->extractBlockFieldValue($row, $fieldKey, $fieldDefinition);
        };

        return [$instance, $fieldDefinitions, $translations, $valueResolver];
    }

    /**
     * Empty optional containers (for example a gallery whose content lives in
     * child instances) do not need translation rows. A block with required
     * fields, or with content in any existing language, still requires a row
     * for every active language.
     *
     * @param array<int, array<string, mixed>> $translations
     * @param array<string, array<string, mixed>> $fieldDefinitions
     */
    public function shouldReportMissing(array $translations, array $fieldDefinitions, callable $valueResolver): bool
    {
        foreach ($fieldDefinitions as $fieldDefinition) {
            if ((bool) ($fieldDefinition['required'] ?? false)) {
                return true;
            }
        }

        foreach ($translations as $translation) {
            foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
                if (! $this->support->isBlank($valueResolver($translation, (string) $fieldKey, $fieldDefinition))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Per-owner audit used by TranslationAuditService::auditOwnerBlocks() to
     * power the admin's contextual block-translation badges on a single
     * page/entry (and its "blocks" builder view), without pulling — or
     * paginating — the sitewide report just to filter it down client-side.
     *
     * Unlike audit()/countAuditable() (which only look at active blocks,
     * because they answer "what needs fixing on the live site"), this
     * includes inactive block instances too: the contextual views render a
     * card for every block regardless of active state, so every card needs a
     * status to show. It also keeps 'complete' results (audit() only
     * collects issues) since the caller needs to render green badges too,
     * and runs every status through TranslationAuditSupport::collapseForBlockBadge()
     * — see that method's docblock for why 'mismatch' and 'outdated' both
     * collapse away for this admin surface; the sitewide audit table
     * remains the only place either is shown verbatim.
     *
     * @param list<object> $activeLanguages
     * @return array{
     *   blocks: array<int, array<string, array{language_id:int,status:string,detail:string}>>,
     *   summary: array<string, array{complete:int,total:int}>,
     * }
     */
    public function auditForOwner(string $ownerType, int $ownerId, array $activeLanguages, ?int $defaultLanguageId = null): array
    {
        $summary = [];
        foreach ($activeLanguages as $lang) {
            $summary[(string) $lang->code] = ['complete' => 0, 'total' => 0];
        }

        $instances = $this->getBlockInstancesForOwner($ownerType, $ownerId);
        if ($instances === []) {
            return ['blocks' => [], 'summary' => $summary];
        }

        $instanceIds = array_map(static fn (array $i): int => (int) $i['id'], $instances);
        $translationsByInstance = $this->support->groupTranslationsByResource(
            $this->blockInstanceTranslationModel->whereIn('instance_id', $instanceIds)->findAll(),
            'instance_id'
        );

        $blocks = [];
        foreach ($instances as $instance) {
            $instanceId = (int) ($instance['id'] ?? 0);
            if ($instanceId <= 0) {
                continue;
            }

            $translatableFields = $this->getTranslatableBlockFieldDefinitions($instance['schema_definition'] ?? null);
            if ($translatableFields === []) {
                continue;
            }

            $translations = $translationsByInstance[$instanceId] ?? [];
            $perLanguage = [];
            foreach ($activeLanguages as $lang) {
                $langId = (int) $lang->id;
                $langCode = (string) $lang->code;

                $translation = $translations[$langId] ?? null;
                $valueResolver = function (array $row, string $fieldKey, array $fieldDefinition): mixed {
                    return $this->extractBlockFieldValue($row, $fieldKey, $fieldDefinition);
                };
                [$status, $detail] = $translation === null
                    && ! $this->shouldReportMissing($translations, $translatableFields, $valueResolver)
                    ? ['complete', '']
                    : $this->support->evaluateTranslationState(
                        $translation,
                        $translations,
                        $translatableFields,
                        $langId,
                        $valueResolver,
                        $langId === $defaultLanguageId
                            ? null
                            : (isset($instance['updated_at']) ? (string) $instance['updated_at'] : null),
                        $defaultLanguageId
                    );

                $status = $this->support->collapseForBlockBadge($status);

                $perLanguage[$langCode] = [
                    'language_id' => $langId,
                    'status' => $status,
                    'detail' => $detail,
                ];

                $summary[$langCode] = [
                    'complete' => $summary[$langCode]['complete'] + ($status === 'complete' ? 1 : 0),
                    'total' => $summary[$langCode]['total'] + 1,
                ];
            }

            $blocks[$instanceId] = $perLanguage;
        }

        return ['blocks' => $blocks, 'summary' => $summary];
    }

    public function countAuditable(): int
    {
        $instances = $this->getBlockInstancesWithTypes();
        $count = 0;

        foreach ($instances as $instance) {
            if ($this->getTranslatableBlockFieldDefinitions($instance['schema_definition'] ?? null) !== []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getBlockInstancesWithTypes(): array
    {
        return $this->blockInstanceModel->findAllWithBlockType(onlyActive: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getBlockInstancesForOwner(string $ownerType, int $ownerId): array
    {
        return $this->blockInstanceModel->findAllWithBlockType($ownerType, $ownerId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getBlockInstanceWithType(int $resourceId): ?array
    {
        return $this->blockInstanceModel->findOneWithBlockType($resourceId);
    }

    /**
     * @param array<string, mixed>|array<int, mixed>|string|null $schemaDefinition
     * @return array<string, array{required: bool, type: string, data_key: string, compareToSource: bool}>
     */
    private function getTranslatableBlockFieldDefinitions(mixed $schemaDefinition): array
    {
        $schema = is_string($schemaDefinition)
            ? json_decode($schemaDefinition, true)
            : (is_array($schemaDefinition) ? $schemaDefinition : []);

        if (!is_array($schema)) {
            return [];
        }

        $fields = $schema['fields'] ?? [];
        if (!is_array($fields) || $fields === []) {
            return [];
        }

        $translatable = [];
        foreach ($fields as $fieldKey => $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            if (! TranslationResourceCatalog::isAuditableBlockField($fieldDef, (string) $fieldKey)) {
                continue;
            }

            $fieldKey = (string) $fieldKey;
            $fieldType = strtolower((string) ($fieldDef['type'] ?? 'string'));
            $translatable[$fieldKey] = [
                'required' => (bool) ($fieldDef['required'] ?? false),
                'type' => $fieldType,
                'data_key' => $fieldKey,
                // URLs are routing/technical values. A localized block may
                // legitimately point to the same route in every language;
                // copying that route must not make an otherwise complete
                // translation appear incomplete in the admin badges.
                'compareToSource' => $fieldType !== 'url',
            ];
        }

        return $translatable;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $fieldDefinition
     */
    private function extractBlockFieldValue(array $row, string $fieldKey, array $fieldDefinition): mixed
    {
        $blockData = $row['block_data'] ?? null;
        if (is_string($blockData)) {
            $decoded = json_decode($blockData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $blockData = $decoded;
            }
        }

        if (is_object($blockData)) {
            $blockData = (array) $blockData;
        }

        if (!is_array($blockData)) {
            $blockData = [];
        }

        $dataKey = (string) ($fieldDefinition['data_key'] ?? $fieldKey);

        return $blockData[$dataKey] ?? null;
    }
}
