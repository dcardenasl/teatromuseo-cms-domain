<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Aligns the generated collection index page titles with TeatroEscuela.
 *
 * @cms-public-route-data-migration
 */
final class NormalizeTheatreSchoolPageTitles extends Migration
{
    public function up(): void
    {
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
        // Forward-only. Historical “Cursos” titles are not part of the public
        // nomenclature and must not be restored.
    }
}
