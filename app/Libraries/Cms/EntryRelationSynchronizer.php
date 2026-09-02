<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use CodeIgniter\Database\BaseConnection;

/** Synchronizes the semantic relation table for a related_entries block. */
final class EntryRelationSynchronizer
{
    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param list<array{entry_id: int, collection_key: string}> $references
     */
    public function sync(int $ownerEntryId, int $sourceBlockInstanceId, string $relationType, array $references): void
    {
        if ($ownerEntryId <= 0 || $sourceBlockInstanceId <= 0 || ! $this->db->tableExists('cms_entry_related')) {
            return;
        }

        $relationType = in_array($relationType, ['related', 'recommended', 'prerequisite', 'sequel'], true)
            ? $relationType
            : 'related';
        $hasSource = $this->db->fieldExists('source_block_instance_id', 'cms_entry_related');

        $this->db->transStart();

        if ($hasSource) {
            $this->db->table('cms_entry_related')
                ->where('entry_id', $ownerEntryId)
                ->where('source_block_instance_id', $sourceBlockInstanceId)
                ->delete();
        }

        foreach ($references as $sortOrder => $reference) {
            $relatedEntryId = (int) $reference['entry_id'];
            if ($relatedEntryId <= 0 || $relatedEntryId === $ownerEntryId) {
                continue;
            }

            $table = $this->db->table('cms_entry_related');
            $query = $table
                ->where('entry_id', $ownerEntryId)
                ->where('related_entry_id', $relatedEntryId)
                ->get();
            $existing = $query === false ? null : $query->getRowArray();

            $payload = [
                'relation_type' => $relationType,
                'sort_order' => $sortOrder,
            ];
            if ($hasSource) {
                $payload['source_block_instance_id'] = $sourceBlockInstanceId;
            }

            if ($existing !== null) {
                $table->where('entry_id', $ownerEntryId)
                    ->where('related_entry_id', $relatedEntryId)
                    ->update($payload);
                continue;
            }

            $table->insert(array_merge([
                'entry_id' => $ownerEntryId,
                'related_entry_id' => $relatedEntryId,
            ], $payload));
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException(lang('Cms.entry_references.sync_failed', [(string) $ownerEntryId]));
        }
    }
}
