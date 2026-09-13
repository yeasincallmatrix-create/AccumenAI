<?php

namespace App\Services\Medical;

use App\Models\Medical\NumberSequence;
use App\Support\MedicalScope;

/**
 * Phase 04 — MR numbers are database-backed sequences (stored
 * MR-YYYY-NNNNN, displayed MR-YY-NNNNN), allocated via
 * NumberSequenceService. Replaces the legacy YY + 3-digit random scheme
 * (5-digit numerics in a disjoint namespace — historical rows are
 * untouched and can never collide with the new format).
 *
 * The institute id stays an optional argument (PatientController passes it
 * explicitly; web-guard callers without one fall back to MedicalScope).
 * Uniqueness is per tenant via composite DB keys, so no tenant segment is
 * embedded in the number itself.
 */
class MrNumberGenerator
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    public function generate(?int $instituteId = null): string
    {
        $instituteId ??= MedicalScope::getInstituteId();

        return $this->sequences->next(NumberSequence::TYPE_MR, $instituteId);
    }

    /**
     * Non-consuming estimate for form previews ("next MR will likely be…").
     * Must never be stored — concurrent allocations may land first.
     */
    public function peek(?int $instituteId = null): string
    {
        $instituteId ??= MedicalScope::getInstituteId();

        return $this->sequences->peek(NumberSequence::TYPE_MR, $instituteId);
    }
}
