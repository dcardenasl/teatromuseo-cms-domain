<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives already-published legacy entries a deterministic publication date.
 *
 * New entries already receive published_at when they transition to published
 * through EntryService. This migration repairs older/imported records that
 * were published with a null value, so publication-date sorting remains
 * meaningful for every public collection.
 *
 * @cms-content-data-migration
 */
final class BackfillPublishedAtForPublishedEntries extends Migration
{
    public function up(): void
    {
        $this->db->table('cms_entries')
            ->set('published_at', 'created_at', false)
            ->where('workflow_status', 'published')
            ->where('published_at IS NULL', null, false)
            ->update();
    }

    public function down(): void
    {
        // The original publication timestamp cannot be recovered safely.
    }
}
