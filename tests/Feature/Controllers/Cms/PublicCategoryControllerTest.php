<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * LAYER-07: PublicCategoryController had zero test coverage. Mirrors the
 * pattern already established by PublicCollectionControllerTest (webappkey
 * auth, CmsFixtureFactory, `{status, data}` envelope assertions).
 *
 * @internal
 */
final class PublicCategoryControllerTest extends CIUnitTestCase
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

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_category_translations`');
        $this->db->query('DELETE FROM `cms_categories`');
        $this->db->query('DELETE FROM `cms_collection_translations`');
        $this->db->query('DELETE FROM `cms_collections`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(2);
        $this->collection = $this->fixtures->collection([
            ['language_id' => $this->languages[0]['id'], 'slug' => $this->fixtures->slug('collection', $this->languages[0]['code'])],
        ]);

        $category = $this->fixtures->category($this->collection['id']);
        $this->categoryId = $category['id'];

        $this->db->table('cms_category_translations')->insert([
            'category_id' => $this->categoryId,
            'language_id' => $this->languages[0]['id'],
            'slug'        => $this->fixtures->slug('category', $this->languages[0]['code']),
            'name'        => $this->fixtures->text('category-name', $this->languages[0]['code']),
            'description' => $this->fixtures->text('category-description', $this->languages[0]['code']),
        ]);
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    public function testListsActiveCategoriesForTheCollection(): void
    {
        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/categories/' . $this->collection['key']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('success', $body['status']);
        $this->assertCount(1, $body['data']);
        $this->assertSame($this->categoryId, $body['data'][0]['id']);
        $this->assertArrayHasKey('slug', $body['data'][0]);
        $this->assertArrayHasKey('name', $body['data'][0]);
    }

    public function testUnknownCollectionKeyReturnsEmptyList(): void
    {
        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/categories/does-not-exist');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('success', $body['status']);
        $this->assertSame([], $body['data']);
    }

    public function testInactiveCategoryIsExcluded(): void
    {
        $this->db->table('cms_categories')->where('id', $this->categoryId)->update(['is_active' => 0]);

        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/categories/' . $this->collection['key']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame([], $body['data']);
    }

    public function testRequestWithoutAppKeyIsRejected(): void
    {
        $this->withHeaders([]);

        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/categories/' . $this->collection['key']);

        $result->assertStatus(401);
    }
}
