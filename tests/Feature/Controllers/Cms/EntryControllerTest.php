<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use App\Libraries\Hub\HubClient;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use Tests\Support\ApiTestCase;
use Tests\Support\Fixtures\CmsFixtureFactory;

/**
 * Authenticated HTTP coverage for EntryController and its taxonomy endpoints.
 *
 * @internal
 */
final class EntryControllerTest extends ApiTestCase
{
    private CmsFixtureFactory $fixtures;

    private int $languageId;

    private int $collectionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_entry_facet_values`');
        $this->db->query('DELETE FROM `cms_entry_categories`');
        $this->db->query('DELETE FROM `cms_entry_tags`');
        $this->db->query('DELETE FROM `cms_entry_translations`');
        $this->db->query('DELETE FROM `cms_entry_versions`');
        $this->db->query('DELETE FROM `cms_entries`');
        $this->db->query('DELETE FROM `cms_category_translations`');
        $this->db->query('DELETE FROM `cms_categories`');
        $this->db->query('DELETE FROM `cms_tag_translations`');
        $this->db->query('DELETE FROM `cms_tags`');
        $this->db->query('DELETE FROM `cms_collections`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languageId = $this->fixtures->languages(1)[0]['id'];
        $this->collectionId = $this->fixtures->collection()['id'];
        $this->authenticate();
    }

    private function authenticate(): void
    {
        $stub = new class (new IntrospectResult(
            valid: true,
            uid: 1,
            permissions: ['cms.entries.read', 'cms.entries.write', 'cms.entries.admin'],
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

    /** @param array<string, mixed> $overrides */
    private function createEntry(array $overrides = []): int
    {
        $payload = array_replace([
            'collection_id' => (string) $this->collectionId,
            'workflow_status' => 'draft',
            'is_featured' => false,
            'translations' => [[
                'language_id' => (string) $this->languageId,
                'slug' => $this->fixtures->slug('entry'),
                'title' => $this->fixtures->text('entry-title'),
            ]],
        ], $overrides);

        $result = $this->post('/api/v1/cms/entries', $payload);
        $result->assertStatus(200);
        $body = json_decode((string) $result->response()->getBody(), true);

        return (int) $body['data']['id'];
    }

    public function testEntryCrudAndIndex(): void
    {
        $entryId = $this->createEntry();

        $show = $this->get("/api/v1/cms/entries/{$entryId}");
        $show->assertStatus(200);
        $showBody = json_decode((string) $show->response()->getBody(), true);
        $this->assertSame($entryId, (int) $showBody['data']['id']);
        $this->assertSame('draft', $showBody['data']['workflow_status']);

        $index = $this->get('/api/v1/cms/entries');
        $index->assertStatus(200);
        $indexBody = json_decode((string) $index->response()->getBody(), true);
        $this->assertCount(1, $indexBody['data']);

        $update = $this->withBodyFormat('json')->put("/api/v1/cms/entries/{$entryId}", [
            'workflow_status' => 'published',
            'translations' => [[
                'language_id' => (string) $this->languageId,
                'slug' => $this->fixtures->slug('entry-updated'),
                'title' => $this->fixtures->text('entry-title-updated'),
            ]],
        ]);
        $update->assertStatus(200);
        $updateBody = json_decode((string) $update->response()->getBody(), true);
        $this->assertSame('published', $updateBody['data']['workflow_status']);

        $delete = $this->delete("/api/v1/cms/entries/{$entryId}");
        $delete->assertStatus(200);

        $missing = $this->get("/api/v1/cms/entries/{$entryId}");
        $missing->assertStatus(404);
    }

    public function testCheckSlugAndTaxonomyEndpoints(): void
    {
        $entryId = $this->createEntry();
        $slug = $this->fixtures->slug('entry-check');

        $available = $this->get("/api/v1/cms/entries/check-slug?slug={$slug}&language_id={$this->languageId}");
        $available->assertStatus(200);
        $availableBody = json_decode((string) $available->response()->getBody(), true);
        $this->assertTrue($availableBody['data']['available']);

        $taken = $this->get('/api/v1/cms/entries/check-slug');
        $taken->assertStatus(200);
        $takenBody = json_decode((string) $taken->response()->getBody(), true);
        $this->assertFalse($takenBody['data']['available']);

        $categoryId = $this->fixtures->category($this->collectionId)['id'];
        $tagId = $this->fixtures->tag()['id'];

        $categories = $this->withBodyFormat('json')->post("/api/v1/cms/entries/{$entryId}/categories", [
            'category_ids' => [(string) $categoryId],
        ]);
        $categories->assertStatus(200);

        $tags = $this->withBodyFormat('json')->post("/api/v1/cms/entries/{$entryId}/tags", [
            'tag_ids' => [(string) $tagId],
        ]);
        $tags->assertStatus(200);

        $taxonomy = $this->withBodyFormat('json')->post("/api/v1/cms/entries/{$entryId}/taxonomy", [
            'category_ids' => [],
            'tag_ids' => [],
        ]);
        $taxonomy->assertStatus(200);

        $this->assertSame(0, (int) $this->db->table('cms_entry_categories')->where('entry_id', $entryId)->countAllResults());
        $this->assertSame(0, (int) $this->db->table('cms_entry_tags')->where('entry_id', $entryId)->countAllResults());
    }
}
