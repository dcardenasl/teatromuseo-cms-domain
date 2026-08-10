<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use dcardenasl\Ci4ApiCore\Queue\QueueManagerInterface;

/**
 * Notifies the public website to invalidate its server-side cache after content changes.
 *
 * Called from afterStore/afterUpdate hooks in CMS services. When configured with
 * the outbox, the event is inserted in the current database transaction and is
 * delivered later by the dispatcher. The legacy queue/direct mode is retained
 * for compatibility with already deployed workers and unit tests.
 */
class CacheInvalidationClient
{
    private string $webUrl;
    private string $invalidateKey;

    private ?CacheInvalidationOutbox $outbox;

    private ?QueueManagerInterface $queueManager;

    private bool $dispatch;

    private string $queueName;

    public function __construct(
        string $webUrl = '',
        string $invalidateKey = '',
        ?QueueManagerInterface $queueManager = null,
        bool $dispatch = true,
        string $queueName = 'default',
        ?CacheInvalidationOutbox $outbox = null,
    ) {
        $this->webUrl        = rtrim($webUrl ?: (string) env('WEB_CACHE_INVALIDATE_URL', ''), '/');
        $this->invalidateKey = $invalidateKey ?: (string) env('WEB_CACHE_INVALIDATE_KEY', '');
        $this->queueManager = $queueManager;
        $this->dispatch     = $dispatch;
        $this->queueName    = $queueName;
        $this->outbox       = $outbox;
    }

    /**
     * Send a cache invalidation request to the web app.
     *
     * @param list<string> $scopes e.g. ['pages', 'menus']
     */
    public function invalidate(array $scopes): void
    {
        if ($this->outbox !== null) {
            $this->outbox->append($scopes);

            return;
        }

        if ($this->dispatch && $this->queueManager !== null) {
            try {
                $this->queueManager->push(
                    \App\Jobs\CacheInvalidationJob::class,
                    ['scopes' => $scopes, 'source' => 'cms_automatic'],
                    $this->queueName,
                );
            } catch (\Throwable $exception) {
                log_message('error', '[CacheInvalidationClient] Could not dispatch invalidation job: ' . $exception->getMessage());
            }

            return;
        }

        $this->invalidateNow($scopes);
    }

    /**
     * Execute the HTTP invalidation, normally from the outbox dispatcher.
     *
     * @param list<string> $scopes
     * @param list<string> $locales
     * @param list<string> $routes
     */
    public function invalidateNow(array $scopes, string $source = 'cms_automatic', array $locales = [], array $routes = []): bool
    {
        if ($this->webUrl === '' || $this->invalidateKey === '' || empty($scopes)) {
            return false;
        }

        $payload = json_encode([
            'scopes' => array_values(array_unique($scopes)),
            'locales' => array_values(array_unique($locales)),
            'routes' => array_values(array_unique($routes)),
        ]);
        if ($payload === false) {
            log_message('error', '[CacheInvalidationClient] json_encode() failed for scopes: ' . implode(',', $scopes));

            return false;
        }

        $ch = curl_init($this->webUrl . '/cache/invalidate');
        if ($ch === false) {
            log_message('error', '[CacheInvalidationClient] curl_init() failed.');

            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Invalidate-Key: ' . $this->invalidateKey,
                'X-Cache-Invalidation-Source: ' . $source,
            ],
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            log_message('error', '[CacheInvalidationClient] cURL error: ' . $error);

            return false;
        }

        if ($status < 200 || $status >= 300) {
            log_message('warning', '[CacheInvalidationClient] HTTP ' . $status
                . ' for scopes [' . implode(',', $scopes) . ']: ' . substr((string) $raw, 0, 200));

            return false;
        }

        log_message('info', '[CacheInvalidationClient] Invalidated [' . implode(',', $scopes) . ']');

        return true;
    }
}
