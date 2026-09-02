<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CourseCurriculum;
use App\Models\CurriculumLesson;
use App\Models\CurriculumModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Curriculum lifecycle (Step 42).
 *
 * Single authority for curriculum versioning. Rules:
 *   - one version per (institute, course) is active at a time; activating a
 *     new version archives the previous active one (existing batches keep
 *     their curriculum_id, so historical data is never rewritten);
 *   - curricula referenced by batches cannot be edited, deactivated or
 *     deleted — change requires a new version;
 *   - version numbers auto-increment per (institute, course).
 * Every mutation is audited via CurriculumAuditService.
 */
class CourseCurriculumService
{
    public function __construct(private readonly CurriculumAuditService $audit) {}

    public function nextVersion(int $instituteId, int $courseId): int
    {
        return (int) CourseCurriculum::query()
            ->withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->where('course_id', $courseId)
            ->max('version') + 1;
    }

    public function activeFor(int $instituteId, int $courseId): ?CourseCurriculum
    {
        return CourseCurriculum::query()
            ->where('course_id', $courseId)
            ->where('status', CourseCurriculum::STATUS_ACTIVE)
            ->latest('version')
            ->first();
    }

    public function create(int $instituteId, int $courseId, array $data, int $actorId): CourseCurriculum
    {
        $curriculum = CourseCurriculum::create([
            'institute_id' => $instituteId,
            'course_id' => $courseId,
            'title' => $data['title'],
            'version' => $this->nextVersion($instituteId, $courseId),
            'effective_date' => $data['effective_date'] ?? null,
            'status' => $data['status'] ?? CourseCurriculum::STATUS_DRAFT,
            'description' => $data['description'] ?? null,
            'total_duration_hours' => $data['total_duration_hours'] ?? null,
            'total_classes' => $data['total_classes'] ?? null,
            'learning_objectives' => $this->linesToArray($data['learning_objectives'] ?? null),
            'version_notes' => $data['version_notes'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->audit->record($instituteId, $actorId, 'curriculum_created', $curriculum->id, null, $this->snapshot($curriculum));

        return $curriculum;
    }

    /**
     * Header fields may change freely while the curriculum is a draft; once a
     * batch references it, editing is blocked — change requires a new version.
     */
    public function update(CourseCurriculum $curriculum, array $data, int $actorId): CourseCurriculum
    {
        $this->assertNotReferenced($curriculum);

        $old = $this->snapshot($curriculum);

        $curriculum->update([
            'title' => $data['title'],
            'effective_date' => $data['effective_date'] ?? null,
            'description' => $data['description'] ?? null,
            'total_duration_hours' => $data['total_duration_hours'] ?? null,
            'total_classes' => $data['total_classes'] ?? null,
            'learning_objectives' => $this->linesToArray($data['learning_objectives'] ?? null),
            'version_notes' => $data['version_notes'] ?? null,
            'updated_by' => $actorId,
        ]);

        $new = $this->snapshot($curriculum);

        if ($old !== $new) {
            $this->audit->record((int) $curriculum->institute_id, $actorId, 'curriculum_updated', $curriculum->id, $old, $new);
        }

        return $curriculum->refresh();
    }

    /**
     * Activate a version: archives the previous active one. Existing batches
     * keep their curriculum_id — activating never rewrites history.
     */
    public function activate(CourseCurriculum $curriculum, int $actorId): CourseCurriculum
    {
        DB::transaction(function () use ($curriculum) {
            CourseCurriculum::query()
                ->withoutGlobalScope('institute')
                ->where('institute_id', $curriculum->institute_id)
                ->where('course_id', $curriculum->course_id)
                ->where('status', CourseCurriculum::STATUS_ACTIVE)
                ->whereKeyNot($curriculum->id)
                ->lockForUpdate()
                ->update(['status' => CourseCurriculum::STATUS_ARCHIVED, 'updated_by' => $curriculum->updated_by]);

            $curriculum->update(['status' => CourseCurriculum::STATUS_ACTIVE]);
        });

        $this->audit->record((int) $curriculum->institute_id, $actorId, 'curriculum_activated', $curriculum->id, null, [
            'version' => $curriculum->version,
        ]);

        return $curriculum->refresh();
    }

    public function setStatus(CourseCurriculum $curriculum, string $status, int $actorId): CourseCurriculum
    {
        if (! in_array($status, CourseCurriculum::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'The selected curriculum status is invalid.',
            ]);
        }

        if ($status === CourseCurriculum::STATUS_DRAFT || $status === CourseCurriculum::STATUS_ARCHIVED) {
            $this->assertNotReferenced($curriculum);
        }

        $oldStatus = $curriculum->status;

        $curriculum->update(['status' => $status]);

        $this->audit->record((int) $curriculum->institute_id, $actorId, 'curriculum_status_changed', $curriculum->id, [
            'status' => $oldStatus,
        ], [
            'status' => $status,
        ]);

        return $curriculum->refresh();
    }

    public function destroy(CourseCurriculum $curriculum, int $actorId): void
    {
        $this->assertNotReferenced($curriculum);

        $old = $this->snapshot($curriculum);

        $curriculum->delete();

        $this->audit->record((int) $curriculum->institute_id, $actorId, 'curriculum_deleted', $curriculum->id, $old, null);
    }

    public function createModule(CourseCurriculum $curriculum, array $data, int $actorId): CurriculumModule
    {
        $this->assertNotReferenced($curriculum);

        $module = CurriculumModule::create([
            'institute_id' => $curriculum->institute_id,
            'curriculum_id' => $curriculum->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'module_type' => $data['module_type'] ?? null,
            'theory_marks' => $data['theory_marks'] ?? null,
            'practical_marks' => $data['practical_marks'] ?? null,
            'viva_marks' => $data['viva_marks'] ?? null,
            'total_marks' => $data['total_marks'] ?? null,
            'credit_hours' => $data['credit_hours'] ?? null,
            'class_count' => $data['class_count'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'is_optional' => ! empty($data['is_optional']),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => $data['status'] ?? CurriculumModule::STATUS_ACTIVE,
        ]);

        $this->audit->record((int) $curriculum->institute_id, $actorId, 'curriculum_module_created', $module->id, null, $this->moduleSnapshot($module));

        return $module;
    }

    public function updateModule(CurriculumModule $module, array $data, int $actorId): CurriculumModule
    {
        $this->assertNotReferenced($module->curriculum);

        $old = $this->moduleSnapshot($module);

        $module->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'module_type' => $data['module_type'] ?? null,
            'theory_marks' => $data['theory_marks'] ?? null,
            'practical_marks' => $data['practical_marks'] ?? null,
            'viva_marks' => $data['viva_marks'] ?? null,
            'total_marks' => $data['total_marks'] ?? null,
            'credit_hours' => $data['credit_hours'] ?? null,
            'class_count' => $data['class_count'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'is_optional' => ! empty($data['is_optional']),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => $data['status'] ?? CurriculumModule::STATUS_ACTIVE,
        ]);

        $new = $this->moduleSnapshot($module);

        if ($old !== $new) {
            $this->audit->record((int) $module->institute_id, $actorId, 'curriculum_module_updated', $module->id, $old, $new);
        }

        return $module->refresh();
    }

    public function destroyModule(CurriculumModule $module, int $actorId): void
    {
        $this->assertNotReferenced($module->curriculum);

        $old = $this->moduleSnapshot($module);

        $module->delete();

        $this->audit->record((int) $module->institute_id, $actorId, 'curriculum_module_deleted', $module->id, $old, null);
    }

    public function createLesson(CurriculumModule $module, array $data, int $actorId): CurriculumLesson
    {
        $this->assertNotReferenced($module->curriculum);

        $lesson = CurriculumLesson::create([
            'institute_id' => $module->institute_id,
            'curriculum_module_id' => $module->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'learning_objective' => $data['learning_objective'] ?? null,
            'content_reference' => $data['content_reference'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => $data['status'] ?? CurriculumLesson::STATUS_ACTIVE,
        ]);

        $this->audit->record((int) $module->institute_id, $actorId, 'curriculum_lesson_created', $lesson->id, null, $this->lessonSnapshot($lesson));

        return $lesson;
    }

    public function updateLesson(CurriculumLesson $lesson, array $data, int $actorId): CurriculumLesson
    {
        $this->assertNotReferenced($lesson->module->curriculum);

        $old = $this->lessonSnapshot($lesson);

        $lesson->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'learning_objective' => $data['learning_objective'] ?? null,
            'content_reference' => $data['content_reference'] ?? null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => $data['status'] ?? CurriculumLesson::STATUS_ACTIVE,
        ]);

        $new = $this->lessonSnapshot($lesson);

        if ($old !== $new) {
            $this->audit->record((int) $lesson->institute_id, $actorId, 'curriculum_lesson_updated', $lesson->id, $old, $new);
        }

        return $lesson->refresh();
    }

    public function destroyLesson(CurriculumLesson $lesson, int $actorId): void
    {
        $this->assertNotReferenced($lesson->module->curriculum);

        $old = $this->lessonSnapshot($lesson);

        $lesson->delete();

        $this->audit->record((int) $lesson->institute_id, $actorId, 'curriculum_lesson_deleted', $lesson->id, $old, null);
    }

    /**
     * A curriculum referenced by a batch must be frozen: editing, deleting or
     * deactivating it would silently rewrite what the batch points to.
     */
    public function assertNotReferenced(CourseCurriculum $curriculum): void
    {
        $referenced = Batch::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $curriculum->institute_id)
            ->where('curriculum_id', $curriculum->id)
            ->exists();

        if ($referenced) {
            throw ValidationException::withMessages([
                'curriculum' => 'This curriculum version is referenced by existing batches and cannot be changed. Create a new version instead.',
            ]);
        }
    }

