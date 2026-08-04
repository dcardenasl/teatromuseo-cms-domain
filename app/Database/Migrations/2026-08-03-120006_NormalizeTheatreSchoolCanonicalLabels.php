<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Synchronizes persisted TeatroEscuela labels with the current seed contract.
 *
 * The name is a product/section name, not a translatable legacy alias. It is
 * intentionally identical in every locale; only the public route is localized.
 *
 * @cms-public-route-data-migration
 */
final class NormalizeTheatreSchoolCanonicalLabels extends Migration
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
        // Forward-only. Legacy localized course labels are not canonical.
    }
}
