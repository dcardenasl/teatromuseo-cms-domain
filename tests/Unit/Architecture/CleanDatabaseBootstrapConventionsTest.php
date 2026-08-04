<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Prevents the clean-install database baseline from accumulating corrective
 * migrations or repair seeders again.
 *
 * @internal
 */
final class CleanDatabaseBootstrapConventionsTest extends CIUnitTestCase
{
    public function testMigrationsOnlyCreateAndDropFinalSchema(): void
    {
        $files = glob(APPPATH . 'Database/Migrations/*.php');
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $name = basename($file);
            $source = file_get_contents($file);

            $this->assertIsString($source);

            // CMS block schemas are JSON documents stored in a content table,
            // so their forward-only normalization is a data migration rather
            // than a database-schema migration. It is explicitly marked in
            // the migration docblock and must remain idempotent and scoped to
            // the CMS schema table.
            if (str_contains($source, '@cms-schema-data-migration')) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}-\d{6}_Normalize[A-Za-z0-9]+\.php$/',
                    $name,
                    "CMS schema data migration {$name} must describe its normalization operation."
                );
                $this->assertStringContainsString("table('cms_content_blocks')", $source);
                $this->assertStringContainsString('JSON_THROW_ON_ERROR', $source);
                $this->assertDoesNotMatchRegularExpression('/\b(?:delete|truncate)\s*\(/i', $source);
                continue;
            }

            // Public route slugs are structural CMS data. They have an
            // explicit forward-only contract so existing installations can
            // be upgraded without turning ordinary bootstrap seeders into
            // destructive repair scripts.
            if (str_contains($source, '@cms-public-route-data-migration')) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}-\d{6}_Normalize[A-Za-z0-9]+\.php$/',
                    $name,
                    "CMS public route data migration {$name} must describe its normalization operation."
                );
                $this->assertTrue(
                    str_contains($source, 'cms_page_translations')
                    || str_contains($source, 'cms_collection_translations'),
                    "CMS public route data migration {$name} must target public translation data."
                );
                $this->assertDoesNotMatchRegularExpression('/\b(?:delete|truncate)\s*\(/i', $source);
                continue;
            }

            if (str_contains($source, '@cms-content-data-migration')) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}-\d{6}_(?:Add|Split|Normalize|Label|Enhance|Bind)[A-Za-z0-9]+\.php$/',
                    $name,
                    "CMS content data migration {$name} must describe its normalization operation."
                );
                $this->assertStringContainsString('cms_', $source);
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}-\d{6}_Create[A-Za-z0-9]+\.php$/',
                $name,
                "Migration {$name} must describe a canonical create operation."
            );
            $this->assertStringContainsString('createTable(', $source, "Migration {$name} must create schema.");
            $this->assertStringContainsString('dropTable(', $source, "Migration {$name} must provide a real rollback.");

            foreach ([
                '/\bALTER\s+TABLE\b/i',
                '/->(?:addColumn|modifyColumn|dropColumn)\s*\(/',
                '/->(?:insert|insertBatch|update|delete|truncate)\s*\(/',
                '/\bINSERT\s+INTO\b/i',
                '/\bDELETE\s+FROM\b/i',
                '/\bUPDATE\s+`[^`]+`\s+/i',
            ] as $forbiddenPattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $forbiddenPattern,
                    $source,
                    "Migration {$name} contains schema patching or data mutation."
                );
            }
        }
    }

    public function testSeederNamesDescribeInitialDataInsteadOfRepairs(): void
    {
        $files = glob(APPPATH . 'Database/Seeds/*.php');
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?:Backfill|Cleanup|Fix|Migrate|Normalize|Repair)/i',
                basename($file),
                'Seeders must create canonical reference or demo data, not repair an older database.'
            );
        }
    }

    public function testSeedersUseCanonicalNestedMediaReferences(): void
    {
        $files = glob(APPPATH . 'Database/Seeds/*.php');
        $concernFiles = glob(APPPATH . 'Database/Seeds/Concerns/*.php');
        $this->assertIsArray($files);
        $this->assertIsArray($concernFiles);

        foreach (array_merge($files, $concernFiles) as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            $this->assertDoesNotMatchRegularExpression(
                "/'(?:image|photo|poster|document|file)_url'\s*=>/",
                $source,
                basename($file) . ' persists a retired flat media key instead of a media_reference object.'
            );
        }
    }
}
