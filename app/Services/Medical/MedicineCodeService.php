<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;
use Illuminate\Support\Facades\DB;

/**
 * Sequential per-tenant medicine codes across three slabs:
 * 4-digit (1000–9999) → 5-digit (10000–99999) → 6-digit (100000–999999).
 *
 * - Numeric codes only; legacy MED- prefixed and other non-numeric codes
 *   are ignored by the sequence (never modified, never reused).
 * - suggestNext() increments from the highest existing code (fast path
 *   for the UI); peekNextCode()/reserveCode() fill the first gap so
 *   deleted numbers are recycled.
 * - reserveCode() runs under SELECT … FOR UPDATE so concurrent creates
 *   in the same institute can never receive the same code.
 */
class MedicineCodeService
{
    /**
     * Three slabs: 4-digit, 5-digit, 6-digit (last).
     * Per-tenant sequence. Unique within institute.
     */
    public const SLABS = [
        ['digits' => 4, 'start' => 1000, 'end' => 9999],
        ['digits' => 5, 'start' => 10000, 'end' => 99999],
        ['digits' => 6, 'start' => 100000, 'end' => 999999],
    ];

    public const MAX_CODE = 999999;
    public const MIN_CODE = 1000;

    /**
     * Peek the next code without reserving (for UI suggestion).
     * Walks 4 → 5 → 6 slabs.
     */
    public function peekNextCode(int $instituteId): ?string
    {
        $existing = $this->getExistingCodes($instituteId);

        return $this->findFirstGap($existing);
    }

    /**
     * Suggest next based on highest existing (increment).
     * 4585 → 4586, 9999 → 10000, 99999 → 100000, 999999 → null
     */
    public function suggestNext(int $instituteId): ?string
    {
        $highest = Medicine::where('institute_id', $instituteId)
            ->whereNotNull('code')
            ->where('code', 'REGEXP', '^[0-9]+$')
            ->selectRaw('MAX(CAST(code AS UNSIGNED)) as max_code')
            ->value('max_code');

        if ($highest === null) {
            return (string) self::MIN_CODE;
        }

        $next = (int) $highest + 1;

        if ($next > self::MAX_CODE) {
            return null; // exhausted
        }

        return (string) $next;
    }

    /**
     * Reserve a code atomically with row-level locking.
     * Prevents race conditions when concurrent creates occur.
     */
    public function reserveCode(int $instituteId): ?string
    {
        return DB::transaction(function () use ($instituteId) {
            // Lock existing codes for this institute
            $codes = Medicine::where('institute_id', $instituteId)
                ->whereNotNull('code')
                ->where('code', 'REGEXP', '^[0-9]+$')
                ->lockForUpdate()
                ->pluck('code')
                ->map(fn ($c) => (int) $c)
                ->toArray();

            return $this->findFirstGap($codes);
        });
    }

    /**
     * Find first unused number across slabs.
     */
    protected function findFirstGap(array $existingCodes): ?string
    {
        $set = array_flip($existingCodes);

        foreach (self::SLABS as $slab) {
            for ($n = $slab['start']; $n <= $slab['end']; $n++) {
                if (! isset($set[$n])) {
                    return (string) $n;
                }
            }
        }

        return null; // exhausted
    }

    /**
     * Get existing numeric codes for institute.
     */
    protected function getExistingCodes(int $instituteId): array
    {
        return Medicine::where('institute_id', $instituteId)
            ->whereNotNull('code')
            ->where('code', 'REGEXP', '^[0-9]+$')
            ->pluck('code')
            ->map(fn ($c) => (int) $c)
            ->toArray();
    }

    /**
     * Get slab usage info for dashboard.
     */
    public function slabInfo(int $instituteId): array
    {
        $used = $this->getExistingCodes($instituteId);
        $info = [];

        foreach (self::SLABS as $slab) {
            $inSlab = array_filter($used, fn ($n) => $n >= $slab['start'] && $n <= $slab['end']);
            $capacity = $slab['end'] - $slab['start'] + 1;
            $count = count($inSlab);
            $info[] = [
                'digits' => $slab['digits'],
                'start' => $slab['start'],
                'end' => $slab['end'],
                'capacity' => $capacity,
                'used' => $count,
                'available' => $capacity - $count,
                'percent_used' => $capacity > 0 ? round(($count / $capacity) * 100, 2) : 0,
            ];
        }

        return $info;
    }
}
