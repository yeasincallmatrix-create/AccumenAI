<?php

namespace App\Services\Medical;

use App\Models\Medical\Patient;
use Illuminate\Support\Facades\DB;

/**
 * Generate unique MR (Medical Record) numbers.
 * Format: MR-YYYY-III-XXXXX
 * YYYY = Year, III = Institute ID (3 digits), XXXXX = Sequential number (5 digits)
 *
 * Phase 1 adaptation: the institute id is an explicit argument (the spec read
 * it from `auth()->user()->institute_id`, which does not exist for web-guard
 * users). Generation is retried until the value is unique, so concurrent
 * registrations cannot collide on the unique `mr_number` column.
 */
class MrNumberGenerator
{
    public function generate(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'MR-'.$year.'-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-';

        return DB::transaction(function () use ($instituteId, $year, $prefix) {
            // Lock this institute's yearly sequence so concurrent requests
            // cannot read the same "last" row.
            $lastPatient = Patient::where('institute_id', $instituteId)
                ->whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;
            if ($lastPatient && preg_match('/(\d{5})$/', (string) $lastPatient->mr_number, $m)) {
                $nextNumber = ((int) $m[1]) + 1;
            } elseif ($lastPatient) {
                // Last MR does not match the pattern (legacy/seeded data) —
                // fall back to count-based sequencing.
                $nextNumber = Patient::where('institute_id', $instituteId)
                    ->whereYear('created_at', $year)
                    ->count() + 1;
            }

            $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            // Belt-and-braces: bump past any out-of-band number.
            while (Patient::where('mr_number', $candidate)->exists()) {
                $nextNumber++;
                $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }

            return $candidate;
        });
    }
}
