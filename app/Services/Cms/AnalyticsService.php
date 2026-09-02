<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\DTO\Request\Cms\TrackEventRequestDTO;
use App\Models\PageViewModel;

/**
 * Owns page-view analytics: period-window resolution, aggregation, and
 * response shaping (percentages, zero-filled breakdowns). PageViewModel
 * (LAYER-05, 2026-08-06) was reduced to a thin Active Record model that
 * only executes scoped queries and returns raw rows/counts — this class is
 * where that data becomes the API-facing analytics contract.
 */
class AnalyticsService
{
    private const ALLOWED_PERIODS = ['1h', '24h', '7d', '30d'];
    private const DEFAULT_PERIOD  = '7d';

    public function __construct(private PageViewModel $model)
    {
    }

    public function record(TrackEventRequestDTO $dto): void
    {
        $referrerDomain = null;
        if ($dto->referrer !== null && $dto->referrer !== '') {
            $host = parse_url($dto->referrer, PHP_URL_HOST);
            $referrerDomain = is_string($host) ? strtolower($host) : null;
        }

        $this->model->record(
            url: $dto->url,
            pageTitle: $dto->page_title,
            referrer: $dto->referrer,
            referrerDomain: $referrerDomain,
            utmSource: $dto->utm_source,
            utmMedium: $dto->utm_medium,
            utmCampaign: $dto->utm_campaign,
            deviceType: $dto->device_type,
            browser: $dto->browser,
            os: $dto->os,
            sessionId: $dto->session_id,
        );
    }

    /**
     * @return array{total_views: int, unique_visitors: int, top_page: string|null, top_page_title: string|null, top_referrer: string|null, period: string}
     */
    public function getOverview(string $period): array
    {
        $period = $this->normalizePeriod($period);
        $since  = $this->periodToDatetime($period);

        $totalViews   = $this->model->countSince($since);
        $uniqueVis    = $this->model->countDistinctSessionsSince($since);
        $topPages     = $this->model->groupByUrlSince($since, 1);
        $topReferrers = $this->model->groupByReferrerDomainSince($since, 1);

        return [
            'total_views'      => $totalViews,
            'unique_visitors'  => $uniqueVis,
            'top_page'         => $topPages !== [] ? $topPages[0]['url'] : null,
            'top_page_title'   => $topPages !== [] ? $topPages[0]['page_title'] : null,
            'top_referrer'     => $topReferrers !== [] ? $topReferrers[0]['domain'] : null,
            'period'           => $period,
        ];
    }

    /**
     * @return array{data: list<array{url: string, page_title: string|null, views: int, percentage: float}>, period: string}
     */
    public function getTopPages(string $period, int $limit = 10): array
    {
        $period = $this->normalizePeriod($period);
        $since  = $this->periodToDatetime($period);
        $total  = $this->model->countSince($since);

        $rows = $this->model->groupByUrlSince($since, min($limit, 50));

        return [
            'data'   => array_map(
                fn (array $row): array => [...$row, 'percentage' => $this->percentageOf($row['views'], $total)],
                $rows
            ),
            'period' => $period,
        ];
    }

    /**
     * @return array{data: list<array{domain: string, views: int, percentage: float}>, period: string}
     */
    public function getTopReferrers(string $period, int $limit = 10): array
    {
        $period = $this->normalizePeriod($period);
        $since  = $this->periodToDatetime($period);
        $total  = $this->model->countSince($since);

        $rows = $this->model->groupByReferrerDomainSince($since, min($limit, 50));

        return [
            'data'   => array_map(
                fn (array $row): array => [...$row, 'percentage' => $this->percentageOf($row['views'], $total)],
                $rows
            ),
            'period' => $period,
        ];
    }

    /**
     * @return array{desktop: int, mobile: int, tablet: int, bot: int, unknown: int, period: string}
     */
    public function getDeviceBreakdown(string $period): array
    {
        $period = $this->normalizePeriod($period);
        $since  = $this->periodToDatetime($period);

        $breakdown = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0, 'bot' => 0, 'unknown' => 0];
        foreach ($this->model->groupByDeviceTypeSince($since) as $row) {
            if (array_key_exists($row['device_type'], $breakdown)) {
                $breakdown[$row['device_type']] = $row['total'];
            }
        }

        return [
            'desktop' => $breakdown['desktop'],
            'mobile'  => $breakdown['mobile'],
            'tablet'  => $breakdown['tablet'],
            'bot'     => $breakdown['bot'],
            'unknown' => $breakdown['unknown'],
            'period'  => $period,
        ];
    }

    /**
     * @return array{data: list<array{label: string, views: int, unique_visitors: int}>, period: string}
     */
    public function getTimeseries(string $period): array
    {
        $period = $this->normalizePeriod($period);
        $since  = $this->periodToDatetime($period);
        // Hourly granularity only makes sense for short windows — daily
        // granularity over a 1h/24h window would collapse everything into
        // a single bucket.
        $hourly = in_array($period, ['1h', '24h'], true);

        return [
            'data'   => $this->model->groupByPeriod($since, $hourly),
            'period' => $period,
        ];
    }

    private function percentageOf(int $count, int $total): float
    {
        return $total > 0 ? round($count / $total * 100, 1) : 0.0;
    }

    private function normalizePeriod(string $period): string
    {
        return in_array($period, self::ALLOWED_PERIODS, true) ? $period : self::DEFAULT_PERIOD;
    }

    private function periodToDatetime(string $period): string
    {
        return match ($period) {
            '1h'  => date('Y-m-d H:i:s', strtotime('-1 hour')),
            '24h' => date('Y-m-d H:i:s', strtotime('-24 hours')),
            '7d'  => date('Y-m-d H:i:s', strtotime('-7 days')),
            '30d' => date('Y-m-d H:i:s', strtotime('-30 days')),
            default => date('Y-m-d H:i:s', strtotime('-7 days')),
        };
    }
}
