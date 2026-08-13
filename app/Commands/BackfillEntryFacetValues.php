<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Config\Services;
use dcardenasl\Ci4ApiCore\Support\JsonCastNormalizer;

/**
 * One-off backfill: materializes `cms_entry_facet_values` for every
 * entry-owned block instance translation that already exists, so
 * `filter_by`/`order_by=field:...` public listings work against the indexed
 * table immediately after the 2026-08-12-100001_CreateCmsEntryFacetValues
 * migration, without waiting for each entry to be re-saved.
 *
 * Delegates the actual field resolution/casting to
 * EntryFacetValueSynchronizer — the exact same code path the write hooks in
 * BlockInstanceService/EntryBlockTemplateInitializer use — so there is no
 * second, drifting implementation of "which fields are facetable."
 *
 * Dry-run by default; pass --confirm to write.
 */
class BackfillEntryFacetValues extends BaseCommand
{
    protected $group       = 'CMS';
    protected $name        = 'cms:backfill-entry-facet-values';
    protected $description = 'Materializes cms_entry_facet_values for existing entry-owned block instances.';

    protected $usage = 'cms:backfill-entry-facet-values [--confirm] [--batch-size=500]';

    protected $options = [
        '--confirm'    => 'Actually write the rows. Without it, only reports what would be synced.',
        '--batch-size' => 'Rows scanned per batch (default 500).',
    ];

    public function run(array $params): void
    {
        $confirm   = (bool) CLI::getOption('confirm');
        $batchSize = max(1, (int) (CLI::getOption('batch-size') ?? 500));

        $db = Database::connect();
        if (! $db->tableExists('cms_entry_facet_values')) {
            CLI::error('Table cms_entry_facet_values is missing. Run migrations first.');

            return;
        }

        $synchronizer = Services::entryFacetValueSynchronizer();

        $total           = 0;
        $synced          = 0;
        $skippedNoSchema = 0;
        $lastId          = 0;

        while (true) {
            $result = $db->table('cms_block_instance_translations bit')
                ->select('bit.id AS translation_id, bit.instance_id, bit.language_id, bit.block_data, bi.owner_id AS entry_id, cb.block_key, cb.schema_definition')
                ->join('cms_block_instances bi', 'bi.id = bit.instance_id')
                ->join('cms_content_blocks cb', 'cb.id = bi.block_id')
                ->where('bi.owner_type', 'entry')
                ->where('bit.id >', $lastId)
                ->orderBy('bit.id', 'ASC')
                ->limit($batchSize)
                ->get();
            $rows = $result === false ? [] : $result->getResultArray();

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row['translation_id'];
                $total++;

                $schema       = JsonCastNormalizer::toArray($row['schema_definition'] ?? null);
                $schemaFields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
                if ($schemaFields === []) {
                    $skippedNoSchema++;

                    continue;
                }

                $blockData = JsonCastNormalizer::toArray($row['block_data'] ?? null);

                if ($confirm) {
                    $synchronizer->sync(
                        (int) $row['entry_id'],
                        (int) $row['instance_id'],
                        (string) $row['block_key'],
                        (int) $row['language_id'],
                        $blockData,
                        $schemaFields
                    );
                }
                $synced++;
            }

            CLI::write("Processed up to translation id {$lastId} ({$total} row(s) scanned so far)...");
        }

        CLI::write('');
        CLI::write(
            ($confirm ? 'Synced' : 'Dry-run — would sync') . " {$synced} block instance translation(s) (of {$total} entry-owned rows scanned).",
            $confirm ? 'green' : 'yellow'
        );
        if ($skippedNoSchema > 0) {
            CLI::write("Skipped (block type declares no schema fields): {$skippedNoSchema}", 'yellow');
        }
        if (! $confirm) {
            CLI::write('Re-run with --confirm to write.', 'yellow');
        }
    }
}
