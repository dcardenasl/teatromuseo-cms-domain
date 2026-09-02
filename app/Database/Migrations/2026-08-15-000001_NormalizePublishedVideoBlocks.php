<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stubbed migration.
 * Content migration moved to CmsContentSanitizationSeeder.
 *
 * @cms-content-data-migration
 */
final class NormalizePublishedVideoBlocks extends Migration
{
    public function up(): void
    {
        // Content normalization is intentionally executed by the idempotent
        // SiteBootstrapSeeder, not by `spark migrate`.
    }

    public function down(): void
    {
        // Content normalization is forward-only; reverting it could undo an
        // editor's later publication decision.
    }
}
