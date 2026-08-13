<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Cms;

use App\Libraries\Hub\HubClient;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\Fixtures\CmsFixtureFactory;
use Tests\Support\Traits\WithWebAppKeyTrait;

/**
 * Behavior coverage for PublicReadController::entries()/entry() — migrated
 * 2026-08-13 from the deleted PublicEntryControllerTest (which exercised the
 * exact-duplicate legacy route `public/{lang}/entries/{collection}[/{slug}]`,
 * removed per docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.F).
 * Both routes always shared the same PublicEntryReader::listPublic()/
 * showPublic() implementation, so this preserves the same real behavior
 * coverage against the surviving public-read route instead of re-testing
 * code that no longer exists.
 *
 * Response envelope differs from the deleted legacy route: public-read uses
 * `{ok: bool, data, meta: {total, per_page, ...}}` (PublicReadEnvelope), not
 * the legacy `{status: 'success', data, meta}` shape — assertions below use
 * `$body['ok']`, not `$body['status']`. Query params also differ:
 * `search=` (not `q=`), `per_page=` (not `limit=`).
 *
 * @internal
 */
final class PublicReadEntryControllerTest extends CIUnitTestCase
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

    private int $langEsId;

    private int $langEnId;

    private int $collectionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureWebAppKey();

        $this->db->disableForeignKeyChecks();
        $this->db->query("DELETE FROM `cms_entry_translations`");
        $this->db->query("DELETE FROM `cms_entries`");
        $this->db->query("DELETE FROM `cms_collections`");
        $this->db->query("DELETE FROM `cms_languages`");
        $this->db->enableForeignKeyChecks();

        if (! $this->db->fieldExists('featured_image_url', 'cms_entry_translations')) {
            $this->db->query(
                "ALTER TABLE `cms_entry_translations`
                 ADD COLUMN `featured_image_url` VARCHAR(2048) NULL AFTER `featured_file_id`"
            );
        }

        $this->fixtures = new CmsFixtureFactory($this->db, self::class);
        $this->languages = $this->fixtures->languages(3);
        $this->collection = $this->fixtures->collection([
            [
                'language_id' => $this->languages[0]['id'],
                'slug' => $this->fixtures->slug('collection', $this->languages[0]['code']),
                'name' => $this->fixtures->text('collection-name', $this->languages[0]['code']),
            ],
            [
                'language_id' => $this->languages[1]['id'],
                'slug' => $this->fixtures->slug('collection', $this->languages[1]['code']),
                'name' => $this->fixtures->text('collection-name', $this->languages[1]['code']),
            ],
        ]);
        $this->langEsId = $this->languages[0]['id'];
        $this->langEnId = $this->languages[1]['id'];
        $this->collectionId = $this->collection['id'];
    }

    protected function tearDown(): void
    {
        $this->restoreWebAppKey();
        Services::reset();
        parent::tearDown();
    }

    /**
     * Stubs the shared HubClient so FileUrlResolver never makes a live HTTP
     * call. Without this, tests asserting a stored featured_image_url
     * fallback only pass by accident — they depend on the real Hub having no
     * file at the fixture's hardcoded file_id, which silently breaks if a
     * local dev Hub happens to be running with real data at that id (see
     * LEGACY-MAP-015 in ../../../../TASKS.md, 2026-08-01).
     */
    private function mockHubClientWithNoFiles(): void
    {
        $stub = new class () extends HubClient {
            public function __construct()
            {
            }

            public function resolvePublicFileMeta(array $fileIds, int $cacheTtl = 300): array
            {
                return [];
            }
        };
        Services::injectMock('hubClient', $stub);
    }

    public function testGetPublicEntriesSuccess(): void
    {
        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'is_featured' => 1,
            'view_count' => 10,
            'sort_order' => 1,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'     => $entryId,
            'language_id'        => $this->langEsId,
            'slug'               => 'primer-post',
            'title'              => 'Primer Post',
            'excerpt'            => 'Esta es la primera entrada de blog.',
            'featured_image_url' => 'http://localhost:8180/uploads/posts/primer-post.png',
        ]);

        $result = $this->get($this->entryPath());

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertCount(1, $body['data']);
        $this->assertSame('primer-post', $body['data'][0]['slug']);
        $this->assertSame('Primer Post', $body['data'][0]['title']);
    }

    public function testGetPublicEntryDetailSuccess(): void
    {
        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'is_featured' => 1,
            'view_count' => 10,
            'sort_order' => 1,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'     => $entryId,
            'language_id'        => $this->langEsId,
            'slug'               => 'primer-post',
            'title'              => 'Primer Post',
            'excerpt'            => 'Esta es la primera entrada de blog.',
            'featured_image_url' => 'http://localhost:8180/uploads/posts/primer-post.png',
        ]);

        $result = $this->get($this->entryPath('/primer-post'));

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertSame('primer-post', $body['data']['slug']);
        $this->assertSame('Primer Post', $body['data']['title']);
        $this->assertIsArray($body['data']['blocks']);
    }

    public function testNewsPublicEntryProjectsFeaturedImageIntoVirtualGallery(): void
    {
        $this->db->table('cms_collections')
            ->where('id', $this->collectionId)
            ->update(['collection_key' => 'noticias']);
        $this->collection['key'] = 'noticias';

        $entry = $this->fixtures->entry($this->collectionId, [[
            'language_id' => $this->langEsId,
            'slug' => 'noticia-con-portada',
            'title' => 'Noticia con portada',
            'featured_image_url' => 'https://example.com/news-cover.jpg',
        ]]);

        $result = $this->get($this->entryPath('/noticia-con-portada'));
        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $blocks = $body['data']['blocks'];

        $this->assertSame(['gallery'], array_column($blocks, 'block_key'));
        $this->assertTrue($blocks[0]['is_virtual']);
        $this->assertSame('gallery_item', $blocks[0]['children'][0]['block_key']);
        $this->assertSame('https://example.com/news-cover.jpg', $blocks[0]['children'][0]['block_config']['image']['url']);
        $this->assertNotEmpty($entry['id']);
    }

    public function testPublicEntriesFallbackToAnotherLocaleWhenCurrentSlugIsEmpty(): void
    {
        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'is_featured' => 0,
            'view_count' => 0,
            'sort_order' => 1,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'           => $entryId,
            'language_id'        => $this->langEsId,
            'slug'               => '',
            'title'              => 'Festival en español',
            'excerpt'            => 'Entrada visible en español, pero sin slug local.',
            'featured_image_url' => 'http://localhost:8180/uploads/festival-es.png',
        ]);

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'           => $entryId,
            'language_id'        => $this->langEnId,
            'slug'               => 'festival-en',
            'title'              => 'Festival in English',
            'excerpt'            => 'Fallback slug.',
            'featured_image_url' => 'http://localhost:8180/uploads/festival-en.png',
        ]);

        $result = $this->get($this->entryPath());

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('festival-en', $body['data'][0]['slug']);
        $this->assertSame('Festival en español', $body['data'][0]['title']);
    }

    public function testListingContentIsOptInAndUsesSchemaData(): void
    {
        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'sort_order' => 1,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id' => $entryId,
            'language_id' => $this->langEsId,
            'slug' => 'con-contenido-listado',
            'title' => 'Con contenido de listado',
            'excerpt' => 'Extracto',
            'schema_data' => json_encode([
                'listing' => [
                    'rich_text' => '<p>Contenido adicional</p>',
                    'image' => ['url' => '/uploads/extra.jpg', 'alt' => 'Imagen adicional'],
                    'secondary_action' => ['label' => 'Explorar', 'url' => '/explorar'],
                ],
            ]),
        ]);

        $normal = $this->get($this->entryPath());
        $normalBody = json_decode($normal->getJSON(), true);
        $this->assertArrayNotHasKey('listing_content', $normalBody['data'][0]);

        $result = $this->get($this->entryPath('?include=listing_content'));
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertSame('<p>Contenido adicional</p>', $body['data'][0]['listing_content']['rich_text']);
        $this->assertSame('/uploads/extra.jpg', $body['data'][0]['listing_content']['image']['url']);
        $this->assertSame('Explorar', $body['data'][0]['listing_content']['secondary_action']['label']);
    }

    public function testGetPublicEntryNotFound(): void
    {
        $result = $this->get($this->entryPath('/no-existe'));
        $result->assertStatus(404);
    }

    public function testListingIncludesFeaturedImage(): void
    {
        $this->mockHubClientWithNoFiles();

        // Create entry with a public featured image URL
        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'is_featured' => 1,
            'view_count' => 10,
            'sort_order' => 1,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'           => $entryId,
            'language_id'        => $this->langEsId,
            'slug'               => 'post-con-imagen',
            'title'              => 'Post con Imagen',
            'excerpt'            => 'Post con imagen destacada',
            'featured_file_id'   => 42,
            'featured_image_url' => 'http://localhost:8180/uploads/posts/post-con-imagen.png',
        ]);

        $result = $this->get($this->entryPath());

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertCount(1, $body['data']);
        $this->assertArrayHasKey('featured_image', $body['data'][0]);
        $this->assertSame('hub_file', $body['data'][0]['featured_image']['source_kind']);
        $this->assertSame(42, $body['data'][0]['featured_image']['file_id']);
        $this->assertStringStartsWith('http://localhost:8180/uploads/', $body['data'][0]['featured_image']['url']);
        $this->assertArrayNotHasKey('featured_image_url', $body['data'][0]);
    }

    public function testPublicEntriesSupportLimitAndOrdering(): void
    {
        $entries = [
            [
                'slug'         => 'zeta',
                'title'        => 'Zeta',
                'sort_order'   => 2,
                'published_at' => '2026-06-02 09:00:00',
            ],
            [
                'slug'         => 'alpha',
                'title'        => 'Alpha',
                'sort_order'   => 1,
                'published_at' => '2026-06-01 09:00:00',
            ],
            [
                'slug'         => 'omega',
                'title'        => 'Omega',
                'sort_order'   => 3,
                'published_at' => '2026-06-03 09:00:00',
            ],
        ];

        foreach ($entries as $entry) {
            $this->db->table('cms_entries')->insert([
                'collection_id'    => $this->collectionId,
                'workflow_status'  => 'published',
                'published_at'     => $entry['published_at'],
                'is_featured'      => 0,
                'view_count'       => 0,
                'sort_order'       => $entry['sort_order'],
                'is_in_sitemap'    => 1,
            ]);
            $entryId = $this->db->insertID();

            $this->db->table('cms_entry_translations')->insert([
                'entry_id'    => $entryId,
                'language_id' => $this->langEsId,
                'slug'        => $entry['slug'],
                'title'       => $entry['title'],
                'excerpt'     => 'Entrada para probar orden.',
            ]);
        }

        $result = $this->get($this->entryPath('?per_page=2&order_by=title&order_direction=asc'));

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertCount(2, $body['data']);
        $this->assertSame('alpha', $body['data'][0]['slug']);
        $this->assertSame('omega', $body['data'][1]['slug']);
        $this->assertSame(3, (int) $body['meta']['total']);
        $this->assertSame(2, (int) $body['meta']['per_page']);
    }

    public function testTeatroEscuelaCollectionOrdersUpcomingFirstThenMostRecentPast(): void
    {
        // TeatroEscuela declares its start_date as a public listing field. The
        // reader sorts it through the generic field contract rather than a
        // collection-specific branch.
        $teatroEscuela = $this->fixtures->collection([
            [
                'language_id' => $this->langEsId,
                'slug' => 'teatroescuela',
                'name' => 'TeatroEscuela',
            ],
        ], ['collection_key' => 'teatroescuela']);

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'teatroescuela_ficha',
            'name' => 'Ficha de curso',
            'schema_definition' => json_encode([
                'fields' => ['start_date' => ['type' => 'date', 'label' => 'Inicio']],
                'listing_fields' => ['start_date' => ['type' => 'date', 'label' => 'Inicio']],
            ], JSON_THROW_ON_ERROR),
        ]);
        $blockId = (int) $this->db->insertID();

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $courses = [
            'hace-un-ano' => $now->modify('-1 year')->format('Y-m-d'),
            'manana' => $now->modify('+1 day')->format('Y-m-d'),
            'ayer' => $now->modify('-1 day')->format('Y-m-d'),
            'en-un-mes' => $now->modify('+1 month')->format('Y-m-d'),
            'hace-una-semana' => $now->modify('-1 week')->format('Y-m-d'),
        ];

        foreach ($courses as $slug => $startDate) {
            $entry = $this->fixtures->entry($teatroEscuela['id'], [
                [
                    'language_id' => $this->langEsId,
                    'slug' => $slug,
                    'title' => $slug,
                ],
            ]);

            $block = $this->fixtures->block($blockId, 'entry', $entry['id']);
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $block['id'],
                'language_id' => $this->langEsId,
                'block_data' => json_encode(['start_date' => $startDate], JSON_THROW_ON_ERROR),
            ]);

            // Fixture inserts block_data directly (bypassing BlockInstanceService/
            // EntryBlockTemplateInitializer), so it must also materialize
            // cms_entry_facet_values itself — real writes go through those
            // services, which call EntryFacetValueSynchronizer automatically.
            // Both the namespaced and bare field_key forms are written to match
            // what the real write path produces (the request below uses the
            // bare `field:start_date` legacy form).
            foreach (["block.teatroescuela_ficha.start_date", 'start_date'] as $fieldKey) {
                $this->db->table('cms_entry_facet_values')->insert([
                    'entry_id' => $entry['id'],
                    'block_instance_id' => $block['id'],
                    'language_id' => $this->langEsId,
                    'field_key' => $fieldKey,
                    'value_type' => 'date',
                    'value_string' => $startDate,
                    'value_date' => $startDate,
                    'value_numeric' => null,
                ]);
            }
        }

        $result = $this->get('/api/v1/public-read/' . $this->languages[0]['code'] . '/entries/teatroescuela?per_page=10&order_by=field:start_date&order_direction=upcoming');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $slugs = array_column($body['data'], 'slug');

        $this->assertSame(['manana', 'en-un-mes', 'ayer', 'hace-una-semana', 'hace-un-ano'], $slugs);

        // The listing template shows/sorts by the course's real start_date, not
        // published_at — exposed as `display_date` through the generic contract.
        $displayDatesBySlug = array_combine($slugs, array_column($body['data'], 'display_date'));
        foreach ($courses as $slug => $startDate) {
            $this->assertSame($startDate, $displayDatesBySlug[$slug]);
        }
    }

    public function testPublicEntriesFilterByLocalizedTitleAndReturnEmptyForUnknownSearch(): void
    {
        foreach ([
            ['slug' => 'plataforma-comercial', 'title' => 'Plataforma comercial'],
            ['slug' => 'banca-digital', 'title' => 'Rediseño de banca digital'],
        ] as $sortOrder => $entry) {
            $this->db->table('cms_entries')->insert([
                'collection_id'   => $this->collectionId,
                'workflow_status' => 'published',
                'sort_order'      => $sortOrder + 1,
                'is_in_sitemap'   => 1,
            ]);
            $entryId = $this->db->insertID();

            $this->db->table('cms_entry_translations')->insert([
                'entry_id'    => $entryId,
                'language_id' => $this->langEsId,
                'slug'        => $entry['slug'],
                'title'       => $entry['title'],
                'excerpt'     => 'Contenido público de prueba.',
            ]);
        }

        $matching = $this->get($this->entryPath('?search=Banca'));
        $matching->assertStatus(200);
        $matchingBody = json_decode($matching->getJSON(), true);

        $this->assertCount(1, $matchingBody['data']);
        $this->assertSame('banca-digital', $matchingBody['data'][0]['slug']);
        $this->assertSame(1, (int) $matchingBody['meta']['total']);

        $unknown = $this->get($this->entryPath('?search=zzzz-sin-resultados'));
        $unknown->assertStatus(200);
        $unknownBody = json_decode($unknown->getJSON(), true);

        $this->assertSame([], $unknownBody['data']);
        $this->assertSame(0, (int) $unknownBody['meta']['total']);
    }

    public function testShowIncludesFeaturedImage(): void
    {
        $this->mockHubClientWithNoFiles();

        // Create entry with a public featured image URL
        $this->db->table('cms_entries')->insert([
            'collection_id' => $this->collectionId,
            'workflow_status' => 'published',
            'is_featured' => 1,
            'view_count' => 10,
            'sort_order' => 1,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'           => $entryId,
            'language_id'        => $this->langEsId,
            'slug'               => 'detalle-con-imagen',
            'title'              => 'Detalle con Imagen',
            'excerpt'            => 'Detalle con imagen destacada',
            'featured_file_id'   => 99,
            'featured_image_url' => 'http://localhost:8180/uploads/posts/detalle-con-imagen.png',
        ]);

        $result = $this->get($this->entryPath('/detalle-con-imagen'));

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('featured_image', $body['data']);
        $this->assertSame('hub_file', $body['data']['featured_image']['source_kind']);
        $this->assertSame(99, $body['data']['featured_image']['file_id']);
        $this->assertSame('http://localhost:8180/uploads/posts/detalle-con-imagen.png', $body['data']['featured_image']['url']);
        $this->assertArrayNotHasKey('featured_image_url', $body['data']);
    }

    public function testGetPublicEntriesFilteredByCategoryAndTag(): void
    {
        // Truncate taxonomy tables
        $this->db->disableForeignKeyChecks();
        $this->db->query("DELETE FROM `cms_entry_categories`");
        $this->db->query("DELETE FROM `cms_entry_tags`");
        $this->db->query("DELETE FROM `cms_category_translations`");
        $this->db->query("DELETE FROM `cms_categories`");
        $this->db->query("DELETE FROM `cms_tag_translations`");
        $this->db->query("DELETE FROM `cms_tags`");
        $this->db->enableForeignKeyChecks();

        // 1. Setup category
        $this->db->table('cms_categories')->insert([
            'collection_id' => $this->collectionId,
            'sort_order'    => 1,
            'is_active'     => 1,
        ]);
        $categoryId = $this->db->insertID();
        $this->db->table('cms_category_translations')->insert([
            'category_id' => $categoryId,
            'language_id' => $this->langEsId,
            'slug'        => 'noticias',
            'name'        => 'Noticias',
        ]);

        // 2. Setup tag
        $this->db->table('cms_tags')->insert([
            'is_active' => 1,
        ]);
        $tagId = $this->db->insertID();
        $this->db->table('cms_tag_translations')->insert([
            'tag_id'      => $tagId,
            'language_id' => $this->langEsId,
            'slug'        => 'php',
            'name'        => 'PHP',
        ]);

        // 3. Create entry
        $this->db->table('cms_entries')->insert([
            'collection_id'   => $this->collectionId,
            'workflow_status' => 'published',
            'is_featured'     => 1,
            'view_count'      => 10,
            'sort_order'      => 1,
            'is_in_sitemap'   => 1,
        ]);
        $entryId = $this->db->insertID();
        $this->db->table('cms_entry_translations')->insert([
            'entry_id'    => $entryId,
            'language_id' => $this->langEsId,
            'slug'        => 'post-filtrado',
            'title'       => 'Post Filtrado',
            'excerpt'     => 'Texto de prueba',
        ]);

        // 4. Link category and tag
        $this->db->table('cms_entry_categories')->insert([
            'entry_id'    => $entryId,
            'category_id' => $categoryId,
            'sort_order'  => 0,
        ]);
        $this->db->table('cms_entry_tags')->insert([
            'entry_id' => $entryId,
            'tag_id'   => $tagId,
        ]);

        // 5. Query without filters -> should return it and include categories & tags
        $result = $this->get($this->entryPath());
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(1, $body['data']);
        $this->assertSame('noticias', $body['data'][0]['categories'][0]['slug']);
        $this->assertSame('php', $body['data'][0]['tags'][0]['slug']);

        // 6. Query with correct category filter -> should return it
        $result = $this->get($this->entryPath('?category=noticias'));
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(1, $body['data']);

        // 7. Query with incorrect category filter -> should return empty
        $result = $this->get($this->entryPath('?category=deportes'));
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(0, $body['data']);

        // 8. Query with correct tag filter -> should return it
        $result = $this->get($this->entryPath('?tag=php'));
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(1, $body['data']);

        // 9. Query with incorrect tag filter -> should return empty
        $result = $this->get($this->entryPath('?tag=java'));
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(0, $body['data']);
    }

    private function insertDraftEntry(): int
    {
        $this->db->table('cms_entries')->insert([
            'collection_id'    => $this->collectionId,
            'workflow_status'  => 'draft',
            'is_featured'      => 0,
            'view_count'       => 0,
            'sort_order'       => 1,
            'is_in_sitemap'    => 0,
        ]);
        $entryId = $this->db->insertID();

        $this->db->table('cms_entry_translations')->insert([
            'entry_id'    => $entryId,
            'language_id' => $this->langEsId,
            'slug'        => 'entrada-borrador',
            'title'       => 'Entrada en borrador',
            'excerpt'     => 'No debe ser visible sin una firma válida.',
        ]);

        return $entryId;
    }

    private function signPreview(string $type, int $id, int $ttlSeconds = 3600): array
    {
        $expires = (string) (time() + $ttlSeconds);

        return [
            'expires' => $expires,
            'sig'     => hash_hmac('sha256', $type . ':' . $id . ':' . $expires, (string) env('CMS_PREVIEW_SECRET', '')),
        ];
    }

    private function entryPath(string $suffix = ''): string
    {
        return '/api/v1/public-read/' . $this->languages[0]['code'] . '/entries/' . $this->collection['key'] . $suffix;
    }

    public function testDraftEntryIsNotFoundByDefault(): void
    {
        $this->insertDraftEntry();

        $result = $this->get($this->entryPath('/entrada-borrador'));
        $result->assertStatus(404);
    }

    public function testDraftEntryIsNotFoundWithBarePreviewFlagAndNoSignature(): void
    {
        $this->insertDraftEntry();

        $result = $this->get($this->entryPath('/entrada-borrador?preview=1'));
        $result->assertStatus(404);
    }

    public function testDraftEntryIsNotFoundWithSignatureForADifferentEntry(): void
    {
        $entryId = $this->insertDraftEntry();
        $token = $this->signPreview('entry', $entryId + 999);

        $result = $this->get($this->entryPath('/entrada-borrador?preview=1&preview_expires=' . $token['expires'] . '&preview_sig=' . $token['sig']));
        $result->assertStatus(404);
    }

    public function testDraftEntryIsVisibleWithAValidSignature(): void
    {
        $entryId = $this->insertDraftEntry();
        $token = $this->signPreview('entry', $entryId);

        $result = $this->get($this->entryPath('/entrada-borrador?preview=1&preview_expires=' . $token['expires'] . '&preview_sig=' . $token['sig']));
        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);
        $this->assertSame('Entrada en borrador', $body['data']['title']);
    }
}
