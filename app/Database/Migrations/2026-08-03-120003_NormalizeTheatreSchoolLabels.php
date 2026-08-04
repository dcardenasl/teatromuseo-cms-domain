<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Brings existing TeatroEscuela collection labels in line with the canonical
 * public nomenclature already used by the bootstrap seeders.
 *
 * @cms-public-route-data-migration
 */
final class NormalizeTheatreSchoolLabels extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'UPDATE cms_collection_translations t '
            . 'INNER JOIN cms_collections c ON c.id = t.collection_id '
            . 'SET t.name = ?, t.listing_title = ?, t.default_meta_title = ? '
            . 'WHERE c.collection_key = ?',
            ['TeatroEscuela', 'TeatroEscuela', 'TeatroEscuela | TeatroMuseo', 'cursos'],
        );
    }

    public function down(): void
    {
        // Forward-only. Restoring the historical “Cursos” labels would make
        // the public section contradict its canonical navigation name.
    }
}
