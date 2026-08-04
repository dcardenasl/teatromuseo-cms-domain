<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Normalizes collection_grid's source and navigation metadata. Editorial
 * content is never touched; only the block type contract is made repeatable.
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
        $configFields['source_type'] = [
            'type' => 'select',
            'label' => 'Origen de contenido',
            'description' => 'Usa CMS, catálogo del museo o programación sin cambiar el bloque visual.',
            'required' => false,
            'options' => ['auto', 'cms_collection', 'catalog_items', 'event_items'],
            'default' => 'auto',
        ];
        $schema['config_fields'] = $configFields;
        $schema['navigation'] = [
            'source' => 'block_config',
            'target' => 'collection_index',
            'required' => false,
        ];

        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        unset($fields['view_all_url']);
        $schema['fields'] = $fields;

        $db->table('cms_content_blocks')
            ->where('id', (int) $row['id'])
            ->update(['schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
