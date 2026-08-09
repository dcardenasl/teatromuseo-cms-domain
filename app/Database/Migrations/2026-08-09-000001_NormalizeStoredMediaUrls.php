<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Content migration moved to CmsContentSanitizationSeeder.
 *
 * @cms-content-data-migration
 */
final class NormalizeStoredMediaUrls extends Migration
{
    public function up(): void
    {
        // Stubbed - logic lives in CmsContentSanitizationSeeder.
    }

    public function down(): void
    {
        // Portable URLs intentionally have no host to restore.
    }
}
