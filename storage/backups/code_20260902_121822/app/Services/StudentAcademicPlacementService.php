<?php

namespace App\Services;

use App\Models\AcademicGroup;
use App\Models\AcademicYear;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates student academic placements + subject selection persistence.
 *
 * The centralized curriculum source of truth stays in AcademicSubjectService;
 * this service is the ONLY place that persists student_subject_selections and
 * it reuses StudentSubjectSelectionValidator (never a second validator).
 *
 * Mandatory subjects are auto-included by the validator whenever a placement
 * is created/updated, and only selected subjects are stored — unselected
 * optional/elective subjects are never inserted.
 */
class StudentAcademicPlacementService
{
    public function __construct(
        private readonly AcademicSubjectService $subjects,
        private readonly StudentSubjectSelectionValidator $validator
    ) {}

    /**
     * Selection-shaped curriculum for the UI (JSON for the AJAX endpoint and
     * the server-rendered subject grid).
     *
     * @return array{
     *     mandatory: array<int, array<string, mixed>>,
     *     groups: array<int, array<string, mixed>>,
     *     ungrouped: array<int, array<string, mixed>>,
     *     config_errors: array<int, string>,
     *     class_id: int,
     *     group_id: int|null,
     * }
     */
    public function selectionData(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup): array
    {
        $selection = $this->subjects->resolveForSelection($institute, $classGrade, $academicGroup);

        return [
            'mandatory' => $this->nodes($selection['mandatory']),
            'groups' => array_map(fn (array $entry) => [
                'group' => $entry['group'],
                'rules' => $entry['rules'],
                'members' => $this->nodes($entry['members']),
            ], $selection['groups']),
            'ungrouped' => $this->nodes($selection['ungrouped']),
            'config_errors' => $selection['config_errors'],
            'class_id' => $classGrade->id,
            'group_id' => $academicGroup?->id,
        ];
    }

