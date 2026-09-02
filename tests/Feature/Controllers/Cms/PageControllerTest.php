<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use App\Libraries\Hub\HubClient;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use Tests\Support\ApiTestCase;
use Tests\Support\Fixtures\CmsFixtureFactory;

/**
 * HTTP coverage for PageController. The unauthenticated smoke checks confirm
 * the route group requires auth; the authenticated flows below exercise the
 * real create/update/delete/checkSlug paths through PageService.
 *
 * @internal
 */
final class PageControllerTest extends ApiTestCase
{
    private CmsFixtureFactory $fixtures;

    /** @var list<array{id:int,code:string,name:string,is_default:bool}> */
    private array $languages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_page_translations`');
        $this->db->query('DELETE FROM `cms_pages`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(1);
    }

    private function authenticate(): void
    {
        $stub = new class (new IntrospectResult(
            valid: true,
            uid: 1,
            permissions: ['cms.pages.read', 'cms.pages.write'],
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
        $result = $this->get('/api/v1/cms/pages');

        $result->assertStatus(401);
    }

    public function testShowNotFound(): void
    {
        $result = $this->get('/api/v1/cms/pages/99999');

        $result->assertStatus(401);
    }

    public function testCreateThenShowReturnsThePage(): void
    {
        $this->authenticate();
        $languageId = $this->languages[0]['id'];

        $create = $this->post('/api/v1/cms/pages', [
            'page_type' => 'generic',
            'status' => 'draft',
            'sort_order' => '0',
            'translations' => [
                [
                    'language_id' => (string) $languageId,
                    'slug' => $this->fixtures->slug('page'),
                    'title' => $this->fixtures->text('page-title'),
                ],
            ],
        ]);

        // PageController::create() passes a Closure (not a plain 'store' string)
        // to handleRequest(), so the controller's $statusCodes = ['store' => 201]
        // map never applies here — same documented gap as
        // FormSubmissionControllerTest::testImportPreservesGivenCreatedAtAndStatus().
        $create->assertStatus(200);
        $createBody = json_decode((string) $create->response()->getBody(), true);
        $pageId = (int) $createBody['data']['id'];
        $this->assertGreaterThan(0, $pageId);

        $this->resetRequest();
        $show = $this->get("/api/v1/cms/pages/{$pageId}");
        $show->assertStatus(200);
    }

    public function testUpdateChangesStatus(): void
    {
        $this->authenticate();
        $languageId = $this->languages[0]['id'];

        $create = $this->post('/api/v1/cms/pages', [
            'page_type' => 'generic',
            'status' => 'draft',
            'translations' => [
                ['language_id' => (string) $languageId, 'slug' => $this->fixtures->slug('page-upd'), 'title' => $this->fixtures->text('page-title-upd')],
            ],
        ]);
        $createBody = json_decode((string) $create->response()->getBody(), true);
        $pageId = (int) $createBody['data']['id'];

        $this->resetRequest();
        // FeatureTestTrait only auto-populates the request body for GET/POST;
        // PUT/PATCH need withBodyFormat('json') or $params is silently dropped
        // (confirmed against CI4's FeatureTestTrait::populateGlobals() — no
        // branch exists for other verbs; see FileTranslationControllerTest).
        $update = $this->withBodyFormat('json')->put("/api/v1/cms/pages/{$pageId}", ['status' => 'published']);
        $update->assertStatus(200);
        $updateBody = json_decode((string) $update->response()->getBody(), true);
        $this->assertSame('published', $updateBody['data']['status']);
    }

    public function testDeleteRemovesThePage(): void
    {
        $this->authenticate();
        $languageId = $this->languages[0]['id'];

        $create = $this->post('/api/v1/cms/pages', [
            'page_type' => 'generic',
            'status' => 'draft',
            'translations' => [
                ['language_id' => (string) $languageId, 'slug' => $this->fixtures->slug('page-del'), 'title' => $this->fixtures->text('page-title-del')],
            ],
        ]);
        $createBody = json_decode((string) $create->response()->getBody(), true);
        $pageId = (int) $createBody['data']['id'];

        $this->resetRequest();
        $delete = $this->delete("/api/v1/cms/pages/{$pageId}");
        $delete->assertStatus(200);

        $this->resetRequest();
        $show = $this->get("/api/v1/cms/pages/{$pageId}");
        $show->assertStatus(404);
    }

    public function testCheckSlugReportsAvailability(): void
    {
        $this->authenticate();
        $languageId = $this->languages[0]['id'];
        $slug = $this->fixtures->slug('page-check');

        $available = $this->get("/api/v1/cms/pages/check-slug?slug={$slug}&language_id={$languageId}");
        $available->assertStatus(200);
        $availableBody = json_decode((string) $available->response()->getBody(), true);
        $this->assertTrue($availableBody['data']['available']);

        $this->resetRequest();
        $this->post('/api/v1/cms/pages', [
            'page_type' => 'generic',
            'status' => 'draft',
            'translations' => [
                ['language_id' => (string) $languageId, 'slug' => $slug, 'title' => $this->fixtures->text('page-title-check')],
            ],
        ]);

        $this->resetRequest();
        $taken = $this->get("/api/v1/cms/pages/check-slug?slug={$slug}&language_id={$languageId}");
        $takenBody = json_decode((string) $taken->response()->getBody(), true);
        $this->assertFalse($takenBody['data']['available']);
    }
}
