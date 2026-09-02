<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Institute;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Institute-facing Course Master authoring (Step 42).
 *
 * Institute-owned courses (institute_id = actor's institute) get a generated,
 * institute-unique course code (INST-###) and a globally collision-safe slug.
 * Delete is guarded: a course referenced by batches, enrollments, results,
 * certificates, exams, assignments, curricula or materials cannot be removed.
 * Every mutation is audited via CourseAuditService.
 */
class CourseMasterService
{
    public function __construct(private readonly CourseAuditService $audit) {}

    /**
     * Institute-unique course code using the institute code/short-name prefix
     * (e.g. "MAWA-001"), falling back to "INST-###". Collision-safe.
     */
    public function generateCourseCode(int $instituteId, ?Institute $institute = null): string
    {
        $source = $institute?->short_name ?: $institute?->institute_code ?: null;
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) Str::slug($source ?? 'INST', '')));
        $prefix = $prefix !== '' ? substr($prefix, 0, 5) : 'INST';

        $seq = 1;
        do {
            $code = $prefix.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $taken = Course::query()
                ->where('institute_id', $instituteId)
                ->where('course_code', $code)
                ->exists();
            $seq++;
        } while ($taken);

        return $code;
    }

    /**
     * Globally collision-safe slug (slug is unique across the shared catalog).
     */
    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'course';

        $suffix = 1;
        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($suffix++);
        }

        return $slug;
    }

    public function create(int $instituteId, array $data, int $actorId): Course
    {
        $institute = Institute::query()->find($instituteId);

        $course = Course::create([
            ...$this->normalize($data),
            'institute_id' => $instituteId,
            'course_code' => $this->generateCourseCode($instituteId, $institute),
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        $this->audit->record($instituteId, $actorId, 'course_created', $course->id, null, $this->snapshot($course));

        return $course;
    }

    public function update(Course $course, int $instituteId, array $data, int $actorId): Course
    {
        $old = $this->snapshot($course);
        $data = $this->normalize($data);

        if (isset($data['name']) && $data['name'] !== $course->name) {
            $data['slug'] = $this->uniqueSlug($data['name']);
        }

        $course->update($data);

        $new = $this->snapshot($course);

        if ($old !== $new) {
            $this->audit->record($instituteId, $actorId, 'course_updated', $course->id, $old, $new);
        }

        return $course->refresh();
    }

    public function destroy(Course $course, int $instituteId, int $actorId): void
    {
        $this->assertDeletable($course);

        $old = $this->snapshot($course);

        $course->delete();

        $this->audit->record($instituteId, $actorId, 'course_deleted', $course->id, $old, null);
    }

    /**
     * A course with any dependent record (or referenced by other institutes'
     * assignments) must not be deleted — cascading removals would silently
     * destroy academic data.
     */
    public function assertDeletable(Course $course): void
    {
        $referenced = Batch::query()->withoutGlobalScopes()->where('course_id', $course->id)->exists()
            || $course->enrollments()->exists()
            || $course->results()->exists()
            || $course->certificates()->exists()
            || $course->exams()->exists()
            || $course->courseRequests()->exists()
            || $course->instituteAssignments()->exists()
            || $course->curricula()->exists()
            || $course->materials()->exists()
            || $course->subjects()->exists();

        if ($referenced) {
            throw ValidationException::withMessages([
                'course' => 'This course cannot be deleted because it is already referenced by batches, enrollments, results, certificates or curricula.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        foreach (['requirements', 'outcomes', 'prerequisites'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->linesToArray($data[$field]);
            }
        }

        if (array_key_exists('display_order', $data)) {
            $data['display_order'] = (int) ($data['display_order'] ?: 0);
        }

        if (array_key_exists('is_featured', $data)) {
            $data['is_featured'] = ! empty($data['is_featured']);
        }

        // DB columns that are NOT NULL must never be inserted as null (MySQL strict mode -> 1048).
        // The form sends null/empty when the user leaves the field blank, so coerce to DB-safe defaults.
        if (array_key_exists('level', $data)) {
            $v = trim((string) ($data['level'] ?? ''));
            $data['level'] = $v !== '' ? $v : 'basic';
        }

        if (array_key_exists('duration_type', $data)) {
            $v = strtolower(trim((string) ($data['duration_type'] ?? '')));
            $allowed = ['hours', 'days', 'weeks', 'months', 'years'];
            $data['duration_type'] = $v !== '' && in_array($v, $allowed, true) ? $v : 'months';
        }

        if (array_key_exists('mode', $data)) {
            $v = strtolower(trim((string) ($data['mode'] ?? '')));
            $allowed = ['offline', 'online', 'hybrid'];
            $data['mode'] = $v !== '' && in_array($v, $allowed, true) ? $v : 'offline';
        }

        if (array_key_exists('duration_value', $data)) {
            $data['duration_value'] = $data['duration_value'] === null || $data['duration_value'] === '' ? 0 : $data['duration_value'];
        }

        if (array_key_exists('batch_capacity_default', $data)) {
            $data['batch_capacity_default'] = $data['batch_capacity_default'] === null || $data['batch_capacity_default'] === '' ? 30 : (int) $data['batch_capacity_default'];
        }

        foreach (['fee', 'discount', 'admission_fee', 'exam_fee', 'certificate_fee'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] === null || $data[$field] === '' ? 0 : $data[$field];
            }
        }

        // Normalize nullable string columns: empty string -> null
        foreach (['language', 'description', 'short_description', 'short_name', 'intro_video', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
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
    private function snapshot(Course $course): array
    {
        return [
            'name' => $course->name,
            'course_code' => $course->course_code,
            'category_id' => $course->category_id,
            'sub_category_id' => $course->sub_category_id,
            'short_name' => $course->short_name,
            'level' => $course->level,
            'language' => $course->language,
            'short_description' => $course->short_description,
            'duration_type' => $course->duration_type,
            'duration_value' => $course->duration_value,
            'weekly_classes' => $course->weekly_classes,
            'total_classes' => $course->total_classes,
            'total_hours' => $course->total_hours,
            'mode' => $course->mode,
            'fee' => $course->fee,
            'discount' => $course->discount,
            'admission_fee' => $course->admission_fee,
            'exam_fee' => $course->exam_fee,
            'certificate_fee' => $course->certificate_fee,
            'status' => $course->status,
            'is_featured' => $course->is_featured,
            'display_order' => $course->display_order,
        ];
    }
}
