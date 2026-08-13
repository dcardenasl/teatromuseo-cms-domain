<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Database\QueryInterface;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * @internal
 */
final class PublicReadQueryBudgetTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use WithWebAppKeyTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->table('cms_page_translations')->where('id >', 0)->delete();
        $this->db->table('cms_pages')->where('id >', 0)->delete();
        $this->db->table('cms_entry_facet_values')->where('id >', 0)->delete();
        $this->db->table('cms_block_instance_translations')->where('id >', 0)->delete();
        $this->db->table('cms_block_instances')->where('id >', 0)->delete();
        $this->db->table('cms_content_blocks')->where('id >', 0)->delete();
        $this->db->table('cms_entry_translations')->where('id >', 0)->delete();
        $this->db->table('cms_entries')->where('id >', 0)->delete();
        $this->db->table('cms_collections')->where('id >', 0)->delete();
        $this->db->table('cms_languages')->where('id >', 0)->delete();
        $this->db->enableForeignKeyChecks();
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    public function testPageListingKeepsAStableQueryBudgetAndUsesTheExistingStatusIndex(): void
    {
        $this->db->table('cms_languages')->insertBatch([
            [
                'code' => 'es',
                'name' => 'Spanish',
                'native_name' => 'Español',
                'is_default' => 1,
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_default' => 0,
                'is_active' => 1,
                'sort_order' => 2,
            ],
        ]);
        $languages = $this->db->table('cms_languages')->select('id, code')->orderBy('id', 'ASC')->get()->getResultArray();
        $esId = (int) $languages[0]['id'];

        $pages = [];
        for ($index = 0; $index < 2000; $index++) {
            $published = $index < 240;
            $pages[] = [
                'page_type' => 'generic',
                'status' => $published ? 'published' : 'draft',
                'sort_order' => $index,
                'is_in_sitemap' => 1,
                'created_at' => '2026-08-10 12:00:00',
                'updated_at' => '2026-08-10 12:00:00',
            ];
        }
        $this->db->table('cms_pages')->insertBatch($pages);

        $publishedPages = $this->db->table('cms_pages')
            ->select('id, sort_order')
            ->where('status', 'published')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $this->assertCount(240, $publishedPages);

        $translations = [];
        foreach ($publishedPages as $page) {
            $id = (int) $page['id'];
            $translations[] = [
                'page_id' => $id,
                'language_id' => $esId,
                'slug' => sprintf('qa02-page-%04d', (int) $page['sort_order']),
                'title' => sprintf('QA-02 page %04d', (int) $page['sort_order']),
                'excerpt' => 'QA-02 fixture',
                'created_at' => '2026-08-10 12:00:00',
                'updated_at' => '2026-08-10 12:00:00',
            ];
        }
        $this->db->table('cms_page_translations')->insertBatch($translations);

        $measurement = $this->measureGet('/api/v1/public-read/es/pages?fields=id,title,slug&per_page=24');
        $measurement['response']->assertStatus(200);

        $body = json_decode((string) $measurement['response']->getJSON(), true);
        $this->assertSame(240, $body['meta']['total'] ?? null);
        $this->assertCount(24, $body['data'] ?? []);
        $this->assertLessThanOrEqual(4, $measurement['query_count'], $this->querySummary($measurement['queries']));
        $this->assertLessThanOrEqual(500.0, $this->totalDuration($measurement['queries']), $this->querySummary($measurement['queries']));

        $pagesSql = $this->findQuery($measurement['queries'], 'FROM `cms_pages`');
        $this->assertNotNull($pagesSql, $this->querySummary($measurement['queries']));

        $plan = $this->db->query('EXPLAIN ' . $pagesSql)->getResultArray();
        $pagesPlan = $this->findPlanRow($plan, 'cms_pages');
        $this->assertNotNull($pagesPlan, json_encode($plan, JSON_UNESCAPED_SLASHES));
        $this->assertSame('idx_page_status', $pagesPlan['key'] ?? null, json_encode($pagesPlan));
        $this->assertNotSame('ALL', $pagesPlan['type'] ?? null, json_encode($pagesPlan));
    }

    /**
     * Regression for docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md
     * §2.A: the pre-fix `findAll()`-without-limit facet branch loaded every
     * candidate entry into PHP before filtering — this test builds a
     * candidate set larger than one page and asserts page 2 returns exactly
     * the expected slice (not an array_slice artifact of a truncated
     * candidate load), that the query budget stays flat regardless of
     * collection size, and that MySQL uses an index on
     * cms_entry_facet_values rather than a full scan.
     */
    public function testFacetFilteredListingPaginatesCorrectlyAndUsesAnIndex(): void
    {
        ['langId' => $langId, 'collectionId' => $collectionId, 'blockId' => $blockId, 'blockKey' => $blockKey] = $this->seedFacetFixtureSchema();

        // 30 entries match the facet value under test, 20 don't — more than
        // one page (per_page=10) of matches, so page 2 only returns the
        // right slice if filtering happened before pagination, in SQL.
        // sort_order is set explicitly (and used as the order_by below)
        // because every entry otherwise shares the same created_at second,
        // which would make ordering non-deterministic and the test flaky.
        $matchingIds = [];
        for ($index = 0; $index < 50; $index++) {
            $matches = $index < 30;
            $entryId = $this->insertFacetedEntry($collectionId, $langId, $blockId, $blockKey, [
                'genre' => $matches ? 'documental' : 'ficcion',
            ], sprintf('facet-entry-%03d', $index), $index);
            if ($matches) {
                $matchingIds[] = $entryId;
            }
        }

        // Noise rows under other field_key values, so the optimizer's index
        // choice for `field_key = 'block.facet_block.genre'` is measured
        // against a realistically mixed table instead of one where every row
        // matches anyway (which makes any index choice look equally cheap).
        // Reuses one real block_instance_id/entry_id pair to satisfy the FK
        // and unique(block_instance_id, language_id, field_key) constraints —
        // these rows exist purely to change field_key's overall selectivity.
        $anyInstance = $this->db->table('cms_block_instances')->select('id, owner_id')->limit(1)->get()->getRowArray();
        $noiseRows = [];
        for ($index = 0; $index < 300; $index++) {
            $noiseRows[] = [
                'entry_id' => (int) $anyInstance['owner_id'],
                'block_instance_id' => (int) $anyInstance['id'],
                'language_id' => $langId,
                'field_key' => 'block.facet_block.noise_' . $index,
                'value_type' => 'string',
                'value_string' => 'noise-' . $index,
                'value_date' => null,
                'value_numeric' => null,
            ];
        }
        $this->db->table('cms_entry_facet_values')->insertBatch($noiseRows);

        $measurement = $this->measureGet(sprintf(
            '/api/v1/public-read/es/entries/%s?filter_by=block.%s.genre&filter_value=documental&per_page=10&page=2&order_by=sort_order&order_direction=asc',
            'facet-collection',
            $blockKey,
        ));
        $measurement['response']->assertStatus(200);

        $body = json_decode((string) $measurement['response']->getJSON(), true);
        $this->assertSame(30, $body['meta']['total'] ?? null, json_encode($body));
        $data = $body['data'] ?? [];
        $this->assertCount(10, $data, json_encode($body));

        $returnedIds = array_map(static fn (array $item): int => (int) $item['id'], $data);
        $expectedPageTwoIds = array_slice($matchingIds, 10, 10);
        sort($returnedIds);
        sort($expectedPageTwoIds);
        $this->assertSame($expectedPageTwoIds, $returnedIds, json_encode(['returned' => $returnedIds, 'expected' => $expectedPageTwoIds]));

        // Flat query budget: the facet filter join + count + page fetch,
        // plus the fixed batch of translation/category/tag lookups
        // listPublic() always does for a page of entries, must not scale
        // with collection size (50 entries here; the old unbounded
        // findAll() would have made this proportional instead).
        $this->assertLessThanOrEqual(10, $measurement['query_count'], $this->querySummary($measurement['queries']));

        $entriesSql = $this->findQuery($measurement['queries'], 'FROM `cms_entries`');
        $this->assertNotNull($entriesSql, $this->querySummary($measurement['queries']));
        $plan = $this->db->query('EXPLAIN ' . $entriesSql)->getResultArray();
        // The DERIVED sub-plan is the one actually reading cms_entry_facet_values —
        // asserting on it (rather than the outer join, which EXPLAIN reports
        // against a synthetic `<derivedN>` table name) verifies MySQL used an
        // index for the field_key lookup instead of a full table scan.
        $facetPlan = $this->findPlanRow($plan, 'cms_entry_facet_values');
        $this->assertNotNull($facetPlan, json_encode($plan, JSON_UNESCAPED_SLASHES));
        $this->assertNotSame('ALL', $facetPlan['type'] ?? null, json_encode($facetPlan));
    }

    /**
     * Regression for the second, previously-undocumented unbounded findAll()
     * in sortByListingField() (order_by=field:...), reachable from
     * CollectionListingViewModel's public ordering query param. Verifies the
     * UPCOMING two-bucket order (soonest-first, then most-recent-past) is
     * computed correctly against the materialized value_date column.
     */
    public function testListingFieldUpcomingOrderingSortsFutureThenPastCorrectly(): void
    {
        ['langId' => $langId, 'collectionId' => $collectionId, 'blockId' => $blockId, 'blockKey' => $blockKey] = $this->seedFacetFixtureSchema();

        $today = new \DateTimeImmutable('today');
        $dates = [
            'past-2'   => $today->modify('-10 days'),
            'past-1'   => $today->modify('-2 days'),
            'future-1' => $today->modify('+2 days'),
            'future-2' => $today->modify('+10 days'),
        ];

        $ids = [];
        $sortOrder = 0;
        foreach ($dates as $label => $date) {
            $ids[$label] = $this->insertFacetedEntry($collectionId, $langId, $blockId, $blockKey, [
                'start_date' => $date->format('Y-m-d H:i:s'),
            ], $label, $sortOrder++);
        }

        $measurement = $this->measureGet(sprintf(
            '/api/v1/public-read/es/entries/%s?order_by=field:block.%s.start_date&order_direction=upcoming&per_page=10',
            'facet-collection',
            $blockKey,
        ));
        $measurement['response']->assertStatus(200);

        $body = json_decode((string) $measurement['response']->getJSON(), true);
        $returnedIds = array_map(static fn (array $item): int => (int) $item['id'], $body['data'] ?? []);

        $this->assertSame(
            [$ids['future-1'], $ids['future-2'], $ids['past-1'], $ids['past-2']],
            $returnedIds,
            json_encode($body)
        );
    }

    /**
     * @return array{langId: int, collectionId: int, blockId: int, blockKey: string}
     */
    private function seedFacetFixtureSchema(): array
    {
        $fixtures = new CmsFixtureFactory($this->db, self::class);
        $languages = $fixtures->languages(2);
        $langId = $languages[0]['id'];

        $this->db->table('cms_collections')->insert([
            'collection_key' => 'facet-collection',
            'collection_type' => 'other',
            'is_active' => 1,
            'requires_approval' => 0,
            'enables_categories' => 0,
            'enables_tags' => 0,
            'sort_order' => 0,
        ]);
        $collectionId = (int) $this->db->insertID();

        $blockKey = 'facet_block';
        $this->db->table('cms_content_blocks')->insert([
            'block_key' => $blockKey,
            'name' => 'Facet block',
            'schema_definition' => json_encode([
                'fields' => [
                    'genre' => ['type' => 'select', 'label' => 'Genre'],
                    'start_date' => ['type' => 'date', 'label' => 'Start date'],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
        $blockId = (int) $this->db->insertID();

        return ['langId' => (int) $langId, 'collectionId' => $collectionId, 'blockId' => $blockId, 'blockKey' => $blockKey];
    }

    /**
     * Inserts a published entry with one entry-owned block instance carrying
     * $blockData, and directly materializes cms_entry_facet_values the same
     * way EntryFacetValueSynchronizer would — the write path itself has its
     * own coverage (EntryFacetValueSynchronizerTest); this fixture isolates
     * the read-side regression under test here.
     *
     * @param array<string, mixed> $blockData
     */
    private function insertFacetedEntry(int $collectionId, int $langId, int $blockId, string $blockKey, array $blockData, string $slug, int $sortOrder): int
    {
        $this->db->table('cms_entries')->insert([
            'collection_id' => $collectionId,
            'workflow_status' => 'published',
            'is_featured' => 0,
            'view_count' => 0,
            'sort_order' => $sortOrder,
            'is_in_sitemap' => 1,
        ]);
        $entryId = (int) $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id' => $entryId,
            'language_id' => $langId,
            'slug' => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
        ]);

        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockId,
            'owner_type' => 'entry',
            'owner_id' => $entryId,
            'sort_order' => 0,
            'is_active' => 1,
            'block_config' => json_encode([], JSON_THROW_ON_ERROR),
        ]);
        $instanceId = (int) $this->db->insertID();

        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $instanceId,
            'language_id' => $langId,
            'block_data' => json_encode($blockData, JSON_THROW_ON_ERROR),
            'is_published' => 1,
        ]);

        $facetRows = [];
        foreach ($blockData as $fieldKey => $value) {
            $isDate = $fieldKey === 'start_date';
            $facetRows[] = [
                'entry_id' => $entryId,
                'block_instance_id' => $instanceId,
                'language_id' => $langId,
                'field_key' => "block.{$blockKey}.{$fieldKey}",
                'value_type' => $isDate ? 'date' : 'string',
                'value_string' => (string) $value,
                'value_date' => $isDate ? (string) $value : null,
                'value_numeric' => null,
            ];
        }
        if ($facetRows !== []) {
            $this->db->table('cms_entry_facet_values')->insertBatch($facetRows);
        }

        return $entryId;
    }


    /**
     * @return array{response: \CodeIgniter\Test\TestResponse, queries: list<array{sql:string,duration_ms:float}>, query_count:int}
     */
    private function measureGet(string $path): array
    {
        $queries = [];
        $listener = static function (mixed $query) use (&$queries): void {
            if (! $query instanceof QueryInterface) {
                return;
            }

            $sql = trim((string) $query);
            if (str_starts_with(strtoupper($sql), 'SELECT')) {
                $queries[] = [
                    'sql' => $sql,
                    'duration_ms' => (float) $query->getDuration(6) * 1000,
                ];
            }
        };

        Events::on('DBQuery', $listener, Events::PRIORITY_LOW);
        $startedAt = microtime(true);
        try {
            $response = $this->get($path);
        } finally {
            Events::removeListener('DBQuery', $listener);
        }

        $elapsedMs = (microtime(true) - $startedAt) * 1000;
        $this->assertLessThan(1000, $elapsedMs, $this->querySummary($queries));

        return ['response' => $response, 'queries' => $queries, 'query_count' => count($queries)];
    }

    /** @param list<array{sql:string,duration_ms:float}> $queries */
    private function findQuery(array $queries, string $fragment): ?string
    {
        foreach ($queries as $query) {
            if (str_contains($query['sql'], $fragment) && str_contains($query['sql'], 'ORDER BY')) {
                return $query['sql'];
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $plan */
    private function findPlanRow(array $plan, string $table): ?array
    {
        foreach ($plan as $row) {
            if (($row['table'] ?? null) === $table) {
                return $row;
            }
        }

        return null;
    }

    /** @param list<array{sql:string,duration_ms:float}> $queries */
    private function querySummary(array $queries): string
    {
        return json_encode($queries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'no queries';
    }

    /** @param list<array{sql:string,duration_ms:float}> $queries */
    private function totalDuration(array $queries): float
    {
        $total = 0.0;
        foreach ($queries as $query) {
            $total += $query['duration_ms'];
        }

        return $total;
    }
}
