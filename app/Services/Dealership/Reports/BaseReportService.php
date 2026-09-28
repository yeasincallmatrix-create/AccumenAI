<?php

namespace App\Services\Dealership\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared snapshot cache for dealership reports (Phase 4).
 *
 * Results are cached in dealership_report_snapshots keyed by
 * (institute_id, report_key, sha256(filters)). Fresh rows (within
 * snapshot_ttl_minutes) are returned as-is; otherwise the report is
 * recomputed and the snapshot is replaced via updateOrInsert.
 */
abstract class BaseReportService
{
    protected function cached(string $reportKey, array $filters, int $instituteId, callable $compute): array
    {
        ksort($filters);
        $hash = hash('sha256', json_encode($filters));
        $ttl = (int) config('dealership.snapshot_ttl_minutes', 15);

        $row = DB::table('dealership_report_snapshots')
            ->where('institute_id', $instituteId)
            ->where('report_key', $reportKey)
            ->where('scope_hash', $hash)
            ->first();

        if ($row && $row->generated_at && Carbon::parse($row->generated_at)->gt(now()->subMinutes($ttl))) {
            $payload = json_decode($row->payload, true);
            if (is_array($payload)) {
                return $payload;
            }
        }

        $payload = $compute();

        DB::table('dealership_report_snapshots')->updateOrInsert(
            ['institute_id' => $instituteId, 'report_key' => $reportKey, 'scope_hash' => $hash],
            [
                'period_start' => $filters['from'] ?? null,
                'period_end' => $filters['to'] ?? null,
                'payload' => json_encode($payload),
                'generated_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $payload;
    }

    protected function applyDateRange($query, array $filters, string $column = 'created_at')
    {
        if (! empty($filters['from'])) {
            $query->whereDate($column, '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate($column, '<=', $filters['to']);
        }

        return $query;
    }
}
