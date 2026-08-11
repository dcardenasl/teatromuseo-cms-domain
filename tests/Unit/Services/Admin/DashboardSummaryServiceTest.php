<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Admin;

use App\Interfaces\Admin\DashboardSummaryRepositoryInterface;
use App\Services\Admin\DashboardSummaryService;
use CodeIgniter\Test\CIUnitTestCase;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * @internal
 */
final class DashboardSummaryServiceTest extends CIUnitTestCase
{
    public function testCountsAndActivityArePermissionFiltered(): void
    {
        $repository = $this->createMock(DashboardSummaryRepositoryInterface::class);
        $repository->expects($this->once())->method('read')->with([
            'cms.pages.read',
            'cms.submissions.read',
        ])->willReturn([
            'counts' => [
                'pages' => 3,
                'entries' => 4,
                'forms' => 5,
            ],
            'submissions' => ['new' => 2],
            'recent_activity' => [
                ['type' => 'page', 'id' => 1],
                ['type' => 'entry', 'id' => 2],
            ],
        ]);

        $result = (new DashboardSummaryService($repository))->read(
            new SecurityContext(7, [], ['cms.pages.read', 'cms.submissions.read'])
        );

        $this->assertSame(3, $result->sections['counts']['pages']);
        $this->assertArrayNotHasKey('entries', $result->sections['counts']);
        $this->assertSame(['new' => 2], $result->sections['submissions']);
        $this->assertSame([['type' => 'page', 'id' => 1]], $result->sections['recent_activity']);
    }

    public function testNoRelevantPermissionDoesNotReadRepository(): void
    {
        $repository = $this->createMock(DashboardSummaryRepositoryInterface::class);
        $repository->expects($this->never())->method('read');

        $result = (new DashboardSummaryService($repository))->read(
            new SecurityContext(7, [], ['users.read'])
        );

        $this->assertSame(['counts' => []], $result->sections);
    }
}
