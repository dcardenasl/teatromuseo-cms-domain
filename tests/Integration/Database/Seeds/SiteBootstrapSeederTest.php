<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Verifies the bootstrap seeds the TeatroMuseo CMS contract.
 *
 * @internal
 */
final class SiteBootstrapSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->disableForeignKeyChecks();
        $tables = [
            'cms_file_references',
            'cms_block_instance_translations',
            'cms_block_instances',
            'cms_content_blocks',
            'cms_form_field_translations',
            'cms_form_fields',
            'cms_form_translations',
            'cms_forms',
            'cms_menu_translations',
            'cms_menu_item_translations',
            'cms_menu_items',
            'cms_menus',
            'cms_entry_categories',
            'cms_entry_tags',
            'cms_entry_translations',
            'cms_entries',
            'cms_category_translations',
            'cms_categories',
            'cms_tag_translations',
            'cms_tags',
            'cms_page_translations',
            'cms_pages',
            'cms_collection_translations',
            'cms_collections',
            'cms_setting_translations',
            'cms_settings',
            'cms_languages'
        ];
        foreach ($tables as $table) {
            $this->db->query("DELETE FROM `{$table}`");
        }
        $this->db->enableForeignKeyChecks();
    }

    public function testBootstrapSeedsContactFormAndTheTeatroMuseoCms(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $languageCodes = array_column(
            $this->db->table('cms_languages')
                ->select('code')
                ->where('is_active', 1)
                ->orderBy('sort_order', 'ASC')
                ->get()
                ->getResultArray(),
            'code'
        );
        $this->assertSame(['es', 'en', 'fr', 'pt'], $languageCodes);

        $this->assertSame(27, $this->db->table('cms_pages')->countAllResults());
        $this->assertSame(9, $this->db->table('cms_collections')->countAllResults());
        $this->assertSame(0, $this->db->table('cms_entries')->countAllResults());
        $this->assertCount(3, $this->db->table('cms_menus')->whereIn('menu_key', ['main', 'footer', 'legal'])->get()->getResultArray());

        $form = $this->db->table('cms_forms')
            ->where('form_key', 'contact')
            ->get()
            ->getRowArray();

        $this->assertNotNull($form);
        $this->assertSame('contact', $form['form_key']);
        $this->assertSame('1', (string) $form['is_active']);
        $this->assertSame('email', $form['autoreply_email_field']);

        $formId = (int) $form['id'];
        $fields = $this->db->table('cms_form_fields')
            ->where('form_id', $formId)
            ->orderBy('display_order', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(3, $fields);
        $this->assertSame('name', $fields[0]['field_key']);
        $this->assertSame('email', $fields[1]['field_key']);
        $this->assertSame('message', $fields[2]['field_key']);

        $contactPage = $this->db->table('cms_pages')
            ->where('page_type', 'contact')
            ->get()
            ->getRowArray();
        $this->assertNotNull($contactPage);

        $contactBlockType = $this->db->table('cms_content_blocks')
            ->where('block_key', 'form_embed')
            ->get()
            ->getRowArray();
        $this->assertNotNull($contactBlockType);

        $collectionGridType = $this->db->table('cms_content_blocks')
            ->where('block_key', 'collection_grid')
            ->get()
            ->getRowArray();
        $this->assertNotNull($collectionGridType);

        $contactBlock = $this->db->table('cms_block_instances')
            ->where('block_id', (int) $contactBlockType['id'])
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $contactPage['id'])
            ->get()
            ->getRowArray();

        $this->assertNotNull($contactBlock);
        $config = json_decode((string) $contactBlock['block_config'], true);
        $this->assertIsArray($config);
        $this->assertSame('contact', $config['form_key'] ?? null);
        $this->assertArrayNotHasKey('show_info_boxes', $config);

        $homePage = $this->pageBySlug(['home']);
        $this->assertNotNull($homePage);

        $homeCollectionGrid = $this->db->table('cms_block_instances')
            ->where('block_id', (int) $collectionGridType['id'])
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $homePage['id'])
            ->get()
            ->getRowArray();

        $this->assertNotNull($homeCollectionGrid);
        $homeConfig = json_decode((string) $homeCollectionGrid['block_config'], true);
        $this->assertIsArray($homeConfig);
        $this->assertSame('1/1', $homeConfig['image_aspect_ratio'] ?? null);

        $contactTranslation = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', (int) $contactBlock['id'])
            ->get()
            ->getResultArray();

        $this->assertNotEmpty($contactTranslation);

        $newsCollection = $this->db->table('cms_collections')
            ->where('collection_key', 'noticias')
            ->get()
            ->getRowArray();
        $this->assertNotNull($newsCollection);
        $this->assertSame('news', $newsCollection['collection_type']);
        $this->assertNotEmpty($newsCollection['block_template']);
        $this->assertNotEmpty($newsCollection['wizard_config']);

        $aboutPage = $this->pageBySlug(['nosotros', 'about', 'a-propos', 'sobre-nos']);
        $this->assertNotNull($aboutPage);
        $this->assertSame(['nosotros', 'about', 'a-propos', 'sobre-nos'], $this->pageTranslationSlugs((int) $aboutPage['id']));
        $this->assertSame(['page_header', 'hero_banner', 'rich_text', 'cards_grid', 'cards_slider', 'asset_showcase', 'accordion', 'cta'], $this->pageBlockKeys((int) $aboutPage['id']));

        $historyPage = $this->pageBySlug(['historia', 'history', 'histoire', 'nossa-historia']);
        $this->assertNotNull($historyPage);
        $this->assertSame(['historia', 'history', 'histoire', 'nossa-historia'], $this->pageTranslationSlugs((int) $historyPage['id']));
        $this->assertSame(['page_header', 'rich_text', 'image', 'timeline', 'metrics_grid', 'cta'], $this->pageBlockKeys((int) $historyPage['id']));

        $catalogTemplatePage = $this->pageBySlug(['__template_catalog_item']);
        $this->assertNotNull($catalogTemplatePage);
        $this->assertSame(
            ['__template_catalog_item', '__template_catalog_item', '__template_catalog_item', '__template_catalog_item'],
            $this->pageTranslationSlugs((int) $catalogTemplatePage['id'])
        );
        $this->assertSame(['catalog_item_header', 'catalog_item_details', 'catalog_item_content', 'catalog_item_gallery'], $this->pageBlockKeys((int) $catalogTemplatePage['id']));

        $eventTemplatePage = $this->pageBySlug(['__template_event_item']);
        $this->assertNotNull($eventTemplatePage);
        $this->assertSame(
            ['__template_event_item', '__template_event_item', '__template_event_item', '__template_event_item'],
            $this->pageTranslationSlugs((int) $eventTemplatePage['id'])
        );
        $this->assertSame(['event_item_header', 'event_item_details', 'event_item_content', 'event_item_gallery'], $this->pageBlockKeys((int) $eventTemplatePage['id']));

        $carteleraPage = $this->pageBySlug(['cartelera']);
        $this->assertNotNull($carteleraPage);
        $this->assertSame(['page_header', 'collection_listing'], $this->pageBlockKeys((int) $carteleraPage['id']));

        $museumListingPage = $this->pageBySlug(['museo/coleccion']);
        $this->assertNotNull($museumListingPage);
        $this->assertSame(['page_header', 'collection_listing'], $this->pageBlockKeys((int) $museumListingPage['id']));

        foreach ([
            ['aviso-legal', 'legal-notice', 'mentions-legales', 'aviso-juridico'],
            ['politica-privacidad', 'privacy-policy', 'politique-confidentialite', 'politica-privacidade'],
            ['politica-cookies', 'cookie-policy', 'politique-cookies', 'politica-cookies'],
            ['derechos-datos', 'data-rights', 'droits-donnees', 'direitos-dados'],
            ['terminos-servicio', 'terms-of-service', 'conditions-utilisation', 'termos-uso'],
            ['transparencia', 'transparency', 'transparence', 'transparencia'],
            ['accesibilidad', 'accessibility', 'accessibilite', 'acessibilidade'],
        ] as $slugs) {
            $this->assertNotNull($this->pageBySlug($slugs));
        }
    }

    public function testBootstrapSeederIsIdempotent(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $countsBefore = [
            'pages' => $this->db->table('cms_pages')->countAllResults(),
            'collections' => $this->db->table('cms_collections')->countAllResults(),
            'entries' => $this->db->table('cms_entries')->countAllResults(),
            'menus' => $this->db->table('cms_menus')->countAllResults(),
            'blocks' => $this->db->table('cms_block_instances')->countAllResults(),
            'file_references' => $this->db->table('cms_file_references')->countAllResults(),
        ];

        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $countsAfter = [
            'pages' => $this->db->table('cms_pages')->countAllResults(),
            'collections' => $this->db->table('cms_collections')->countAllResults(),
            'entries' => $this->db->table('cms_entries')->countAllResults(),
            'menus' => $this->db->table('cms_menus')->countAllResults(),
            'blocks' => $this->db->table('cms_block_instances')->countAllResults(),
            'file_references' => $this->db->table('cms_file_references')->countAllResults(),
        ];

        $this->assertSame($countsBefore, $countsAfter);
    }

    /**
     * @param list<string> $slugs
     */
    private function pageBySlug(array $slugs): ?array
    {
        $row = $this->db->table('cms_pages')
            ->select('cms_pages.id')
            ->join('cms_page_translations', 'cms_page_translations.page_id = cms_pages.id')
            ->whereIn('cms_page_translations.slug', $slugs)
            ->where('cms_pages.deleted_at IS NULL', null, false)
            ->orderBy('cms_pages.id', 'ASC')
            ->get()
            ->getRowArray();

        return $row;
    }

    /**
     * @return list<string>
     */
    private function pageTranslationSlugs(int $pageId): array
    {
        $rows = $this->db->table('cms_page_translations pt')
            ->select('pt.slug')
            ->join('cms_languages l', 'l.id = pt.language_id')
            ->where('pt.page_id', $pageId)
            ->orderBy('l.sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): string => (string) ($row['slug'] ?? ''), $rows);
    }

    /**
     * @return list<string>
     */
    private function pageBlockKeys(int $pageId): array
    {
        $rows = $this->db->table('cms_block_instances bi')
            ->select('cb.block_key')
            ->join('cms_content_blocks cb', 'cb.id = bi.block_id')
            ->where('bi.owner_type', 'page')
            ->where('bi.owner_id', $pageId)
            ->where('bi.parent_instance_id IS NULL', null, false)
            ->orderBy('bi.sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): string => (string) ($row['block_key'] ?? ''), $rows);
    }

    private function blockKeyForInstance(int $blockId): string
    {
        $row = $this->db->table('cms_content_blocks')
            ->where('id', $blockId)
            ->get()
            ->getRowArray();

        return (string) ($row['block_key'] ?? '');
    }
}
