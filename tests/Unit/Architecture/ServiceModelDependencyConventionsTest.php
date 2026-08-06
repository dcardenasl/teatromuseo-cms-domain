<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail against growing direct Model/DB coupling in the service layer
 * (ADR-0002, ADR-005 — Repository/DI pattern; plan maestro H-011, ARCH-01/02).
 *
 * Detects three ways a Service can bypass the Repository/DI seam:
 *   - use_model:   `use App\Models\...;`                    (imported Model class)
 *   - model_call:  `model(\App\Models\X::class)` / `model('App\Models\X')` (inline FQCN resolution)
 *   - db_connect:  `Database::connect()`                      (direct DB connection)
 *
 * This is a ratchet, not a wall (plan maestro ARCH-02: "inventariar violaciones
 * existentes sin permitir ninguna nueva"). BASELINE below pins every currently
 * known violation with its exact per-pattern occurrence count so the test stays
 * green today. It fails if:
 *   - a Service not in BASELINE starts using any of the three patterns, or
 *   - a Service already in BASELINE increases its count for a pattern.
 *
 * It does NOT fail if a count drops or a file stops violating entirely — fixing
 * violations should always be safe. When you fix one, shrink/remove its entry
 * here to keep the ratchet tight.
 */
class ServiceModelDependencyConventionsTest extends CIUnitTestCase
{
    /**
     * Known violations as of 2026-07-11. Do not add entries without a matching
     * architecture decision — prefer extending the repository/service DI pattern.
     *
     * @var array<string, array<string, int>>
     */
    private const BASELINE = [
        'app/Services/Cms/AnalyticsService.php' => ['use_model' => 1],
        // 2026-08-06 (LAYER-05): NEW entry — same shape as AnalyticsService
        // above (single constructor-injected Model, no repository exists for
        // this table). RequestMetricsService is the aggregation logic
        // extracted from RequestLogModel::getStats(); see its own docblock.
        'app/Services/System/RequestMetricsService.php' => ['use_model' => 1],
        // 2026-07-21 (DOM-114): model_call grew from 3 to 5 — moved the
        // instance->entry->collection->blockType lock-check inline from
        // BlockInstanceController::assertBlockNotLocked() into
        // beforeDelete()/assertBlockNotLocked() here (2 new call sites:
        // EntryModel, CollectionModel; the block_type lookup reuses the
        // existing blockTypeById() helper, no new call site). Same coupling,
        // now correctly owned by the service instead of leaking into the
        // controller.
        'app/Services/Cms/BlockInstanceService.php' => ['model_call' => 5],
        // 2026-08-06 (LAYER-03): NEW entry, but a net reduction in raw DB
        // access, not new debt — this file used to run 3 single-table
        // queries against a constructor-injected BaseConnection (not caught
        // by this ratchet's patterns at all, since it isn't
        // `Database::connect()` or `model()`). Replaced with
        // BlockInstanceModel::findAllForBlockType() and
        // CollectionModel::existsByKey() (constructor-injected Models
        // instead), which the ratchet does catch as `use_model`. Net effect:
        // the file is more honest about its coupling now, not less clean.
        'app/Services/Cms/BlockTypeService.php' => ['use_model' => 2],
        // 2026-08-06 (LAYER-03): all 3 db_connect call sites (join against
        // cms_content_blocks for schema_definition) migrated into
        // BlockInstanceModel::findAllWithBlockType()/findOneWithBlockType() —
        // db_connect 3 -> 0. model_call grew 1 -> 2 for the new
        // `model(BlockInstanceModel::class)` constructor injection (same
        // lazy-model pattern already used for blockInstanceTranslationModel
        // in this file); the join itself still uses the query builder, but
        // now owned by the Model layer instead of called raw from the
        // Service, which is where ADR-0002/ADR-005 says it belongs.
        'app/Services/Cms/BlockInstanceTranslationAuditor.php' => ['model_call' => 2],
        'app/Services/Cms/CategoryService.php' => ['model_call' => 5],
        'app/Services/Cms/CollectionService.php' => ['model_call' => 4],
        // 2026-08-06 (LAYER-03 audit pass): db_connect here is
        // `Config\Database::connect()->transStart()/transComplete()/
        // transRollback()` wrapping 5 separate model-layer inserts across a
        // single logical transaction — not a query bypass (no `->table()`/
        // `->builder()` call exists on this connection anywhere in the
        // file). CI4 models sharing the same DB group share the same
        // underlying connection, so this is the idiomatic way to coordinate
        // a multi-model transaction; there's no Model method to move this
        // into. Deliberately left as-is, not silently ratified.
        'app/Services/Cms/EntryBlockTemplateInitializer.php' => ['model_call' => 5, 'db_connect' => 1],
        // 2026-08-06 (LAYER-03): all 3 db_connect call sites migrated into
        // Models — db_connect 3 -> 0, model_call 11 -> 14. The active-language
        // lookup (was a raw `cms_languages` query) now goes through
        // LanguageModel; the two pivot-table delete+insertBatch pairs
        // (cms_entry_categories/cms_entry_tags) now go through the new
        // EntryCategoryModel::replaceForEntry()/EntryTagModel::replaceForEntry()
        // (plain Models, not BaseAuditableModel — see those files' docblocks
        // for why). Net effect: same query count, owned by the Model layer.
        'app/Services/Cms/EntryService.php' => ['model_call' => 14],
        // FormService.php was split 2026-07-19 into three single-responsibility
        // classes (form CRUD, field CRUD, public definition assembly); the
        // model coupling below is the same coupling redistributed, not new debt.
        'app/Services/Cms/FormFieldService.php' => ['use_model' => 3, 'model_call' => 1],
        'app/Services/Cms/FormPublicDefinitionAssembler.php' => ['use_model' => 5, 'model_call' => 2],
        // 2026-08-06 (LAYER-03): use_model grew 2 -> 3 for the new
        // FormSubmissionModel constructor dependency, which replaced a raw
        // `$this->db->table('cms_form_submissions')->countAllResults()` call
        // in delete() with FormSubmissionModel::countByFormId(). The other
        // db-builder call site in this file (getUsages(), a JSON_EXTRACT
        // join) is a documented, deliberate exception — see its inline
        // comment — not caught by this ratchet's patterns since $this->db is
        // constructor-injected rather than a `Database::connect()` call.
        'app/Services/Cms/FormService.php' => ['use_model' => 3],
        // 2026-08-06 (LAYER-03): db_connect 1 -> 0 — countByStatus() moved its
        // single-table GROUP BY into FormSubmissionModel::countByStatus().
        'app/Services/Cms/FormSubmissionService.php' => ['use_model' => 1, 'model_call' => 3],
        'app/Services/Cms/LanguageService.php' => ['model_call' => 2],
        'app/Services/Cms/MenuItemService.php' => ['model_call' => 6],
        'app/Services/Cms/MenuService.php' => ['model_call' => 2],
        'app/Services/Cms/PageService.php' => ['model_call' => 8],
        // 2026-08-02: model_call grew from 6 to 7 — added blockInstanceTranslationModel()
        // (same lazy-getter pattern as the other 6 models already here) so
        // batchResolveCursoStartDates() could join cms_block_instance_translations ->
        // cms_block_instances -> cms_content_blocks via the Model layer instead of
        // Database::connect() (which would have added a new db_connect violation
        // instead). Needed for the cursos listing's "upcoming first, then most-recent-
        // past" ordering — start_date lives in a block's translated block_data, not a
        // cms_entries column, so no existing model already exposed it.
        'app/Services/Cms/PublicEntryReader.php' => ['model_call' => 7],
        'app/Services/Cms/SettingService.php' => ['model_call' => 2],
        'app/Services/Cms/TagService.php' => ['model_call' => 4],
    ];

    /** @var array<string, string> */
    private const PATTERNS = [
        'use_model' => '/^use\s+App\\\\Models\\\\/m',
        'model_call' => '/\bmodel\s*\(/',
        'db_connect' => '/\bDatabase\s*::\s*connect\s*\(/',
    ];

    public function testServicesDoNotGrowDirectModelOrDbCoupling(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $serviceDir = $root . DIRECTORY_SEPARATOR . 'app/Services';

        /** @var array<string, array<string, int>> $actual */
        $actual = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($serviceDir));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                continue;
            }

            // Strip comments and string literals so text inside them can't trigger
            // a false positive, while preserving line breaks for multi-line regexes.
            $code = '';
            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
                    $code .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR));

            foreach (self::PATTERNS as $name => $pattern) {
                $count = preg_match_all($pattern, $code);
                if ($count > 0) {
                    $actual[$relative][$name] = $count;
                }
            }
        }

        ksort($actual);

        $violations = [];
        foreach ($actual as $relative => $byPattern) {
            $baselineForFile = self::BASELINE[$relative] ?? null;
            if ($baselineForFile === null) {
                $violations[] = sprintf(
                    '%s: NEW file not in baseline (%s)',
                    $relative,
                    implode(', ', array_map(static fn (string $p, int $c): string => "{$p}={$c}", array_keys($byPattern), $byPattern))
                );
                continue;
            }

            foreach ($byPattern as $pattern => $count) {
                $allowed = $baselineForFile[$pattern] ?? 0;
                if ($count > $allowed) {
                    $violations[] = "{$relative}: {$pattern} count {$count} exceeds baseline {$allowed}";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Service layer Model/DB coupling grew beyond the pinned baseline:\n- " . implode("\n- ", $violations) . "\n\n" .
            'Prefer repositories/interfaces (ADR-0002, ADR-005). If this is a justified exception, ' .
            'update ServiceModelDependencyConventionsTest::BASELINE deliberately — do not raise counts silently.'
        );
    }
}
