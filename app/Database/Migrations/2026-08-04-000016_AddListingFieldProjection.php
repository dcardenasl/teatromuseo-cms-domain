<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the explicit public date-field projection to existing block schemas.
 *
 * @cms-content-data-migration
 */
final class AddListingFieldProjection extends Migration
{
    public function up(): void
    {
        $rows = $this->db->table('cms_content_blocks')->select('id, block_key, schema_definition')->where('is_active', 1)->get()->getResultArray();
        foreach ($rows as $row) {
            $schema = json_decode((string) ($row['schema_definition'] ?? '{}'), true);
            if (! is_array($schema)) {
                continue;
            }
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $listingFields = [];
            foreach ($fields as $key => $field) {
                if (is_array($field) && ($field['type'] ?? '') === 'date') {
                    $listingFields[(string) $key] = ['label' => (string) ($field['label'] ?? $key), 'type' => 'date'];
                }
            }
            if (in_array((string) ($row['block_key'] ?? ''), ['collection_grid', 'collection_listing'], true)) {
                $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
                $configFields['date_field'] = [
                    'type' => 'select',
                    'label' => 'Fecha visible en tarjeta',
                    'description' => 'Usa una fecha declarada por la ficha o una fecha editorial estándar.',
                    'options' => ['auto', 'published_at', 'created_at', 'listing.publication_date', 'listing.start_date', 'listing.end_date', 'listing.opening_date', 'listing.closing_date', 'listing.premiere_date', 'listing.performance_date', 'listing.recorded_at'],
                    'default' => 'auto',
                    'required' => false,
                ];
                $schema['config_fields'] = $configFields;
            }
            if ($listingFields === [] && ! isset($schema['listing_fields']) && ! in_array((string) ($row['block_key'] ?? ''), ['collection_grid', 'collection_listing'], true)) {
                continue;
            }
            $schema['listing_fields'] = $listingFields;
            $this->db->table('cms_content_blocks')->where('id', (int) $row['id'])->update([
                'schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        $blockIds = $this->blockIds(['collection_grid', 'collection_listing']);
        if ($blockIds === []) {
            return;
        }
        $instances = $this->db->table('cms_block_instances i')
            ->select('i.id, i.block_config, c.collection_key')
            ->join('cms_collections c', "c.id = JSON_UNQUOTE(JSON_EXTRACT(i.block_config, '$.collection_id'))", 'left', false)
            ->whereIn('i.block_id', $blockIds)
            ->get()
            ->getResultArray();
        foreach ($instances as $instance) {
            $config = json_decode((string) ($instance['block_config'] ?? '{}'), true);
            if (! is_array($config)) {
                continue;
            }
            $collectionKey = strtolower((string) ($config['collection_key'] ?? $instance['collection_key'] ?? ''));
            if (! in_array($collectionKey, ['teatroescuela', 'cursos'], true)) {
                continue;
            }
            $config['date_field'] = 'listing.start_date';
            $config['order_by'] = 'field:start_date';
            $config['order_direction'] = 'asc';
            $this->db->table('cms_block_instances')->where('id', (int) $instance['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        // Projection metadata is additive and safe to retain on rollback.
    }

    /** @param list<string> $keys @return list<int> */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')->select('id')->whereIn('block_key', $keys)->get()->getResultArray();
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }
}
