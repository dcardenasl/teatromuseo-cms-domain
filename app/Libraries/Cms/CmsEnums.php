<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

final class CmsEnums
{
    public const WORKFLOW_STATUS    = ['draft', 'in_review', 'approved', 'published', 'archived'];
    public const PAGE_STATUS        = ['draft', 'published', 'archived'];
    public const PAGE_TYPE          = ['home', 'generic', 'contact', 'privacy', 'terms', '404', '500', 'maintenance', 'about', 'history', 'events', 'catalog_listing', 'collection_index', 'template_catalog_item', 'template_event_item', 'press', 'publications', 'transparency'];
    /**
     * Singleton template pages: rendered only with a runtime context injected
     * by the public site (never resolvable by slug on their own), and the only
     * page types the public by-type endpoint may serve.
     */
    public const PAGE_TEMPLATE_TYPES = ['template_catalog_item', 'template_event_item'];
    public const MENU_LINK_TYPES    = ['page', 'entry', 'collection_listing', 'event_listing', 'custom_url', 'no_link'];
    public const SITEMAP_CHANGEFREQ = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
    public const NON_TRANSLATABLE_TYPES = ['media_reference', 'repeater', 'boolean', 'integer', 'select', 'number'];

    /** @param array<string> $values */
    public static function inListRule(array $values): string
    {
        return 'in_list[' . implode(',', $values) . ']';
    }
}
