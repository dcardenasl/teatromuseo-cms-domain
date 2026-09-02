<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Seeds;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class CmsCollectionGridAspectRatioSeederTest extends CIUnitTestCase
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
            'cms_languages',
        ];
        foreach ($tables as $table) {
            $this->db->query("DELETE FROM `{$table}`");
        }
        $this->db->enableForeignKeyChecks();
    }

    public function testSeederPreservesEditoriallyChosenCollectionGridRatios(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call(\App\Database\Seeds\SiteBootstrapSeeder::class);

        $homePage = $this->db->table('cms_pages')
            ->where('page_type', 'home')
            ->get()
            ->getRowArray();
        $this->assertNotNull($homePage);

        $collectionGridType = $this->db->table('cms_content_blocks')
            ->where('block_key', 'collection_grid')
            ->get()
            ->getRowArray();
        $this->assertNotNull($collectionGridType);

        $this->db->table('cms_block_instances')->insert([
            'block_id' => (int) $collectionGridType['id'],
            'owner_type' => 'page',
            'owner_id' => (int) $homePage['id'],
            'parent_instance_id' => null,
            'sort_order' => 99,
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode([
                'collection_key' => 'cursos',
                'image_aspect_ratio' => '4/3',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $instanceId = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $instanceId);

        $seeder->call(\App\Database\Seeds\CmsCollectionGridAspectRatioSeeder::class);

        $row = $this->db->table('cms_block_instances')
            ->where('id', (int) $instanceId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($row);

        $config = json_decode((string) $row['block_config'], true);
        $this->assertIsArray($config);
        $this->assertSame('4/3', $config['image_aspect_ratio'] ?? null);
    }
}
