<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Adds the shared source selector to collection_grid without replacing an
 * existing block schema. This is intentionally additive so rerunning the
 * bootstrap cannot erase editor-defined block metadata.
 */
class CmsCollectionGridSourceSeeder extends Seeder
{
    public function run(): void
    {
        $db = $this->db;
        $row = $db->table('cms_content_blocks')
            ->where('block_key', 'collection_grid')
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return;
        }

        $schema = json_decode((string) ($row['schema_definition'] ?? ''), true);
        if (! is_array($schema)) {
            return;
        }

        $configFields = is_array($schema['config_fields'] ?? null)
            ? $schema['config_fields']
            : [];
        if (array_key_exists('source_type', $configFields)) {
            return;
        }

        $configFields['source_type'] = [
            'type' => 'select',
            'label' => 'Origen de contenido',
            'description' => 'Usa CMS, catálogo del museo o programación sin cambiar el bloque visual.',
            'required' => false,
            'options' => ['auto', 'cms_collection', 'catalog_items', 'event_items'],
            'default' => 'auto',
        ];
        $schema['config_fields'] = $configFields;

        $db->table('cms_content_blocks')
            ->where('id', (int) $row['id'])
            ->update(['schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
