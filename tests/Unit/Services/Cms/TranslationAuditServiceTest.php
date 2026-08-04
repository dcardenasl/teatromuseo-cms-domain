<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cms;

use App\Interfaces\Cms\TranslationAuditServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class TranslationAuditServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private int $langEsId;
    private int $langEnId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->disableForeignKeyChecks();
        $this->db->query("DELETE FROM `cms_page_translations`");
        $this->db->query("DELETE FROM `cms_pages`");
        $this->db->query("DELETE FROM `cms_menu_translations`");
        $this->db->query("DELETE FROM `cms_menus`");
        $this->db->query("DELETE FROM `cms_menu_item_translations`");
        $this->db->query("DELETE FROM `cms_menu_items`");
        $this->db->query("DELETE FROM `cms_setting_translations`");
        $this->db->query("DELETE FROM `cms_settings`");
        $this->db->query("DELETE FROM `cms_block_instance_translations`");
        $this->db->query("DELETE FROM `cms_block_instances`");
        $this->db->query("DELETE FROM `cms_content_blocks`");
        $this->db->query("DELETE FROM `cms_form_field_translations`");
        $this->db->query("DELETE FROM `cms_form_fields`");
        $this->db->query("DELETE FROM `cms_form_translations`");
        $this->db->query("DELETE FROM `cms_forms`");
        $this->db->query("DELETE FROM `cms_entry_translations`");
        $this->db->query("DELETE FROM `cms_entries`");
        $this->db->query("DELETE FROM `cms_collection_translations`");
        $this->db->query("DELETE FROM `cms_collections`");
        $this->db->query("DELETE FROM `cms_category_translations`");
        $this->db->query("DELETE FROM `cms_categories`");
        $this->db->query("DELETE FROM `cms_tag_translations`");
        $this->db->query("DELETE FROM `cms_tags`");
        $this->db->query("DELETE FROM `cms_languages`");
        $this->db->enableForeignKeyChecks();

        // Seed languages
        $this->db->table('cms_languages')->insert([
            'code'       => 'es',
            'name'       => 'Spanish',
            'native_name' => 'Español',
            'is_default' => 1,
            'is_active'  => 1,
        ]);
        $this->langEsId = $this->db->insertID();

        $this->db->table('cms_languages')->insert([
            'code'       => 'en',
            'name'       => 'English',
            'native_name' => 'English',
            'is_default' => 0,
            'is_active'  => 1,
        ]);
        $this->langEnId = $this->db->insertID();
    }

    public function testServiceImplementsItsInterface(): void
    {
        $service = Services::translationAuditService(false);
        $this->assertInstanceOf(TranslationAuditServiceInterface::class, $service);
    }

    public function testGetOverallCompleteness(): void
    {
        // Add a page
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        // Translation for Spanish (complete)
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
        ]);

        // Translation for English (incomplete: missing slug)
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEnId,
            'slug' => '',
            'title' => 'Home',
        ]);

        $service = Services::translationAuditService(false);
        $stats = $service->getOverallCompleteness();

        $this->assertCount(2, $stats);

        $esStat = array_values(array_filter($stats, fn ($s) => $s['code'] === 'es'))[0];
        $enStat = array_values(array_filter($stats, fn ($s) => $s['code'] === 'en'))[0];

        // Spanish: 1 page complete / 1 page total = 100%
        $this->assertEquals(100, $esStat['percentage']);
        // English: 0 page complete / 1 page total = 0%
        $this->assertEquals(0, $enStat['percentage']);
    }

    public function testGetMissingTranslationsReport(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        // Complete ES
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
        ]);

        // Missing EN completely
        $service = Services::translationAuditService(false);
        $report = $service->getMissingTranslationsReport();

        $this->assertCount(1, $report);
        $this->assertEquals('page', $report[0]['resource']);
        $this->assertEquals($pageId, $report[0]['resource_id']);
        $this->assertEquals($this->langEnId, $report[0]['language_id']);
        $this->assertEquals('missing', $report[0]['status']);
        // Pages have no canonical `title` column at all (it only lives in
        // cms_page_translations) — the reference name must come from the
        // ES translation's title, never a technical "Page #N" placeholder.
        $this->assertSame('Inicio', $report[0]['reference_name']);
    }

    public function testMissingTranslationsReportFiltersByResourceStatusAndSearch(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
        ]);

        $service = Services::translationAuditService(false);

        // Searching by the real title (not a technical "Page #N" placeholder
        // that no longer exists once a translation supplies a real name).
        $report = $service->getMissingTranslationsReport([
            'resource' => 'page',
            'status' => 'missing',
            'search' => 'inicio',
        ]);

        $this->assertCount(1, $report);
        $this->assertSame('page', $report[0]['resource']);
        $this->assertSame('missing', $report[0]['status']);
        $this->assertSame('Inicio', $report[0]['reference_name']);
    }

    public function testMissingSettingTranslationRowsAreReportedOnlyForNonDefaultLanguages(): void
    {
        $this->db->table('cms_settings')->insert([
            'setting_key' => 'site_name',
            'setting_value' => 'Mi Sitio',
            'setting_type' => 'string',
            'setting_group' => 'identity',
            'is_translatable' => 1,
            'is_public' => 1,
            'is_active' => 1,
            'sort_order' => 10,
        ]);
        $settingId = $this->db->insertID();

        $service = Services::translationAuditService(false);
        $report = $service->getMissingTranslationsReport();

        $this->assertCount(1, $report);
        $this->assertSame('setting', $report[0]['resource']);
        $this->assertSame($settingId, $report[0]['resource_id']);
        $this->assertSame($this->langEnId, $report[0]['language_id']);
        $this->assertSame('missing', $report[0]['status']);

        $audit = $service->auditResource('setting', $settingId);
        $this->assertSame('complete', $audit['es']['status']);
        $this->assertSame('missing', $audit['en']['status']);
    }

    public function testAuditResource(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        // Incomplete ES (missing slug)
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => '',
            'title' => 'Inicio',
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('page', $pageId);

        $this->assertEquals('incomplete', $audit['es']['status']);
        $this->assertEquals('missing', $audit['en']['status']);
    }

    public function testAuditResourceFlagsACompleteTranslationOlderThanTheSourceAsOutdated(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
            'updated_at' => '2026-07-20 12:00:00',
        ]);
        $pageId = $this->db->insertID();

        // Complete, but translated before the page's latest update.
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
            'updated_at' => '2026-07-19 08:00:00',
        ]);

        // Complete and translated after the page's latest update: stays complete.
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEnId,
            'slug' => 'home',
            'title' => 'Home',
            'updated_at' => '2026-07-20 13:00:00',
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('page', $pageId);

        $this->assertEquals('outdated', $audit['es']['status']);
        $this->assertEquals('complete', $audit['en']['status']);
    }

    public function testDefaultLanguageIsNotMarkedOutdatedAgainstItsOwnSourceResource(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
            'updated_at' => '2026-07-20 12:00:00',
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
            'updated_at' => '2026-07-19 08:00:00',
        ]);

        $audit = Services::translationAuditService(false)->auditResource('page', $pageId);

        $this->assertSame('complete', $audit['es']['status']);
    }

    public function testGetMissingTranslationsReportCoversCmsContent(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
        ]);

        $this->db->table('cms_menus')->insert([
            'menu_key' => 'main',
            'location' => 'header',
            'is_active' => 1,
        ]);
        $menuId = $this->db->insertID();
        $this->db->table('cms_menu_translations')->insert([
            'menu_id' => $menuId,
            'language_id' => $this->langEsId,
            'name' => 'Principal',
        ]);

        $this->db->table('cms_collections')->insert([
            'collection_key' => 'news',
            'is_active' => 1,
            'requires_approval' => 0,
            'enables_categories' => 1,
            'enables_tags' => 1,
            'default_sitemap_priority' => 0.5,
            'default_changefreq' => 'weekly',
            'sort_order' => 1,
        ]);
        $collectionId = $this->db->insertID();
        $this->db->table('cms_collection_translations')->insert([
            'collection_id' => $collectionId,
            'language_id' => $this->langEsId,
            'slug' => 'noticias',
            'name' => 'Noticias',
        ]);

        $this->db->table('cms_categories')->insert([
            'collection_id' => $collectionId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $categoryId = $this->db->insertID();
        $this->db->table('cms_category_translations')->insert([
            'category_id' => $categoryId,
            'language_id' => $this->langEsId,
            'slug' => 'destacadas',
            'name' => 'Destacadas',
        ]);

        $this->db->table('cms_tags')->insert([
            'is_active' => 1,
        ]);
        $tagId = $this->db->insertID();
        $this->db->table('cms_tag_translations')->insert([
            'tag_id' => $tagId,
            'language_id' => $this->langEsId,
            'slug' => 'destacado',
            'name' => 'Destacado',
        ]);

        $this->db->table('cms_entries')->insert([
            'collection_id' => $collectionId,
            'workflow_status' => 'published',
            'view_count' => 0,
            'sort_order' => 1,
            'is_featured' => 0,
            'is_in_sitemap' => 1,
        ]);
        $entryId = $this->db->insertID();
        $this->db->table('cms_entry_translations')->insert([
            'entry_id' => $entryId,
            'language_id' => $this->langEsId,
            'slug' => 'nota',
            'title' => 'Nota',
        ]);

        $this->db->table('cms_forms')->insert([
            'form_key' => 'contact',
            'is_active' => 1,
            'has_captcha' => 0,
            'notify_email' => null,
            'autoreply_enabled' => 0,
            'autoreply_email_field' => null,
        ]);
        $formId = $this->db->insertID();
        $this->db->table('cms_form_translations')->insert([
            'form_id' => $formId,
            'language_id' => $this->langEsId,
            'name' => 'Contacto',
            'submit_label' => 'Enviar',
        ]);

        $this->db->table('cms_form_fields')->insert([
            'form_id' => $formId,
            'field_key' => 'message',
            'field_type' => 'textarea',
            'display_order' => 1,
            'is_required' => 1,
            'is_active' => 1,
        ]);
        $fieldId = $this->db->insertID();
        $this->db->table('cms_form_field_translations')->insert([
            'form_field_id' => $fieldId,
            'language_id' => $this->langEsId,
            'label' => 'Mensaje',
        ]);

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'cta',
            'name' => 'CTA',
            'description' => null,
            'category' => 'marketing',
            'icon' => null,
            'schema_definition' => json_encode([
                'fields' => [
                    'heading' => ['type' => 'string'],
                    'text' => ['type' => 'text'],
                    'cta_label' => ['type' => 'string'],
                    'cta_url' => ['type' => 'url'],
                    'css_class' => ['type' => 'string'],
                    'is_visible' => ['type' => 'boolean'],
                ],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => 1,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => null,
        ]);
        $blockInstanceId = $this->db->insertID();
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $blockInstanceId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode([
                'heading' => 'Únete',
                'text' => 'Texto del bloque',
                'cta_label' => 'Comprar',
                'cta_url' => '/comprar',
                'css_class' => 'hero-cta',
                'is_visible' => true,
            ]),
            'is_published' => 1,
        ]);

        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $blockInstanceId,
            'language_id' => $this->langEnId,
            'block_data' => json_encode([
                'heading' => 'Join us',
                'text' => 'Block text',
                'cta_label' => '',
                'cta_url' => '/buy',
                'css_class' => 'hero-cta',
                'is_visible' => true,
            ]),
            'is_published' => 1,
        ]);

        $service = Services::translationAuditService(false);
        $report = $service->getMissingTranslationsReport();
        $resources = array_values(array_map(static fn (array $row) => $row['resource'], $report));

        $this->assertContains('page', $resources);
        $this->assertContains('menu', $resources);
        $this->assertContains('collection', $resources);
        $this->assertContains('category', $resources);
        $this->assertContains('tag', $resources);
        $this->assertContains('entry', $resources);
        $this->assertContains('form', $resources);
        $this->assertContains('form_field', $resources);
        $this->assertContains('block_instance', $resources);

        $pageAudit = $service->auditResource('page', $pageId);
        $this->assertSame('complete', $pageAudit['es']['status']);
        $this->assertSame('missing', $pageAudit['en']['status']);

        $menuAudit = $service->auditResource('menu', $menuId);
        $this->assertSame('complete', $menuAudit['es']['status']);
        $this->assertSame('missing', $menuAudit['en']['status']);

        // 'mismatch' collapses to 'incomplete' for block_instance specifically
        // — see TranslationAuditSupport::collapseForBlockBadge().
        $audit = $service->auditResource('block_instance', $blockInstanceId);
        $this->assertSame('complete', $audit['es']['status']);
        $this->assertSame('incomplete', $audit['en']['status']);
    }

    public function testOptionalFieldsMismatchesAreDetectedOnPageTranslations(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
            'excerpt' => 'Resumen en español',
        ]);

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEnId,
            'slug' => 'home',
            'title' => 'Home',
            'excerpt' => '',
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('page', $pageId);

        $this->assertSame('complete', $audit['es']['status']);
        $this->assertSame('mismatch', $audit['en']['status']);
        $this->assertStringContainsString('excerpt', $audit['en']['detail']);
    }

    public function testMissingMenuTranslationRowsAreReported(): void
    {
        $this->db->table('cms_menus')->insert([
            'menu_key' => 'main',
            'location' => 'header',
            'is_active' => 1,
        ]);
        $menuId = $this->db->insertID();

        $this->db->table('cms_menu_translations')->insert([
            'menu_id' => $menuId,
            'language_id' => $this->langEsId,
            'name' => 'Principal',
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('menu', $menuId);

        $this->assertSame('complete', $audit['es']['status']);
        $this->assertSame('missing', $audit['en']['status']);
    }

    public function testOptionalBlockFieldsMismatchCollapsesToIncompleteViaAuditResource(): void
    {
        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'cta',
            'name' => 'CTA',
            'description' => null,
            'category' => 'marketing',
            'icon' => null,
            'schema_definition' => json_encode([
                'fields' => [
                    'heading' => ['type' => 'string', 'required' => true],
                    'text' => ['type' => 'text', 'required' => false],
                    'cta_label' => ['type' => 'string', 'required' => false],
                ],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => 1,
            'parent_instance_id' => null,
            'sort_order' => 1,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => null,
        ]);
        $blockInstanceId = $this->db->insertID();

        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $blockInstanceId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode([
                'heading' => 'Únete',
                'text' => 'Texto',
                'cta_label' => 'Comprar',
            ]),
            'is_published' => 1,
        ]);

        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $blockInstanceId,
            'language_id' => $this->langEnId,
            'block_data' => json_encode([
                'heading' => 'Join us',
                'text' => '',
                'cta_label' => '',
            ]),
            'is_published' => 1,
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('block_instance', $blockInstanceId);

        $this->assertSame('complete', $audit['es']['status']);
        // 'mismatch' collapses to 'incomplete' for block_instance specifically
        // — see TranslationAuditSupport::collapseForBlockBadge().
        $this->assertSame('incomplete', $audit['en']['status']);
    }

    public function testAuditOwnerBlocksReturnsPerLanguageStatusAndCollapsesMismatchToIncomplete(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'hero',
            'name' => 'Hero',
            'category' => 'marketing',
            'schema_definition' => json_encode([
                'fields' => [
                    'heading' => ['type' => 'string', 'required' => true],
                    'subheading' => ['type' => 'string', 'required' => false],
                ],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        // Top-level block: ES complete, EN's optional field is blank while
        // ES has it filled -> mismatch, which must surface as 'incomplete'
        // here (this admin-facing endpoint keeps the same 4-state vocabulary
        // as every other resource's "Ver" panel).
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $parentInstanceId = $this->db->insertID();
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $parentInstanceId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode(['heading' => 'Hola', 'subheading' => 'Sub']),
            'is_published' => 1,
        ]);
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $parentInstanceId,
            'language_id' => $this->langEnId,
            'block_data' => json_encode(['heading' => 'Hello', 'subheading' => '']),
            'is_published' => 1,
        ]);

        // Child block (same owner via parent_instance_id): both languages
        // complete.
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => $parentInstanceId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $childInstanceId = $this->db->insertID();
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $childInstanceId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode(['heading' => 'Hijo']),
            'is_published' => 1,
        ]);
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $childInstanceId,
            'language_id' => $this->langEnId,
            'block_data' => json_encode(['heading' => 'Child']),
            'is_published' => 1,
        ]);

        $service = Services::translationAuditService(false);
        $result = $service->auditOwnerBlocks('page', $pageId);

        $this->assertArrayHasKey($parentInstanceId, $result['blocks']);
        $this->assertArrayHasKey($childInstanceId, $result['blocks']);
        $this->assertSame('complete', $result['blocks'][$parentInstanceId]['es']['status']);
        $this->assertSame('incomplete', $result['blocks'][$parentInstanceId]['en']['status']);
        $this->assertSame('complete', $result['blocks'][$childInstanceId]['es']['status']);
        $this->assertSame('complete', $result['blocks'][$childInstanceId]['en']['status']);

        $this->assertSame(['complete' => 2, 'total' => 2], $result['summary']['es']);
        $this->assertSame(['complete' => 1, 'total' => 2], $result['summary']['en']);
    }

    /**
     * Reproduces the false positive David reported 2026-07-21: a fully
     * translated block whose instance row was touched afterwards (e.g. by
     * reordering, which bumps cms_block_instances.updated_at for every
     * sibling regardless of content) must not show as anything other than
     * 'complete' in the block-scoped badges/tab dots — 'outdated' collapses
     * away here specifically (see collapseForBlockBadge()), unlike pages,
     * entries, etc. where 'outdated' is still a real, surfaced signal.
     */
    public function testOutdatedBlockTranslationCollapsesToCompleteInBothBlockScopedEndpoints(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'image',
            'name' => 'Imagen',
            'category' => 'media',
            'schema_definition' => json_encode([
                'fields' => ['alt_text' => ['type' => 'string', 'required' => true]],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
            'updated_at' => '2026-07-21 03:50:41',
        ]);
        $instanceId = $this->db->insertID();

        // Fully translated, but saved a day before the instance's last
        // touch (e.g. a reorder) — this is what 'outdated' detects.
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $instanceId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode(['alt_text' => 'Plataforma E-commerce Nacional']),
            'is_published' => 1,
            'updated_at' => '2026-07-20 04:26:05',
        ]);
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $instanceId,
            'language_id' => $this->langEnId,
            'block_data' => json_encode(['alt_text' => 'National E-commerce Platform']),
            'is_published' => 1,
            'updated_at' => '2026-07-21 03:50:41',
        ]);

        $service = Services::translationAuditService(false);

        $owner = $service->auditOwnerBlocks('page', $pageId);
        $this->assertSame('complete', $owner['blocks'][$instanceId]['es']['status']);
        $this->assertSame('complete', $owner['blocks'][$instanceId]['en']['status']);
        $this->assertSame(['complete' => 1, 'total' => 1], $owner['summary']['es']);

        $resource = $service->auditResource('block_instance', $instanceId);
        $this->assertSame('complete', $resource['es']['status']);
        $this->assertSame('complete', $resource['en']['status']);
    }

    public function testAuditOwnerBlocksIsolatesByOwnerTypeAndOwnerId(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'rich_text',
            'name' => 'Rich Text',
            'category' => 'content',
            'schema_definition' => json_encode([
                'fields' => ['body' => ['type' => 'text', 'required' => true]],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        // Belongs to our page — must be included.
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $ownBlockId = $this->db->insertID();
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $ownBlockId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode(['body' => 'Texto']),
            'is_published' => 1,
        ]);

        // Same owner_id but owner_type='entry' — a different polymorphic
        // owner entirely, must NOT leak into the page's result.
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'entry',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $otherOwnerTypeBlockId = $this->db->insertID();

        // A different page entirely — must NOT leak either.
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 2,
        ]);
        $otherPageId = $this->db->insertID();
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $otherPageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $otherPageBlockId = $this->db->insertID();

        $service = Services::translationAuditService(false);
        $result = $service->auditOwnerBlocks('page', $pageId);

        $this->assertArrayHasKey($ownBlockId, $result['blocks']);
        $this->assertArrayNotHasKey($otherOwnerTypeBlockId, $result['blocks']);
        $this->assertArrayNotHasKey($otherPageBlockId, $result['blocks']);
    }

    public function testAuditOwnerBlocksReturnsEmptyBlocksWithZeroedSummaryWhenOwnerHasNoBlocks(): void
    {
        $service = Services::translationAuditService(false);
        $result = $service->auditOwnerBlocks('page', 999999);

        $this->assertSame([], $result['blocks']);
        $this->assertSame(['complete' => 0, 'total' => 0], $result['summary']['es']);
        $this->assertSame(['complete' => 0, 'total' => 0], $result['summary']['en']);
    }

    /**
     * Root cause of the 2026-08-02 report: the legacy migration seeded every
     * non-Spanish translation row with the Spanish text as a placeholder,
     * and this audit never checked for that — 98% of migrated entries
     * silently reported "complete". A non-default-language value that is
     * byte-identical to the default language's own value on a genuine
     * content field (title, excerpt, ...) must now report 'untranslated',
     * not 'complete'.
     */
    public function testAuditResourceFlagsTextIdenticalToTheDefaultLanguageAsUntranslated(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'inicio',
            'title' => 'Inicio',
            'excerpt' => 'Resumen en español',
        ]);

        // EN row exists and every required field is non-blank, but the
        // title/excerpt were never actually translated — copy-pasted
        // verbatim from the Spanish source.
        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEnId,
            'slug' => 'home',
            'title' => 'Inicio',
            'excerpt' => 'Resumen en español',
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('page', $pageId);

        $this->assertSame('complete', $audit['es']['status']);
        $this->assertSame('untranslated', $audit['en']['status']);
        $this->assertStringContainsString('title', $audit['en']['detail']);
        $this->assertStringContainsString('excerpt', $audit['en']['detail']);

        $report = $service->getMissingTranslationsReport();
        $pageIssue = array_values(array_filter($report, static fn (array $r): bool => $r['resource'] === 'page'))[0];
        $this->assertSame('untranslated', $pageIssue['status']);
    }

    /**
     * `slug` is deliberately excluded from the identical-to-source check:
     * this site intentionally reuses the same slug across locales for some
     * pages (confirmed live against teatromuseo.cl), so an identical slug
     * must never be flagged even though title/excerpt genuinely differ.
     */
    public function testIdenticalSlugAcrossLanguagesIsNotFlaggedAsUntranslated(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEsId,
            'slug' => 'home',
            'title' => 'Inicio',
        ]);

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $this->langEnId,
            'slug' => 'home',
            'title' => 'Home',
        ]);

        $service = Services::translationAuditService(false);
        $audit = $service->auditResource('page', $pageId);

        $this->assertSame('complete', $audit['en']['status']);
    }

    /**
     * Block content (rich text, hero sliders, CTAs, ...) is the largest
     * share of the untranslated-legacy-content backlog. Verified through
     * both entry points that consume evaluateTranslationState() for block
     * instances: the sitewide report shows 'untranslated' verbatim, while
     * the block-scoped endpoints collapse it to 'incomplete' — same
     * treatment as 'mismatch', per collapseForBlockBadge().
     */
    public function testBlockInstanceTextIdenticalToDefaultLanguageIsFlaggedAsUntranslated(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'rich_text',
            'name' => 'Rich Text',
            'category' => 'content',
            'schema_definition' => json_encode([
                'fields' => ['body' => ['type' => 'richtext', 'required' => true]],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $instanceId = $this->db->insertID();

        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $instanceId,
            'language_id' => $this->langEsId,
            'block_data' => json_encode(['body' => 'Texto en español']),
            'is_published' => 1,
        ]);
        $this->db->table('cms_block_instance_translations')->insert([
            'instance_id' => $instanceId,
            'language_id' => $this->langEnId,
            'block_data' => json_encode(['body' => 'Texto en español']),
            'is_published' => 1,
        ]);

        $service = Services::translationAuditService(false);

        $report = $service->getMissingTranslationsReport();
        $blockIssue = array_values(array_filter($report, static fn (array $r): bool => $r['resource'] === 'block_instance'))[0];
        $this->assertSame('untranslated', $blockIssue['status']);

        $resourceAudit = $service->auditResource('block_instance', $instanceId);
        $this->assertSame('incomplete', $resourceAudit['en']['status']);

        $ownerAudit = $service->auditOwnerBlocks('page', $pageId);
        $this->assertSame('incomplete', $ownerAudit['blocks'][$instanceId]['en']['status']);
    }

    public function testAFieldThatMatchesTheSourceDoesNotMakeAPartiallyTranslatedBlockPending(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'test_partial_translation',
            'name' => 'Test partial translation',
            'category' => 'content',
            'schema_definition' => json_encode(['fields' => [
                'venue' => ['type' => 'string', 'required' => false],
                'description' => ['type' => 'richtext', 'required' => false],
            ]]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $instanceId = $this->db->insertID();

        foreach ([
            $this->langEsId => ['venue' => 'Teatro Museo', 'description' => 'Descripción en español'],
            $this->langEnId => ['venue' => 'Teatro Museo', 'description' => 'Description in English'],
        ] as $languageId => $data) {
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $instanceId,
                'language_id' => $languageId,
                'block_data' => json_encode($data),
                'is_published' => 1,
            ]);
        }

        $audit = Services::translationAuditService(false)->auditResource('block_instance', $instanceId);

        $this->assertSame('complete', $audit['en']['status']);
    }

    public function testIdenticalBlockUrlAcrossLanguagesIsNotFlaggedAsUntranslated(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();

        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'collection_grid',
            'name' => 'Collection Grid',
            'category' => 'content',
            'schema_definition' => json_encode([
                'fields' => [
                    'section_title' => ['type' => 'string', 'required' => false],
                ],
            ]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();

        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $instanceId = $this->db->insertID();

        foreach ([
            $this->langEsId => ['section_title' => 'Cartelera'],
            $this->langEnId => ['section_title' => "What's on"],
        ] as $languageId => $data) {
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $instanceId,
                'language_id' => $languageId,
                'block_data' => json_encode($data),
                'is_published' => 1,
            ]);
        }

        $audit = Services::translationAuditService(false)->auditResource('block_instance', $instanceId);

        $this->assertSame('complete', $audit['en']['status']);
    }

    public function testSharedBlockIdentifiersAndOperationalStringsAreNotAuditedAsTranslations(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();
        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'test_operational_values',
            'name' => 'Test operational values',
            'category' => 'content',
            'schema_definition' => json_encode(['fields' => [
                'video_id' => ['type' => 'string', 'required' => false],
                'duration' => ['type' => 'string', 'required' => false],
                'title' => ['type' => 'string', 'required' => false],
            ]]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $instanceId = $this->db->insertID();

        foreach ([
            $this->langEsId => ['video_id' => 'abc123', 'duration' => '90 min', 'title' => 'Título'],
            $this->langEnId => ['video_id' => 'abc123', 'duration' => '90 min', 'title' => 'Título'],
        ] as $languageId => $data) {
            $this->db->table('cms_block_instance_translations')->insert([
                'instance_id' => $instanceId,
                'language_id' => $languageId,
                'block_data' => json_encode($data),
                'is_published' => 1,
            ]);
        }

        $audit = Services::translationAuditService(false)->auditResource('block_instance', $instanceId);

        $this->assertSame('complete', $audit['en']['status']);
    }

    public function testEmptyOptionalBlockContainerDoesNotRequireTranslationRows(): void
    {
        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = $this->db->insertID();
        $this->db->table('cms_content_blocks')->insert([
            'block_key' => 'test_optional_container',
            'name' => 'Test optional container',
            'category' => 'layout',
            'schema_definition' => json_encode(['fields' => [
                'title' => ['type' => 'string', 'required' => false],
                'description' => ['type' => 'textarea', 'required' => false],
            ]]),
            'supports_pages' => 1,
            'supports_entries' => 1,
            'is_container' => 1,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $blockTypeId = $this->db->insertID();
        $this->db->table('cms_block_instances')->insert([
            'block_id' => $blockTypeId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $instanceId = $this->db->insertID();

        $audit = Services::translationAuditService(false)->auditResource('block_instance', $instanceId);

        $this->assertSame('complete', $audit['es']['status']);
        $this->assertSame('complete', $audit['en']['status']);
        $this->assertSame([], array_values(array_filter(
            Services::translationAuditService(false)->getMissingTranslationsReport(),
            static fn (array $row): bool => (int) ($row['resource_id'] ?? 0) === $instanceId
        )));
    }

    public function testOverallCompletenessIncludesExpandedCmsResources(): void
    {
        $this->db->table('cms_collections')->insert([
            'collection_key' => 'news',
            'is_active' => 1,
            'requires_approval' => 0,
            'enables_categories' => 1,
            'enables_tags' => 1,
            'default_sitemap_priority' => 0.5,
            'default_changefreq' => 'weekly',
            'sort_order' => 1,
        ]);
        $collectionId = $this->db->insertID();
        $this->db->table('cms_collection_translations')->insert([
            'collection_id' => $collectionId,
            'language_id' => $this->langEsId,
            'slug' => 'noticias',
            'name' => 'Noticias',
        ]);

        $service = Services::translationAuditService(false);
        $stats = $service->getOverallCompleteness();

        $enStat = array_values(array_filter($stats, fn ($s) => $s['code'] === 'en'))[0];
        $esStat = array_values(array_filter($stats, fn ($s) => $s['code'] === 'es'))[0];

        $this->assertGreaterThanOrEqual(1, $enStat['total_elements']);
        $this->assertLessThan($enStat['total_elements'], $enStat['completed_elements']);
        $this->assertGreaterThanOrEqual(1, $esStat['total_elements']);
    }
}
