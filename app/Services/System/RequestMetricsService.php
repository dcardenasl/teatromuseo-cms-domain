<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\RequestLogModel;

/**
 * Aggregates `request_logs` into SLO/health metrics: totals, success/error
 * rates, response-time percentiles, status-code breakdown. Extracted from
 * RequestLogModel::getStats() (LAYER-05) — the rate/percentage/SLO-target
 * comparisons are business logic that has no business living in a Model;
 * RequestLogModel itself now only exposes thin, scoped finders
 * (countSince(), countByResponseCodeRange(), avgResponseTimeSince(),
 * percentileResponseTime(), getSlowRequests()) that this service composes.
 *
 * Not yet wired to a controller route as of this extraction — same
 * unconsumed-but-real-logic state RequestLogModel::getStats() was already
 * in (see tests/Integration/Models/RequestLogModelTest.php, now
 * tests/Unit/Services/System/RequestMetricsServiceTest.php). Exposing it
 * publicly (e.g. a `/api/v1/system/metrics` endpoint) is a separate,
 * unscoped decision.
 */
class RequestMetricsService
{
    public function __construct(private RequestLogModel $model)
    {
    }

    /**
     * @param string $period 'hour'|'day'|'week'|'month'
     * @return array<string, mixed>
     */
    public function getStats(string $period = 'day'): array
    {
        $since = $this->sinceFromPeriod($period);

        $totalRequests = $this->model->countSince($since);
        $successfulRequests = $this->model->countByResponseCodeRange($since, 200, 400);
        $failedRequests = $this->model->countByResponseCodeRange($since, 400, 600);
        $avgResponseTime = $this->model->avgResponseTimeSince($since);
        $p95 = $this->model->percentileResponseTime($since, 0.95, $totalRequests);
        $p99 = $this->model->percentileResponseTime($since, 0.99, $totalRequests);

        $errorRate = $totalRequests > 0 ? ($failedRequests / $totalRequests) * 100 : 0.0;
        $availability = $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 100.0;
        $latencyTarget = config('Api')->sloP95TargetMs ?? 500;

        return [
            'period' => $period,
            'since' => $since,
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'p95_response_time_ms' => $p95,
            'p99_response_time_ms' => $p99,
            'error_rate_percent' => round($errorRate, 2),
            'availability_percent' => round($availability, 2),
            'status_code_breakdown' => $this->getStatusCodeBreakdown($since),
            'slo' => [
                'p95_target_ms' => $latencyTarget,
                'p95_target_met' => $p95 <= $latencyTarget,
            ],
        ];
    }

    /**
     * @param int $threshold Threshold in milliseconds
     * @return array<int, array<int|string, bool|float|int|object|string|null>|object>
     */
    public function getSlowRequests(int $threshold = 1000, int $limit = 10): array
    {
        return $this->model->getSlowRequests($threshold, $limit);
    }

    /**
     * @return array{'2xx':int,'3xx':int,'4xx':int,'5xx':int}
     */
    private function getStatusCodeBreakdown(string $since): array
    {
        return [
            '2xx' => $this->model->countByResponseCodeRange($since, 200, 300),
            '3xx' => $this->model->countByResponseCodeRange($since, 300, 400),
            '4xx' => $this->model->countByResponseCodeRange($since, 400, 500),
            '5xx' => $this->model->countByResponseCodeRange($since, 500, 600),
        ];
    }

    private function sinceFromPeriod(string $period): string
    {
        return match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };
    }
}
