<?php

declare(strict_types=1);

namespace App\Libraries\Cms;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use JsonException;

/**
 * Transactional outbox for public-cache invalidations.
 *
 * The caller normally runs inside a CMS transaction. The insert therefore
 * commits or rolls back with the content mutation. Delivery is intentionally
 * handled by CacheInvalidationOutboxDispatcher in a separate process.
 */
final class CacheInvalidationOutbox
{
    /** @var BaseConnection<object, object>|null */
    private readonly ?BaseConnection $db;

    /** @param BaseConnection<object, object>|null $db */
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db;
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $locales
     * @param list<string> $routes
     */
    public function append(array $scopes, array $locales = [], array $routes = [], string $source = 'cms_automatic'): int
    {
        $payload = [
            'scopes' => $this->normalize($scopes),
            'locales' => $this->normalize($locales),
            'routes' => $this->normalize($routes),
        ];
        if ($payload['scopes'] === []) {
            return 0;
        }

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Could not encode cache invalidation outbox payload.', 0, $exception);
        }

        $eventKey = hash('sha256', $encoded . '|' . microtime(true) . '|' . bin2hex(random_bytes(16)));
        $db = $this->connection();
        try {
            $db->table('cache_invalidation_outbox')->insert([
                'event_key' => $eventKey,
                'payload' => $encoded,
                'source' => $this->source($source),
                'available_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            if (! $this->isDuplicate($exception)) {
                throw $exception;
            }
        }

        return (int) $db->insertID();
    }

    /**
     * @return list<array{id: int, payload: array<string, mixed>, source: string, attempts: int, lock_token: string}>
     */
    public function claim(int $limit = 20, int $leaseSeconds = 300): array
    {
        $limit = max(1, min(100, $limit));
        $leaseSeconds = max(30, min(3600, $leaseSeconds));
        $db = $this->connection();
        $now = date('Y-m-d H:i:s');

        $db->table('cache_invalidation_outbox')
            ->where('dispatched_at', null)
            ->where('lock_expires_at <', $now)
            ->set(['lock_token' => null, 'lock_expires_at' => null])
            ->update();

        $query = $db->table('cache_invalidation_outbox')
            ->where('dispatched_at', null)
            ->where('available_at <=', $now)
            ->groupStart()
                ->where('lock_token', null)
                ->orWhere('lock_expires_at <', $now)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get();
        $rows = $query ? $query->getResultArray() : [];
        $claimed = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $token = hash('sha256', $id . '|' . microtime(true) . '|' . bin2hex(random_bytes(16)));
            $expires = date('Y-m-d H:i:s', time() + $leaseSeconds);
            $updated = $db->table('cache_invalidation_outbox')
                ->where('id', $id)
                ->where('dispatched_at', null)
                ->groupStart()
                    ->where('lock_token', null)
                    ->orWhere('lock_expires_at <', $now)
                ->groupEnd()
                ->update(['lock_token' => $token, 'lock_expires_at' => $expires]);
            if ($updated !== true || $db->affectedRows() !== 1) {
                continue;
            }

            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            if (! is_array($payload) || ! is_array($payload['scopes'] ?? null)) {
                $this->release($id, $token, 'Invalid outbox payload.', 3600);
                continue;
            }

            $claimed[] = [
                'id' => $id,
                'payload' => $payload,
                'source' => (string) ($row['source'] ?? 'remote'),
                'attempts' => (int) ($row['attempts'] ?? 0),
                'lock_token' => $token,
            ];
        }

        return $claimed;
    }

    public function markDispatched(int $id, string $token): bool
    {
        $db = $this->connection();

        return $db->table('cache_invalidation_outbox')
            ->where('id', $id)
            ->where('lock_token', $token)
            ->where('dispatched_at', null)
            ->update([
                'dispatched_at' => date('Y-m-d H:i:s'),
                'lock_token' => null,
                'lock_expires_at' => null,
                'last_error' => null,
            ]) && $db->affectedRows() === 1;
    }

    public function release(int $id, string $token, string $error, int $delaySeconds = 60): bool
    {
        $db = $this->connection();
        $delaySeconds = max(10, min(3600, $delaySeconds));

        return $db->table('cache_invalidation_outbox')
            ->where('id', $id)
            ->where('lock_token', $token)
            ->where('dispatched_at', null)
            ->set('attempts', 'attempts + 1', false)
            ->update([
                'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
                'lock_token' => null,
                'lock_expires_at' => null,
                'last_error' => mb_substr($error, 0, 2000),
            ]) && $db->affectedRows() === 1;
    }

    /** @return array{pending: int, locked: int, dispatched: int, oldest_pending_at: string|null} */
    public function status(): array
    {
        $db = $this->connection();
        $pending = (int) $db->table('cache_invalidation_outbox')->where('dispatched_at', null)->countAllResults();
        $locked = (int) $db->table('cache_invalidation_outbox')
            ->where('dispatched_at', null)
            ->where('lock_token IS NOT NULL', null, false)
            ->countAllResults();
        $dispatched = (int) $db->table('cache_invalidation_outbox')->where('dispatched_at IS NOT NULL', null, false)->countAllResults();
        $oldestResult = $db->table('cache_invalidation_outbox')
            ->select('created_at')
            ->where('dispatched_at', null)
            ->orderBy('created_at', 'ASC')
            ->get(1);
        $oldest = $oldestResult !== false ? $oldestResult->getRowArray() : null;

        return [
            'pending' => $pending,
            'locked' => $locked,
            'dispatched' => $dispatched,
            'oldest_pending_at' => is_array($oldest) && is_string($oldest['created_at'] ?? null) ? $oldest['created_at'] : null,
        ];
    }

    /** @return BaseConnection<object, object> */
    private function connection(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function normalize(array $values): array
    {
        $values = array_map(static fn (mixed $value): string => trim((string) $value), $values);
        $values = array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
        sort($values);

        return array_values(array_unique($values));
    }

    private function source(string $source): string
    {
        $source = trim($source);

        return $source !== '' ? mb_substr($source, 0, 64) : 'cms_automatic';
    }

    private function isDuplicate(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique constraint');
    }
}
