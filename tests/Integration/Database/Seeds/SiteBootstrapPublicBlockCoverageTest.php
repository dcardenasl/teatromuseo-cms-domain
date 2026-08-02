<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class SiteBootstrapPublicBlockCoverageTest extends CIUnitTestCase
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
            'cms_languages',
        ];

        foreach ($tables as $table) {
            $this->db->query("DELETE FROM `{$table}`");
        }
        $this->db->enableForeignKeyChecks();
    }

    public function testBootstrapCoversAllPublicTopLevelBlocks(): void
    {
        // 'gallery', 'video_player', 'tabs', 'alert', and 'container' intentionally have no
        // entry in $expected below (removed 2026-08-02 along with the demo pages — Portfolio/
        // Components/Media/Landing — that were their only page-level user, per a fresh
        // SiteBootstrapSeeder run with those seeders gone). 'gallery' is still meaningfully
        // used on this site, just at entry scope (real obras/festivales galleries via the
        // legacy ETL), never on a top-level page, which is what this test checks. The other
        // four have zero usage anywhere now — this site's real content never needed them
        // (e.g. video content uses the project-specific `video_ficha` fields instead of the
        // generic `video_player` block).
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $rows = $this->db->table('cms_block_instances')
            ->select('cms_content_blocks.block_key')
            ->join('cms_content_blocks', 'cms_content_blocks.id = cms_block_instances.block_id')
            ->where('cms_block_instances.owner_type', 'page')
            ->get()
            ->getResultArray();

        $covered = [];
        foreach ($rows as $row) {
            $covered[(string) $row['block_key']] = true;
        }

        $expected = [
            'hero_slider',
            'collection_grid',
            'cta',
            'page_header',
            'form_embed',
            'contact_info',
            'map_embed',
            'social_links',
            'hero_banner',
            'rich_text',
            'image',
            'catalog_item_header',
            'catalog_item_gallery',
            'event_item_header',
            'cards_grid',
            'metrics_grid',
            'cards_slider',
            'asset_showcase',
            'accordion',
            'collection_listing',
        ];

        foreach ($expected as $blockKey) {
            $this->assertArrayHasKey($blockKey, $covered, sprintf('Missing public coverage for block "%s".', $blockKey));
        }
    }
}
