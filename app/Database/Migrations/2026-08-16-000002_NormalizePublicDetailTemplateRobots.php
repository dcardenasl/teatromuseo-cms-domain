<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Content migration moved to CmsContentSanitizationSeeder.
 *
 * @cms-content-data-migration
 */
final class NormalizePublicDetailTemplateRobots extends Migration
{
    public function up(): void
    {
        // Content normalization is applied by CmsContentSanitizationSeeder.
    }

    public function down(): void
    {
        // Content normalization is forward-only.
    }
}
