<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Verifies the bootstrap seeds the TeatroMuseo content set.
 *
 * @internal
 */
final class SiteBootstrapContentTest extends CIUnitTestCase
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
            'cms_entry_categories',
            'cms_entry_tags',
            'cms_entry_translations',
            'cms_entries',
            'cms_category_translations',
            'cms_categories',
            'cms_tag_translations',
            'cms_tags',
            'cms_menu_item_translations',
            'cms_menu_items',
            'cms_menus',
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

    public function testBootstrapSeedsCoreDemoPagesMenusAndBlocks(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $this->assertSame(
            ['es', 'en', 'fr', 'pt'],
            array_column(
                $this->db->table('cms_languages')
                    ->select('code')
                    ->where('is_active', 1)
                    ->orderBy('sort_order', 'ASC')
                    ->get()
                    ->getResultArray(),
                'code'
            )
        );

        $this->assertSame(31, $this->db->table('cms_pages')->countAllResults());
        $this->assertSame(10, $this->db->table('cms_collections')->countAllResults());
        $this->assertSame(20, $this->db->table('cms_entries')->countAllResults());

        $menu = $this->db->table('cms_menus')->whereIn('menu_key', ['main', 'footer', 'legal'])->get()->getResultArray();
        $this->assertCount(3, $menu);

        $newsCollection = $this->db->table('cms_collections')
            ->where('collection_key', 'noticias')
            ->get()
            ->getRowArray();
        $this->assertNotNull($newsCollection);

        $homePage = $this->db->table('cms_pages')
            ->where('page_type', 'home')
            ->get()
            ->getRowArray();
        $homeBlocks = $this->db->table('cms_block_instances')
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $homePage['id'])
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
        $this->assertGreaterThanOrEqual(3, count($homeBlocks));

        $heroBlock = $this->db->table('cms_content_blocks')
            ->where('block_key', 'hero_slider')
            ->get()
            ->getRowArray();
        $this->assertNotNull($heroBlock);

        $heroInstance = $this->db->table('cms_block_instances')
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $homePage['id'])
            ->where('block_id', (int) $heroBlock['id'])
            ->get()
            ->getRowArray();
        $this->assertNotNull($heroInstance);

        $contactBlock = $this->db->table('cms_content_blocks')
            ->where('block_key', 'form_embed')
            ->get()
            ->getRowArray();
        $this->assertNotNull($contactBlock);

        $contactPage = $this->db->table('cms_pages')
            ->where('page_type', 'contact')
            ->get()
            ->getRowArray();
        $this->assertNotNull($contactPage);

        $contactInstance = $this->db->table('cms_block_instances')
            ->where('owner_type', 'page')
            ->where('owner_id', (int) $contactPage['id'])
            ->where('block_id', (int) $contactBlock['id'])
            ->get()
            ->getRowArray();
        $this->assertNotNull($contactInstance);

        $config = json_decode((string) $contactInstance['block_config'], true);
        $this->assertSame('contact', $config['form_key'] ?? null);

        $carteleraPage = $this->pageBySlug(['cartelera']);
        $this->assertNotNull($carteleraPage);
        $this->assertSame(['page_header', 'collection_listing'], $this->pageBlockKeys((int) $carteleraPage['id']));

        $museumListingPage = $this->pageBySlug(['museo/coleccion']);
        $this->assertNotNull($museumListingPage);
        $this->assertSame(['page_header', 'collection_listing'], $this->pageBlockKeys((int) $museumListingPage['id']));

        $mainMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'main')
            ->get()
            ->getRowArray();
        $this->assertNotNull($mainMenu);
        $this->assertSame(
            ['Inicio', 'Nosotros', 'Programación', 'Museo', 'Educación', 'Prensa y Medios', 'Contacto'],
            $this->menuLabels((int) $mainMenu['id'], 'es', null)
        );

        $footerMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'footer')
            ->get()
            ->getRowArray();
        $this->assertNotNull($footerMenu);
        $this->assertSame(
            ['Explora', 'Institución', 'Prensa y Medios'],
            $this->menuLabels((int) $footerMenu['id'], 'es', null)
        );

        $legalMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'legal')
            ->get()
            ->getRowArray();
        $this->assertNotNull($legalMenu);
        $this->assertCount(7, $this->menuLabels((int) $legalMenu['id'], 'es', null));

        $aboutPage = $this->pageBySlug(['nosotros', 'about', 'a-propos', 'sobre-nos']);
        $this->assertNotNull($aboutPage);
        $this->assertSame(['page_header', 'hero_banner', 'rich_text', 'cards_grid', 'cards_slider', 'asset_showcase', 'accordion', 'cta'], $this->pageBlockKeys((int) $aboutPage['id']));

        $historyPage = $this->pageBySlug(['historia', 'history', 'histoire', 'nossa-historia']);
        $this->assertNotNull($historyPage);
        $this->assertSame(['page_header', 'rich_text', 'image', 'timeline', 'metrics_grid', 'cta'], $this->pageBlockKeys((int) $historyPage['id']));

        $catalogTemplatePage = $this->pageBySlug(['__template_catalog_item']);
        $this->assertNotNull($catalogTemplatePage);
        $this->assertSame(['catalog_item_header', 'catalog_item_details', 'catalog_item_content', 'catalog_item_gallery'], $this->pageBlockKeys((int) $catalogTemplatePage['id']));

        $eventTemplatePage = $this->pageBySlug(['__template_event_item']);
        $this->assertNotNull($eventTemplatePage);
        $this->assertSame(['event_item_header', 'event_item_details', 'event_item_content', 'event_item_gallery'], $this->pageBlockKeys((int) $eventTemplatePage['id']));
    }

    public function testEditorialCollectionsExposeOptionalMediaBlocksWithoutAutoCreatingThem(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $collection = $this->db->table('cms_collections')
            ->where('collection_key', 'obras')
            ->get()
            ->getRowArray();
        $this->assertNotNull($collection);

        $template = json_decode((string) $collection['block_template'], true);
        $this->assertIsArray($template);

        $blocks = [];
        foreach (($template['blocks'] ?? []) as $block) {
            if (is_array($block) && isset($block['block_key'])) {
                $blocks[(string) $block['block_key']] = $block;
            }
        }

        $this->assertArrayHasKey('obra_ficha', $blocks);
        $this->assertArrayHasKey('gallery', $blocks);
        $this->assertArrayHasKey('video_gallery', $blocks);
        $this->assertFalse($blocks['gallery']['auto_create'] ?? true);
        $this->assertFalse($blocks['video_gallery']['auto_create'] ?? true);

        foreach (['gallery', 'gallery_item', 'video_gallery'] as $blockKey) {
            $block = $this->db->table('cms_content_blocks')
                ->where('block_key', $blockKey)
                ->get()
                ->getRowArray();
            $this->assertNotNull($block, "Missing {$blockKey} block type.");
            $this->assertSame(1, (int) $block['supports_entries'], "{$blockKey} must support entry content.");
        }
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

    /**
     * @return list<string>
     */
    private function menuLabels(int $menuId, string $langCode, ?int $parentId): array
    {
        $builder = $this->db->table('cms_menu_items mi')
            ->select('mt.label')
            ->join('cms_menu_item_translations mt', 'mt.menu_item_id = mi.id')
            ->join('cms_languages l', 'l.id = mt.language_id')
            ->where('mi.menu_id', $menuId)
            ->where('mi.is_active', 1)
            ->where('l.code', $langCode)
            ->orderBy('mi.sort_order', 'ASC')
            ->orderBy('mi.id', 'ASC');

        if ($parentId === null) {
            $builder->where('mi.parent_id IS NULL', null, false);
        } else {
            $builder->where('mi.parent_id', $parentId);
        }

        $rows = $builder->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) ($row['label'] ?? ''), $rows);
    }
}
