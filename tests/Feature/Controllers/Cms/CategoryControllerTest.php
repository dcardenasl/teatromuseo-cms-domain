<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use App\Libraries\Hub\HubClient;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use Tests\Support\ApiTestCase;
use Tests\Support\Fixtures\CmsFixtureFactory;

/**
 * HTTP coverage for CategoryController. The unauthenticated smoke checks
 * confirm the route group requires auth; the authenticated flows below
 * exercise the real create/update/delete/checkSlug paths through
 * CategoryService.
 *
 * @internal
 */
final class CategoryControllerTest extends ApiTestCase
{
    private CmsFixtureFactory $fixtures;

    private int $languageId;

    private int $collectionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_category_translations`');
        $this->db->query('DELETE FROM `cms_categories`');
        $this->db->query('DELETE FROM `cms_collections`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languageId = $this->fixtures->languages(1)[0]['id'];
        $this->collectionId = $this->fixtures->collection()['id'];
    }

    private function authenticate(): void
    {
        $stub = new class (new IntrospectResult(
            valid: true,
            uid: 1,
            permissions: ['cms.categories.read', 'cms.categories.write'],
            exp: time() + 3600,
            error: null
        )) extends HubClient {
            public function __construct(private readonly IntrospectResult $result)
            {
            }

            public function introspect(string $token): IntrospectResult
            {
                return $this->result;
            }
        };

        Services::injectMock('hubClient', $stub);
        $this->setTestRequestHeaders(['Authorization' => 'Bearer fake-test-token']);
    }

    public function testIndexSmoke(): void
    {
        $result = $this->get('/api/v1/cms/categories');

        $result->assertStatus(401);
    }

    public function testShowNotFound(): void
    {
        $result = $this->get('/api/v1/cms/categories/99999');

        $result->assertStatus(401);
    }

    public function testCreateThenShowReturnsTheCategory(): void
    {
        $this->authenticate();

        $create = $this->post('/api/v1/cms/categories', [
            'collection_id' => (string) $this->collectionId,
            'sort_order' => '0',
            'is_active' => 'true',
            'translations' => [
                [
                    'language_id' => (string) $this->languageId,
                    'slug' => $this->fixtures->slug('category'),
                    'name' => $this->fixtures->text('category-name'),
                ],
            ],
        ]);

        // CategoryController::create() passes a Closure to handleRequest(), so
        // the $statusCodes = ['store' => 201] map never applies (same gap as
        // PageController — see PageControllerTest for the full explanation).
        $create->assertStatus(200);
        $createBody = json_decode((string) $create->response()->getBody(), true);
        $categoryId = (int) $createBody['data']['id'];
        $this->assertGreaterThan(0, $categoryId);

        $this->resetRequest();
        $show = $this->get("/api/v1/cms/categories/{$categoryId}");
        $show->assertStatus(200);
    }

    public function testUpdateChangesSortOrder(): void
    {
        $this->authenticate();
        $categoryId = $this->fixtures->category($this->collectionId)['id'];

        $update = $this->withBodyFormat('json')->put("/api/v1/cms/categories/{$categoryId}", ['sort_order' => 5]);
        $update->assertStatus(200);
        $updateBody = json_decode((string) $update->response()->getBody(), true);
        $this->assertSame(5, $updateBody['data']['sort_order']);
    }

    public function testDeleteRemovesTheCategory(): void
    {
        $this->authenticate();
        $categoryId = $this->fixtures->category($this->collectionId)['id'];

        $delete = $this->delete("/api/v1/cms/categories/{$categoryId}");
        $delete->assertStatus(200);

        $this->resetRequest();
        $show = $this->get("/api/v1/cms/categories/{$categoryId}");
        $show->assertStatus(404);
    }

    public function testCheckSlugReportsAvailability(): void
    {
        $this->authenticate();
        $slug = $this->fixtures->slug('category-check');

        $available = $this->get("/api/v1/cms/categories/check-slug?slug={$slug}&language_id={$this->languageId}");
        $available->assertStatus(200);
        $availableBody = json_decode((string) $available->response()->getBody(), true);
        $this->assertTrue($availableBody['data']['available']);

        $this->resetRequest();
        $this->post('/api/v1/cms/categories', [
            'collection_id' => (string) $this->collectionId,
            'translations' => [
                ['language_id' => (string) $this->languageId, 'slug' => $slug, 'name' => $this->fixtures->text('category-name-check')],
            ],
        ]);

        $this->resetRequest();
        $taken = $this->get("/api/v1/cms/categories/check-slug?slug={$slug}&language_id={$this->languageId}");
        $takenBody = json_decode((string) $taken->response()->getBody(), true);
        $this->assertFalse($takenBody['data']['available']);
    }
}
