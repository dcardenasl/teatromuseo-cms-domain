<?php

declare(strict_types=1);

namespace App\Libraries\Hub;

use dcardenasl\Ci4ApiCore\Http\Client\HubClient as CoreHubClient;

/**
 * HTTP client subclass for the central hub (ci4-api-starter).
 *
 * Extends the core HubClient to add role management endpoints specific to the website builder app.
 */
class HubClient extends CoreHubClient
{
    protected function recordBreadcrumb(string $method, string $url, ?int $status, float $durationMs, int $attempt): void
    {
        parent::recordBreadcrumb($method, $url, $status, $durationMs, $attempt);
        \App\Support\PublicReadTelemetry::recordHttp($status, $durationMs, $attempt);
    }

    /**
     * Find a role by its unique code in the hub.
     *
     * @return array<string, mixed>|null
     */
    public function findRoleByCode(string $code, string $bearerToken): ?array
    {
        $data = $this->request('GET', '/api/v1/iam/roles', [
            'headers' => array_merge($this->appKeyHeaders(), [
                'Authorization' => 'Bearer ' . $bearerToken,
            ]),
            'query' => ['filter[code]' => $code, 'per_page' => 1],
        ]);

        $items = $data['items'] ?? $data;
        return is_array($items) ? ($items[0] ?? null) : null;
    }

    /**
     * Attach a list of permissions (by code) to a role (by ID) in the hub.
     *
     * @param list<string> $permissionCodes
     */
    public function attachPermissionsToRole(int $roleId, array $permissionCodes, string $bearerToken): void
    {
        if (empty($permissionCodes)) {
            return;
        }

        $this->request('POST', "/api/v1/iam/roles/{$roleId}/permissions/attach", [
            'headers' => array_merge($this->appKeyHeaders(), [
                'Authorization' => 'Bearer ' . $bearerToken,
            ]),
            'json' => ['permission_codes' => $permissionCodes],
        ]);
    }

    /**
     * Batch-resolve public file metadata (id, url, variants) from the Hub.
     *
     * Results are cached using the CI4 cache store with a configurable TTL
     * (default 300 s). Already-cached IDs are not re-fetched.
     *
     * @param  list<int>  $fileIds
     * @param  int        $cacheTtl  Seconds to cache each file's metadata
     * @return array<int, array<string, mixed>>
     */
    public function resolvePublicFileMeta(array $fileIds, int $cacheTtl = 300): array
    {
        $fileIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $fileIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($fileIds === []) {
            return [];
        }

        $cache = $this->cache;
        $result = [];
        $miss = [];
        $staleKeyById = [];
        $staleTtl = max(1, (int) env('PUBLIC_READ_HUB_STALE_TTL', 900));

        foreach ($fileIds as $id) {
            $cached = $cache->get($this->fileMetaCacheKey($id));
            if (is_array($cached)) {
                $result[$id] = $cached;
            } else {
                $miss[] = $id;
                $staleKeyById[$id] = $this->fileMetaStaleCacheKey($id);
                $stale = $cache->get($staleKeyById[$id]);
                if (is_array($stale)) {
                    $result[$id] = $stale;
                }
            }
        }

        if ($miss === []) {
            return $result;
        }

        foreach (array_chunk($miss, 200) as $batch) {
            $startedAt = microtime(true);
            $status = null;
            $url = rtrim($this->config->url, '/') . '/api/v1/internal/files/batch-meta';
            try {
                $headers = array_merge($this->appKeyHeaders(), ['Accept' => 'application/json']);
                $requestId = \dcardenasl\Ci4ApiCore\Http\RequestIdHolder::get();
                if ($requestId !== null) {
                    $headers['X-Request-Id'] = $requestId;
                }
                $response = $this->http->request('GET', $url, [
                    'headers' => $headers,
                    'query'   => ['ids' => $batch],
                    'connect_timeout' => max(0.1, (float) env('PUBLIC_READ_HUB_CONNECT_TIMEOUT', 0.25)),
                    'timeout' => max(0.25, (float) env('PUBLIC_READ_HUB_TIMEOUT', 1.0)),
                    'http_errors' => false,
                ]);
                $status = $response->getStatusCode();
                $this->recordBreadcrumb('GET', $url, $status, (microtime(true) - $startedAt) * 1000, 1);
                if ($status < 200 || $status >= 300) {
                    continue;
                }

                $decoded = json_decode((string) $response->getBody(), true);
                $data = is_array($decoded) && is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
                if (! is_array($data)) {
                    continue;
                }

                foreach ($data as $fileId => $meta) {
                    if (! is_array($meta)) {
                        continue;
                    }
                    $id = isset($meta['id']) && is_numeric($meta['id']) ? (int) $meta['id'] : (int) $fileId;
                    if ($id <= 0) {
                        continue;
                    }
                    $result[$id] = $meta;
                    $freshTtl = max(1, $cacheTtl);
                    $cache->save($this->fileMetaCacheKey($id), $meta, $freshTtl);
                    $cache->save($this->fileMetaStaleCacheKey($id), $meta, $staleTtl);
                }
            } catch (\Throwable $e) {
                $this->recordBreadcrumb('GET', $url, $status, (microtime(true) - $startedAt) * 1000, 1);
                log_message('error', '[HubClient] resolvePublicFileMeta failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Invalidate cached file metadata for a given file ID.
     * Call this when the Hub notifies the Domain of a file update.
     */
    public function invalidateFileMetaCache(int $fileId): void
    {
        $cache = \Config\Services::cache();
        $cache->delete($this->fileMetaCacheKey($fileId));
        $cache->delete($this->fileMetaStaleCacheKey($fileId));
    }

    private function fileMetaCacheKey(int $fileId): string
    {
        return 'hub_file_meta_v3_' . $fileId;
    }

    private function fileMetaStaleCacheKey(int $fileId): string
    {
        return 'hub_file_meta_v3_stale_' . $fileId;
    }

    /**
     * Queue an email via the Hub's internal email endpoint.
     *
     * The Hub is the single email sender — website builder apps must never send emails directly.
     *
     * @return int Job ID (0 if queuing failed)
     */
    public function queueEmail(string $to, string $subject, string $message, ?string $textMessage = null): int
    {
        try {
            $data = $this->request('POST', '/api/v1/internal/email/queue', [
                'headers' => $this->appKeyHeaders(),
                'json'    => [
                    'to'           => $to,
                    'subject'      => $subject,
                    'message'      => $message,
                    'text_message' => $textMessage,
                ],
            ]);

            return (int) ($data['job_id'] ?? 0);
        } catch (\Throwable $e) {
            log_message('error', '[HubClient] queueEmail failed: ' . $e->getMessage());
            return 0;
        }
    }
}
