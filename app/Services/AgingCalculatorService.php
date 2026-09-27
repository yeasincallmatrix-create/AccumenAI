<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aging bucket calculations for AR / AP report modules (Phase B).
 *
 * Bucket definitions are config-driven — config('accounting.aging.buckets')
 * is the single source of truth established by Phase A. The fallback below
 * mirrors that config so the service degrades gracefully if the key is
 * missing.
 *
 * Data sources (Phase A config):
 *  - AR: invoices (institute_id, due_date, due_amount, status)
 *  - AP: purchase_invoices (supplier_id, due_date, due_amount, status)
 */
class AgingCalculatorService
{
    /**
     * Aging buckets (days overdue), derived from Phase A config.
     *
     * @return array<string, array{label: string, min: int, max: int|null}>
     */
    public function buckets(): array
    {
        $raw = config('accounting.aging.buckets');

        $fallback = [
            ['key' => 'current', 'label' => 'Current (0-30)', 'min' => 0, 'max' => 30],
            ['key' => 'd31_60', 'label' => '31-60 Days', 'min' => 31, 'max' => 60],
            ['key' => 'd61_90', 'label' => '61-90 Days', 'min' => 61, 'max' => 90],
            ['key' => 'd91_120', 'label' => '91-120 Days', 'min' => 91, 'max' => 120],
            ['key' => 'd121_plus', 'label' => '120+ Days', 'min' => 121, 'max' => null],
        ];

        if (!is_array($raw) || $raw === []) {
            $raw = $fallback;
        }

        // Config ships as a list of ['key','label','min','max'] rows.
        if (isset($raw[0]) && is_array($raw[0]) && isset($raw[0]['key'])) {
            $keyed = [];
            foreach ($raw as $row) {
                $keyed[$row['key']] = [
                    'label' => $row['label'] ?? strtoupper($row['key']),
                    'min' => $row['min'] ?? 0,
                    'max' => $row['max'] ?? null,
                ];
            }

            return $keyed;
        }

        // Already a keyed map (label/min/max per key).
        return $raw;
    }

    /**
     * AR Aging — customer receivables from invoices.
     *
     * Invoice status enum: unpaid|partial|paid|cancelled — there is no
     * 'overdue' value; overdue is derived from due_date vs as-of date.
     */
    public function arAging(?int $instituteId = null, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        $outstanding = 'COALESCE(NULLIF(due_amount, 0), GREATEST(payable_amount - COALESCE(paid_amount, 0), 0))';

        $query = DB::table('invoices')
            ->select(
                'id',
                'invoice_number',
                'institute_id',
                'due_date',
                DB::raw($outstanding.' AS outstanding'),
                'status'
            )
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereRaw($outstanding.' > 0');

        if ($instituteId) {
            $query->where('institute_id', $instituteId);
        }

        return $this->bucketize($query->get(), $asOf);
    }

    /**
     * AP Aging — supplier payables from purchase_invoices.
     *
     * purchase_invoices.status is the posting lifecycle
     * (draft|posted|cancelled|reversed), not a payment state: only posted,
     * non-deleted rows carry an AP liability, and settlement is read from
     * due_amount (falling back to grand_total - paid_amount).
     */
    public function apAging(?int $supplierId = null, ?Carbon $asOf = null, ?int $instituteId = null): array
    {
        $asOf = $asOf ?? now();

        $outstanding = 'COALESCE(NULLIF(due_amount, 0), GREATEST(grand_total - COALESCE(paid_amount, 0), 0))';

        $query = DB::table('purchase_invoices')
            ->select(
                'id',
                'invoice_number',
                'supplier_id',
                'due_date',
                DB::raw($outstanding.' AS outstanding'),
                'status'
            )
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereRaw($outstanding.' > 0');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($instituteId) {
            $query->where('institute_id', $instituteId);
        }

        return $this->bucketize($query->get(), $asOf);
    }

    /**
     * Bucketize rows by days overdue against the config-driven buckets.
     */
    private function bucketize(Collection $rows, Carbon $asOf): array
    {
        $buckets = [];
        foreach ($this->buckets() as $key => $meta) {
            $buckets[$key] = [
                'label' => $meta['label'],
                'min' => $meta['min'],
                'max' => $meta['max'],
                'rows' => [],
                'total' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $days = $this->daysOverdue($row->due_date, $asOf);
            $bucketKey = $this->bucketFor($days);

            $buckets[$bucketKey]['rows'][] = [
                'id' => $row->id,
                'invoice_number' => $row->invoice_number,
                'due_date' => $row->due_date,
                'days_overdue' => $days,
                'outstanding' => (float) $row->outstanding,
            ];
            $buckets[$bucketKey]['total'] += (float) $row->outstanding;
        }

        return [
            'as_of' => $asOf->toDateString(),
            'buckets' => $buckets,
            'grand_total' => array_sum(array_column($buckets, 'total')),
            'row_count' => $rows->count(),
        ];
    }

    /**
     * Whole days between due date and as-of date; future due dates clamp
     * to 0 (not yet overdue).
     */
    public function daysOverdue(?string $dueDate, Carbon $asOf): int
    {
        if (!$dueDate) {
            return 0;
        }

        $due = Carbon::parse($dueDate)->startOfDay();
        $diff = $due->diffInDays($asOf->startOfDay(), false);

        return $diff < 0 ? 0 : (int) $diff;
    }

    /**
     * Config-driven bucket assignment (first matching min..max range wins).
     */
    public function bucketFor(int $days): string
    {
        $buckets = $this->buckets();
        $keys = array_keys($buckets);

        foreach ($buckets as $key => $meta) {
            $min = (int) $meta['min'];
            $max = $meta['max'];

            if ($days < $min) {
                continue;
            }
            if ($max !== null && $days > (int) $max) {
                continue;
            }

            return $key;
        }

        // No range matched: below the first bucket → first, above the last
        // closed range → last.
        $first = $buckets[$keys[0]];
        if ($days < (int) $first['min']) {
            return $keys[0];
        }

        return $keys[array_key_last($keys)];
    }

    public function bucketLabel(string $key): string
    {
        return $this->buckets()[$key]['label'] ?? $key;
    }
}
