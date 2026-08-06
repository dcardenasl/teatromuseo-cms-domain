<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * LAYER-07: PublicTagController had zero test coverage. Mirrors
 * PublicCategoryControllerTest — tags aren't scoped to a collection in the
 * schema (TagService::listPublic() only uses collectionKey to check the
 * collection exists and is active), so every active tag is returned
 * regardless of which collection key resolved it.
 *
 * @internal
 */
final class PublicTagControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use WithWebAppKeyTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private CmsFixtureFactory $fixtures;

    /** @var list<array{id:int,code:string,name:string,is_default:bool}> */
    private array $languages;

    /** @var array{id:int,key:string,translations:list<array<string,mixed>>} */
    private array $collection;

    private int $tagId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_tag_translations`');
        $this->db->query('DELETE FROM `cms_tags`');
        $this->db->query('DELETE FROM `cms_collection_translations`');
        $this->db->query('DELETE FROM `cms_collections`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(2);
        $this->collection = $this->fixtures->collection([
            ['language_id' => $this->languages[0]['id'], 'slug' => $this->fixtures->slug('collection', $this->languages[0]['code'])],
        ]);

        $tag = $this->fixtures->tag();
        $this->tagId = $tag['id'];

        $this->db->table('cms_tag_translations')->insert([
            'tag_id'      => $this->tagId,
            'language_id' => $this->languages[0]['id'],
            'slug'        => $this->fixtures->slug('tag', $this->languages[0]['code']),
            'name'        => $this->fixtures->text('tag-name', $this->languages[0]['code']),
        ]);
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    public function testListsActiveTagsForAnExistingCollectionKey(): void
    {
        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/tags/' . $this->collection['key']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('success', $body['status']);
        $this->assertCount(1, $body['data']);
        $this->assertSame($this->tagId, $body['data'][0]['id']);
        $this->assertArrayHasKey('slug', $body['data'][0]);
        $this->assertArrayHasKey('name', $body['data'][0]);
    }

    public function testUnknownCollectionKeyReturnsEmptyList(): void
    {
        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/tags/does-not-exist');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('success', $body['status']);
        $this->assertSame([], $body['data']);
    }

    public function testInactiveTagIsExcluded(): void
    {
        $this->db->table('cms_tags')->where('id', $this->tagId)->update(['is_active' => 0]);

        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/tags/' . $this->collection['key']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame([], $body['data']);
    }

    public function testRequestWithoutAppKeyIsRejected(): void
    {
        $this->withHeaders([]);

        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/tags/' . $this->collection['key']);

        $result->assertStatus(401);
    }
}
