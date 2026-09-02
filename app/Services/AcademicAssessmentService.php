<?php

namespace App\Services;

use App\Models\AcademicAssessment;
use App\Models\AcademicGroup;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Institute;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates academic assessment (exam) configuration: instance + subject +
 * dynamic component persistence.
 *
 * Security model:
 *   - institute_id is NEVER read from input; the caller passes the resolved
 *     Institute from the authenticated user / workspace.
 *   - branch_id is NEVER read from input; the caller passes the acting user's
 *     branch (null = whole-institute assessment).
 *   - academic years must be institute-owned; classes must be part of the
 *     institute's effective structure; groups must belong to the class.
 *   - subjects must come from the effective curriculum for the class/group;
 *     components must be global or institute-owned (Component::availableFor).
 *
 * The total full mark is always derived from the component rows — never stored.
 */
class AcademicAssessmentService
{
    public function __construct(
        private readonly AcademicSubjectService $subjects,
        private readonly AcademicAssessmentAuditService $audit,
    ) {}

    // ------------------------------------------------------------- Selectable subjects

    /**
     * Flat list of subjects selectable for a class/group assessment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function subjectsForSelection(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup): array
    {
        $selection = $this->subjects->resolveForSelection($institute, $classGrade, $academicGroup);
        $nodes = [];

        foreach ($selection['mandatory'] as $node) {
            $nodes[] = $this->node($node);
        }

        foreach ($selection['groups'] as $entry) {
            foreach ($entry['members'] as $node) {
                $nodes[] = $this->node($node);
            }
        }

        foreach ($selection['ungrouped'] as $node) {
            $nodes[] = $this->node($node);
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        return [
            'id' => (int) $node['subject']->id,
            'name' => $node['name'],
            'short_name' => $node['subject']->short_name,
            'subject_code' => $node['subject']->subject_code,
            'requirement_type' => $node['requirement_type'],
            'selection_group_id' => $node['selection_group_id'],
        ];
    }

    // ------------------------------------------------------------- Persistence

    /**
     * @param  array<int, array<string, mixed>>  $subjectPayload
     */
    public function store(
        Institute $institute,
        ?Branch $branch,
        ?int $createdBy,
        array $data,
        array $subjectPayload
    ): AcademicAssessment {
        $year = $this->requireInstituteYear($institute, (int) $data['academic_year_id']);
        $classGrade = $this->requireClassWithinInstitute($institute, (int) $data['class_grade_id']);
        $academicGroup = $this->requireGroupWithinClass($classGrade, $data['academic_group_id'] ?? null);
        $assessmentType = $this->resolveAssessmentType($institute, $data['assessment_type_id'] ?? null);
        $validated = $this->validateSubjects($institute, $classGrade, $academicGroup, $subjectPayload);
        $this->assertExamDateWithinYear($year, $data['exam_date'] ?? null);

        return DB::transaction(function () use ($institute, $branch, $createdBy, $data, $year, $classGrade, $academicGroup, $assessmentType, $validated) {
            // Duplicate protection: same institute + year + class + group + name must be unique
            $duplicate = AcademicAssessment::where('institute_id', $institute->id)
                ->where('academic_year_id', $year->id)
                ->where('class_grade_id', $classGrade->id)
                ->where('academic_group_id', $academicGroup?->id)
                ->where('name', trim($data['name']))
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'An assessment with this name already exists for the same academic year, class and group.']);
            }

            $displayOrder = isset($data['display_order']) && is_numeric($data['display_order'])
                ? (int) $data['display_order']
                : $this->nextDisplayOrder($year, $classGrade, $academicGroup);

            $assessment = AcademicAssessment::create([
                'institute_id' => $institute->id,
                'branch_id' => $branch?->id,
                'academic_year_id' => $year->id,
                'class_grade_id' => $classGrade->id,
                'academic_group_id' => $academicGroup?->id,
                'assessment_type_id' => $assessmentType?->id,
                'name' => trim($data['name']),
                'exam_date' => $data['exam_date'] ?? null,
                'status' => $data['status'],
                'display_order' => $displayOrder,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
            ]);

            $this->syncSubjects($assessment, $validated);

            $this->audit->record(
                $institute->id,
                $createdBy,
                'assessment.created',
                $assessment->id,
                null,
                $this->summary($assessment, $validated)
            );

