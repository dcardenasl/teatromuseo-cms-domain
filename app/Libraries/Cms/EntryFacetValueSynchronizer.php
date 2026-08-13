<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use CodeIgniter\Database\BaseConnection;

/**
 * Materializes the facetable/orderable field values of one block instance
 * translation into `cms_entry_facet_values`, so public listings can filter/
 * order by `block.<block_key>.<field>` (or the bare `<field>` form) with a
 * real indexed WHERE/ORDER BY instead of loading every candidate entry into
 * PHP. See docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.A.
 *
 * Mirrors EntryRelationSynchronizer's shape (delete-then-reinsert per owning
 * row, its own nested transaction, silent no-op when the owner id is invalid
 * or the table doesn't exist yet) so both write paths that persist
 * cms_block_instance_translations.block_data — BlockInstanceService and
 * EntryBlockTemplateInitializer — can call it identically.
 */
final class EntryFacetValueSynchronizer
{
    /**
     * Field types PublicEntryReader treats as safe to surface as a facet/
     * order value (see its now-deleted batchResolveListingField(), whose
     * allow-list this mirrors verbatim so materialization and read-time
     * resolution never drift apart).
     */
    private const FACETABLE_TYPES = [
        'string', 'text', 'textarea', 'richtext', 'date', 'datetime',
        'number', 'integer', 'select', 'boolean',
    ];

    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array<string, mixed> $blockData Already-sanitized block_data for this language.
     * @param array<string, mixed> $schemaFields schema_definition['fields'] for this block type.
     */
    public function sync(
        int $entryId,
        int $blockInstanceId,
        string $blockKey,
        int $languageId,
        array $blockData,
        array $schemaFields
    ): void {
        if ($entryId <= 0 || $blockInstanceId <= 0 || $languageId <= 0 || $blockKey === ''
            || ! $this->db->tableExists('cms_entry_facet_values')
        ) {
            return;
        }

        $rows = $this->buildRows($entryId, $blockInstanceId, $blockKey, $languageId, $blockData, $schemaFields);

        $this->db->transStart();

        $this->db->table('cms_entry_facet_values')
            ->where('block_instance_id', $blockInstanceId)
            ->where('language_id', $languageId)
            ->delete();

        if ($rows !== []) {
            $this->db->table('cms_entry_facet_values')->insertBatch($rows);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException(lang('Cms.entry_facets.sync_failed', [(string) $entryId]));
        }
    }

    /**
     * Removes every materialized row for a block instance. Normally
     * unnecessary — cms_block_instances rows are hard-deleted (see
     * BlockInstancePurger) and the FK on block_instance_id cascades — but
     * exposed explicitly for callers that need to purge without deleting the
     * block instance itself (kept symmetric with sync()'s delete-then-insert
     * shape, not relied on anywhere today).
     */
    public function purgeForBlockInstance(int $blockInstanceId): void
    {
        if ($blockInstanceId <= 0 || ! $this->db->tableExists('cms_entry_facet_values')) {
            return;
        }

        $this->db->table('cms_entry_facet_values')
            ->where('block_instance_id', $blockInstanceId)
            ->delete();
    }

    /**
     * @param array<string, mixed> $blockData
     * @param array<string, mixed> $schemaFields
     * @return list<array<string, mixed>>
     */
    private function buildRows(
        int $entryId,
        int $blockInstanceId,
        string $blockKey,
        int $languageId,
        array $blockData,
        array $schemaFields
    ): array {
        $rows = [];
        $now  = date('Y-m-d H:i:s');

        foreach ($schemaFields as $fieldKey => $definition) {
            if (! is_string($fieldKey) || $fieldKey === '' || ! is_array($definition)) {
                continue;
            }

            $type = (string) ($definition['type'] ?? '');
            if (! in_array($type, self::FACETABLE_TYPES, true)) {
                continue;
            }

            $raw = $blockData[$fieldKey] ?? null;
            if (is_array($raw)) {
                $raw = implode(', ', array_map(
                    static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
                    $raw
                ));
            }
            if (! is_scalar($raw)) {
                continue;
            }

            $stringValue = trim((string) $raw);
            if ($stringValue === '') {
                continue;
            }

            [$valueType, $valueDate, $valueNumeric] = $this->castTyped($type, $stringValue);

            $base = [
                'entry_id'          => $entryId,
                'block_instance_id' => $blockInstanceId,
                'language_id'       => $languageId,
                'value_type'        => $valueType,
                'value_string'      => mb_substr($stringValue, 0, 255),
                'value_date'        => $valueDate,
                'value_numeric'     => $valueNumeric,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            // Namespaced form — what `block.<block_key>.<field>` references resolve to.
            $rows[] = $base + ['field_key' => 'block.' . $blockKey . '.' . $fieldKey];
            // Bare form — what an unprefixed `filter_by`/`order_by=field:<field>` resolves
            // to, matching the $legacyField branch PublicEntryReader already supported.
            $rows[] = $base + ['field_key' => $fieldKey];
        }

        return $rows;
    }

    /** @return array{0: string, 1: ?string, 2: ?float} value_type, value_date, value_numeric */
    private function castTyped(string $type, string $value): array
    {
        if (in_array($type, ['date', 'datetime'], true)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return ['date', date('Y-m-d H:i:s', $timestamp), null];
            }
        }

        if (in_array($type, ['number', 'integer'], true) && is_numeric($value)) {
            return ['numeric', null, (float) $value];
        }

        return ['string', null, null];
    }
}
