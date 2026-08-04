<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Re-applies the canonical navigation metadata to block types.
 *
 * Block instances do not own a schema snapshot: BlockInstanceSerializer joins
 * cms_block_instances with cms_content_blocks and reads the schema from the
 * block type. Keeping this migration scoped to cms_content_blocks makes it
 * safe for existing instances and prevents a second, divergent schema source.
 *
 * @cms-schema-data-migration
 */
final class SyncBlockInstanceNavigationSchemas extends Migration
{
    private const BLOCK_KEYS = ['collection_grid', 'collection_listing', 'page_header'];

    public function up(): void
    {
        $rows = $this->db->table('cms_content_blocks')
            ->select('id, block_key, schema_definition')
            ->whereIn('block_key', self::BLOCK_KEYS)
            ->get()
            ->getResultArray();

        $table = $this->db->table('cms_content_blocks');
        foreach ($rows as $row) {
            $blockKey = (string) ($row['block_key'] ?? '');
            $schema = json_decode((string) ($row['schema_definition'] ?? ''), true);
            if (! in_array($blockKey, self::BLOCK_KEYS, true) || ! is_array($schema)) {
                continue;
            }

            $normalized = $this->normalizeSchema($schema, $blockKey);
            if ($normalized === $schema) {
                continue;
            }

            $table->where('id', (int) $row['id'])->update([
                'schema_definition' => json_encode(
                    $normalized,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: restoring the obsolete snapshot would reintroduce a
        // second navigation contract and cannot be done safely per instance.
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema, string $blockKey): array
    {
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        if ($blockKey === 'collection_grid') {
            unset($fields['view_all_url']);
        }
        if ($blockKey === 'page_header') {
            unset($fields['breadcrumb_url']);
        }
        $schema['fields'] = $fields;

        $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
        if ($blockKey === 'collection_listing') {
            unset($configFields['source_path']);
        }
        $schema['config_fields'] = $configFields;

        $schema['navigation'] = match ($blockKey) {
            'collection_grid' => [
                'source' => 'block_config',
                'target' => 'collection_index',
                'required' => false,
            ],
            'collection_listing' => [
                'source' => 'block_config',
                'target' => 'listing_page',
                'required' => false,
                'event_page_type' => 'events',
                'catalog_page_type' => 'catalog_listing',
            ],
            default => [
                'source' => 'owner',
                'target' => 'parent_page',
                'required' => false,
            ],
        };

        return $schema;
    }
}
