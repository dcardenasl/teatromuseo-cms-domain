<?php

declare(strict_types=1);

namespace App\Filters;

use dcardenasl\Ci4ApiCore\Http\Filters\AbstractWebAppKeyRequiredFilter;

/**
 * Validates that the X-App-Key header matches the configured WEB_API_KEY.
 *
 * Used on /api/v1/public/* routes so they are only callable by the Web app,
 * not directly from browsers or third parties. Fails closed (403) when
 * `WEB_API_KEY` is unset — the original bug here (before this filter extended
 * the package base) was a bare `return null` on the empty-key branch, which
 * let every request through unauthenticated whenever the key was unset.
 */
class WebAppKeyRequiredFilter extends AbstractWebAppKeyRequiredFilter
{
    protected function webAppKey(): string
    {
        return (string) env('WEB_API_KEY', '');
    }
}
