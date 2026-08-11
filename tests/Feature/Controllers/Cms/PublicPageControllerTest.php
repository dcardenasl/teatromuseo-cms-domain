<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * @internal
 */
final class PublicPageControllerTest extends CIUnitTestCase
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

    private string $draftPageSlug;

    private string $draftPageTitle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->query("DELETE FROM `cms_page_translations`");
        $this->db->query("DELETE FROM `cms_pages`");
        $this->db->query("DELETE FROM `cms_languages`");
        $this->db->enableForeignKeyChecks();

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(3);
        $this->draftPageSlug = $this->fixtures->slug('draft-page', $this->languages[0]['code']);
        $this->draftPageTitle = $this->fixtures->text('draft-title', $this->languages[0]['code']);
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        parent::tearDown();
    }

    public function testGetPublicPageSuccess(): void
    {
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation]);

        $result = $this->get($this->pagePath($translation['slug']));

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertSame('success', $body['status']);
        $this->assertSame($translation['slug'], $body['data']['slug']);
        $this->assertSame($translation['title'], $body['data']['title']);
        $this->assertSame($translation['excerpt'], $body['data']['excerpt']);
        $this->assertSame($translation['og_image_url'], $body['data']['og_image']['url']);
        $this->assertSame('external_url', $body['data']['og_image']['source_kind']);
    }

    public function testPublicReadPageUsesVersionedEnvelope(): void
    {
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation]);

        $result = $this->get('/api/v1/public-read/' . $this->languages[0]['code'] . '/pages/' . $translation['slug']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertPublicReadEnvelope($body, 'cms');
        $this->assertSame($this->languages[0]['code'], $body['meta']['locale'] ?? null);
        $this->assertSame($translation['title'], $body['data']['title'] ?? null);
        $this->assertArrayHasKey('blocks', $body['data'] ?? []);
    }

    public function testPublicReadPageFallsBackToTheDefaultLocale(): void
    {
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation]);

        $result = $this->get('/api/v1/public-read/' . $this->languages[1]['code'] . '/pages/' . $translation['slug']);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertPublicReadEnvelope($body, 'cms');
        $this->assertSame($translation['title'], $body['data']['title'] ?? null);
    }

    public function testPublicReadNavigationRequiresWebAppKey(): void
    {
        $result = $this->withHeaders([])->get('/api/v1/public-read/' . $this->languages[0]['code'] . '/navigation');

        $result->assertStatus(401);
    }

    public function testPublicReadNavigationIncludesCollectionSlugsForCollectionAndEntryItems(): void
    {
        $locale = $this->languages[0]['code'];
        $languageId = $this->languages[0]['id'];
        $collectionSlug = $this->fixtures->slug('navigation-collection', $locale);
        $entrySlug = $this->fixtures->slug('navigation-entry', $locale);
        $collection = $this->fixtures->collection([[
            'language_id' => $languageId,
            'slug' => $collectionSlug,
            'name' => $this->fixtures->text('navigation-collection', $locale),
        ]]);
        $indexPageSlug = $this->fixtures->slug('navigation-collection-index', $locale);
        $this->fixtures->page([[
            'language_id' => $languageId,
            'slug' => $indexPageSlug,
            'title' => $this->fixtures->text('navigation-collection-index', $locale),
        ]], [
            'page_type' => 'collection_index',
            'collection_id' => $collection['id'],
        ]);
        $entry = $this->fixtures->entry($collection['id'], [[
            'language_id' => $languageId,
            'slug' => $entrySlug,
            'title' => $this->fixtures->text('navigation-entry', $locale),
        ]]);
        $menu = $this->fixtures->menu([[
            'language_id' => $languageId,
            'name' => $this->fixtures->text('navigation-menu', $locale),
        ]], ['menu_key' => 'main', 'location' => 'header']);

        $this->db->table('cms_menu_items')->insert([
            'menu_id' => $menu['id'],
            'link_type' => 'collection_listing',
            'collection_id' => $collection['id'],
            'link_target' => '_self',
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $this->db->table('cms_menu_items')->insert([
            'menu_id' => $menu['id'],
            'link_type' => 'entry',
            'entry_id' => $entry['id'],
            'link_target' => '_self',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        $items = $this->db->table('cms_menu_items')
            ->select('id')
            ->where('menu_id', $menu['id'])
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
        foreach ($items as $index => $item) {
            $this->db->table('cms_menu_item_translations')->insert([
                'menu_item_id' => $item['id'],
                'language_id' => $languageId,
                'label' => $this->fixtures->text('navigation-item-' . $index, $locale),
            ]);
        }

        $result = $this->withHeaders($this->webAppKeyHeader())
            ->get('/api/v1/public-read/' . $locale . '/navigation');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $navigationItems = $body['data']['main']['items'] ?? [];

        $this->assertSame($indexPageSlug, $navigationItems[0]['navigation']['slug']);
        $this->assertSame($indexPageSlug, $navigationItems[0]['navigation']['collection_slug']);
        $this->assertSame($entrySlug, $navigationItems[1]['navigation']['slug']);
        $this->assertSame($indexPageSlug, $navigationItems[1]['navigation']['collection_slug']);
    }

    public function testPublicReadPageRejectsUnknownSparseFields(): void
    {
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation]);

        $result = $this->get('/api/v1/public-read/' . $this->languages[0]['code'] . '/pages/' . $translation['slug'] . '?fields=unknown');

        $result->assertStatus(400);
    }

    public function testGetPublicPageNotFound(): void
    {
        $result = $this->get($this->pagePath($this->fixtures->slug('missing')));

        $result->assertStatus(404);
    }

    public function testDraftPageIsNotFoundByDefault(): void
    {
        $this->insertDraftPage();

        $result = $this->get($this->pagePath($this->draftSlug()));

        $result->assertStatus(404);
    }

    public function testDraftPageIsNotFoundWithBarePreviewFlagAndNoSignature(): void
    {
        $this->insertDraftPage();

        $result = $this->get($this->pagePath($this->draftSlug()) . '?preview=1');

        $result->assertStatus(404);
    }

    public function testDraftPageIsNotFoundWithTamperedSignature(): void
    {
        $this->insertDraftPage();
        $token = $this->signPreview($this->previewIdentifier());

        $result = $this->get($this->previewPath($token, 'deadbeef'));

        $result->assertStatus(404);
    }

    public function testDraftPageIsNotFoundWithExpiredSignature(): void
    {
        $this->insertDraftPage();
        $token = $this->signPreview($this->previewIdentifier(), -60);

        $result = $this->get($this->previewPath($token));

        $result->assertStatus(404);
    }

    public function testDraftPageIsNotFoundWithSignatureForADifferentSlug(): void
    {
        $this->insertDraftPage();
        $token = $this->signPreview($this->languages[0]['code'] . ':' . $this->fixtures->slug('other-page'));

        $result = $this->get($this->previewPath($token));

        $result->assertStatus(404);
    }

    public function testDraftPageIsVisibleWithAValidSignature(): void
    {
        $this->insertDraftPage();
        $token = $this->signPreview($this->previewIdentifier());

        $result = $this->get($this->previewPath($token));
        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertSame($this->draftPageTitle, $body['data']['title']);
    }

    public function testByTypeResolvesTheSingletonTemplatePage(): void
    {
        $translation = $this->pageTranslation(0);
        $page = $this->fixtures->page([$translation], ['page_type' => 'template_catalog_item']);

        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/pages/by-type/template_catalog_item');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame('success', $body['status']);
        $this->assertSame($page['id'], (int) $body['data']['id']);
        $this->assertSame('template_catalog_item', $body['data']['page_type']);
    }

    public function testByTypeRejectsNonTemplatePageTypes(): void
    {
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation], ['page_type' => 'home']);

        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/pages/by-type/home');

        $result->assertStatus(404);
    }

    public function testByTypeReturns404WhenNoTemplateExists(): void
    {
        $result = $this->get('/api/v1/public/' . $this->languages[0]['code'] . '/pages/by-type/template_event_item');

        $result->assertStatus(404);
    }

    public function testTemplatePagesAreNotResolvableBySlug(): void
    {
        $translation = $this->pageTranslation(0);
        $this->fixtures->page([$translation], ['page_type' => 'template_event_item']);

        $result = $this->get($this->pagePath($translation['slug']));

        $result->assertStatus(404);
    }

    /** @return array<string, mixed> */
    private function pageTranslation(int $languagePosition): array
    {
        $language = $this->languages[$languagePosition];

        return [
            'language_id' => $language['id'],
            'slug' => $this->fixtures->slug('page', $language['code']),
            'title' => $this->fixtures->text('page-title', $language['code']),
            'excerpt' => $this->fixtures->text('page-excerpt', $language['code']),
            'og_image_url' => 'https://example.com/' . $this->fixtures->slug('image') . '.jpg',
        ];
    }

    private function insertDraftPage(): int
    {
        $translation = [
            'language_id' => $this->languages[0]['id'],
            'slug' => $this->draftSlug(),
            'title' => $this->draftPageTitle,
            'excerpt' => $this->fixtures->text('draft-excerpt', $this->languages[0]['code']),
        ];
        $page = $this->fixtures->page([$translation], [
            'status' => 'draft',
            'is_in_sitemap' => 0,
        ]);

        return $page['id'];
    }

    private function draftSlug(): string
    {
        return $this->draftPageSlug;
    }

    private function previewIdentifier(): string
    {
        return $this->languages[0]['code'] . ':' . $this->draftSlug();
    }

    /** @return array{expires:string,sig:string} */
    private function signPreview(string $identifier, int $ttlSeconds = 3600): array
    {
        $expires = (string) (time() + $ttlSeconds);

        return [
            'expires' => $expires,
            'sig' => hash_hmac('sha256', 'page:' . $identifier . ':' . $expires, (string) env('CMS_PREVIEW_SECRET', '')),
        ];
    }

    /** @param array{expires:string,sig:string} $token */
    private function previewPath(array $token, ?string $signature = null): string
    {
        return $this->pagePath($this->draftSlug()) . '?preview=1&preview_expires=' . $token['expires']
            . '&preview_sig=' . ($signature ?? $token['sig']);
    }

    private function pagePath(string $slug): string
    {
        return '/api/v1/public/' . $this->languages[0]['code'] . '/pages/' . $slug;
    }

    /** @param array<string, mixed> $body */
    private function assertPublicReadEnvelope(array $body, string $domain): void
    {
        $this->assertTrue($body['ok'] ?? false);
        $this->assertSame(1, $body['version'] ?? null);
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['meta'] ?? null);
        $this->assertSame($domain, $body['source']['domain'] ?? null);
        $this->assertSame('fresh', $body['source']['state'] ?? null);
        $this->assertFalse($body['source']['stale'] ?? true);
        $this->assertIsArray($body['messages'] ?? null);
    }
}
