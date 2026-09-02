<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * MIG-01 converted the 41 CMS content-editing migrations (2026-08-03 through
 * 2026-08-05) into idempotent seeder methods on CmsContentSanitizationSeeder,
 * because `spark migrate` used to silently revert edits an editor made from
 * the admin. Those migrations are now inert stubs (see
 * CleanDatabaseBootstrapConventionsTest), so this covers the settings-
 * normalization slice that used to be asserted against
 * NormalizeSiteSettings::up() directly — plus the idempotency guarantee the
 * conversion exists for, which the old migration-level test had no way to
 * express.
 *
 * @internal
 */
final class CmsContentSanitizationSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';

    private const RETIRED_SETTING_KEYS = [
        'site_title',
        'footer_bg_color',
        'footer_text_color',
        'footer_border_color',
        'contact_admin_email',
        'contact_from_email',
        'contact_site_name',
        'contact_autoreply_message',
        'social_twitter',
        'social_linkedin',
        'social_tiktok',
        'social_pinterest',
        'social_github',
    ];

    public function testNormalizeSiteSettingsRemovesRetiredKeysNormalizesAnalyticsProviderAndIsIdempotent(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        // Simulate a pre-existing install that still carries the retired
        // settings and a legacy (pre-normalization) analytics_provider row —
        // the state CmsContentSanitizationSeeder must be able to repair on a
        // database that already has real content in every other cms_ table,
        // not just an empty one.
        foreach (self::RETIRED_SETTING_KEYS as $settingKey) {
            $this->insertSetting($settingKey);
        }

        $this->db->table('cms_settings')
            ->where('setting_key', 'analytics_provider')
            ->update([
                'input_type'   => 'text',
                'options_json' => null,
                'description'  => 'Legacy analytics provider',
            ]);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $this->assertSiteSettingsNormalized();

        // Idempotency is the entire point of MIG-01: running the seeder again
        // against an already-normalized database must be a no-op, not a
        // second mutation, a duplicate row, or an error.
        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $this->assertSiteSettingsNormalized();
    }

    public function testNormalizePublicNavigationSlugsRepairsTheatreSchoolCollectionAndIndexPage(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $collection = $this->db->table('cms_collections')
            ->where('collection_key', 'teatroescuela')
            ->get()
            ->getRowArray();
        $this->assertIsArray($collection);

        $page = $this->db->table('cms_pages')
            ->where('collection_id', (int) $collection['id'])
            ->where('page_type', 'collection_index')
            ->get()
            ->getRowArray();
        $this->assertIsArray($page);

        // Reproduce the stale state found in the imported database: the
        // collection slugs and page-index slugs all point to the Spanish key.
        $this->db->table('cms_collection_translations')
            ->where('collection_id', (int) $collection['id'])
            ->update(['slug' => 'teatroescuela']);
        $this->db->table('cms_page_translations')
            ->where('page_id', (int) $page['id'])
            ->update(['slug' => 'teatroescuela']);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $expected = [
            'en' => 'theaterschool',
            'es' => 'teatroescuela',
            'fr' => 'theatreecole',
            'pt' => 'escola-de-teatro',
        ];
        $this->assertSame($expected, $this->localizedSlugs('cms_collection_translations', 'collection_id', (int) $collection['id']));
        $this->assertSame($expected, $this->localizedSlugs('cms_page_translations', 'page_id', (int) $page['id']));

        // Deployment may invoke the sanitizer repeatedly; it must remain
        // idempotent after the repair has been applied.
        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);
        $this->assertSame($expected, $this->localizedSlugs('cms_page_translations', 'page_id', (int) $page['id']));
    }

    public function testRouteAlignmentRepairsExistingLocalizedPageSlugs(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $eventsPage = $this->db->table('cms_pages')
            ->where('page_type', 'events')
            ->get()
            ->getRowArray();
        $aboutPage = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('p.deleted_at IS NULL', null, false)
            ->where('pt.slug', 'about')
            ->get()
            ->getRowArray();
        $this->assertIsArray($eventsPage);
        $this->assertIsArray($aboutPage);

        // Reproduce the mixed beta state: the page rows exist, but localized
        // URL translations were written with one legacy slug per language.
        $this->db->table('cms_page_translations')
            ->where('page_id', (int) $eventsPage['id'])
            ->update(['slug' => 'events']);
        $this->db->table('cms_page_translations')
            ->where('page_id', (int) $aboutPage['id'])
            ->update(['slug' => 'about-us']);

        $seeder->call(\App\Database\Seeds\CmsTeatroMuseoRouteAlignmentSeeder::class);

        $this->assertSame(
            ['en' => 'programming', 'es' => 'cartelera', 'fr' => 'programmation', 'pt' => 'programacao'],
            $this->localizedSlugs('cms_page_translations', 'page_id', (int) $eventsPage['id'])
        );
        $this->assertSame(
            ['en' => 'about', 'es' => 'nosotros', 'fr' => 'a-propos', 'pt' => 'sobre-nos'],
            $this->localizedSlugs('cms_page_translations', 'page_id', (int) $aboutPage['id'])
        );
    }

    public function testPublicDetailTemplateSeoDefaultsRemainEditableAndAreIdempotent(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $templatePages = $this->db->table('cms_pages')
            ->select('id')
            ->whereIn('page_type', ['template_catalog_item', 'template_event_item'])
            ->get()
            ->getResultArray();
        $this->assertNotEmpty($templatePages);

        $templatePageIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            $templatePages,
        );
        $this->db->table('cms_page_translations')
            ->whereIn('page_id', $templatePageIds)
            ->update(['og_type' => null]);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $types = array_values(array_unique(array_column(
            $this->db->table('cms_page_translations')
                ->select('og_type')
                ->whereIn('page_id', $templatePageIds)
                ->get()
                ->getResultArray(),
            'og_type',
        )));
        self::assertSame(['article'], $types);

        // A second run must preserve the normalized result and not mutate
        // editorial values that were already selected by the CMS.
        $this->db->table('cms_page_translations')
            ->where('page_id', $templatePageIds[0])
            ->where('og_type', 'article')
            ->update(['og_type' => 'website']);
        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $editorialType = $this->db->table('cms_page_translations')
            ->where('page_id', $templatePageIds[0])
            ->where('og_type', 'website')
            ->countAllResults();
        self::assertSame(4, $editorialType);
    }

    public function testRemovePeopleNavigationRetiresStaleGenericLocalizedIndexPages(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $language = $this->db->table('cms_languages')
            ->where('code', 'es')
            ->get()
            ->getRowArray();
        $this->assertIsArray($language);

        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 999,
            'is_in_sitemap' => 1,
        ]);
        $pageId = (int) $this->db->insertID();
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => (int) $language['id'],
            'slug' => 'personas',
            'title' => 'Personas',
        ]);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $page = $this->db->table('cms_pages')->where('id', $pageId)->get()->getRowArray();
        $this->assertIsArray($page);
        $this->assertSame('draft', $page['status']);
        $this->assertNotNull($page['deleted_at']);

        $peopleCollection = $this->db->table('cms_collections')
            ->where('collection_key', 'personas')
            ->get()
            ->getRowArray();
        $this->assertIsArray($peopleCollection);
        $this->assertSame(0, (int) $peopleCollection['is_active']);
    }

    public function testTeatroEscuelaOrderingIsMigratedOnceAndRemainsConfigurable(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $row = $this->db->table('cms_block_instances')
            ->select('id, block_config')
            ->where('owner_type', 'page')
            ->like('block_config', 'teatroescuela')
            ->get()
            ->getRowArray();
        $this->assertIsArray($row);

        $config = json_decode((string) $row['block_config'], true);
        $this->assertIsArray($config);
        $this->assertSame('upcoming', $config['order_direction']);
        $this->assertSame('upcoming', $config['listing_projection']['order']['direction']);
        $this->assertSame('block.teatroescuela_ficha.start_date', $config['listing_projection']['order']['field']);
        $this->assertSame(2, (int) $config['listing_projection']['version']);

        // Older Admin clients submitted the nested projection as a JSON
        // string. The sanitizer must decode it instead of rebuilding a
        // lossy legacy projection from the top-level fields.
        $config['listing_projection'] = json_encode($config['listing_projection'], JSON_THROW_ON_ERROR);
        $this->db->table('cms_block_instances')
            ->where('id', (int) $row['id'])
            ->update(['block_config' => json_encode($config, JSON_THROW_ON_ERROR)]);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $normalized = $this->db->table('cms_block_instances')
            ->select('block_config')
            ->where('id', (int) $row['id'])
            ->get()
            ->getRowArray();
        $normalizedConfig = json_decode((string) ($normalized['block_config'] ?? '{}'), true);
        $this->assertIsArray($normalizedConfig['listing_projection'] ?? null);
        $this->assertSame('block.teatroescuela_ficha.start_date', $normalizedConfig['listing_projection']['order']['field']);

        // A later editor change is configuration, not legacy state. The
        // idempotent sanitizer must preserve it on the next bootstrap.
        $normalizedConfig['order_direction'] = 'desc';
        $normalizedConfig['listing_projection']['order']['direction'] = 'desc';
        $this->db->table('cms_block_instances')
            ->where('id', (int) $row['id'])
            ->update(['block_config' => json_encode($normalizedConfig, JSON_THROW_ON_ERROR)]);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $updated = $this->db->table('cms_block_instances')
            ->select('block_config')
            ->where('id', (int) $row['id'])
            ->get()
            ->getRowArray();
        $updatedConfig = json_decode((string) ($updated['block_config'] ?? '{}'), true);
        $this->assertSame('desc', $updatedConfig['order_direction']);
        $this->assertSame('desc', $updatedConfig['listing_projection']['order']['direction']);
    }

    public function testPublishedVideoBlocksAreExposedAndDraftVideoBlocksRemainPrivate(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $videos = $this->db->table('cms_collections')
            ->where('collection_key', 'videos')
            ->get()
            ->getRowArray();
        $videoType = $this->db->table('cms_content_blocks')
            ->where('block_key', 'video_ficha')
            ->get()
            ->getRowArray();
        $spanish = $this->db->table('cms_languages')
            ->where('code', 'es')
            ->get()
            ->getRowArray();

        $this->assertIsArray($videos);
        $this->assertIsArray($videoType);
        $this->assertIsArray($spanish);

        $createVideo = function (string $workflow, array $data) use ($videos, $videoType, $spanish): int {
            $this->db->table('cms_entries')->insert([
                'collection_id' => (int) $videos['id'],
                'workflow_status' => $workflow,
                'published_at' => $workflow === 'published' ? date('Y-m-d H:i:s') : null,
                'is_in_sitemap' => 1,
            ]);
            $entryId = (int) $this->db->insertID();

            $this->db->table('cms_block_instances')->insert([
                'block_id' => (int) $videoType['id'],
                'owner_type' => 'entry',
                'owner_id' => $entryId,
                'sort_order' => 1,
                'is_active' => 1,
                'block_config' => '{}',
            ]);
            $instanceId = (int) $this->db->insertID();

            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $instanceId,
                'language_id' => (int) $spanish['id'],
                'block_data' => json_encode($data, JSON_THROW_ON_ERROR),
                'is_published' => 0,
            ]);

            return $instanceId;
        };

        $publishedId = $createVideo('published', [
            'provider' => 'youtube',
            'video_id' => '1SBdy9DB9oQ',
            'video_url' => 'https://www.youtube.com/watch?v=1SBdy9DB9oQ',
        ]);
        $draftId = $createVideo('draft', [
            'provider' => 'youtube',
            'video_id' => '1SBdy9DB9oQ',
            'video_url' => 'https://www.youtube.com/watch?v=1SBdy9DB9oQ',
        ]);
        $invalidId = $createVideo('published', [
            'provider' => 'youtube',
            'video_id' => 'invalid',
            'video_url' => 'https://www.youtube.com/watch?v=invalid',
        ]);

        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);

        $published = $this->db->table('cms_block_instance_translations')->where('instance_id', $publishedId)->get()->getRowArray();
        $draft = $this->db->table('cms_block_instance_translations')->where('instance_id', $draftId)->get()->getRowArray();
        $invalid = $this->db->table('cms_block_instance_translations')->where('instance_id', $invalidId)->get()->getRowArray();

        $this->assertIsArray($published);
        $this->assertIsArray($draft);
        $this->assertIsArray($invalid);
        $this->assertSame(1, (int) $published['is_published']);
        $this->assertSame(0, (int) $draft['is_published']);
        $this->assertSame(0, (int) $invalid['is_published']);

        // The normalization is safe to run repeatedly after deployment.
        $seeder->call(\App\Database\Seeds\CmsContentSanitizationSeeder::class);
        $publishedAgain = $this->db->table('cms_block_instance_translations')->where('instance_id', $publishedId)->get()->getRowArray();
        $this->assertSame(1, (int) ($publishedAgain['is_published'] ?? 0));
    }

    private function assertSiteSettingsNormalized(): void
    {
        $candidateKeys = [...self::RETIRED_SETTING_KEYS, 'analytics_provider', 'site_name'];

        $remainingKeys = array_column(
            $this->db->table('cms_settings')
                ->select('setting_key')
                ->whereIn('setting_key', $candidateKeys)
                ->orderBy('setting_key', 'ASC')
                ->get()
                ->getResultArray(),
            'setting_key'
        );

        $this->assertSame(['analytics_provider', 'site_name'], $remainingKeys);

        $analyticsProvider = $this->db->table('cms_settings')
            ->where('setting_key', 'analytics_provider')
            ->get()
            ->getRowArray();

        $this->assertIsArray($analyticsProvider);
        $this->assertSame('select', $analyticsProvider['input_type'] ?? null);
        $this->assertSame(
            ['none', 'ga4', 'plausible', 'fathom'],
            array_column(json_decode((string) ($analyticsProvider['options_json'] ?? '[]'), true), 'value')
        );
        $this->assertSame(
            'Proveedor de analytics: none | ga4 | plausible | fathom',
            $analyticsProvider['description'] ?? null
        );
    }

    /** @param array<string, mixed> $overrides */
    private function insertSetting(string $settingKey, array $overrides = []): void
    {
        $this->db->table('cms_settings')->insert(array_merge([
            'setting_key'     => $settingKey,
            'setting_value'   => 'legacy-value',
            'setting_type'    => 'string',
            'input_type'      => 'text',
            'setting_group'   => 'general',
            'is_translatable' => 0,
            'is_public'       => 1,
            'is_active'       => 1,
            'sort_order'      => 0,
        ], $overrides));
    }

    /** @return array<string, string> */
    private function localizedSlugs(string $table, string $foreignKey, int $id): array
    {
        $rows = $this->db->table($table . ' t')
            ->select('l.code, t.slug')
            ->join('cms_languages l', 'l.id = t.language_id')
            ->where('t.' . $foreignKey, $id)
            ->get()
            ->getResultArray();

        $slugs = [];
        foreach ($rows as $row) {
            $slugs[(string) $row['code']] = (string) $row['slug'];
        }
        ksort($slugs);

        return $slugs;
    }
}
