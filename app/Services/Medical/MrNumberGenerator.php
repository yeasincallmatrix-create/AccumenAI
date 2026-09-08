<?php

namespace App\Services\Medical;

use App\Models\Medical\Patient;
use App\Support\MedicalScope;

/**
 * Generate a unique patient ID in format: YY + 3-digit random.
 * Example: 26047 (year 2026, random 047).
 * Total: 5 digits, purely numeric, unique per tenant (institute).
 *
 * The institute id stays an optional argument (PatientController passes it
 * explicitly; web-guard callers without one fall back to MedicalScope).
 */
class MrNumberGenerator
{
    public function generate(?int $instituteId = null): string
    {
        $instituteId ??= MedicalScope::getInstituteId();

        $yy = date('y'); // Last 2 digits of year (e.g. 26)

        do {
            $random = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $number = $yy.$random;
        } while ($this->exists($number, $instituteId));

        return $number;
    }

    /**
     * Check if the generated number already exists for this tenant.
     */
    private function exists(string $number, int $instituteId): bool
    {
        return Patient::where('institute_id', $instituteId)
            ->where('mr_number', $number)
            ->exists();
    }
}
