<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\HrEmployee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HR-3 — Employee Document expiry & checklist helpers.
 *
 * Uses the generic Document infrastructure (polymorphic hr-employee) and
 * HR category flags (is_required, expiry_applicable) to derive:
 *  - expired documents (expiry_date past)
 *  - expiring soon (within N days)
 *  - missing required documents per employee
 */
class HrDocumentService
{
    public static function certificateCategoryId(int $instituteId): ?int
    {
        $cat = DocumentCategory::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'training-certificate')
                  ->orWhere('slug', 'training-certificate')
                  ->orWhere('name', 'like', '%Training%Certificate%');
            })->first();
        if ($cat) return $cat->id;
        // fallback to generic hr-employee category
        $fallback = DocumentCategory::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId))
            ->where('is_active', true)
            ->get()->firstWhere(fn (DocumentCategory $c) => $c->appliesTo('hr-employee'));
        return $fallback?->id;
    }

    /**
     * Required categories for hr-employee (active + is_required + hr-employee scoped).
     */
    public function requiredCategories(int $instituteId): Collection
    {
        return DocumentCategory::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId))
            ->where('is_active', true)
            ->where('is_required', true)
            ->get()
            ->filter(fn (DocumentCategory $c) => $c->appliesTo('hr-employee'))
            ->values();
    }

    /**
     * All active hr-employee categories.
     */
    public function activeCategories(int $instituteId): Collection
    {
        return DocumentCategory::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId))
            ->where('is_active', true)
            ->get()
            ->filter(fn (DocumentCategory $c) => $c->appliesTo('hr-employee'))
            ->values();
    }

    /**
     * Documents for an employee (query builder already tenant-scoped via Document global scopes).
     */
    public function documentsForEmployee(HrEmployee $employee): Collection
    {
        return Document::query()
            ->where('documentable_type', HrEmployee::class)
            ->where('documentable_id', $employee->id)
            ->where('status', Document::STATUS_ACTIVE)
            ->with(['category'])
            ->orderByDesc('id')
            ->get();
    }

    public function expiredDocuments(int $instituteId, ?int $branchId = null): Collection
    {
        $query = Document::query()
            ->where('institute_id', $instituteId)
            ->where('documentable_type', HrEmployee::class)
            ->where('status', Document::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->with(['category', 'documentable']);

        if ($branchId !== null) {
            // Respect branch isolation: only documents whose branch matches or employee branch matches
            $employeeIds = HrEmployee::query()->where('branch_id', $branchId)->pluck('id');
            $query->where(function ($q) use ($branchId, $employeeIds) {
                $q->where('branch_id', $branchId)
                  ->orWhereIn('documentable_id', $employeeIds);
            });
        }

        return $query->orderBy('expiry_date')->get();
    }

    public function expiringSoonDocuments(int $instituteId, int $days = 30, ?int $branchId = null): Collection
    {
        $query = Document::query()
            ->where('institute_id', $instituteId)
            ->where('documentable_type', HrEmployee::class)
            ->where('status', Document::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->with(['category', 'documentable']);

        if ($branchId !== null) {
            $employeeIds = HrEmployee::query()->where('branch_id', $branchId)->pluck('id');
            $query->where(function ($q) use ($branchId, $employeeIds) {
                $q->where('branch_id', $branchId)
                  ->orWhereIn('documentable_id', $employeeIds);
            });
        }

        return $query->orderBy('expiry_date')->get();
    }

    /**
     * Employees missing at least one required document type.
     * Returns collection of ['employee' => HrEmployee, 'missing' => Collection<DocumentCategory>]
     */
    public function missingRequiredDocuments(int $instituteId, ?int $branchId = null): Collection
    {
        $required = $this->requiredCategories($instituteId);
        if ($required->isEmpty()) {
            return collect();
        }

        $employeeQuery = HrEmployee::query()
            ->where('institute_id', $instituteId)
            ->with(['branch', 'department', 'designation']);

        if ($branchId !== null) {
            $employeeQuery->where('branch_id', $branchId);
        }

        $employees = $employeeQuery->get();
        $result = collect();

        foreach ($employees as $employee) {
            $existingCategoryIds = Document::query()
                ->where('institute_id', $instituteId)
                ->where('documentable_type', HrEmployee::class)
                ->where('documentable_id', $employee->id)
                ->where('status', Document::STATUS_ACTIVE)
                ->pluck('category_id')
                ->all();

            $missing = $required->filter(fn (DocumentCategory $cat) => ! in_array($cat->id, $existingCategoryIds, true));
            if ($missing->isNotEmpty()) {
                $result->push(['employee' => $employee, 'missing' => $missing->values()]);
            }
        }

        return $result;
    }

    /**
     * Dashboard aggregates for HR.
     */
    public function dashboardStats(int $instituteId, ?int $branchId = null): array
    {
        $expired = $this->expiredDocuments($instituteId, $branchId);
        $expiring = $this->expiringSoonDocuments($instituteId, 30, $branchId);
        $missing = $this->missingRequiredDocuments($instituteId, $branchId);

        return [
            'expired_count' => $expired->count(),
            'expiring_soon_count' => $expiring->count(),
            'missing_required_count' => $missing->count(),
            'expired' => $expired->take(10),
            'expiring_soon' => $expiring->take(10),
            'missing' => $missing->take(10),
        ];
    }
}
