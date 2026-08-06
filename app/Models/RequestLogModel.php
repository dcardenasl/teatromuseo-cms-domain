<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RequestLogModel extends Model
{
    protected $table = 'request_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'method',
        'uri',
        'user_id',
        'ip_address',
        'user_agent',
        'response_code',
        'response_time',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Get slow requests
     *
     * @param int $threshold Threshold in milliseconds
     * @param int $limit
     * @return array<int, array<int|string, bool|float|int|object|string|null>|object>
     */
    public function getSlowRequests(int $threshold = 1000, int $limit = 10): array
    {
        return $this->select('method, uri, response_time, created_at')
            ->where('response_time >', $threshold)
            ->orderBy('response_time', 'DESC')
            ->limit($limit)
            ->find();
    }

    /**
     * Count requests logged since a datetime.
     */
    public function countSince(string $since): int
    {
        return (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->countAllResults();
    }

    /**
     * Count requests with a response_code in [$min, $max) since a datetime.
     */
    public function countByResponseCodeRange(string $since, int $min, int $max): int
    {
        return (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->where('response_code >=', $min)
            ->where('response_code <', $max)
            ->countAllResults();
    }

    /**
     * Average response time (ms) since a datetime.
     */
    public function avgResponseTimeSince(string $since): float
    {
        $query = $this->db->table($this->table)
            ->select('AVG(response_time) as avg_response_time')
            ->where('created_at >=', $since)
            ->get();

        $row = $query ? $query->getRow() : null;

        return $row ? (float) ($row->avg_response_time ?? 0) : 0.0;
    }

    /**
     * Efficiently calculates a percentile response time (ms) directly from
     * the DB using LIMIT/OFFSET (O(1) memory) — $totalCount is supplied by
     * the caller (already computed via countSince()) rather than recounted
     * here, so callers that need several percentiles for the same window
     * only pay for the count once.
     */
    public function percentileResponseTime(string $since, float $percentile, int $totalCount): float
    {
        if ($totalCount === 0) {
            return 0.0;
        }

        $offset = (int) floor($percentile * $totalCount);
        // Ensure offset is within bounds (0 to totalCount - 1)
        $offset = max(0, min($offset, $totalCount - 1));

        $query = $this->db->table($this->table)
            ->select('response_time')
            ->where('created_at >=', $since)
            ->orderBy('response_time', 'ASC')
            ->limit(1, $offset)
            ->get();

        $row = $query ? $query->getRow() : null;

        return $row ? (float) $row->response_time : 0.0;
    }
}
