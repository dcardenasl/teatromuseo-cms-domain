<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Content migration moved to CmsContentSanitizationSeeder.
 *
 * @cms-content-data-migration
 */
final class NormalizePublicDetailTemplateSocialType extends Migration
{
    public function up(): void
    {
        // Content normalization is applied by CmsContentSanitizationSeeder.
    }

    public function down(): void
    {
        // Content migrations are intentionally not destructive.
    }
}
