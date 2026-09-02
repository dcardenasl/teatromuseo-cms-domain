<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories\Admin;

use App\Models\CategoryModel;
use App\Models\CollectionModel;
use App\Models\EntryModel;
use App\Models\EntryTranslationModel;
use App\Models\FormModel;
use App\Models\FormSubmissionModel;
use App\Models\MenuModel;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Models\TagModel;
use App\Repositories\Admin\DashboardSummaryRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class DashboardSummaryRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testReadUsesTheCurrentTranslationProjection(): void
    {
        $this->db->table('cms_languages')->insert([
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'is_default' => 1,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
        $languageId = (int) $this->db->insertID();

        $this->db->table('cms_pages')->insert([
            'page_type' => 'generic',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $pageId = (int) $this->db->insertID();

        $this->db->table('cms_page_translations')->insert([
            'page_id' => $pageId,
            'language_id' => $languageId,
            'slug' => 'dashboard-schema',
            'title' => 'Dashboard schema',
        ]);

        $repository = new DashboardSummaryRepository(
            new PageModel(),
            new EntryModel(),
            new CollectionModel(),
            new MenuModel(),
            new CategoryModel(),
            new TagModel(),
            new FormModel(),
            new FormSubmissionModel(),
            new PageTranslationModel(),
            new EntryTranslationModel(),
        );

        $result = $repository->read(['cms.pages.read']);

        $this->assertSame(1, $result['counts']['pages']);
        $this->assertSame('Dashboard schema', $result['recent_activity'][0]['translations'][0]['title']);
        $this->assertSame('dashboard-schema', $result['recent_activity'][0]['translations'][0]['slug']);
    }
}