            return $assessment->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $subjectPayload
     */
    public function update(
        Institute $institute,
        AcademicAssessment $assessment,
        array $data,
        array $subjectPayload,
        ?int $actorId = null,
    ): AcademicAssessment {
        abort_if($assessment->isLocked(), 422, 'This assessment is locked and its configuration can no longer be changed.');

        $year = $this->requireInstituteYear($institute, (int) $data['academic_year_id']);
        $classGrade = $this->requireClassWithinInstitute($institute, (int) $data['class_grade_id']);
        $academicGroup = $this->requireGroupWithinClass($classGrade, $data['academic_group_id'] ?? null);
        $assessmentType = $this->resolveAssessmentType($institute, $data['assessment_type_id'] ?? null);
        $validated = $this->validateSubjects($institute, $classGrade, $academicGroup, $subjectPayload);
        $this->assertExamDateWithinYear($year, $data['exam_date'] ?? null);

        $old = $this->summary($assessment, $assessment->subjects()->with('components')->get()->toArray());

        return DB::transaction(function () use ($institute, $assessment, $data, $year, $classGrade, $academicGroup, $assessmentType, $validated, $old, $actorId) {
            // Duplicate protection on update (exclude self)
            $duplicate = AcademicAssessment::where('institute_id', $institute->id)
                ->where('academic_year_id', $year->id)
                ->where('class_grade_id', $classGrade->id)
                ->where('academic_group_id', $academicGroup?->id)
                ->where('name', trim($data['name']))
                ->where('id', '!=', $assessment->id)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'An assessment with this name already exists for the same academic year, class and group.']);
            }

            $assessment->update([
                'academic_year_id' => $year->id,
                'class_grade_id' => $classGrade->id,
                'academic_group_id' => $academicGroup?->id,
                'assessment_type_id' => $assessmentType?->id,
                'name' => trim($data['name']),
                'exam_date' => $data['exam_date'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            $assessment->subjects()->delete();
            $this->syncSubjects($assessment, $validated);

            $this->audit->record(
                $institute->id,
                $actorId,
                'assessment.updated',
                $assessment->id,
                $old,
                $this->summary($assessment, $validated)
            );

            return $assessment->refresh();
        });
    }

    public function destroy(AcademicAssessment $assessment, ?int $actorId = null): void
    {
        abort_if($assessment->isLocked(), 422, 'This assessment is locked and can no longer be removed.');

        DB::transaction(function () use ($assessment, $actorId) {
            $this->audit->record(
                $assessment->institute_id,
                $actorId,
                'assessment.deleted',
                $assessment->id,
                $this->summary($assessment, $assessment->subjects()->with('components')->get()->toArray()),
                null
            );

            $assessment->delete();
        });
    }

    /**
     * Freeze an assessment. Marks entry, configuration edits and deletion are
     * refused until an explicitly permission-gated unlock. The lock is
     * audited; locking an already-locked assessment is a no-op.
     */
    public function lock(AcademicAssessment $assessment, ?int $actorId = null): AcademicAssessment
    {
        if ($assessment->isLocked()) {
            return $assessment;
        }

        DB::transaction(function () use ($assessment, $actorId) {
            $assessment->update([
                'locked_at' => now(),
                'locked_by' => $actorId,
            ]);

            $this->audit->record(
                $assessment->institute_id,
                $actorId,
                'assessment.locked',
                $assessment->id,
                null,
                ['locked_at' => $assessment->locked_at?->toDateTimeString()]
            );
        });

        return $assessment->refresh();
    }

    /**
     * Unlock a frozen assessment (explicitly permission-gated by the route).
     * Audited; unlocking an unlocked assessment is a no-op.
     */
    public function unlock(AcademicAssessment $assessment, ?int $actorId = null): AcademicAssessment
    {
        if (! $assessment->isLocked()) {
            return $assessment;
        }

        DB::transaction(function () use ($assessment, $actorId) {
            $assessment->update([
                'locked_at' => null,
                'locked_by' => null,
            ]);

            $this->audit->record(
                $assessment->institute_id,
                $actorId,
                'assessment.unlocked',
                $assessment->id,
                null,
                null
            );
        });

        return $assessment->refresh();
    }

    // ------------------------------------------------------------- Internals