    private function linesToArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $lines = array_values(array_filter(array_map('trim', $value), fn ($l) => $l !== ''));

            return $lines !== [] ? $lines : null;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        return $lines !== [] ? $lines : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CourseCurriculum $curriculum): array
    {
        return [
            'title' => $curriculum->title,
            'version' => $curriculum->version,
            'effective_date' => $curriculum->effective_date?->toDateString(),
            'status' => $curriculum->status,
            'description' => $curriculum->description,
            'total_duration_hours' => $curriculum->total_duration_hours,
            'total_classes' => $curriculum->total_classes,
            'learning_objectives' => $curriculum->learning_objectives,
            'version_notes' => $curriculum->version_notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleSnapshot(CurriculumModule $module): array
    {
        return [
            'name' => $module->name,
            'code' => $module->code,
            'module_type' => $module->module_type,
            'theory_marks' => $module->theory_marks,
            'practical_marks' => $module->practical_marks,
            'viva_marks' => $module->viva_marks,
            'total_marks' => $module->total_marks,
            'credit_hours' => $module->credit_hours,
            'class_count' => $module->class_count,
            'duration_hours' => $module->duration_hours,
            'is_optional' => $module->is_optional,
            'display_order' => $module->display_order,
            'status' => $module->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonSnapshot(CurriculumLesson $lesson): array
    {
        return [
            'title' => $lesson->title,
            'description' => $lesson->description,
            'duration_minutes' => $lesson->duration_minutes,
            'learning_objective' => $lesson->learning_objective,
            'content_reference' => $lesson->content_reference,
            'display_order' => $lesson->display_order,
            'status' => $lesson->status,
        ];
    }
}
