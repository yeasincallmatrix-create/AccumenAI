<?php

namespace App\Services\Medical;

use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;

/**
 * Calculates the consultation fee for an appointment booking.
 *
 * A patient who completed a visit with the same doctor within the doctor's
 * configured follow-up window pays the follow-up fee; otherwise the
 * first-visit fee applies. Doctors without a profile (plain user accounts)
 * resolve to null so booking continues unchanged (fee_applied stays null).
 */
class AppointmentFeeService
{
    /**
     * @return array{fee: ?float, is_follow_up: bool, days_since_last_visit: ?int, doctor_profile_id: ?int}
     */
    public function calculateFor(int $doctorUserId, Patient $patient, ?int $instituteId = null): array
    {
        $profile = Doctor::resolveForUser($doctorUserId, $instituteId);

        if (! $profile) {
            return [
                'fee' => null,
                'is_follow_up' => false,
                'days_since_last_visit' => null,
                'doctor_profile_id' => null,
            ];
        }

        $isFollowUp = $profile->hasFollowUpRateFor($patient);

        return [
            'fee' => $profile->getApplicableFee($patient),
            'is_follow_up' => $isFollowUp,
            'days_since_last_visit' => $profile->daysSinceLastVisitFor($patient),
            'doctor_profile_id' => $profile->id,
        ];
    }
}