    /**
     * Compact JSON snapshot of an assessment + its subject/component
     * configuration, used for audit old/new values.
     *
     * @param  array<int, array<string, mixed>>  $subjectPayload
     * @return array<string, mixed>
     */
    private function summary(AcademicAssessment $assessment, array $subjectPayload): array
    {
        $summary = [
            'name' => $assessment->name,
            'academic_year_id' => $assessment->academic_year_id,
            'class_grade_id' => $assessment->class_grade_id,
            'academic_group_id' => $assessment->academic_group_id,
            'assessment_type_id' => $assessment->assessment_type_id,
            'exam_date' => $assessment->exam_date?->toDateTimeString(),
            'status' => $assessment->status,
            'display_order' => $assessment->display_order,
            'notes' => $assessment->notes,
            'subjects' => [],
        ];

        foreach ($subjectPayload as $row) {
            $components = [];
            foreach ($row['components'] ?? [] as $component) {
                $components[] = [
                    'component_id' => (int) $component['component_id'],
                    'full_mark' => (float) $component['full_mark'],
                    'pass_mark' => (float) $component['pass_mark'],
                    'mandatory_pass' => (bool) ($component['mandatory_pass'] ?? false),
                ];
            }

            $summary['subjects'][] = [
                'subject_id' => (int) $row['subject_id'],
                'pass_rule' => $row['pass_rule'] ?? AssessmentSubject::PASS_RULE_TOTAL_ONLY,
                'components' => $components,
            ];
        }

        return $summary;
    }

