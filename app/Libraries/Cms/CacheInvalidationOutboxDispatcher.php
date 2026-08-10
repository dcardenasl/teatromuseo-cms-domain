<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

final class CacheInvalidationOutboxDispatcher
{
    public function __construct(
        private readonly CacheInvalidationOutbox $outbox,
        private readonly CacheInvalidationClient $client,
    ) {
    }

    /** @return array{claimed: int, dispatched: int, retried: int} */
    public function dispatch(int $limit = 20): array
    {
        $events = $this->outbox->claim($limit);
        $dispatched = 0;
        $retried = 0;

        foreach ($events as $event) {
            $payload = $event['payload'];
            $scopes = $this->strings($payload['scopes'] ?? []);
            $locales = $this->strings($payload['locales'] ?? []);
            $routes = $this->strings($payload['routes'] ?? []);
            $source = (string) ($event['source'] ?? 'cms_automatic');

            try {
                $sent = $this->client->invalidateNow($scopes, $source, $locales, $routes);
            } catch (\Throwable $exception) {
                $sent = false;
                log_message('error', '[CacheInvalidationOutboxDispatcher] Event failed: ' . $exception->getMessage());
            }

            if ($sent && $this->outbox->markDispatched($event['id'], $event['lock_token'])) {
                $dispatched++;
                continue;
            }

            $delay = min(3600, max(60, 60 * (2 ** min(5, $event['attempts']))));
            $this->outbox->release($event['id'], $event['lock_token'], 'Web invalidation delivery failed.', $delay);
            $retried++;
        }

        return ['claimed' => count($events), 'dispatched' => $dispatched, 'retried' => $retried];
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $values = array_map(static fn (mixed $item): string => trim((string) $item), $value);

        return array_values(array_filter($values, static fn (string $item): bool => $item !== ''));
    }
}