    /**
     * Create a placement and persist the valid subject selection.
     *
     * @param  array<int, int|string>  $selectedIds
     */
    public function storePlacement(
        Institute $institute,
        Student $student,
        AcademicYear $year,
        ClassGrade $classGrade,
        ?AcademicGroup $academicGroup,
        array $selectedIds,
        string $status = StudentAcademicPlacement::STATUS_ACTIVE,
        ?string $notes = null
    ): StudentAcademicPlacement {
        $this->assertTenantMatch($institute, $student, $year);
        $this->assertAcademicYearActive($year);
        $this->assertBranchContext($student, $year);
        $this->requireClassWithinInstitute($institute, $classGrade, $academicGroup);

        return DB::transaction(function () use ($institute, $student, $year, $classGrade, $academicGroup, $status, $notes, $selectedIds) {
            // Concurrency-safe duplicate guard: lock the student+year slot
            $already = StudentAcademicPlacement::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $year->id)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'This student already has an academic placement for the selected year.',
                ]);
            }

            $selection = $this->validatedSelection($institute, $classGrade, $academicGroup, $selectedIds);
            $snapshot = $this->buildStructureSnapshot($classGrade, $academicGroup);

            try {
                $payload = [
                    'institute_id' => $institute->id,
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'class_grade_id' => $classGrade->id,
                    'academic_group_id' => $academicGroup?->id,
                    'status' => $status,
                    'notes' => $notes,
                ];

                // C3 — Structure Versioning: persist frozen snapshot (idempotent if columns missing).
                try {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('student_academic_placements', 'structure_snapshot')) {
                        $payload['structure_snapshot'] = $snapshot;
                        $payload['structure_version'] = 1;
                        $payload['class_grade_name_snapshot'] = $classGrade->name;
                        $payload['academic_group_name_snapshot'] = $academicGroup?->name;
                    }
                } catch (\Throwable $e) {}

                $placement = StudentAcademicPlacement::create($payload);
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique violation race fallback
                if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() === '23000') {
                    throw ValidationException::withMessages([
                        'academic_year_id' => 'This student already has an academic placement for the selected year.',
                    ]);
                }
                throw $e;
            }

            $this->syncSelections($placement, $selection);

            // P2-1 — Audit log for placement creation (inside same transaction, rollback-safe).
            $this->auditPlacement('placement_created', $placement, null, [
                'student_id' => $placement->student_id,
                'class_grade_id' => $placement->class_grade_id,
                'academic_year_id' => $placement->academic_year_id,
                'academic_group_id' => $placement->academic_group_id,
                'status' => $placement->status,
                'notes' => $placement->notes,
            ]);

            return $placement;
        });
    }

    /**
     * Update a placement (class/group/status/notes + replacement of the
     * subject selection). Mandatory subjects are re-evaluated against the
     * current effective curriculum on every save.
     *
     * @param  array<int, int|string>  $selectedIds
     */
    public function updatePlacement(
        Institute $institute,
        StudentAcademicPlacement $placement,
        AcademicYear $year,
        ClassGrade $classGrade,
        ?AcademicGroup $academicGroup,
        array $selectedIds,
        string $status = StudentAcademicPlacement::STATUS_ACTIVE,
        ?string $notes = null
    ): StudentAcademicPlacement {
        $this->assertTenantMatch($institute, $placement->student ?? $placement->student()->withTrashed()->first(), $year);
        $this->assertAcademicYearActive($year);
        $this->assertNotFrozen($placement);
        // P2-4: branch context must match the target student/year's branch (if scoped)
        $targetStudent = $placement->student ?? $placement->student()->withTrashed()->first();
        if ($targetStudent !== null) {
            $this->assertBranchContext($targetStudent, $year);
        }
        $this->requireClassWithinInstitute($institute, $classGrade, $academicGroup);

        // Duplicate-year guard on update (changing year to an already-occupied year)
        $duplicateYear = StudentAcademicPlacement::query()
            ->where('student_id', $placement->student_id)
            ->where('academic_year_id', $year->id)
            ->where('id', '!=', $placement->id)
            ->exists();
        if ($duplicateYear) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'This student already has an academic placement for the selected year.',
            ]);
        }

        $selection = $this->validatedSelection($institute, $classGrade, $academicGroup, $selectedIds);

        return DB::transaction(function () use ($placement, $year, $classGrade, $academicGroup, $status, $notes, $selection) {
            // Lock placement row for concurrent updates
            StudentAcademicPlacement::query()->whereKey($placement->id)->lockForUpdate()->first();

            $snapshot = $this->buildStructureSnapshot($classGrade, $academicGroup);
            $updatePayload = [
                'academic_year_id' => $year->id,
                'class_grade_id' => $classGrade->id,
                'academic_group_id' => $academicGroup?->id,
                'status' => $status,
                'notes' => $notes,
            ];
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('student_academic_placements', 'structure_snapshot')) {
                    $updatePayload['structure_snapshot'] = $snapshot;
                    $updatePayload['structure_version'] = (int) ($placement->structure_version ?? 1) + 1;
                    $updatePayload['class_grade_name_snapshot'] = $classGrade->name;
                    $updatePayload['academic_group_name_snapshot'] = $academicGroup?->name;
                }
            } catch (\Throwable $e) {}

            $oldValues = $placement->getOriginal();
            $placement->update($updatePayload);

            $placement->selections()->delete();
            $this->syncSelections($placement, $selection);

            // P2-1 — Audit log for placement update with dirty diff (old vs new).
            $newValues = $placement->refresh()->toArray();
            $dirty = array_intersect_key($newValues, array_flip(['academic_year_id','class_grade_id','academic_group_id','status','notes']));
            $oldDirty = array_intersect_key($oldValues, array_flip(['academic_year_id','class_grade_id','academic_group_id','status','notes']));
            $this->auditPlacement('placement_updated', $placement, $oldDirty, $dirty);

            return $placement->refresh();
        });
    }

    /**
     * P3-1 — Archive (soft-delete) a placement for withdrawn/transferred students.
     *
     * Sets status = 'archived' and soft-deletes (deleted_at = now()). Historical
     * marks/results snapshots remain untouched — no cascade delete.
     *
     * Blocks when the placement has active (not yet frozen) marks. Frozen marks
     * (those already snapshotted in AcademicFinalResultStudent/Row) are allowed
     * to be archived because the historical snapshot preserves the data.
     */
    public function archivePlacement(StudentAcademicPlacement $placement, ?int $actorId = null): StudentAcademicPlacement
    {
        if ($placement->trashed() || $placement->status === StudentAcademicPlacement::STATUS_ARCHIVED) {
            throw ValidationException::withMessages(['placement' => 'This placement is already archived.']);
        }

        $hasMarks = \App\Models\AcademicStudentMark::query()->where('academic_placement_id', $placement->id)->exists();
        $hasFinalSnapshot = \App\Models\AcademicFinalResultStudent::query()->where('placement_id', $placement->id)->exists()
            || \App\Models\AcademicFinalResultRow::query()->where('placement_id', $placement->id)->exists();

        // Block if there are active marks that have not been frozen into a final-result snapshot
        if ($hasMarks && ! $hasFinalSnapshot) {
            throw ValidationException::withMessages(['placement' => 'Cannot archive placement with active marks not yet frozen/finalized. Finalize or clear marks first.']);
        }

        return DB::transaction(function () use ($placement, $actorId) {
            StudentAcademicPlacement::query()->whereKey($placement->id)->lockForUpdate()->first();

            $oldValues = ['status' => $placement->status, 'deleted_at' => $placement->deleted_at];

            $placement->status = StudentAcademicPlacement::STATUS_ARCHIVED;
            $placement->save();
            $placement->delete();

            $this->auditPlacement('placement_archived', $placement, $oldValues, [
                'status' => StudentAcademicPlacement::STATUS_ARCHIVED,
                'deleted_at' => $placement->deleted_at?->toDateTimeString() ?? now()->toDateTimeString(),
            ]);

            // Also log via direct audit table if actorId provided
            try {
                if ($actorId !== null) {
                    \App\Models\AuditLog::create([
                        'institute_id' => $placement->institute_id,
                        'user_type' => 'institute_user',
                        'user_id' => $actorId,
                        'action' => 'placement_archived',
                        'module' => 'education',
                        'record_id' => $placement->id,
                        'old_values' => json_encode($oldValues),
                        'new_values' => json_encode(['status' => StudentAcademicPlacement::STATUS_ARCHIVED, 'deleted_at' => now()->toDateTimeString()]),
                        'ip_address' => request()->ip(),
                        'user_agent' => substr((string) request()->userAgent(), 0, 255),
                        'created_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {}

            return $placement;
        });
    }

    private function assertTenantMatch(Institute $institute, ?Student $student, AcademicYear $year): void
    {
        if ($student !== null && (int) $student->institute_id !== (int) $institute->id) {
            throw ValidationException::withMessages(['student_id' => 'The selected student does not belong to this institute.']);
        }
        if ((int) $year->institute_id !== (int) $institute->id) {
            throw ValidationException::withMessages(['academic_year_id' => 'The selected academic year does not belong to this institute.']);
        }
    }

    private function assertAcademicYearActive(AcademicYear $year): void
    {
        if (! $year->status) {
            throw ValidationException::withMessages(['academic_year_id' => 'The selected academic year is closed and cannot accept new placements.']);
        }
    }

    private function assertNotFrozen(StudentAcademicPlacement $placement): void
    {
        $hasMarks = \App\Models\AcademicStudentMark::query()->where('academic_placement_id', $placement->id)->exists();
        $hasFinalRows = \App\Models\AcademicFinalResultRow::query()->where('placement_id', $placement->id)->exists();
        $hasFinalStudents = \App\Models\AcademicFinalResultStudent::query()->where('placement_id', $placement->id)->exists();
        if ($hasMarks || $hasFinalRows || $hasFinalStudents) {
            throw ValidationException::withMessages([
                'placement' => 'This placement has historical marks or finalized results and cannot be modified. Create a new placement for changes.',
            ]);
        }
    }

    /**
     * P2-4 — Branch Context Double-Scope guard: when a branch is active in
     * BranchContext, the student (and year, if it were branch-scoped) must
     * belong to that branch. When no branch context is set, any branch is allowed.
     */
    private function assertBranchContext(Student $student, AcademicYear $year): void
    {
        $branchId = BranchContext::id();
        if ($branchId !== null && (int) $student->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages([
                'student_id' => 'Student does not belong to the current branch context.',
            ]);
        }

        // AcademicYear is institute-scoped only (no branch_id column); if a future
        // branch_id column is added, enforce it here. Check dynamically to stay
        // backward compatible with existing schema.
        if ($branchId !== null && \Illuminate\Support\Facades\Schema::hasColumn('academic_years', 'branch_id')) {
            $yearBranchId = $year->getAttribute('branch_id');
            if ($yearBranchId !== null && (int) $yearBranchId !== (int) $branchId) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Academic year does not belong to the current branch context.',
                ]);
            }
        }
    }

    /**
     * C1 — Placement Country Leak guard: ensure the target class belongs to
     * the institute's effective country/structure, and that the optional
     * group belongs to that class. Prevents cross-country placement (e.g. BD
     * student placed into a US class) which would corrupt curriculum resolution.
     */
    private function requireClassWithinInstitute(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup): void
    {
        $effectiveClasses = $this->subjects->effectiveClasses($institute);
        $effectiveIds = array_map(fn (array $entry) => (int) $entry['class_grade']->id, $effectiveClasses);

        if (! in_array((int) $classGrade->id, $effectiveIds, true)) {
            throw ValidationException::withMessages([
                'class_grade_id' => "Class grade is not available for this institute's country/structure.",
            ]);
        }

        if ($academicGroup !== null) {
            if ((int) $academicGroup->class_grade_id !== (int) $classGrade->id) {
                throw ValidationException::withMessages([
                    'academic_group_id' => 'The selected group does not belong to the selected class.',
                ]);
            }

            if (! (bool) $academicGroup->status) {
                throw ValidationException::withMessages([
                    'academic_group_id' => 'The selected group is not available for this institute\'s structure.',
                ]);
            }
        }
    }

    /**
     * C3 — Structure Versioning: frozen snapshot of the class/group at placement time.
     * Stored as JSON so historical placements remain readable after a master is
     * renamed, deactivated or soft-deleted. The snapshot is versioned per placement
     * update (structure_version incremented on each updatePlacement).
     *
     * @return array<string, mixed>
     */
    private function buildStructureSnapshot(ClassGrade $classGrade, ?AcademicGroup $academicGroup): array
    {
        return [
            'class_grade' => [
                'id' => (int) $classGrade->id,
                'name' => $classGrade->name,
                'code' => $classGrade->code ?? null,
                'country_id' => $classGrade->country_id ?? null,
                'education_system_id' => $classGrade->education_system_id ?? null,
                'academic_level_id' => $classGrade->academic_level_id ?? null,
            ],
            'academic_group' => $academicGroup ? [
                'id' => (int) $academicGroup->id,
                'name' => $academicGroup->name,
                'code' => $academicGroup->code ?? null,
                'class_grade_id' => $academicGroup->class_grade_id ?? null,
            ] : null,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * P2-1 — Audit log helper for placements (wrapped in same DB transaction).
     * Uses audit_logs with module=education so placement history is traceable.
     */
    private function auditPlacement(string $action, StudentAcademicPlacement $placement, ?array $oldValues, ?array $newValues): void
    {
        try {
            \App\Models\AuditLog::create([
                'institute_id' => $placement->institute_id,
                'user_type' => 'institute_user',
                'user_id' => auth()->id(),
                'action' => $action,
                'module' => 'education',
                'record_id' => $placement->id,
                'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
                'new_values' => $newValues !== null ? json_encode($newValues) : null,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never block the placement transaction; log silently.
        }
    }

    /**
     * Run the existing validator and turn structured errors into a form-facing
     * ValidationException (kept under the `subjects` key for the server-side
     * alert block).
     *
     * @param  array<int, int|string>  $selectedIds
     */
    private function validatedSelection(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup, array $selectedIds): array
    {
        $selection = $this->validator->validate($institute, $classGrade, $academicGroup, $selectedIds, true);

        if (! $selection['valid']) {
            $messages = array_map(fn (array $error) => $error['message'], $selection['errors']);
            throw ValidationException::withMessages(['subjects' => $messages]);
        }

        return $selection;
    }

    /**
     * Persist only the resolved selected ids (mandatory auto-included) as
     * student_subject_selections rows.
     */
    private function syncSelections(StudentAcademicPlacement $placement, array $selection): void
    {
        $flat = $selection['selection']['flat'];
        $sourceMap = $this->sourceMap($selection['selection']);

        foreach ($selection['selected_ids'] as $subjectId) {
            $info = $flat[$subjectId] ?? null;

            StudentSubjectSelection::create([
                'academic_placement_id' => $placement->id,
                'institute_id' => $placement->institute_id,
                'subject_id' => $subjectId,
                'selection_group_id' => $info['selection_group_id'] ?? null,
                'is_selected' => true,
                'is_mandatory' => (bool) ($info['mandatory'] ?? false),
                'source' => $sourceMap[$subjectId] ?? null,
            ]);
        }
    }

    /**
     * subject_id → resolver source (inherited | customized | custom).
     */
    private function sourceMap(array $selection): array
    {
        $map = [];

        foreach ($selection['mandatory'] as $node) {
            $map[(int) $node['subject']->id] = $node['source'];
        }

        foreach ($selection['groups'] as $entry) {
            foreach ($entry['members'] as $node) {
                $map[(int) $node['subject']->id] = $node['source'];
            }
        }

        foreach ($selection['ungrouped'] as $node) {
            $map[(int) $node['subject']->id] = $node['source'];
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function nodes(array $nodes): array
    {
        return array_map(fn (array $node) => [
            'id' => (int) $node['subject']->id,
            'name' => $node['name'],
            'short_name' => $node['subject']->short_name,
            'requirement_type' => $node['requirement_type'],
            'source' => $node['source'],
            'selection_group_id' => $node['selection_group_id'],
        ], $nodes);
    }
}
