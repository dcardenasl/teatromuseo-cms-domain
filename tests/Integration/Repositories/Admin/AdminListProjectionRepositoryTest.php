<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories\Admin;

use App\Models\CategoryModel;
use App\Models\CollectionModel;
use App\Models\MenuModel;
use App\Models\PageModel;
use App\Models\SettingModel;
use App\Models\TagModel;
use App\Repositories\Cms\CategoryListRepository;
use App\Repositories\Cms\CollectionListRepository;
use App\Repositories\Cms\MenuListRepository;
use App\Repositories\Cms\PageListRepository;
use App\Repositories\Cms\SettingListRepository;
use App\Repositories\Cms\TagListRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class AdminListProjectionRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $migrateOnce = true;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testCmsAdminListProjectionsExecuteAsSinglePaginatedReads(): void
    {
        $repositories = [
            new CategoryListRepository(new CategoryModel(), $this->db),
            new CollectionListRepository(new CollectionModel(), $this->db),
            new PageListRepository(new PageModel(), $this->db),
            new TagListRepository(new TagModel(), $this->db),
            new SettingListRepository(new SettingModel(), $this->db),
            new MenuListRepository(new MenuModel(), $this->db),
        ];

        foreach ($repositories as $repository) {
            $result = $repository->paginateAdminList([], 1, 20);

            $this->assertSame([], $result['data']);
            $this->assertSame(0, $result['total']);
            $this->assertSame(1, $result['page']);
            $this->assertSame(20, $result['per_page']);
        }
    }
}
