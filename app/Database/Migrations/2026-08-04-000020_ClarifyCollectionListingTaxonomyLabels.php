<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Clarifies that listing taxonomy switches control different public surfaces.
 *
 * @cms-content-data-migration
 */
final class ClarifyCollectionListingTaxonomyLabels extends Migration
{
    public function up(): void
    {
        $rows = $this->db->table('cms_content_blocks')
            ->select('id, schema_definition')
            ->where('block_key', 'collection_listing')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $schema = json_decode((string) ($row['schema_definition'] ?? '{}'), true);
            if (! is_array($schema)) {
                continue;
            }

            $configFields = is_array($schema['config_fields'] ?? null) ? $schema['config_fields'] : [];
            if (is_array($configFields['show_tags'] ?? null)) {
                $configFields['show_tags']['label'] = 'Mostrar filtro por etiquetas o tipo';
                $configFields['show_tags']['description'] = 'Muestra chips de filtro sobre el listado. No muestra la etiqueta dentro de cada tarjeta.';
            }
            if (is_array($configFields['show_item_categories'] ?? null)) {
                $configFields['show_item_categories']['label'] = 'Mostrar clasificación en cada tarjeta';
                $configFields['show_item_categories']['description'] = 'Muestra la categoría o el tipo del elemento dentro de su tarjeta. No crea filtros.';
            }

            $schema['config_fields'] = $configFields;
            $this->db->table('cms_content_blocks')
                ->where('id', (int) ($row['id'] ?? 0))
                ->update(['schema_definition' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    public function down(): void
    {
        // Keep the clarified schema labels on rollback; they are editorial metadata only.
    }
}
