<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Restores the stable internal collection key for TeatroEscuela.
 *
 * The public nomenclature belongs in localized translations and routes. The
 * internal key remains `cursos` because blocks, presets and services use it
 * as the durable integration identifier.
 *
 * @cms-public-route-data-migration
 */
final class NormalizeTheatreSchoolCollectionKey extends Migration
{
    public function up(): void
    {
        $existing = $this->db->table('cms_collections')
            ->select('id')
            ->where('collection_key', 'cursos')
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            // A prior partial seed may have created the canonical row already.
            // Keep it and remove the duplicate legacy-key row only when it is
            // safe to do so; normally this branch is never reached.
            $legacy = $this->db->table('cms_collections')
                ->select('id')
                ->where('collection_key', 'TeatroEscuela')
                ->get()
                ->getRowArray();

            if ($legacy !== null && (int) $legacy['id'] !== (int) $existing['id']) {
                throw new \RuntimeException('Cannot normalize TeatroEscuela collection key: both cursos and TeatroEscuela exist.');
            }

            return;
        }

        $this->db->query(
            'UPDATE cms_collections SET collection_key = ? WHERE collection_key = ?',
            ['cursos', 'TeatroEscuela'],
        );

        $this->db->query(
            'UPDATE cms_page_translations t '
            . 'INNER JOIN cms_pages p ON p.id = t.page_id '
            . 'INNER JOIN cms_collections c ON c.id = p.collection_id '
            . 'SET t.title = ?, t.meta_title = ? '
            . 'WHERE p.page_type = ? AND c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'collection_index', 'cursos'],
        );
    }

    public function down(): void
    {
        // Forward-only. Reintroducing the display label as an integration key
        // would recreate the collision between internal and public naming.
    }
}
