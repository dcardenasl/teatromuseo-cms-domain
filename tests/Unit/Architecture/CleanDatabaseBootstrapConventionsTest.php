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
                // The verb must name the operation the migration performs on
                // existing content. The list was widened on 2026-08-05 to the
                // set actually in use: 19 content migrations had landed since
                // this guardrail was written without carrying the
                // `@cms-content-data-migration` marker, so they fell through to
                // the schema branch below and the suite had been red ever since.
                // They are now tagged and their verbs enumerated here.
                //
                // Extend this list rather than renaming an applied migration:
                // CI4 keys the `migrations` table by filename, so a rename makes
                // the migration run a second time.
                //
                // MIG-01 (2026-08-06) went further: all 41 of these migrations
                // were stubbed into no-ops and their actual CMS-table mutation
                // was ported into a correspondingly named `sanitize_*` method on
                // `CmsContentSanitizationSeeder`, invoked idempotently from
                // `SiteBootstrapSeeder` instead of `spark migrate` — which used
                // to revert edits an editor made in the admin. A stub therefore
                // no longer touches `cms_` itself; it must instead (a) document
                // where its logic went and (b) never regain a real operation.
                // testContentDataMigrationsMapToASanitizationSeederMethod()
                // below is what now enforces that the CMS-table mutation still
                // exists, on the seeder side.
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}-\d{6}_(?:Add|Backfill|Bind|Canonicalize|Clarify|Complete|Consolidate|Create|Enhance|Label|Move|Normalize|Persist|Preserve|Remove|Rename|Restore|Retire|Split|Sync|Unify)[A-Za-z0-9]+\.php$/',
                    $name,
                    "CMS content data migration {$name} must describe its normalization operation."
                );
                $this->assertStringContainsString(
                    'Content migration moved to CmsContentSanitizationSeeder.',
                    $source,
                    "Stubbed content migration {$name} must document that its logic moved to CmsContentSanitizationSeeder."
                );

                foreach ([
                    '/\bALTER\s+TABLE\b/i',
                    '/->(?:addColumn|modifyColumn|dropColumn|createTable)\s*\(/',
                    '/->(?:insert|insertBatch|update|delete|truncate)\s*\(/',
                    '/\bINSERT\s+INTO\b/i',
                    '/\bDELETE\s+FROM\b/i',
                    '/\bUPDATE\s+`[^`]+`\s+/i',
                ] as $forbiddenPattern) {
                    $this->assertDoesNotMatchRegularExpression(
                        $forbiddenPattern,
                        $source,
                        "Stubbed content migration {$name} must not perform any schema or data operation — that logic belongs in CmsContentSanitizationSeeder now."
                    );
                }
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

    /**
     * MIG-01 stubbed every `@cms-content-data-migration` into a no-op and
     * ported its real work into `CmsContentSanitizationSeeder`. This is the
     * guardrail that used to assert the migration file itself touched
     * `cms_`; now that the mutation lives in the seeder, it asserts the same
     * thing one hop over — that every stub still has a live, CMS-scoped
     * `sanitize_*` counterpart, called in the same chronological order the
     * migrations used to run in.
     */
    public function testContentDataMigrationsMapToASanitizationSeederMethod(): void
    {
        $files = glob(APPPATH . 'Database/Migrations/*.php');
        $this->assertIsArray($files);
        sort($files);

        $stubbedNames = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            if (! str_contains($source, '@cms-content-data-migration')) {
                continue;
            }

            $stubbedNames[] = preg_replace('/^\d{4}-\d{2}-\d{2}-\d{6}_(.+)\.php$/', '$1', basename($file));
        }
        $this->assertNotEmpty($stubbedNames, 'Expected at least one @cms-content-data-migration stub.');

        $seederSource = file_get_contents(APPPATH . 'Database/Seeds/CmsContentSanitizationSeeder.php');
        $this->assertIsString($seederSource);

        $this->assertSame(
            1,
            preg_match('/public function run\(\): void\s*\{(.*?)\n    \}/s', $seederSource, $runMatch),
            'CmsContentSanitizationSeeder::run() must be present and parseable.'
        );
        preg_match_all('/\$this->sanitize_([A-Za-z0-9]+)\(\);/', $runMatch[1], $callMatches);
        $calledMethods = $callMatches[1];

        // One sanitize_* call per stub, in the same chronological order —
        // this is what lets a reader trace a migration filename forward to
        // the code that replaced it after MIG-01.
        $this->assertSame(
            count($stubbedNames),
            count($calledMethods),
            'CmsContentSanitizationSeeder::run() must call exactly one sanitize_* method per @cms-content-data-migration stub, in the same chronological order.'
        );

        // Split into one chunk per declared primary sanitize_* method (its
        // private `Sanitize_Foo_helper()` collaborators use a capitalized
        // prefix and stay inside their owner's chunk) so each method's own
        // body — not just anywhere in this 3000+ line file — is what gets
        // checked for a real CMS-table mutation.
        $methodChunks = preg_split('/\n    private function sanitize_/', $seederSource);
        array_shift($methodChunks); // drop the class preamble / run() body

        $methodBodies = [];
        foreach ($methodChunks as $chunk) {
            $this->assertSame(1, preg_match('/^([A-Za-z0-9]+)\(/', $chunk, $nameMatch));
            $methodBodies[$nameMatch[1]] = $chunk;
        }

        foreach ($calledMethods as $methodName) {
            $this->assertArrayHasKey(
                $methodName,
                $methodBodies,
                "CmsContentSanitizationSeeder calls sanitize_{$methodName}() but declares no such private method."
            );
            $this->assertStringContainsString(
                'cms_',
                $methodBodies[$methodName],
                "sanitize_{$methodName}() must operate on a cms_ table — it stands in for a migration that used to."
            );
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
