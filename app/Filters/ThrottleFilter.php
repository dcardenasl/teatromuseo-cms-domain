<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractThrottleFilter;

/**
 * Rate-limiting filter for the website builder app. Inherits fixed-window IP + user-id
 * bucketing from {@see AbstractThrottleFilter}; limits come from Config\Api
 * (`rateLimitWindow`, `rateLimitRequests`, `rateLimitUserRequests`).
 */
class ThrottleFilter extends AbstractThrottleFilter
{
    /**
     * The server-rendered Web app fans out several public GETs per page.
     * Those calls all arrive from one hosting IP, so the generic IP bucket
     * would rate-limit the whole site instead of an individual caller.
     * Public reads are already gated by WebAppKeyRequiredFilter; isolate that
     * trusted caller in its own explicitly configurable bucket.
     *
     * Writes and protected routes intentionally retain the inherited IP/user
     * buckets.
     *
     * @return list<array{key: string, limit: int, window: int}>
     */
    protected function resolveBuckets(RequestInterface $request): array
    {
        if (strtolower($request->getMethod()) === 'get' && $this->isPublicRead($request)) {
            $appKey = trim($request->getHeaderLine('X-App-Key'));

            if ($appKey !== '') {
                return [[
                    'key'    => 'rate_limit_public_read_app_' . hash('sha256', $appKey),
                    'limit'  => max(1, (int) env('PUBLIC_READ_RATE_LIMIT_REQUESTS', 600)),
                    'window' => max(1, (int) env('PUBLIC_READ_RATE_LIMIT_WINDOW', 60)),
                ]];
            }
        }

        return parent::resolveBuckets($request);
    }

    private function isPublicRead(RequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_contains($path, '/api/v1/public/')
            || str_contains($path, '/api/v1/cms/public/')
            || str_contains($path, '/api/v1/public-read/');
    }
}
