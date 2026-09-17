<?php

namespace App\Services\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DentalChart;
use App\Models\Medical\DentalProcedure;
use App\Models\Medical\DentalTreatmentPlan;
use Illuminate\Support\Facades\DB;

class DentalService
{
    public function getOrCreateChart(int $instituteId, int $patientId, ?int $dentistId = null): DentalChart
    {
        return DentalChart::firstOrCreate(
            ['institute_id' => $instituteId, 'patient_id' => $patientId],
            [
                'dentist_id' => $dentistId,
                'tooth_conditions' => [],
                'total_teeth' => 32,
            ]
        );
    }

    public function updateTooth(DentalChart $chart, string $toothNumber, string $condition, ?string $notes = null): void
    {
        DB::transaction(function () use ($chart, $toothNumber, $condition, $notes) {
            $old = ClinicalAuditLog::snapshot($chart);
            $conditions = $chart->tooth_conditions ?? [];
            $conditions[$toothNumber] = [
                'condition' => $condition,
                'notes' => $notes,
                'updated_at' => now()->toIso8601String(),
            ];
            $chart->update([
                'tooth_conditions' => $conditions,
                'last_assessed_at' => now(),
            ]);
            $chart->recalculateCounts();
            [$oldVals, $newVals] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($chart->fresh()));
            if ($oldVals || $newVals) {
                ClinicalAuditLog::record($chart, 'tooth_updated', ['old' => $oldVals, 'new' => $newVals]);
            }
        });
    }

    public static function quadrantFromTooth(string $toothNumber): string
    {
        $firstDigit = (int) substr($toothNumber, 0, 1);
        return match ($firstDigit) {
            1 => 'upper_right',
            2 => 'upper_left',
            3 => 'lower_left',
            4 => 'lower_right',
            default => 'unknown',
        };
    }

    public function completeStep(DentalTreatmentPlan $plan, int $stepIndex): void
    {
        DB::transaction(function () use ($plan, $stepIndex) {
            $old = ClinicalAuditLog::snapshot($plan);
            $steps = $plan->planned_steps ?? [];
            if (! isset($steps[$stepIndex])) {
                return;
            }

            $steps[$stepIndex]['status'] = 'completed';
            $steps[$stepIndex]['completed_at'] = now()->toIso8601String();

            $completedCount = collect($steps)->where('status', 'completed')->count();

            $plan->update([
                'planned_steps' => $steps,
                'completed_steps' => $completedCount,
                'status' => $completedCount >= $plan->total_steps ? 'completed' : $plan->status,
            ]);

            [$oldVals, $newVals] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($plan->fresh()));
            if ($oldVals || $newVals) {
                ClinicalAuditLog::record($plan, 'step_completed', ['old' => $oldVals, 'new' => $newVals]);
            }
        });
    }

    public function discontinuePlan(DentalTreatmentPlan $plan, string $reason): void
    {
        DB::transaction(function () use ($plan, $reason) {
            $old = ClinicalAuditLog::snapshot($plan);
            $plan->update([
                'status' => 'discontinued',
                'discontinue_reason' => $reason,
            ]);
            [$oldVals, $newVals] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($plan->fresh()));
            if ($oldVals || $newVals) {
                ClinicalAuditLog::record($plan, 'discontinued', ['old' => $oldVals, 'new' => $newVals, 'reason' => $reason]);
            }
        });
    }

    public function todayProcedures(int $instituteId, ?int $branchId = null)
    {
        $query = DentalProcedure::where('institute_id', $instituteId)
            ->whereDate('performed_at', today())
            ->with(['patient', 'dentist']);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->orderBy('performed_at')->get();
    }

    public function activePlansCount(int $instituteId, ?int $branchId = null): int
    {
        $query = DentalTreatmentPlan::where('institute_id', $instituteId)->where('status', 'active');
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->count();
    }

    public function upcomingFollowUps(int $instituteId, ?int $branchId = null, int $days = 7)
    {
        $query = DentalProcedure::where('institute_id', $instituteId)
            ->where('follow_up_date', '>=', today())
            ->where('follow_up_date', '<=', today()->addDays($days))
            ->where('status', '!=', 'followed_up')
            ->whereNotNull('follow_up_date')
            ->with(['patient', 'dentist']);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->orderBy('follow_up_date')->get();
    }
}
