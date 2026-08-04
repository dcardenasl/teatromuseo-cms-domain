<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes block navigation declarative and removes editorial fields that were
 * never the source of truth for public URLs.
 *
 * @cms-schema-data-migration
 *
 * Existing block instances are intentionally untouched. Removing a field from
 * a schema changes what can be authored going forward; it does not erase JSON
 * already stored in editorial translations.
 */
final class NormalizeBlockNavigationSchemas extends Migration
{
    public function up(): void
    {
        $table = $this->db->table('cms_content_blocks');

        foreach (['collection_grid', 'collection_listing', 'page_header'] as $blockKey) {
            $row = $table->where('block_key', $blockKey)->get()->getRowArray();
            if (! is_array($row)) {
                continue;
            }

            $schema = json_decode((string) ($row['schema_definition'] ?? ''), true);
            if (! is_array($schema)) {
                continue;
            }

            $changed = false;
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            if ($blockKey === 'collection_grid' && array_key_exists('view_all_url', $fields)) {
                unset($fields['view_all_url']);
                $changed = true;
            }
            if ($fields !== ($schema['fields'] ?? null)) {
                $schema['fields'] = $fields;
                $changed = true;
            }

            $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
            if ($blockKey === 'collection_listing' && array_key_exists('source_path', $configFields)) {
                unset($configFields['source_path']);
                $changed = true;
            }
            if ($configFields !== ($schema['config_fields'] ?? null)) {
                $schema['config_fields'] = $configFields;
                $changed = true;
            }

            if ($blockKey === 'page_header' && array_key_exists('breadcrumb_url', $fields)) {
                unset($fields['breadcrumb_url']);
                $schema['fields'] = $fields;
                $changed = true;
            }

            $navigation = $blockKey === 'collection_grid'
                ? [
                    'source' => 'block_config',
                    'target' => 'collection_index',
                    'required' => false,
                ]
                : ($blockKey === 'collection_listing'
                ? [
                    'source' => 'block_config',
                    'target' => 'listing_page',
                    'required' => false,
                    'event_page_type' => 'events',
                    'catalog_page_type' => 'catalog_listing',
                ]
                : [
                    'source' => 'owner',
                    'target' => 'parent_page',
                    'required' => false,
                ]);
            if (($schema['navigation'] ?? null) !== $navigation) {
                $schema['navigation'] = $navigation;
                $changed = true;
            }

            if ($changed) {
                $table->where('id', (int) $row['id'])->update([
                    'schema_definition' => json_encode(
                        $schema,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                    ),
                ]);
            }
        }
    }

    public function down(): void
    {
        // The migration is intentionally forward-only: restoring obsolete
        // authoring fields would reintroduce a second navigation contract.
    }
}
