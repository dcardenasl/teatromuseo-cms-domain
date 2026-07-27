<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use App\Exceptions\BlockTemplateValidationException;
use CodeIgniter\Database\BaseConnection;

/**
 * Normalizes and validates entry references declared by block schemas.
 *
 * The persisted contract is deliberately small and stable:
 * - entry_reference: {entry_id: int, collection_key: string}
 * - entry_reference_list: list of the same objects
 *
 * Editors may submit an entry id directly. The collection is then inferred
 * when the schema targets exactly one collection. This keeps the admin form
 * ergonomic while ensuring the database always receives a typed reference.
 */
final class BlockReferenceValidator
{
    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public function normalizeBlockData(array $data, array $fields, ?int $ownerEntryId = null): array
    {
        foreach ($fields as $fieldKey => $definition) {
            $primitive = (string) ($definition['type'] ?? $definition['primitive'] ?? '');
            if (! in_array($primitive, ['entry_reference', 'entry_reference_list'], true)) {
                continue;
            }

            $value = $data[$fieldKey] ?? null;
            if ($primitive === 'entry_reference') {
                $data[$fieldKey] = $this->normalizeSingle($fieldKey, $value, $definition, $ownerEntryId);
                continue;
            }

            $data[$fieldKey] = $this->normalizeList($fieldKey, $value, $definition, $ownerEntryId);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{entry_id: int, collection_key: string}|null
     */
    private function normalizeSingle(string $fieldKey, mixed $value, array $definition, ?int $ownerEntryId): ?array
    {
        if ($value === null || $value === '') {
            if ((bool) ($definition['required'] ?? false)) {
                $this->fail('required', $fieldKey);
            }

            return null;
        }

        $reference = $this->normalizeReference($fieldKey, $value, $definition);
        $this->validateReferences($fieldKey, [$reference], $definition, $ownerEntryId);

        return $reference;
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<array{entry_id: int, collection_key: string}>
     */
    private function normalizeList(string $fieldKey, mixed $value, array $definition, ?int $ownerEntryId): array
    {
        if ($value === null || $value === '') {
            $value = [];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $references = [];
        $seen = [];
        foreach ($value as $item) {
            $reference = $this->normalizeReference($fieldKey, $item, $definition);
            $identity = $reference['collection_key'] . ':' . $reference['entry_id'];
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $references[] = $reference;
        }

        $min = max(0, (int) ($definition['min_items'] ?? ($definition['required'] ?? false ? 1 : 0)));
        $max = isset($definition['max_items']) ? max(0, (int) $definition['max_items']) : null;
        if (count($references) < $min) {
            $this->fail('min_items', $fieldKey);
        }
        if ($max !== null && count($references) > $max) {
            $this->fail('max_items', $fieldKey);
        }

        $this->validateReferences($fieldKey, $references, $definition, $ownerEntryId);

        return $references;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{entry_id: int, collection_key: string}
     */
    private function normalizeReference(string $fieldKey, mixed $value, array $definition): array
    {
        $allowedCollections = $this->allowedCollections($definition);
        $entryId = null;
        $collectionKey = null;

        if (is_array($value)) {
            $entryId = $value['entry_id'] ?? $value['id'] ?? null;
            $collectionKey = $value['collection_key'] ?? $value['collection'] ?? null;
        } elseif (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $entryId = (int) $value;
        } elseif (is_string($value) && str_contains($value, ':')) {
            [$encodedCollection, $encodedId] = explode(':', trim($value), 2);
            if (trim($encodedCollection) !== '' && ctype_digit(trim($encodedId))) {
                $collectionKey = trim($encodedCollection);
                $entryId = (int) $encodedId;
            }
        }

        if (! is_int($entryId) && ! (is_string($entryId) && ctype_digit(trim($entryId)))) {
            $this->fail('shape', $fieldKey);
        }
        $entryId = (int) $entryId;
        if ($entryId <= 0) {
            $this->fail('shape', $fieldKey);
        }

        if (! is_string($collectionKey) || trim($collectionKey) === '') {
            if (count($allowedCollections) === 1) {
                $collectionKey = $allowedCollections[0];
            } else {
                $this->fail('collection', $fieldKey);
            }
        }
        $collectionKey = trim($collectionKey);

        if ($allowedCollections !== [] && ! in_array($collectionKey, $allowedCollections, true)) {
            $this->fail('collection', $fieldKey);
        }

        return ['entry_id' => $entryId, 'collection_key' => $collectionKey];
    }

    /**
     * @param list<array{entry_id: int, collection_key: string}> $references
     * @param array<string, mixed> $definition
     */
    private function validateReferences(string $fieldKey, array $references, array $definition, ?int $ownerEntryId): void
    {
        if ($references === []) {
            return;
        }

        if ($ownerEntryId !== null) {
            foreach ($references as $reference) {
                if ($reference['entry_id'] === $ownerEntryId) {
                    $this->fail('self', $fieldKey);
                }
            }
        }

        $entryIds = array_values(array_unique(array_map(
            static fn (array $reference): int => $reference['entry_id'],
            $references
        )));
        $result = $this->db->table('cms_entries e')
            ->select('e.id, c.collection_key')
            ->join('cms_collections c', 'c.id = e.collection_id')
            ->whereIn('e.id', $entryIds)
            ->where('e.deleted_at IS NULL', null, false)
            ->get();
        if ($result === false) {
            $this->fail('not_found', $fieldKey);
        }
        $rows = $result->getResultArray();

        $found = [];
        foreach ($rows as $row) {
            $found[(int) $row['id']] = (string) $row['collection_key'];
        }

        foreach ($references as $reference) {
            if (($found[$reference['entry_id']] ?? null) !== $reference['collection_key']) {
                $this->fail('not_found', $fieldKey);
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    private function allowedCollections(array $definition): array
    {
        $collections = $definition['collection_keys'] ?? $definition['allowed_collections'] ?? [];
        if (is_string($definition['collection_key'] ?? null)) {
            $collections = [$definition['collection_key']];
        }
        if (! is_array($collections)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $collections),
            static fn (string $value): bool => $value !== ''
        )));
    }

    private function fail(string $reason, string $fieldKey): never
    {
        $message = match ($reason) {
            'required' => lang('Cms.entry_references.required', [$fieldKey]),
            'min_items' => lang('Cms.entry_references.min_items', [$fieldKey]),
            'max_items' => lang('Cms.entry_references.max_items', [$fieldKey]),
            'collection' => lang('Cms.entry_references.collection', [$fieldKey]),
            'self' => lang('Cms.entry_references.self', [$fieldKey]),
            'not_found' => lang('Cms.entry_references.not_found', [$fieldKey]),
            default => lang('Cms.entry_references.shape', [$fieldKey]),
        };

        throw new BlockTemplateValidationException($message, 'translations.' . $fieldKey);
    }
}