    /**
     * display_order defaults to max + 1 within the same year + class + group
     * (spec: never rely on DB id for ordering).
     */
    public function nextDisplayOrder(AcademicYear $year, ClassGrade $classGrade, ?AcademicGroup $academicGroup): int
    {
        $max = AcademicAssessment::query()
            ->where('academic_year_id', $year->id)
            ->where('class_grade_id', $classGrade->id)
            ->when($academicGroup, fn ($q) => $q->where('academic_group_id', $academicGroup->id))
            ->max('display_order');

        return (int) $max + 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function validateSubjects(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup, array $payload): array
    {
        if ($payload === []) {
            throw ValidationException::withMessages(['subjects' => 'Select at least one subject for this assessment.']);
        }

        $valid = $this->subjectIdSet($institute, $classGrade, $academicGroup);
        $validComponents = Component::query()->availableFor($institute)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $seenSubjects = [];
        foreach ($payload as $index => $row) {
            $subjectId = (int) ($row['subject_id'] ?? 0);
            $passRule = $row['pass_rule'] ?? AssessmentSubject::PASS_RULE_TOTAL_ONLY;

            if (! in_array($passRule, AssessmentSubject::PASS_RULES, true)) {
                throw ValidationException::withMessages([
                    "subjects.$index.pass_rule" => 'Invalid pass rule for this subject.',
                ]);
            }

            if (! isset($valid[$subjectId])) {
                throw ValidationException::withMessages([
                    "subjects.$index" => 'The selected subject is not part of the class/group curriculum.',
                ]);
            }

            if (isset($seenSubjects[$subjectId])) {
                throw ValidationException::withMessages([
                    "subjects.$index" => 'Each subject can only appear once in an assessment.',
                ]);
            }
            $seenSubjects[$subjectId] = true;

            $components = $row['components'] ?? [];
            if ($components === []) {
                throw ValidationException::withMessages([
                    "subjects.$index.components" => 'Add at least one component for this subject.',
                ]);
            }

            $seenComponents = [];
            foreach ($components as $ci => $component) {
                $componentId = (int) ($component['component_id'] ?? 0);

                if (! in_array($componentId, $validComponents, true)) {
                    throw ValidationException::withMessages([
                        "subjects.$index.components.$ci.component_id" => 'Invalid component.',
                    ]);
                }

                if (isset($seenComponents[$componentId])) {
                    throw ValidationException::withMessages([
                        "subjects.$index.components.$ci.component_id" => 'Each component can only appear once per subject.',
                    ]);
                }
                $seenComponents[$componentId] = true;

                $full = (float) ($component['full_mark'] ?? 0);
                // P2-6 — Auto-fill pass_mark from country defaults if not provided.
                if (! isset($component['pass_mark']) || $component['pass_mark'] === '' || $component['pass_mark'] === null) {
                    $componentModel = \App\Models\Component::find($componentId);
                    $component['pass_mark'] = $this->defaultPassMark($institute, $componentModel, $full);
                    // Update payload so syncSubjects persists the default.
                    $payload[$index]['components'][$ci]['pass_mark'] = $component['pass_mark'];
                }
                $pass = (float) ($component['pass_mark'] ?? 0);

                if ($full <= 0) {
                    throw ValidationException::withMessages([
                        "subjects.$index.components.$ci.full_mark" => 'Full mark must be greater than zero.',
                    ]);
                }

                if ($pass < 0 || $pass > $full) {
                    throw ValidationException::withMessages([
                        "subjects.$index.components.$ci.pass_mark" => 'Pass mark must be between 0 and the full mark.',
                    ]);
                }
            }
        }

        return $payload;
    }

    /**
     * P2-6 — Resolve default pass mark for a component based on country.
     * Looks up country_pass_mark_defaults table first, then config/pass_marks.php,
     * falls back to 33% of full_mark. Practical components (name contains
     * practical/viva/lab) use the practical percentage for BD.
     */
    private function defaultPassMark(Institute $institute, ?\App\Models\Component $component, float $full): float
    {
        $iso2 = null;
        try {
            if (! empty($institute->country_id) && class_exists(\App\Models\Country::class)) {
                $country = \App\Models\Country::find($institute->country_id);
                $iso2 = $country?->iso2;
            }
        } catch (\Throwable $e) {}

        $iso2 = strtoupper($iso2 ?? 'GLOBAL');
        $componentName = strtolower($component?->name ?? '');
        $isPractical = str_contains($componentName, 'practical') || str_contains($componentName, 'viva') || str_contains($componentName, 'lab');

        // 1) Try DB table.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('country_pass_mark_defaults')) {
                $type = $isPractical ? 'practical' : 'theory';
                $row = \Illuminate\Support\Facades\DB::table('country_pass_mark_defaults')
                    ->where('country_code', $iso2)
                    ->where(function ($q) use ($type, $componentName) {
                        $q->where('component_type', $type)
                          ->orWhere('component_name', $componentName)
                          ->orWhere('component_type', 'default');
                    })
                    ->orderByRaw("CASE WHEN component_name = ? THEN 0 WHEN component_type = ? THEN 1 ELSE 2 END", [$componentName, $type])
                    ->first();

                if ($row !== null) {
                    $pct = (float) $row->pass_percentage;
                    return round($full * $pct / 100, 2);
                }

                // Fallback to GLOBAL.
                $global = \Illuminate\Support\Facades\DB::table('country_pass_mark_defaults')
                    ->where('country_code', 'GLOBAL')
                    ->where('component_type', 'default')
                    ->first();
                if ($global !== null) {
                    return round($full * (float) $global->pass_percentage / 100, 2);
                }
            }
        } catch (\Throwable $e) {}

        // 2) Fallback to config/pass_marks.php
        $config = config('pass_marks', []);
        $entry = $config[$iso2] ?? $config['global'] ?? ['default' => 33];

        if ($isPractical && isset($entry['practical'])) {
            $pct = (float) $entry['practical'];
        } elseif (! $isPractical && isset($entry['theory'])) {
            $pct = (float) $entry['theory'];
        } else {
            $pct = (float) ($entry['default'] ?? 33);
        }

        // Per-component override wins.
        if (! empty($entry['components']) && isset($entry['components'][strtolower($componentName)])) {
            $pct = (float) $entry['components'][strtolower($componentName)];
        }

