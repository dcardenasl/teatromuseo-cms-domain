<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * Feature coverage for the ADR 006 composite bootstrap endpoints:
 * `layout` (navigation + collections + settings) and
 * `page-bootstrap/{path}` (redirect + page).
 *
 * @internal
 */
final class PublicReadBootstrapControllerTest extends CIUnitTestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->query('DELETE FROM `cms_menu_item_translations`');
        $this->db->query('DELETE FROM `cms_menu_items`');
        $this->db->query('DELETE FROM `cms_menu_translations`');
        $this->db->query('DELETE FROM `cms_menus`');
        $this->db->query('DELETE FROM `cms_setting_translations`');
        $this->db->query('DELETE FROM `cms_settings`');
        $this->db->query('DELETE FROM `cms_collection_translations`');
        $this->db->query('DELETE FROM `cms_collections`');
        $this->db->query('DELETE FROM `cms_slug_redirects`');
        $this->db->query('DELETE FROM `cms_redirects`');
        $this->db->query('DELETE FROM `cms_page_translations`');
        $this->db->query('DELETE FROM `cms_pages`');
        $this->db->query('DELETE FROM `cms_languages`');
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(3);
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    public function testLayoutRequiresWebAppKey(): void
    {
        $result = $this->withHeaders([])->get('/api/v1/public-read/' . $this->languages[0]['code'] . '/layout');

        $result->assertStatus(401);
    }

    public function testLayoutComposesNavigationCollectionsAndSettingsInOneResponse(): void
    {
        $locale = $this->languages[0]['code'];
        $languageId = $this->languages[0]['id'];

        $menu = $this->fixtures->menu([[
            'language_id' => $languageId,
            'name' => $this->fixtures->text('bootstrap-menu', $locale),
        ]], ['menu_key' => 'main', 'location' => 'header']);
        $this->db->table('cms_menu_items')->insert([
            'menu_id' => $menu['id'],
            'link_type' => 'custom_url',
            'link_target' => '_self',
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $itemId = (int) $this->db->insertID();
        $itemLabel = $this->fixtures->text('bootstrap-menu-item', $locale);
        $this->db->table('cms_menu_item_translations')->insert([
            'menu_item_id' => $itemId,
            'language_id' => $languageId,
            'label' => $itemLabel,
            'custom_url' => 'https://example.com',
        ]);

        $collectionSlug = $this->fixtures->slug('bootstrap-collection', $locale);
        $this->fixtures->collection([[
            'language_id' => $languageId,
            'slug' => $collectionSlug,
            'name' => $this->fixtures->text('bootstrap-collection', $locale),
        ]], ['collection_key' => $this->fixtures->slug('bootstrap-collection-key')]);

        $settingKey = 'site_name';
        $this->db->table('cms_settings')->insert([
            'setting_key' => $settingKey,
            'setting_value' => 'TeatroMuseo',
            'setting_type' => 'string',
            'is_translatable' => 0,
            'is_public' => 1,
            'is_active' => 1,
            'sort_order' => 1,
        ]);

        $result = $this->get('/api/v1/public-read/' . $locale . '/layout');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertPublicReadEnvelope($body);

        $this->assertSame($itemLabel, $body['data']['navigation']['main']['items'][0]['label'] ?? null);
        $this->assertSame('TeatroMuseo', $body['data']['settings'][$settingKey] ?? null);
        $collectionKeys = array_column($body['data']['collections'] ?? [], 'slug');
        $this->assertContains($collectionSlug, $collectionKeys);
    }

    public function testPageBootstrapRequiresWebAppKey(): void
    {
        $result = $this->withHeaders([])->get('/api/v1/public-read/' . $this->languages[0]['code'] . '/page-bootstrap/nosotros');

        $result->assertStatus(401);
    }

    public function testPageBootstrapReturnsThePageWithNoRedirect(): void
    {
        $locale = $this->languages[0]['code'];
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation]);

        $result = $this->get('/api/v1/public-read/' . $locale . '/page-bootstrap/' . $translation['slug']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertPublicReadEnvelope($body);
        $this->assertNull($body['data']['redirect']);
        $this->assertSame($translation['title'], $body['data']['page']['title'] ?? null);
        $this->assertArrayHasKey('blocks', $body['data']['page'] ?? []);
    }

    public function testPageBootstrapReturnsARedirectAndStillReportsNoPageAtTheOldPath(): void
    {
        $locale = $this->languages[0]['code'];
        $oldPath = $this->fixtures->slug('old-bootstrap-path');
        $newUrl = 'https://example.com/' . $this->fixtures->slug('destination');
        $this->db->table('cms_redirects')->insert([
            'old_path' => $oldPath,
            'new_url' => $newUrl,
            'redirect_type' => 302,
            'is_active' => 1,
        ]);

        $result = $this->get('/api/v1/public-read/' . $locale . '/page-bootstrap/' . $oldPath);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertPublicReadEnvelope($body);
        $this->assertSame($newUrl, $body['data']['redirect']['new_url'] ?? null);
        $this->assertSame(302, $body['data']['redirect']['redirect_type'] ?? null);
        $this->assertNull($body['data']['page']);
    }

    public function testPageBootstrapAnswers200WithBothNullWhenNeitherRedirectNorPageExist(): void
    {
        $locale = $this->languages[0]['code'];

        $result = $this->get('/api/v1/public-read/' . $locale . '/page-bootstrap/' . $this->fixtures->slug('never-existed'));

        // The composite always answers 200 with explicit nulls, unlike the
        // separate `public/redirects/{path}` endpoint's own 404 — the
        // caller (Web) still needs to try other resolution strategies
        // (collection entry, fallback listing) before deciding this is a
        // true 404. See ADR 006 and PublicReadPageBootstrapReader.
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertPublicReadEnvelope($body);
        $this->assertNull($body['data']['redirect']);
        $this->assertNull($body['data']['page']);
    }

    /** @return array<string, mixed> */
    private function pageTranslation(int $languagePosition): array
    {
        $language = $this->languages[$languagePosition];

        return [
            'language_id' => $language['id'],
            'slug' => $this->fixtures->slug('bootstrap-page', $language['code']),
            'title' => $this->fixtures->text('bootstrap-page-title', $language['code']),
            'excerpt' => $this->fixtures->text('bootstrap-page-excerpt', $language['code']),
        ];
    }

    /** @param array<string, mixed> $body */
    private function assertPublicReadEnvelope(array $body): void
    {
        $this->assertTrue($body['ok'] ?? false);
        $this->assertSame(1, $body['version'] ?? null);
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['meta'] ?? null);
        $this->assertSame('cms', $body['source']['domain'] ?? null);
        $this->assertSame('fresh', $body['source']['state'] ?? null);
        $this->assertFalse($body['source']['stale'] ?? true);
        $this->assertIsArray($body['messages'] ?? null);
    }
}
