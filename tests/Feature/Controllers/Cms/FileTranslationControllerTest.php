<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use App\Libraries\Hub\HubClient;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use Tests\Support\ApiTestCase;

/**
 * LAYER-07: FileTranslationController (a plain generic-CRUD ApiController
 * over FileTranslationServiceInterface) had zero test coverage. Auth
 * pattern mirrors SettingConnectionControllerTest: stub HubClient::introspect().
 *
 * @internal
 */
final class FileTranslationControllerTest extends ApiTestCase
{
    private int $languageId = 0;
    private int $fileId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateRequest();
        // This suite's DatabaseTestTrait config does not roll back rows
        // between test methods (matches the manual-DELETE pattern every
        // other Feature test class in this app already uses in setUp(),
        // e.g. PublicCollectionControllerTest) — without this, translations
        // created by one test are still present for the next.
        $this->db->query('DELETE FROM `cms_file_translations`');
        $this->languageId = $this->seedLanguage();
        $this->fileId = random_int(100000, 999999);
    }

    private function authenticateRequest(): void
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

    private function seedLanguage(): int
    {
        $id = $this->db->table('cms_languages')->insert([
            'code'        => 'xx-' . bin2hex(random_bytes(2)),
            'name'        => 'Test Language',
            'native_name' => 'Test Language',
            'is_default'  => 0,
            'is_active'   => 1,
            'sort_order'  => 99,
        ]);

        return (int) $this->db->insertID();
    }

    public function testIndexReturnsEmptyListInitially(): void
    {
        $result = $this->get("api/v1/cms/files/{$this->fileId}/translations");

        $result->assertStatus(200);
        $body = json_decode((string) $result->response()->getBody(), true);

        $this->assertSame('success', $body['status']);
        $this->assertSame([], $body['data']['items'] ?? $body['data']);
    }

    public function testCreateThenIndexReturnsTheTranslation(): void
    {
        $create = $this->post("api/v1/cms/files/{$this->fileId}/translations", [
            'language_id' => $this->languageId,
            'alt_text'    => 'Portada del festival',
            'caption'     => 'Foto de portada',
        ]);

        $create->assertStatus(201);
        $createBody = json_decode((string) $create->response()->getBody(), true);

        $this->assertSame('success', $createBody['status']);
        $this->assertSame($this->fileId, $createBody['data']['file_id']);
        $this->assertSame($this->languageId, $createBody['data']['language_id']);
        $this->assertSame('Portada del festival', $createBody['data']['alt_text']);

        $this->resetRequest();
        $index = $this->get("api/v1/cms/files/{$this->fileId}/translations");
        $indexBody = json_decode((string) $index->response()->getBody(), true);

        $items = $indexBody['data']['items'] ?? $indexBody['data'];
        $this->assertCount(1, $items);
        $this->assertSame('Portada del festival', $items[0]['alt_text']);
    }

    /**
     * Regression test for a real bug found while writing this coverage
     * (LAYER-07, 2026-08-06): `index()` merges `file_id` into
     * FileTranslationIndexRequestDTO via additionalParams, and the DTO's
     * toArray() does include `file_id`, but
     * BaseRepository::paginateCriteria() only ever reads `criteria['filter']`
     * (an array), `criteria['sort']`, and `criteria['search']` — a bare
     * top-level `file_id` key was being silently ignored, so `GET
     * /cms/files/{fileId}/translations` returned every file's translations,
     * not just this one's. Fixed via
     * FileTranslationService::applyQueryOptions(), which now folds `file_id`
     * into `criteria['filter']['file_id']`.
     */
    public function testIndexIsScopedByFileId(): void
    {
        $otherFileId = $this->fileId + 1;

        $this->post("api/v1/cms/files/{$this->fileId}/translations", [
            'language_id' => $this->languageId,
            'alt_text'    => 'belongs to this file',
        ]);

        $this->resetRequest();
        $otherLanguageId = $this->seedLanguage();
        $this->post("api/v1/cms/files/{$otherFileId}/translations", [
            'language_id' => $otherLanguageId,
            'alt_text'    => 'belongs to a different file',
        ]);

        $this->resetRequest();
        $index = $this->get("api/v1/cms/files/{$this->fileId}/translations");
        $indexBody = json_decode((string) $index->response()->getBody(), true);
        $items = $indexBody['data']['items'] ?? $indexBody['data'];

        $this->assertCount(1, $items);
        $this->assertSame('belongs to this file', $items[0]['alt_text']);
    }

    public function testCreateDuplicateLanguageForSameFileReturnsValidationError(): void
    {
        $this->post("api/v1/cms/files/{$this->fileId}/translations", [
            'language_id' => $this->languageId,
            'alt_text'    => 'first',
        ]);

        $this->resetRequest();
        $duplicate = $this->post("api/v1/cms/files/{$this->fileId}/translations", [
            'language_id' => $this->languageId,
            'alt_text'    => 'second',
        ]);

        $duplicate->assertStatus(422);
    }

    public function testShowMissingTranslationReturnsNotFound(): void
    {
        $result = $this->get("api/v1/cms/files/{$this->fileId}/translations/999999");

        $result->assertStatus(404);
    }

    public function testUpdateChangesFields(): void
    {
        $create = $this->post("api/v1/cms/files/{$this->fileId}/translations", [
            'language_id' => $this->languageId,
            'alt_text'    => 'original',
        ]);
        $createBody = json_decode((string) $create->response()->getBody(), true);
        $id = (int) $createBody['data']['id'];

        $this->resetRequest();
        // FeatureTestTrait only auto-populates the request body for GET/POST;
        // PUT/PATCH need withBodyFormat('json') or $params is silently
        // dropped (confirmed against CI4's FeatureTestTrait::populateGlobals()
        // — no branch exists for other verbs).
        $update = $this->withBodyFormat('json')->put("api/v1/cms/files/{$this->fileId}/translations/{$id}", [
            'language_id' => $this->languageId,
            'alt_text'    => 'updated',
        ]);
        $update->assertStatus(200);
        $updateBody = json_decode((string) $update->response()->getBody(), true);

        $this->assertSame('updated', $updateBody['data']['alt_text']);
    }

    public function testDeleteRemovesTheTranslation(): void
    {
        $create = $this->post("api/v1/cms/files/{$this->fileId}/translations", [
            'language_id' => $this->languageId,
            'alt_text'    => 'to be deleted',
        ]);
        $createBody = json_decode((string) $create->response()->getBody(), true);
        $id = (int) $createBody['data']['id'];

        $this->resetRequest();
        $delete = $this->delete("api/v1/cms/files/{$this->fileId}/translations/{$id}");
        $delete->assertStatus(200);

        $this->resetRequest();
        $show = $this->get("api/v1/cms/files/{$this->fileId}/translations/{$id}");
        $show->assertStatus(404);
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        Services::resetSingle('hubClient');
        $this->clearTestRequestHeaders();

        $result = $this->get("api/v1/cms/files/{$this->fileId}/translations");

        $result->assertStatus(401);
    }
}
