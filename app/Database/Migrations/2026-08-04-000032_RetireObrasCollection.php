<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retires the empty legacy Obras collection without deleting its history.
 *
 * @cms-content-data-migration
 */
final class RetireObrasCollection extends Migration
{
    public function up(): void
    {
        $this->db->table('cms_collections')
            ->where('collection_key', 'obras')
            ->update(['is_active' => 0]);
    }

    public function down(): void
    {
        // The legacy collection remains retired after rollback.
    }
}
