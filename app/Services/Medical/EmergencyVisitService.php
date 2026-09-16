<?php

namespace App\Services\Medical;

use App\Models\Medical\EmergencyVisit;
use App\Models\Medical\NumberSequence;

class EmergencyVisitService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
    ) {}

    /**
     * Generate the next emergency visit number for an institute.
     */
    public function generateVisitNumber(int $instituteId): string
    {
        return $this->sequences->next(NumberSequence::TYPE_EMERGENCY, $instituteId);
    }

    /**
     * Auto-assign triage level based on vitals and chief complaint.
     * Returns the recommended triage level.
     */
    public function suggestTriageLevel(array $vitals, ?string $chiefComplaint = null): string
    {
        // Red flags: cardiac arrest, unresponsive, severe hemorrhage
        $hasRedFlags = $this->checkRedFlags($vitals, $chiefComplaint);
        if ($hasRedFlags) {
            return EmergencyVisit::TRIAGE_RED;
        }

        // Orange: unstable vitals, severe pain, altered consciousness
        $hasOrangeFlags = $this->checkOrangeFlags($vitals, $chiefComplaint);
        if ($hasOrangeFlags) {
            return EmergencyVisit::TRIAGE_ORANGE;
        }

        // Yellow: stable but needs prompt attention
        $hasYellowFlags = $this->checkYellowFlags($vitals, $chiefComplaint);
        if ($hasYellowFlags) {
            return EmergencyVisit::TRIAGE_YELLOW;
        }

        // Green: stable, minor complaints
        return EmergencyVisit::TRIAGE_GREEN;
    }

    private function checkRedFlags(array $vitals, ?string $chiefComplaint): bool
    {
        // Cardiac/respiratory arrest
        if (isset($vitals['heart_rate']) && $vitals['heart_rate'] == 0) {
            return true;
        }
        if (isset($vitals['spo2']) && $vitals['spo2'] < 50) {
            return true;
        }
        if (isset($vitals['systolic_bp']) && $vitals['systolic_bp'] < 60) {
            return true;
        }
        if (isset($vitals['temperature']) && ($vitals['temperature'] > 41 || $vitals['temperature'] < 30)) {
            return true;
        }

        return false;
    }

    private function checkOrangeFlags(array $vitals, ?string $chiefComplaint): bool
    {
        if (isset($vitals['heart_rate']) && ($vitals['heart_rate'] > 130 || $vitals['heart_rate'] < 40)) {
            return true;
        }
        if (isset($vitals['spo2']) && $vitals['spo2'] < 88) {
            return true;
        }
        if (isset($vitals['systolic_bp']) && $vitals['systolic_bp'] < 90) {
            return true;
        }
        if (isset($vitals['respiratory_rate']) && ($vitals['respiratory_rate'] > 30 || $vitals['respiratory_rate'] < 8)) {
            return true;
        }

        return false;
    }

    private function checkYellowFlags(array $vitals, ?string $chiefComplaint): bool
    {
        if (isset($vitals['temperature']) && ($vitals['temperature'] > 39 || $vitals['temperature'] < 35)) {
            return true;
        }
        if (isset($vitals['systolic_bp']) && ($vitals['systolic_bp'] < 100 || $vitals['systolic_bp'] > 180)) {
            return true;
        }
        if (isset($vitals['heart_rate']) && ($vitals['heart_rate'] > 110 || $vitals['heart_rate'] < 50)) {
            return true;
        }

        return false;
    }
}
