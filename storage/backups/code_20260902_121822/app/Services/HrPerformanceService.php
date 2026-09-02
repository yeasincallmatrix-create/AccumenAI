<?php

namespace App\Services;

use App\Models\HrEmployee;
use App\Models\HrKpi;
use App\Models\HrPerformancePeriod;
use App\Models\HrPerformanceReview;
use App\Models\HrPerformanceReviewKpi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrPerformanceService
{
    public function __construct(private readonly HrAuditService $audit) {}

    public function createPeriod(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrPerformancePeriod
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);
        if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => 'End date must be after start date.']);
        }
        $overlap = HrPerformancePeriod::where('institute_id', $instituteId)
            ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                    ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                    ->orWhere(fn ($qq) => $qq->where('start_date', '<=', $data['start_date'])->where('end_date', '>=', $data['end_date']));
            })->where('status', '!=', 'closed')->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['period' => 'Period overlaps with existing active period.']);
        }

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId) {
            $period = HrPerformancePeriod::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'name' => trim($data['name']),
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => $data['status'] ?? 'active',
                'display_order' => (int) ($data['display_order'] ?? 0),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_performance_period_created', $period->id, null, $period->getAttributes());

            return $period;
        });
    }

    public function closePeriod(HrPerformancePeriod $period, int $instituteId, ?int $actorId): HrPerformancePeriod
    {
        abort_if((int) $period->institute_id !== (int) $instituteId, 404);
        abort_if($period->status === 'closed', 422, 'Already closed.');

        return DB::transaction(function () use ($period, $actorId, $instituteId) {
            $old = $period->getAttributes();
            $period->update(['status' => 'closed', 'updated_by' => $actorId]);
            $this->audit->record($instituteId, $actorId, 'hr_performance_period_closed', $period->id, $old, $period->fresh()->getAttributes());

            return $period->fresh();
        });
    }

    // KPI
    public function createKpi(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrKpi
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId) {
            $kpi = HrKpi::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'target' => $data['target'] ?? null,
                'measurement' => $data['measurement'] ?? null,
                'weight' => $data['weight'] ?? 1,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'display_order' => (int) ($data['display_order'] ?? 0),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_kpi_created', $kpi->id, null, $kpi->getAttributes());

            return $kpi;
        });
    }

    public function updateKpi(HrKpi $kpi, array $data, int $instituteId, ?int $actorId): HrKpi
    {
        abort_if((int) $kpi->institute_id !== (int) $instituteId, 404);
        $old = $kpi->getAttributes();

        return DB::transaction(function () use ($kpi, $data, $actorId, $instituteId, $old) {
            $kpi->update([
                'name' => isset($data['name']) ? trim($data['name']) : $kpi->name,
                'description' => $data['description'] ?? $kpi->description,
                'target' => $data['target'] ?? $kpi->target,
                'measurement' => $data['measurement'] ?? $kpi->measurement,
                'weight' => isset($data['weight']) ? $data['weight'] : $kpi->weight,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $kpi->is_active,
                'updated_by' => $actorId,
            ]);
            $this->audit->record($instituteId, $actorId, 'hr_kpi_updated', $kpi->id, $old, $kpi->fresh()->getAttributes());

            return $kpi->fresh();
        });
    }

    // Review
    public function createReview(array $data, int $instituteId, ?int $branchId, ?int $actorId): HrPerformanceReview
    {
        $this->assertBranchOfInstitute($branchId, $instituteId);
        $employee = HrEmployee::where('institute_id', $instituteId)->where('id', $data['employee_id'])->firstOrFail();
        $period = HrPerformancePeriod::where('institute_id', $instituteId)->where('id', $data['period_id'])->firstOrFail();
        abort_if($period->status === 'closed', 422, 'Period is closed.');

        // unique employee+period enforced by DB, but check early
        $exists = HrPerformanceReview::where('employee_id', $employee->id)->where('period_id', $period->id)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['employee_id' => 'Review already exists for this employee and period.']);
        }

        $reviewerId = $data['reviewer_id'] ?? $employee->reporting_manager_id;
        if ($reviewerId) {
            $this->assertEmployeeOfInstitute($reviewerId, $instituteId);
        }

        return DB::transaction(function () use ($data, $instituteId, $branchId, $actorId, $employee, $period, $reviewerId) {
            $review = HrPerformanceReview::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId ?? $employee->branch_id,
                'employee_id' => $employee->id,
                'reviewer_id' => $reviewerId,
                'period_id' => $period->id,
                'review_date' => $data['review_date'] ?? now()->toDateString(),
                'status' => $data['status'] ?? 'draft',
                'comments' => $data['comments'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            // Attach KPIs if provided
            if (! empty($data['kpis']) && is_array($data['kpis'])) {
                foreach ($data['kpis'] as $kpiData) {
                    $kpiId = $kpiData['kpi_id'] ?? null;
                    if ($kpiId) {
                        $this->assertKpiOfInstitute($kpiId, $instituteId);
                    }
                    HrPerformanceReviewKpi::create([
                        'review_id' => $review->id,
                        'kpi_id' => $kpiId,
                        'name' => $kpiData['name'] ?? ($kpiId ? HrKpi::find($kpiId)->name : 'KPI'),
                        'description' => $kpiData['description'] ?? null,
                        'target' => $kpiData['target'] ?? null,
                        'measurement' => $kpiData['measurement'] ?? null,
                        'weight' => $kpiData['weight'] ?? 1,
                        'score' => $kpiData['score'] ?? null,
                        'max_score' => $kpiData['max_score'] ?? 100,
                        'comments' => $kpiData['comments'] ?? null,
                    ]);
                }
                $this->recalculateOverall($review);
            }
            $this->audit->record($instituteId, $actorId, 'hr_performance_review_created', $review->id, null, $review->getAttributes());

            return $review->fresh();
        });
    }

    public function evaluate(HrPerformanceReview $review, array $data, int $instituteId, ?int $actorId, string $role = 'manager'): HrPerformanceReview
    {
        abort_if((int) $review->institute_id !== (int) $instituteId, 404);
        abort_if($review->status === 'approved' || $review->status === 'rejected', 422, 'Already finalized.');

        return DB::transaction(function () use ($review, $data, $instituteId, $actorId, $role) {
            $old = $review->getAttributes();

            // Update KPI scores if provided
            if (! empty($data['kpi_scores']) && is_array($data['kpi_scores'])) {
                foreach ($data['kpi_scores'] as $rkpiId => $scoreData) {
                    $rkpi = HrPerformanceReviewKpi::where('review_id', $review->id)->where('id', $rkpiId)->first();
                    if ($rkpi) {
                        $rkpi->update([
                            'score' => $scoreData['score'] ?? $rkpi->score,
                            'comments' => $scoreData['comments'] ?? $rkpi->comments,
                        ]);
                    }
                }
            }
            if (! empty($data['kpis']) && is_array($data['kpis'])) {
                // Add new KPI rows
                foreach ($data['kpis'] as $kpiData) {
                    HrPerformanceReviewKpi::create([
                        'review_id' => $review->id,
                        'kpi_id' => $kpiData['kpi_id'] ?? null,
                        'name' => $kpiData['name'],
                        'target' => $kpiData['target'] ?? null,
                        'measurement' => $kpiData['measurement'] ?? null,
                        'weight' => $kpiData['weight'] ?? 1,
                        'score' => $kpiData['score'] ?? null,
                        'max_score' => $kpiData['max_score'] ?? 100,
                        'comments' => $kpiData['comments'] ?? null,
                    ]);
                }
            }
            $this->recalculateOverall($review->fresh());

            $updates = ['updated_by' => $actorId];
            if (isset($data['overall_score'])) {
                $updates['overall_score'] = $data['overall_score'];
            } elseif ($review->fresh()->overall_score !== null) {
                $updates['overall_score'] = $review->fresh()->overall_score;
            }

            if ($role === 'self') {
                $updates['self_score'] = $data['self_score'] ?? $data['overall_score'] ?? $review->self_score;
                $updates['self_comments'] = $data['self_comments'] ?? $data['comments'] ?? $review->self_comments;
                $updates['status'] = $data['status'] ?? 'submitted';
            } elseif ($role === 'manager') {
                $updates['manager_score'] = $data['manager_score'] ?? $data['overall_score'] ?? $review->manager_score;
                $updates['manager_comments'] = $data['manager_comments'] ?? $data['comments'] ?? $review->manager_comments;
                $updates['status'] = $data['status'] ?? 'manager_review';
            } elseif ($role === 'hr') {
                $updates['hr_score'] = $data['hr_score'] ?? $data['overall_score'] ?? $review->hr_score;
                $updates['hr_comments'] = $data['hr_comments'] ?? $data['comments'] ?? $review->hr_comments;
                $updates['status'] = $data['status'] ?? 'hr_review';
                // actions
                $updates['promotion_recommendation'] = $data['promotion_recommendation'] ?? $review->promotion_recommendation;
                $updates['training_recommendation'] = $data['training_recommendation'] ?? $review->training_recommendation;
                $updates['improvement_plan'] = $data['improvement_plan'] ?? $review->improvement_plan;
                $updates['recognition'] = $data['recognition'] ?? $review->recognition;
            } else {
                // generic
                $updates['comments'] = $data['comments'] ?? $review->comments;
                if (isset($data['status'])) {
                    $updates['status'] = $data['status'];
                }
            }
            if (isset($data['overall_score'])) {
                $updates['overall_score'] = $data['overall_score'];
            }
            if (isset($data['status'])) {
                $updates['status'] = $data['status'];
            }

            $review->update($updates);
            // Do NOT automatically change salary/designation

            $this->audit->record($instituteId, $actorId, 'hr_performance_review_updated', $review->id, $old, $review->fresh()->getAttributes());

            return $review->fresh();
        });
    }

    public function approve(HrPerformanceReview $review, int $instituteId, ?int $actorId, string $decision = 'approved'): HrPerformanceReview
    {
        abort_if((int) $review->institute_id !== (int) $instituteId, 404);
        abort_if(! in_array($review->status, ['pending', 'submitted', 'manager_review', 'hr_review'], true), 422, 'Not in reviewable state.');

        return DB::transaction(function () use ($review, $instituteId, $actorId, $decision) {
            $old = $review->getAttributes();
            $review->update(['status' => $decision, 'updated_by' => $actorId]);
            $this->audit->record($instituteId, $actorId, 'hr_performance_review_'.$decision, $review->id, $old, $review->fresh()->getAttributes());

            return $review->fresh();
        });
    }

    private function recalculateOverall(HrPerformanceReview $review): void
    {
        $kpis = HrPerformanceReviewKpi::where('review_id', $review->id)->get();
        if ($kpis->isEmpty()) {
            return;
        }
        $totalWeight = $kpis->sum('weight');
        if ($totalWeight == 0) {
            return;
        }
        $weightedScore = 0;
        foreach ($kpis as $k) {
            if ($k->score !== null) {
                $weightedScore += ((float) $k->score / (float) ($k->max_score ?: 100) * 100) * (float) $k->weight;
            }
        }
        $overall = $totalWeight > 0 ? $weightedScore / (float) $totalWeight : null;
        if ($overall !== null) {
            $review->update(['overall_score' => round($overall, 2)]);
        }
    }

    private function assertBranchOfInstitute(?int $branchId, int $instituteId): void
    {
        if ($branchId === null) {
            return;
        }
        $exists = DB::table('branches')->where('id', $branchId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Branch does not belong to this institute.');
    }

    private function assertEmployeeOfInstitute(int $employeeId, int $instituteId): void
    {
        $exists = DB::table('hr_employees')->where('id', $employeeId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'Employee does not belong to this institute.');
    }

    private function assertKpiOfInstitute(int $kpiId, int $instituteId): void
    {
        $exists = DB::table('hr_kpis')->where('id', $kpiId)->where('institute_id', $instituteId)->exists();
        abort_if(! $exists, 422, 'KPI does not belong to this institute.');
    }
}
