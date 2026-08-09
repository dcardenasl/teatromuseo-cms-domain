<?php

declare(strict_types=1);

namespace App\Support;

use CodeIgniter\Database\QueryInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\RequestIdHolder;

/**
 * Request-scoped metrics for public-read and existing public endpoints.
 *
 * SQL text and request bodies are intentionally not recorded. The shared core
 * owns correlation IDs; this app-owned collector adds domain-specific SQL and
 * public response measurements without changing controller contracts.
 */
final class PublicReadTelemetry
{
    private static bool $active = false;
    private static ?float $startedAt = null;
    private static string $path = '';
    private static string $locale = '';
    private static int $queryCount = 0;
    private static float $queryDurationMs = 0.0;
    private static int $httpCount = 0;
    private static float $httpDurationMs = 0.0;
    private static int $httpAttempts = 0;

    public static function begin(RequestInterface $request): void
    {
        self::$active = self::isPublicPath($request->getUri()->getPath());
        self::$startedAt = self::$active ? microtime(true) : null;
        self::$path = $request->getUri()->getPath();
        self::$locale = $request instanceof IncomingRequest ? $request->getLocale() : '';
        self::$queryCount = 0;
        self::$queryDurationMs = 0.0;
        self::$httpCount = 0;
        self::$httpDurationMs = 0.0;
        self::$httpAttempts = 0;
    }

    public static function recordQuery(mixed $query): void
    {
        if (! self::$active || ! $query instanceof QueryInterface) {
            return;
        }

        self::$queryCount++;
        self::$queryDurationMs += (float) $query->getDuration(6) * 1000;
    }

    public static function recordHttp(?int $status, float $durationMs, int $attempt): void
    {
        if (! self::$active) {
            return;
        }

        self::$httpCount++;
        self::$httpDurationMs += $durationMs;
        self::$httpAttempts += max(1, $attempt);
    }

    public static function after(ResponseInterface $response): ResponseInterface
    {
        if (! self::$active || self::$startedAt === null) {
            return $response;
        }

        $body = (string) $response->getBody();
        $meta = self::responseMeta($body);
        $payload = [
            'component'             => 'teatromuseo-cms-domain',
            'event'                 => 'public_telemetry',
            'request_id'            => RequestIdHolder::get(),
            'path'                  => self::$path,
            'locale'                => self::$locale,
            'duration_ms'           => round((microtime(true) - self::$startedAt) * 1000, 2),
            'status'                => $response->getStatusCode(),
            'response_bytes'        => strlen($body),
            'sql_query_count'       => self::$queryCount,
            'sql_duration_ms'       => round(self::$queryDurationMs, 2),
            'outbound_http_count'   => self::$httpCount,
            'outbound_http_attempts' => self::$httpAttempts,
            'outbound_http_duration_ms' => round(self::$httpDurationMs, 2),
            'source_revision'       => $meta['source_revision'],
            'snapshot_revision'     => $meta['snapshot_revision'],
            'source_state'          => $meta['source_state'],
            'stale'                 => $meta['stale'],
        ];
        log_message(
            'info',
            '[public-telemetry] ' . (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $response;
    }

    public static function reset(): void
    {
        self::$active = false;
        self::$startedAt = null;
        self::$path = '';
        self::$locale = '';
        self::$queryCount = 0;
        self::$queryDurationMs = 0.0;
        self::$httpCount = 0;
        self::$httpDurationMs = 0.0;
        self::$httpAttempts = 0;
    }

    private static function isPublicPath(string $path): bool
    {
        return str_contains($path, 'public/') || str_contains($path, 'public-read/');
    }

    /**
     * @return array{source_revision:?string,snapshot_revision:?string,source_state:?string,stale:bool}
     */
    private static function responseMeta(string $body): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return [
                'source_revision' => null,
                'snapshot_revision' => null,
                'source_state' => null,
                'stale' => false,
            ];
        }

        $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
        $source = is_array($decoded['source'] ?? null) ? $decoded['source'] : [];

        return [
            'source_revision' => is_string($meta['source_revision'] ?? null) ? $meta['source_revision'] : null,
            'snapshot_revision' => is_string($meta['snapshot_revision'] ?? null) ? $meta['snapshot_revision'] : null,
            'source_state' => is_string($source['state'] ?? null) ? $source['state'] : null,
            'stale' => ($source['stale'] ?? false) === true,
        ];
    }
}
