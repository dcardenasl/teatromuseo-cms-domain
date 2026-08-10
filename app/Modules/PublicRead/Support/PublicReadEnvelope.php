<?php

declare(strict_types=1);

namespace App\Modules\PublicRead\Support;

use DateTimeImmutable;
use DateTimeZone;
use dcardenasl\Ci4ApiCore\Support\ApiResult;

/** Canonical response envelope for CMS PublicRead resources. */
final class PublicReadEnvelope
{
    /** @param array<int|string, mixed> $data @param array<string, mixed> $meta */
    public static function success(
        string $locale,
        array $data,
        string $sourceRevision,
        ?int $page = null,
        ?int $perPage = null,
        ?int $total = null,
        array $meta = [],
        string $domain = 'cms',
    ): ApiResult {
        return new ApiResult([
            'version' => 1,
            'ok' => true,
            'data' => $data,
            'meta' => array_merge([
                'locale' => $locale,
                'source_revision' => $sourceRevision,
                'snapshot_revision' => null,
                'fields' => [],
                'generated_at' => self::now(),
                'expires_at' => null,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ], $meta),
            'source' => ['domain' => $domain, 'state' => 'fresh', 'stale' => false],
            'messages' => [],
        ]);
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
