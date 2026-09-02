<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use App\Entities\MenuItemEntity;
use App\Models\PageModel;

/**
 * Maps CMS menu destinations to semantic routes owned by public domains.
 */
final class PublicNavigationResolver
{
    /**
     * @return array{route_key: string|null, target_type: string|null, target_id: int|null}
     */
    public function resolve(MenuItemEntity $item): array
    {
        if ($item->link_type === 'event_listing') {
            return [
                'route_key' => 'events',
                'target_type' => 'event_listing',
                'target_id' => null,
            ];
        }

        if ($item->link_type !== 'page' || $item->page_id === null) {
            return $this->emptyResult();
        }

        $page = (new PageModel())->find((int) $item->page_id);
        $pageType = is_object($page) ? (string) ($page->page_type ?? '') : '';
        $routeKey = match ($pageType) {
            'events' => 'events',
            'catalog_listing' => 'catalog',
            default => null,
        };

        return $routeKey === null
            ? $this->emptyResult()
            : [
                'route_key' => $routeKey,
                'target_type' => $pageType,
                'target_id' => (int) $item->page_id,
            ];
    }

    /** @return array{route_key: null, target_type: null, target_id: null} */
    private function emptyResult(): array
    {
        return [
            'route_key' => null,
            'target_type' => null,
            'target_id' => null,
        ];
    }
}