        return round($full * $pct / 100, 2);
    }

    /**
     * @return array<int, true>
     */
    private function subjectIdSet(Institute $institute, ClassGrade $classGrade, ?AcademicGroup $academicGroup): array
    {
        $set = [];

        foreach ($this->subjectsForSelection($institute, $classGrade, $academicGroup) as $node) {
            $set[$node['id']] = true;
        }

        return $set;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    private function syncSubjects(AcademicAssessment $assessment, array $payload): void
    {
        foreach ($payload as $order => $row) {
            $subject = AssessmentSubject::create([
                'assessment_id' => $assessment->id,
                'subject_id' => (int) $row['subject_id'],
                'pass_rule' => $row['pass_rule'] ?? AssessmentSubject::PASS_RULE_TOTAL_ONLY,
                'display_order' => $order + 1,
                'status' => 'active',
            ]);

            foreach ($row['components'] as $ci => $component) {
                AssessmentSubjectComponent::create([
                    'assessment_subject_id' => $subject->id,
                    'component_id' => (int) $component['component_id'],
                    'full_mark' => (float) $component['full_mark'],
                    'pass_mark' => (float) $component['pass_mark'],
                    'mandatory_pass' => (bool) ($component['mandatory_pass'] ?? false),
                    'display_order' => $ci + 1,
                    'status' => 'active',
                ]);
            }
        }
    }

    private function resolveAssessmentType(Institute $institute, int|string|null $typeId): ?AssessmentType
    {
        if (! filled($typeId)) {
            return null;
        }

        $type = AssessmentType::query()->availableFor($institute)->find((int) $typeId);

        if ($type === null) {
            throw ValidationException::withMessages(['assessment_type_id' => 'Invalid assessment type.']);
        }

        return $type;
    }

    /**
     * P3-2 — Exam date must be within academic year bounds (+/- grace days).
     * Grace days are configurable via config('academic.exam_date_grace_days', 7).
     * Emits a log warning when the date is within 7 days of the strict boundary.
     */
    private function assertExamDateWithinYear(AcademicYear $year, mixed $examDate): void
    {
        if ($examDate === null || $examDate === '') {
            return;
        }

        // If year has no dates configured, no bound check
        if (empty($year->start_date) || empty($year->end_date)) {
            return;
        }

        try {
            $exam = \Illuminate\Support\Carbon::parse($examDate)->startOfDay();
            $start = \Illuminate\Support\Carbon::parse($year->start_date)->startOfDay();
            $end = \Illuminate\Support\Carbon::parse($year->end_date)->startOfDay();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['exam_date' => 'Invalid exam date.']);
        }

        $graceDays = (int) config('academic.exam_date_grace_days', 7);
        $graceDays = max(0, $graceDays);

        $graceStart = $start->copy()->subDays($graceDays);
        $graceEnd = $end->copy()->addDays($graceDays);

        if ($exam->lt($graceStart) || $exam->gt($graceEnd)) {
            throw ValidationException::withMessages(['exam_date' => 'Exam date must be within the academic year.']);
        }

        // Warning if near boundary (within grace window but outside strict bounds, or within 3 days inside)
        $nearBoundary = false;
        if ($exam->lt($start) || $exam->gt($end)) {
            // In grace period outside strict year
            $nearBoundary = true;
        } elseif ($exam->diffInDays($start, false) >= 0 && $exam->diffInDays($start, false) <= 3) {
            $nearBoundary = true;
        } elseif ($exam->diffInDays($end, false) <= 0 && $exam->diffInDays($end, false) >= -3) {
            $nearBoundary = true;
        }

        if ($nearBoundary) {
            try {
                \Illuminate\Support\Facades\Log::warning('Assessment exam date near academic year boundary', [
                    'academic_year_id' => $year->id,
                    'exam_date' => $exam->toDateString(),
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'grace_days' => $graceDays,
                ]);
            } catch (\Throwable $e) {}
        }
    }

    private function requireInstituteYear(Institute $institute, int $yearId): AcademicYear
    {
        $year = AcademicYear::query()->where('institute_id', $institute->id)->find($yearId);

        if ($year === null) {
            throw ValidationException::withMessages(['academic_year_id' => 'Invalid academic year for this institute.']);
        }

        return $year;
    }

    private function requireClassWithinInstitute(Institute $institute, int $classGradeId): ClassGrade
    {
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            if ((int) $entry['class_grade']->id === $classGradeId) {
                return $entry['class_grade'];
            }
        }

        throw ValidationException::withMessages(['class_grade_id' => 'Invalid class / grade for this institute.']);
    }

    private function requireGroupWithinClass(ClassGrade $classGrade, int|string|null $groupId): ?AcademicGroup
    {
        if (! filled($groupId)) {
            return null;
        }

        $group = $classGrade->groups()->where('status', true)->find((int) $groupId);

        if ($group === null) {
            throw ValidationException::withMessages(['academic_group_id' => 'Invalid group / stream.']);
        }

        return $group;
    }
}
