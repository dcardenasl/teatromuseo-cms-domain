<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Libraries\Cms\CacheInvalidationClient;
use Config\Services;
use dcardenasl\Ci4ApiCore\Queue\Job;

final class CacheInvalidationJob extends Job
{
    public function handle(): void
    {
        // New jobs drain the transactional outbox. The payload branch keeps
        // already queued legacy invalidations deliverable during deployment.
        if (! array_key_exists('scopes', $this->data)) {
            Services::cacheInvalidationOutboxDispatcher(false)->dispatch(20);

            return;
        }

        $scopes = $this->data['scopes'] ?? [];
        if (! is_array($scopes)) {
            throw new \InvalidArgumentException('Cache invalidation scopes must be an array.');
        }

        $normalizedScopes = array_values(array_filter(
            array_map(static fn (mixed $scope): string => trim((string) $scope), $scopes),
            static fn (string $scope): bool => $scope !== '',
        ));

        $source = is_string($this->data['source'] ?? null)
            ? trim((string) $this->data['source'])
            : 'cms_automatic';

        (new CacheInvalidationClient(dispatch: false))->invalidateNow(
            $normalizedScopes,
            $source !== '' ? $source : 'cms_automatic',
            is_array($this->data['locales'] ?? null) ? $this->data['locales'] : [],
            is_array($this->data['routes'] ?? null) ? $this->data['routes'] : [],
        );
    }
}
