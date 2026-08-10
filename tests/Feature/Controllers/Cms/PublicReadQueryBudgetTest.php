<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Database\QueryInterface;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
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
