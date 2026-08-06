<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Thin Active Record model for `page_views`: fillable fields, one write
 * method (record(), which truncates fields to their column widths — a
 * data-integrity concern, not business logic), and simple scoped finders
 * that return raw rows/counts. AnalyticsService owns period-window
 * resolution, percentage/rate computation, and response shaping — see its
 * docblock (LAYER-05, 2026-08-06): this model used to carry all of that
 * (getTotalViews/getUniqueVisitors/getTopPages/getTopReferrers/
 * getDeviceBreakdown/getBrowserBreakdown/getDailyTrend), making it an
 * analytics service disguised as a model. `getBrowserBreakdown` had zero
 * callers anywhere (AnalyticsService never exposed it, no route existed)
 * and was dropped rather than migrated — see TASKS.md for the note.
 */
class PageViewModel extends Model
{
    protected $table = 'page_views';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'url',
        'page_title',
        'referrer',
        'referrer_domain',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'device_type',
        'browser',
        'os',
        'session_id',
        'created_at',
    ];

    public function record(string $url, ?string $pageTitle, ?string $referrer, ?string $referrerDomain, ?string $utmSource, ?string $utmMedium, ?string $utmCampaign, string $deviceType, ?string $browser, ?string $os, ?string $sessionId): void
    {
        $this->insert([
            'url'             => substr($url, 0, 500),
            'page_title'      => $pageTitle !== null ? substr($pageTitle, 0, 255) : null,
            'referrer'        => $referrer !== null ? substr($referrer, 0, 500) : null,
            'referrer_domain' => $referrerDomain !== null ? substr($referrerDomain, 0, 100) : null,
            'utm_source'      => $utmSource,
            'utm_medium'      => $utmMedium,
            'utm_campaign'    => $utmCampaign,
            'device_type'     => $deviceType,
            'browser'         => $browser,
            'os'              => $os,
            'session_id'      => $sessionId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function countSince(string $since): int
    {
        $query = $this->builder()
            ->selectCount('id', 'total')
            ->where('created_at >=', $since)
            ->get();

        $row = $query !== false ? $query->getRowArray() : null;

        return (int) ($row['total'] ?? 0);
    }

    public function countDistinctSessionsSince(string $since): int
    {
        $query = $this->builder()
            ->select('COUNT(DISTINCT session_id) as total')
            ->where('created_at >=', $since)
            ->where('session_id IS NOT NULL')
            ->get();

        $row = $query !== false ? $query->getRowArray() : null;

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Raw view counts grouped by url+page_title, most-viewed first. No
     * percentage — that's AnalyticsService's job, since it needs the
     * period's total (a separate query) to compute it.
     *
     * @return list<array{url: string, page_title: string|null, views: int}>
     */
    public function groupByUrlSince(string $since, int $limit): array
    {
        $query = $this->builder()
            ->select('url, page_title, COUNT(*) as views')
            ->where('created_at >=', $since)
            ->groupBy('url, page_title')
            ->orderBy('views', 'DESC')
            ->limit($limit)
            ->get();

        $rows = $query !== false ? $query->getResultArray() : [];

        return array_values(array_map(static fn (array $row): array => [
            'url'        => (string) $row['url'],
            'page_title' => isset($row['page_title']) ? (string) $row['page_title'] : null,
            'views'      => (int) $row['views'],
        ], $rows));
    }

    /**
     * @return list<array{domain: string, views: int}>
     */
    public function groupByReferrerDomainSince(string $since, int $limit): array
    {
        $query = $this->builder()
            ->select('referrer_domain as domain, COUNT(*) as views')
            ->where('created_at >=', $since)
            ->where('referrer_domain IS NOT NULL')
            ->groupBy('referrer_domain')
            ->orderBy('views', 'DESC')
            ->limit($limit)
            ->get();

        $rows = $query !== false ? $query->getResultArray() : [];

        return array_values(array_map(static fn (array $row): array => [
            'domain' => (string) $row['domain'],
            'views'  => (int) $row['views'],
        ], $rows));
    }

    /**
     * @return list<array{device_type: string, total: int}>
     */
    public function groupByDeviceTypeSince(string $since): array
    {
        $query = $this->builder()
            ->select('device_type, COUNT(*) as total')
            ->where('created_at >=', $since)
            ->groupBy('device_type')
            ->get();

        $rows = $query !== false ? $query->getResultArray() : [];

        return array_values(array_map(static fn (array $row): array => [
            'device_type' => (string) $row['device_type'],
            'total'       => (int) $row['total'],
        ], $rows));
    }

    /**
     * View/unique-visitor counts grouped by hour or day, oldest first.
     * $hourly picks the SQL DATE_FORMAT granularity — a query-construction
     * detail, not the business decision of which granularity a given period
     * warrants (that stays in AnalyticsService).
     *
     * @return list<array{label: string, views: int, unique_visitors: int}>
     */
    public function groupByPeriod(string $since, bool $hourly): array
    {
        $groupFormat = $hourly
            ? "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')"
            : 'DATE(created_at)';

        $query = $this->builder()
            ->select("{$groupFormat} as label, COUNT(*) as views, COUNT(DISTINCT session_id) as unique_visitors")
            ->where('created_at >=', $since)
            ->groupBy($groupFormat)
            ->orderBy('label', 'ASC')
            ->get();

        $rows = $query !== false ? $query->getResultArray() : [];

        return array_values(array_map(static fn (array $row): array => [
            'label'           => (string) $row['label'],
            'views'           => (int) $row['views'],
            'unique_visitors' => (int) $row['unique_visitors'],
        ], $rows));
    }
}
