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

        $this->assertSame(27, $this->db->table('cms_pages')->countAllResults());
        $this->assertSame(9, $this->db->table('cms_collections')->countAllResults());
        $this->assertSame(0, $this->db->table('cms_entries')->countAllResults());

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
        $this->assertNotNull($homePage);

        $homeBlocks = $this->db->table('cms_block_instances')
            ->select('cms_content_blocks.block_key, cms_block_instances.block_config')
            ->join('cms_content_blocks', 'cms_content_blocks.id = cms_block_instances.block_id')
            ->where('cms_block_instances.owner_type', 'page')
            ->where('cms_block_instances.owner_id', (int) $homePage['id'])
            ->where('cms_block_instances.parent_instance_id IS NULL', null, false)
            ->orderBy('cms_block_instances.sort_order', 'ASC')
            ->get()->getResultArray();
        $this->assertSame(['hero_slider', 'collection_grid', 'collection_grid', 'collection_grid', 'cta'], array_column($homeBlocks, 'block_key'));
        $this->assertSame('cartelera', json_decode((string) $homeBlocks[1]['block_config'], true)['collection_key']);
        $this->assertSame('cursos', json_decode((string) $homeBlocks[2]['block_config'], true)['collection_key']);
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

        $this->assertSame(['en' => 'events', 'es' => 'cartelera', 'fr' => 'programme', 'pt' => 'eventos'], $this->localizedSlugsForPageType('events'));
        $this->assertSame(['en' => 'museum/collection', 'es' => 'museo/coleccion', 'fr' => 'musee/collection', 'pt' => 'museu/colecao'], $this->localizedSlugsForPageType('catalog_listing'));

        $courseCollection = $this->db->table('cms_collections')
            ->where('collection_key', 'cursos')
            ->get()
            ->getRowArray();
        $this->assertNotNull($courseCollection);
        $this->assertSame(['en' => 'theaterschool', 'es' => 'teatroescuela', 'fr' => 'theatreecole', 'pt' => 'escola-de-teatro'], $this->localizedSlugsForCollection((int) $courseCollection['id']));
        $courseTranslations = $this->db->table('cms_collection_translations')
            ->select('name, listing_title, default_meta_title')
            ->where('collection_id', (int) $courseCollection['id'])
            ->get()->getResultArray();
        $this->assertNotEmpty($courseTranslations);
        foreach ($courseTranslations as $translation) {
            $this->assertSame('TeatroEscuela', $translation['name']);
            $this->assertSame('TeatroEscuela', $translation['listing_title']);
            $this->assertSame('TeatroEscuela | TeatroMuseo', $translation['default_meta_title']);
        }

        $coursePage = $this->db->table('cms_pages')
            ->where('page_type', 'collection_index')
            ->where('collection_id', (int) $courseCollection['id'])
            ->get()->getRowArray();
        $this->assertNotNull($coursePage);
        $coursePageTranslations = $this->db->table('cms_page_translations')
            ->select('title, meta_title')
            ->where('page_id', (int) $coursePage['id'])
            ->get()->getResultArray();
        $this->assertNotEmpty($coursePageTranslations);
        foreach ($coursePageTranslations as $translation) {
            $this->assertSame('TeatroEscuela', $translation['title']);
            $this->assertSame('TeatroEscuela | TeatroMuseo', $translation['meta_title']);
        }

        $mainMenu = $this->db->table('cms_menus')
            ->where('menu_key', 'main')
            ->get()
            ->getRowArray();
        $this->assertNotNull($mainMenu);
        $this->assertSame(
            ['Inicio', 'Nosotros', 'Programación', 'Museo', 'TeatroEscuela', 'Prensa y Medios', 'Contacto'],
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
        $this->assertSame(['page_header', 'hero_slider', 'rich_text', 'cards_grid', 'team_grid', 'cta'], $this->pageBlockKeys((int) $aboutPage['id']));

        $historyPage = $this->pageBySlug(['historia', 'history', 'histoire', 'nossa-historia']);
        $this->assertNotNull($historyPage);
        $this->assertSame(['page_header', 'rich_text'], $this->pageBlockKeys((int) $historyPage['id']));

        $historyText = $this->db->table('cms_block_instance_translations bit')
            ->select('bit.block_data')
            ->join('cms_block_instances bi', 'bi.id = bit.instance_id')
            ->join('cms_content_blocks cb', 'cb.id = bi.block_id')
            ->join('cms_languages l', 'l.id = bit.language_id')
            ->where('bi.owner_type', 'page')
            ->where('bi.owner_id', (int) $historyPage['id'])
            ->where('cb.block_key', 'rich_text')
            ->where('l.code', 'es')
            ->get()
            ->getRowArray();
        $historyData = json_decode((string) ($historyText['block_data'] ?? ''), true);
        $this->assertStringContainsString('25 de julio de 2007', (string) ($historyData['content'] ?? ''));
        $this->assertStringContainsString('50.000 personas', (string) ($historyData['content'] ?? ''));

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

    /** @return array<string, string> */
    private function localizedSlugsForPageType(string $pageType): array
    {
        $rows = $this->db->table('cms_page_translations pt')
            ->select('l.code, pt.slug')
            ->join('cms_pages p', 'p.id = pt.page_id')
            ->join('cms_languages l', 'l.id = pt.language_id')
            ->where('p.page_type', $pageType)
            ->get()
            ->getResultArray();

        $slugs = [];
        foreach ($rows as $row) {
            $slugs[(string) $row['code']] = (string) $row['slug'];
        }
        ksort($slugs);

        return $slugs;
    }

    /** @return array<string, string> */
    private function localizedSlugsForCollection(int $collectionId): array
    {
        $rows = $this->db->table('cms_collection_translations ct')
            ->select('l.code, ct.slug')
            ->join('cms_languages l', 'l.id = ct.language_id')
            ->where('ct.collection_id', $collectionId)
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
