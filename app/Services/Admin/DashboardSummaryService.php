<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\DTO\Response\Admin\DashboardSummaryResponseDTO;
use App\Interfaces\Admin\DashboardSummaryRepositoryInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

final readonly class DashboardSummaryService
{
    public function __construct(
        private DashboardSummaryRepositoryInterface $repository,
    ) {
    }

    /**
     * Return only CMS sections allowed by the current user's permissions.
     *
     * @return DashboardSummaryResponseDTO
     */
    public function read(SecurityContext $context): DashboardSummaryResponseDTO
    {
        $permissions = $context->permissions;
        $allowedPermissions = [
            'cms.pages.read',
            'cms.entries.read',
            'cms.collections.read',
            'cms.menus.read',
            'cms.categories.read',
            'cms.tags.read',
            'cms.forms.read',
            'cms.submissions.read',
        ];
        if (array_intersect($allowedPermissions, $permissions) === []) {
            return DashboardSummaryResponseDTO::fromArray([
                'version' => 1,
                'generated_at' => date(DATE_ATOM),
                'sections' => ['counts' => []],
            ]);
        }

        $source = $this->repository->read($permissions);
        $sourceCounts = is_array($source['counts'] ?? null) ? $source['counts'] : [];
        $counts = [];

        $permissionMap = [
            'pages' => 'cms.pages.read',
            'entries' => 'cms.entries.read',
            'collections' => 'cms.collections.read',
            'menus' => 'cms.menus.read',
            'categories' => 'cms.categories.read',
            'tags' => 'cms.tags.read',
            'forms' => 'cms.forms.read',
        ];

        foreach ($permissionMap as $key => $permission) {
            if (in_array($permission, $permissions, true)) {
                $counts[$key] = (int) ($sourceCounts[$key] ?? 0);
            }
        }

        $result = [
            'version' => 1,
            'generated_at' => date(DATE_ATOM),
            'sections' => [
                'counts' => $counts,
            ],
        ];

        if (in_array('cms.submissions.read', $permissions, true)) {
            $result['sections']['submissions'] = is_array($source['submissions'] ?? null)
                ? $source['submissions']
                : [];
        }

        if (in_array('cms.pages.read', $permissions, true) || in_array('cms.entries.read', $permissions, true)) {
            $activity = is_array($source['recent_activity'] ?? null) ? $source['recent_activity'] : [];
            $result['sections']['recent_activity'] = array_values(array_filter(
                $activity,
                static function (mixed $item) use ($permissions): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    return ($item['type'] ?? '') === 'page'
                        ? in_array('cms.pages.read', $permissions, true)
                        : in_array('cms.entries.read', $permissions, true);
                }
            ));
        }

        return DashboardSummaryResponseDTO::fromArray($result);
    }
}
