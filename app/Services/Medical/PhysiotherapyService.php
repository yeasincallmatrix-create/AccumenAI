<?php

namespace App\Services\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\PhysiotherapyPlan;
use App\Models\Medical\PhysiotherapySession;
use Illuminate\Support\Facades\DB;

class PhysiotherapyService
{
    public function nextSessionOrder(PhysiotherapyPlan $plan): int
    {
        return (int) $plan->sessions()->max('session_order') + 1;
    }

    public function averagePainReduction(PhysiotherapyPlan $plan): ?float
    {
        $sessions = $plan->sessions()
            ->whereNotNull('pain_score_before')
            ->whereNotNull('pain_score_after')
            ->get();
        if ($sessions->isEmpty()) return null;
        $totalReduction = $sessions->sum(fn($s) => $s->pain_score_before - $s->pain_score_after);
        return round($totalReduction / $sessions->count(), 2);
    }

    public function progressPercent(PhysiotherapyPlan $plan): int
    {
        if ($plan->sessions_planned <= 0) return 0;
        return min(100, (int) round(($plan->sessions_completed / $plan->sessions_planned) * 100));
    }

    public function markAttended(PhysiotherapySession $session, array $data): void
    {
        DB::transaction(function () use ($session, $data) {
            $old = ClinicalAuditLog::snapshot($session);
            $session->update([
                'status' => 'attended',
                'attended_at' => now(),
                'pain_score_before' => $data['pain_score_before'] ?? null,
                'pain_score_after' => $data['pain_score_after'] ?? null,
                'assessment_notes' => $data['assessment_notes'] ?? null,
                'treatment_given' => $data['treatment_given'] ?? null,
                'exercises_done' => $data['exercises_done'] ?? null,
                'progress_notes' => $data['progress_notes'] ?? null,
                'next_session_focus' => $data['next_session_focus'] ?? null,
            ]);
            [$oldVals, $newVals] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($session->fresh()));
            if ($oldVals || $newVals) {
                ClinicalAuditLog::record($session, 'attended', ['old' => $oldVals, 'new' => $newVals]);
            }

            $plan = $session->plan;
            $planOld = ClinicalAuditLog::snapshot($plan);
            $plan->increment('sessions_completed');
            $plan->refresh();

            if ($plan->sessions_completed >= $plan->sessions_planned) {
                $plan->update(['status' => 'completed']);
            }
            [$pOld, $pNew] = ClinicalAuditLog::diff($planOld, ClinicalAuditLog::snapshot($plan->fresh()));
            if ($pOld || $pNew) {
                ClinicalAuditLog::record($plan, 'progress_updated', ['old' => $pOld, 'new' => $pNew]);
            }
        });
    }

    public function markNoShow(PhysiotherapySession $session): void
    {
        DB::transaction(function () use ($session) {
            $old = ClinicalAuditLog::snapshot($session);
            $session->update(['status' => 'no_show']);
            [$o, $n] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($session->fresh()));
            if ($o || $n) {
                ClinicalAuditLog::record($session, 'no_show', ['old' => $o, 'new' => $n]);
            }
        });
    }

    public function completePlan(PhysiotherapyPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $old = ClinicalAuditLog::snapshot($plan);
            $plan->update(['status' => 'completed']);
            [$o, $n] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($plan->fresh()));
            if ($o || $n) {
                ClinicalAuditLog::record($plan, 'completed', ['old' => $o, 'new' => $n]);
            }
        });
    }

    public function discontinuePlan(PhysiotherapyPlan $plan, string $reason): void
    {
        DB::transaction(function () use ($plan, $reason) {
            $old = ClinicalAuditLog::snapshot($plan);
            $plan->update([
                'status' => 'discontinued',
                'discontinue_reason' => $reason,
            ]);
            [$o, $n] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($plan->fresh()));
            if ($o || $n) {
                ClinicalAuditLog::record($plan, 'discontinued', ['old' => $o, 'new' => $n, 'reason' => $reason]);
            }
        });
    }

    public function todaySessions(int $instituteId, ?int $branchId = null)
    {
        $query = PhysiotherapySession::where('institute_id', $instituteId)
            ->whereDate('session_date', today())
            ->whereIn('status', ['scheduled', 'attended'])
            ->with(['plan.patient', 'therapist']);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->orderBy('session_date')->get();
    }

    public function activePlansCount(int $instituteId, ?int $branchId = null): int
    {
        $query = PhysiotherapyPlan::where('institute_id', $instituteId)->where('status', 'active');
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->count();
    }

    public function todaysAttendedCount(int $instituteId, ?int $branchId = null): int
    {
        $query = PhysiotherapySession::where('institute_id', $instituteId)
            ->whereDate('session_date', today())
            ->where('status', 'attended');
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->count();
    }
}
