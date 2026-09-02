<?php

namespace App\Services\Education;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Course;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\InstituteCourse;
use App\Models\Training\Enrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Education fee structures (Step 37) — CRUD on structures + their items and
 * the resolution of the most specific active structure for an enrollment
 * (batch > course > branch > institute-wide, academic-year aware).
 */
class FeeStructureService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): FeeStructure
    {
        $data = $this->validate($instituteId, $branchId, $data);
        $items = $data['items'];

        return DB::transaction(function () use ($instituteId, $branchId, $data, $items, $actorId) {
            $structure = FeeStructure::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'course_id' => $data['course_id'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'name' => $data['name'],
                'installments_count' => (int) ($data['installments_count'] ?? 1),
                'installments_interval_days' => (int) ($data['installments_interval_days'] ?? 30),
                'status' => $data['status'] ?? FeeStructure::STATUS_DRAFT,
                'billing_frequency' => $data['billing_frequency'] ?? FeeStructure::FREQ_MONTHLY,
                'auto_generate_monthly' => $data['auto_generate_monthly'] ?? false,
                'created_by' => $actorId,
            ]);

            foreach ($items as $item) {
                $structure->items()->create([
                    'fee_head_id' => (int) $item['fee_head_id'],
                    'amount' => round((float) $item['amount'], 2),
                    'is_optional' => (bool) ($item['is_optional'] ?? false),
                ]);
            }

            return $structure->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FeeStructure $structure, array $data, ?int $actorId = null): FeeStructure
    {
        $data = $this->validate($structure->institute_id, $structure->branch_id, $data, $structure->id);
        $items = $data['items'];

        return DB::transaction(function () use ($structure, $data, $items, $actorId) {
            $structure->forceFill([
                'course_id' => $data['course_id'] ?? null,
                'batch_id' => $data['batch_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'name' => $data['name'],
                'installments_count' => (int) ($data['installments_count'] ?? 1),
                'installments_interval_days' => (int) ($data['installments_interval_days'] ?? 30),
                'status' => $data['status'] ?? FeeStructure::STATUS_DRAFT,
                'billing_frequency' => $data['billing_frequency'] ?? FeeStructure::FREQ_MONTHLY,
                'auto_generate_monthly' => $data['auto_generate_monthly'] ?? false,
                'updated_by' => $actorId,
            ])->save();

            $structure->items()->delete();
            foreach ($items as $item) {
                $structure->items()->create([
                    'fee_head_id' => (int) $item['fee_head_id'],
                    'amount' => round((float) $item['amount'], 2),
                    'is_optional' => (bool) ($item['is_optional'] ?? false),
                ]);
            }

            return $structure->fresh()->load('items');
        });
    }

    public function destroy(FeeStructure $structure): void
    {
        $structure->delete();
    }

    /**
     * Resolve the most specific active fee structure for an enrollment.
     * Structures that explicitly target a different batch/course/branch/year
     * are excluded; the remainder is ranked by specificity.
     */
    public function resolveForEnrollment(Enrollment $enrollment, ?int $branchId = null, ?int $actorId = null): ?FeeStructure
    {
        $instituteId = (int) $enrollment->institute_id;
        $student = $enrollment->student;
        $batch = $enrollment->batch;
        $batchBranchId = $batch !== null ? $batch->branch_id : null;

        $query = FeeStructure::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('status', FeeStructure::STATUS_ACTIVE)
            ->with('items.feeHead');

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }

        $candidates = $query->get();

        $currentYearId = AcademicYear::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('is_current', true)
            ->value('id');

        $studentYearId = $student !== null ? $student->applied_academic_year_id : null;

        $best = null;
        $bestScore = -1;

        foreach ($candidates as $structure) {
            if ($structure->batch_id !== null && (int) $structure->batch_id !== (int) $enrollment->batch_id) {
                continue;
            }

            if ($structure->course_id !== null && (int) $structure->course_id !== (int) ($enrollment->batch?->course_id ?? 0)) {
                continue;
            }

            if ($structure->branch_id !== null && $batchBranchId !== null
                && (int) $structure->branch_id !== (int) $batchBranchId) {
                continue;
            }

            if ($structure->branch_id !== null && $batchBranchId === null) {
                continue;
            }

            if ($structure->academic_year_id !== null) {
                $yearMatches = $studentYearId !== null
                    && (int) $structure->academic_year_id === (int) $studentYearId;

                $currentMatches = $currentYearId !== null
                    && (int) $structure->academic_year_id === (int) $currentYearId;

                if (! $yearMatches && ! $currentMatches) {
                    continue;
                }
            }

            $score = 0;

            if ($structure->batch_id !== null) {
                $score += 4;
            }

            if ($structure->course_id !== null) {
                $score += 2;
            }

            if ($structure->branch_id !== null && $batchBranchId !== null
                && (int) $structure->branch_id === (int) $batchBranchId) {
                $score += 1;
            }

            if ($structure->academic_year_id !== null) {
                $score += $studentYearId !== null && (int) $structure->academic_year_id === (int) $studentYearId ? 3 : 1;
            } elseif ($studentYearId !== null) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $structure;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data, ?int $ignoreId = null): array
    {
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:150'],
            'course_id' => ['nullable', 'integer'],
            'batch_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:12'],
            'installments_interval_days' => ['required', 'integer', 'min:0', 'max:730'],
            'status' => ['required', 'in:draft,active,archived'],
            'billing_frequency' => ['nullable', Rule::in(FeeStructure::BILLING_FREQUENCIES)],
            'auto_generate_monthly' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_head_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.is_optional' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        if (filled($data['course_id'] ?? null)) {
            $assigned = InstituteCourse::query()
                ->where('institute_id', $instituteId)
                ->where('course_id', (int) $data['course_id'])
                ->exists();

            if (! $assigned) {
                throw ValidationException::withMessages([
                    'course_id' => 'The selected course is not assigned to this institute.',
                ]);
            }
        }

        if (filled($data['batch_id'] ?? null)) {
            $batch = Batch::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->find((int) $data['batch_id']);

            if ($batch === null) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The selected batch does not exist in this institute.',
                ]);
            }

            if ($batch->branch_id !== null && $branchId !== null && (int) $batch->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The selected batch belongs to a different branch.',
                ]);
            }

            if (filled($data['course_id'] ?? null) && (int) $batch->course_id !== (int) $data['course_id']) {
                throw ValidationException::withMessages([
                    'batch_id' => 'The selected batch does not belong to the selected course.',
                ]);
            }
        }

        if (filled($data['academic_year_id'] ?? null)) {
            $year = AcademicYear::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->find((int) $data['academic_year_id']);

            if ($year === null) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'The selected academic year does not exist in this institute.',
                ]);
            }
        }

        $headIds = array_column($data['items'], 'fee_head_id');
        if (count($headIds) !== count(array_unique($headIds))) {
            throw ValidationException::withMessages([
                'items' => 'Each fee head may appear only once in a fee structure.',
            ]);
        }

        $heads = FeeHead::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $headIds)
            ->get()
            ->keyBy('id');

        foreach ($data['items'] as $index => $item) {
            $head = $heads[(int) $item['fee_head_id']] ?? null;

            if ($head === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.fee_head_id" => 'The selected fee head does not exist in this institute.',
                ]);
            }

            if ($head->branch_id !== null && $branchId !== null && (int) $head->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    "items.{$index}.fee_head_id" => 'The selected fee head belongs to a different branch.',
                ]);
            }
        }

        if ($ignoreId === null) {
            $duplicate = FeeStructure::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('course_id', $data['course_id'] ?? null)
                ->where('batch_id', $data['batch_id'] ?? null)
                ->where('academic_year_id', $data['academic_year_id'] ?? null)
                ->where('name', $data['name'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'A fee structure with this name and target already exists.',
                ]);
            }
        }

        return $data;
    }
}
