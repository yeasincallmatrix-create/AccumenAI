<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteSubject;
use App\Models\Notification;
use App\Models\Subject;
use App\Models\SubjectRequest;
use App\Support\InstituteDomain;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    public const COURSES_COLUMNS = ['serial', 'code', 'course', 'category', 'mode', 'fee', 'subjects', 'batches', 'status'];

    public const SUBJECTS_COLUMNS = ['serial', 'name', 'code', 'category', 'status'];

    public const BATCHES_COLUMNS = ['serial', 'code', 'name', 'course', 'shift', 'start', 'seats', 'status'];

    public const ARCHIVE_COLUMNS = ['serial', 'code', 'name', 'course', 'shift', 'start', 'seats', 'status', 'action'];

    // Legacy GET /courses (courses.index) retired — canonical is /courses/manage (courses.manage.index)
    // Previous index() rendered resources/views/courses/index.blade.php with livewire.courses.list.
    // Method removed permanently; use CourseMasterController@index for Course Master.

    public function subjects(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');
        $instituteId = $request->user()->institute_id;
        $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));

        $query = $this->subjectQuery($instituteId, $derived)
            ->with(['category' => fn ($q) => $q->withoutGlobalScope('institute')])
            ->when($q !== '', function ($query) use ($q) {
                return $query->where(function (Builder $w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('subject_code', 'like', "%{$q}%")
                        ->orWhere('short_name', 'like', "%{$q}%");
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->orderBy('name');

        $subjects = (clone $query)->paginate(20)->withQueryString();
        $allSubjects = (clone $query)->get();

        $visibleColumns = $request->user()->preference('columns_course_subjects', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        // Tenant + domain scoped categories (never bypass without institute_id)
        $requestCategories = CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $derived)
            ->orderBy('name')
            ->get(['id', 'name', 'subject_type']);

        return view('courses.subjects', [
            'subjects' => $subjects,
            'allSubjects' => $allSubjects,
            'q' => $q,
            'categoryId' => $categoryId,
            'status' => $status,
            'filterCategories' => $this->subjectCategoriesBySubjectType($instituteId, $derived),
            'requestCategories' => $requestCategories,
            'visibleColumns' => $visibleColumns,
            'coursesCount' => $this->coursesCount($instituteId, $derived),
            'subjectsCount' => $subjects->total(),
            'batchesCount' => $this->batchesCount($instituteId, $derived),
            'archiveCount' => $this->archiveCount($instituteId, $derived),
        ]);
    }

    public function batches(Request $request): View
    {
        $instituteId = $request->user()->institute_id;
        $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));

        $batches = Batch::query()
            ->with('course:id,name')
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $derived)))
            ->where('status', '!=', 'archived')
            ->when($request->query('course_id'), function (Builder $q, $courseId) {
                $q->where('course_id', $courseId);
            })
            ->when($request->query('branch_id'), function (Builder $q, $branchId) {
                $q->where('branch_id', $branchId);
            })
            ->when($request->query('status'), function (Builder $q, $status) {
                $q->where('status', $status);
            })
            ->when($request->query('shift'), function (Builder $q, $shift) {
                $q->where('shift', $shift);
            })
            ->when($request->query('start_before'), function (Builder $q, $date) {
                $q->where('start_date', '<', $date);
            })
            ->when($request->query('start_after'), function (Builder $q, $date) {
                $q->where('start_date', '>', $date);
            })
            ->when($request->query('q'), function (Builder $q, $search) {
                $q->where(function (Builder $w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('batch_code', 'like', "%{$search}%")
                        ->orWhereHas('course', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('sort') === 'oldest', fn (Builder $q) => $q->orderBy('start_date', 'asc'))
            ->when($request->query('sort') === 'latest', fn (Builder $q) => $q->orderBy('start_date', 'desc'))
            ->when($request->query('sort') === null, fn (Builder $q) => $q->latest('id'))
            ->paginate(20)
            ->withQueryString();

        $visibleColumns = $request->user()->preference('columns_course_batches', self::BATCHES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::BATCHES_COLUMNS, (array) $visibleColumns));

        return view('courses.batches', [
            'batches' => $batches,
            'q' => $request->query('q'),
            'courseId' => $request->query('course_id'),
            'branchId' => $request->query('branch_id'),
            'status' => $request->query('status'),
            'shift' => $request->query('shift'),
            'filterShifts' => $this->batchShiftsBySubjectType($instituteId, $derived),
            'courses' => $this->domainCourses($instituteId, $derived),
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'visibleColumns' => $visibleColumns,
            'coursesCount' => $this->coursesCount($instituteId, $derived),
            'subjectsCount' => $this->subjectsCount($instituteId, $derived),
            'batchesCount' => $batches->total(),
            'archiveCount' => $this->archiveCount($instituteId, $derived),
        ]);
    }

    public function archive(Request $request): View
    {
        $instituteId = $request->user()->institute_id;
        $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));

        $query = Batch::query()
            ->with('course:id,name')
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $derived)))
            ->where('status', 'archived')
            ->when($request->query('course_id'), function (Builder $q, $courseId) {
                $q->where('course_id', $courseId);
            })
            ->when($request->query('branch_id'), function (Builder $q, $branchId) {
                $q->where('branch_id', $branchId);
            })
            ->when($request->query('shift'), function (Builder $q, $shift) {
                $q->where('shift', $shift);
            })
            ->when($request->query('start_before'), function (Builder $q, $date) {
                $q->where('start_date', '<', $date);
            })
            ->when($request->query('start_after'), function (Builder $q, $date) {
                $q->where('start_date', '>', $date);
            })
            ->when($request->query('q'), function (Builder $q, $search) {
                $q->where(function (Builder $w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('batch_code', 'like', "%{$search}%")
                        ->orWhereHas('course', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('sort') === 'oldest', fn (Builder $q) => $q->orderBy('start_date', 'asc'))
            ->when($request->query('sort') === 'latest', fn (Builder $q) => $q->orderBy('start_date', 'desc'))
            ->when($request->query('sort') === null, fn (Builder $q) => $q->latest('id'));

        $batches = (clone $query)->paginate(20)->withQueryString();
        $allBatches = (clone $query)->get();

        $visibleColumns = $request->user()->preference('columns_course_archive', self::ARCHIVE_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::ARCHIVE_COLUMNS, (array) $visibleColumns));

        return view('courses.archive', [
            'batches' => $batches,
            'allBatches' => $allBatches,
            'q' => $request->query('q'),
            'courseId' => $request->query('course_id'),
            'branchId' => $request->query('branch_id'),
            'shift' => $request->query('shift'),
            'filterShifts' => $this->batchShiftsBySubjectType($instituteId, $derived),
            'courses' => $this->domainCourses($instituteId, $derived),
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'visibleColumns' => $visibleColumns,
            'coursesCount' => $this->coursesCount($instituteId, $derived),
            'subjectsCount' => $this->subjectsCount($instituteId, $derived),
            'batchesCount' => $this->batchesCount($instituteId, $derived),
            'archiveCount' => $batches->total(),
        ]);
    }

    /**
     * Single course page showing every batch related to the course.
     */
    public function show(Request $request, Course $course): View
    {
        $instituteId = $request->user()->institute_id;

        $course->load(['category:id,name', 'subjects:id,name']);

        $batches = Batch::query()
            ->where('course_id', $course->id)
            ->where('status', '!=', 'archived')
            ->with('course:id,name')
            ->withCount(['exams as attended_exams' => fn ($q) => $q->whereHas('results')])
            ->when($request->query('branch_id'), fn (Builder $q, $branchId) => $q->where('branch_id', $branchId))
            ->when($request->query('shift'), fn (Builder $q, $shift) => $q->where('shift', $shift))
            ->when($request->query('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($request->query('sort') === 'oldest', fn (Builder $q) => $q->orderBy('start_date', 'asc'))
            ->when($request->query('sort') === 'latest', fn (Builder $q) => $q->orderBy('start_date', 'desc'))
            ->when($request->query('sort') === null, fn (Builder $q) => $q->latest('id'))
            ->paginate(20)
            ->withQueryString();

        return view('courses.show', [
            'course' => $course,
            'batches' => $batches,
            'branchId' => $request->query('branch_id'),
            'shift' => $request->query('shift'),
            'status' => $request->query('status'),
            'filterShifts' => Batch::query()
                ->where('course_id', $course->id)
                ->whereNotNull('shift')
                ->distinct()
                ->orderBy('shift')
                ->pluck('shift')
                ->all(),
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'editData' => collect($batches->items())->mapWithKeys(fn (Batch $batch) => [
                $batch->id => [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'name' => $batch->name,
                    'course_id' => $batch->course_id,
                    'shift' => $batch->shift,
                    'start_date' => $batch->start_date ? Carbon::parse($batch->start_date)->format('Y-m-d') : null,
                    'end_date' => $batch->end_date ? Carbon::parse($batch->end_date)->format('Y-m-d') : null,
                    'seat_capacity' => $batch->seat_capacity,
                    'status' => $batch->status,
                ],
            ])->all(),
            'sendExamSubjects' => collect($batches->items())->mapWithKeys(fn (Batch $batch) => [
                $batch->id => $course->subjects->map(fn (Subject $subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ])->values()->all(),
            ])->all(),
            'subjectOptions' => $this->courseSubjectQuery($course, $instituteId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Subject $subject) => ['id' => $subject->id, 'name' => $subject->name])
                ->values()
                ->all(),
            'attachedSubjectIds' => $course->subjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ]);
    }

    /**
     * Attach the given subjects to a course (course_subjects pivot). Only
     * subjects the institute can use are accepted; existing attachments are
     * replaced (sync) so the checkbox list behaves as a full editor.
     */
    public function syncSubjects(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $instituteId = $request->user()->institute_id;

        $data = $request->validate([
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer'],
        ]);

        $ids = collect($data['subjects'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $available = $this->courseSubjectQuery($course, $instituteId)->pluck('id')->all();
        $allowed = array_values(array_intersect($ids, $available));

        $course->subjects()->sync($allowed);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('courses.subjects_updated'),
                'data' => ['course_id' => $course->id, 'subject_count' => count($allowed)],
            ]);
        }

        return back()->with('status', mawa_lang('courses.subjects_updated'));
    }

    /**
     * Institute user proposes a new subject. Nothing is created yet — the
     * proposal is stored as a pending subject request for a platform admin
     * to review. The course (category) is locked once the request is sent.
     */
    public function requestSubject(Request $request): RedirectResponse|JsonResponse
    {
        $instituteId = $request->user()->institute_id;
        $institute = Institute::find($instituteId);
        $derivedType = InstituteDomain::subjectTypeFor($institute);
        // Server-derived: never trust client subject_type
        $subjectType = $derivedType;

        $data = $request->validate([
            // subject_type from client is ignored — derived from InstituteDomain
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', $derivedType)],
            'category_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $categoryId = $data['category_id'] ?? null;
        $categoryName = trim((string) ($data['category_name'] ?? ''));

        if ($categoryName !== '') {
            $category = CourseCategory::query()
                ->where('institute_id', $instituteId)
                ->where('subject_type', $subjectType)
                ->where('name', $categoryName)
                ->first();

            if (! $category) {
                $category = CourseCategory::create([
                    'name' => $categoryName,
                    'slug' => Str::slug($categoryName.'-'.strtolower(Str::random(6))),
                    'subject_type' => $subjectType,
                    'institute_id' => $instituteId,
                    'status' => 'active',
                ]);
            }

            $categoryId = $category->id;
        }

        if (! $categoryId) {
            return $request->expectsJson()
                ? response()->json([
                    'success' => false,
                    'message' => mawa_lang('courses.subject_category_required'),
                ], 422)
                : back()->withErrors(['category_name' => mawa_lang('courses.subject_category_required')]);
        }

        $pendingExists = SubjectRequest::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'pending')
            ->where('name', $data['name'])
            ->exists();

        if ($pendingExists) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('courses.subject_request_duplicate'),
                ], 422);
            }

            return back()->withErrors(['name' => mawa_lang('courses.subject_request_duplicate')]);
        }

        $subjectRequest = SubjectRequest::create([
            'institute_id' => $instituteId,
            'category_id' => $data['category_id'],
            'subject_type' => $subjectType,
            'name' => $data['name'],
            'short_name' => $data['short_name'],
            'subject_code' => $data['subject_code'],
            'description' => $data['description'],
            'requested_by' => $request->user()->getAuthIdentifier(),
            'status' => 'pending',
        ]);

        try {
            Notification::create([
                'scope' => 'institute',
                'institute_id' => $instituteId,
                'category' => 'subject_request',
                'title' => 'Subject request submitted',
                'message' => "Subject '{$data['name']}' has been submitted for approval.",
                'link_url' => null,
                'created_by_type' => 'institute_user',
                'created_by_id' => $request->user()->getAuthIdentifier(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification.subject_request_failed', ['error' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('courses.subject_request_submitted'),
            ]);
        }

        return back()->with('status', mawa_lang('courses.subject_request_submitted'));
    }

    /**
     * User edits a subject their institute created (after admin approval).
     * The course (category) is intentionally not editable — only the subject
     * details can change.
     */
    public function updateSubject(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $instituteId = $request->user()->institute_id;
        $isOwned = (int) $subject->institute_id === (int) $instituteId;
        $isPlatform = blank($subject->institute_id);

        if (! $isOwned && ! $isPlatform) {
            abort(403, mawa_lang('courses.subject_not_editable'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $subject->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('courses.subject_updated'),
                'data' => ['id' => $subject->id],
            ]);
        }

        return back()->with('status', mawa_lang('courses.subject_updated'));
    }

    /**
     * Category ids for a subject type — tenant-isolated (never bypass without institute_id).
     */
    private function categoryIdsBySubjectType(int $instituteId, string $subjectType): array
    {
        return CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $subjectType)
            ->pluck('id')
            ->all();
    }

    /**
     * Subjects available to the institute — tenant-isolated, domain-filtered.
     * Uses InstituteSubject assignment if present, otherwise institute-owned subjects.
     */
    private function subjectQuery(int $instituteId, string $subjectType): EloquentBuilder
    {
        $assigned = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->pluck('subject_id');

        $query = Subject::query()
            ->where('subject_type', $subjectType)
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at');

        if ($assigned->isNotEmpty()) {
            // Restrict to assigned when assignments exist, still tenant-scoped
            $query->whereIn('id', $assigned);
        }

        return $query;
    }

    /**
     * Subjects relevant to a course: institute-available subjects (domain-derived)
     * that belong to the course's category. Courses without a category fall
     * back to the domain-filtered list so the picker still works.
     */
    private function courseSubjectQuery(Course $course, int $instituteId): EloquentBuilder
    {
        $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));
        $query = $this->subjectQuery($instituteId, $derived);

        if ($course->category_id) {
            $query->where('category_id', $course->category_id);
        }

        return $query;
    }

    /**
     * Courses for the active domain — tenant-isolated, no global fallback leak.
     */
    private function domainCourses(int $instituteId, string $subjectType)
    {
        $courses = InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType)))
            ->with('course:id,name')
            ->get()
            ->pluck('course')
            ->filter()
            ->values();

        if ($courses->isNotEmpty()) {
            return $courses;
        }

        // Tenant-isolated fallback: institute-owned courses in domain
        return Course::query()
            ->where('institute_id', $instituteId)
            ->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Backward compat: professionalCourses delegated to domainCourses
     */
    private function professionalCourses(int $instituteId)
    {
        $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));
        return $this->domainCourses($instituteId, $derived);
    }

    private function coursesCount(int $instituteId, string $subjectType): int
    {
        return InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType)))
            ->count();
    }

    private function subjectsCount(int $instituteId, string $subjectType): int
    {
        return $this->subjectQuery($instituteId, $subjectType)->count();
    }

    private function batchesCount(int $instituteId, string $subjectType): int
    {
        return Batch::query()
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType)))
            ->where('status', '!=', 'archived')
            ->where('institute_id', $instituteId)
            ->count();
    }

    private function archiveCount(int $instituteId, string $subjectType): int
    {
        return Batch::query()
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType)))
            ->where('status', 'archived')
            ->where('institute_id', $instituteId)
            ->count();
    }

    /**
     * Funnel options (categories + modes) tenant-isolated, domain-filtered.
     */
    private function courseOptionsBySubjectType(int $instituteId, string $subjectType): array
    {
        $assigned = InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType)))
            ->with('course:id,category_id,mode')
            ->get();

        if ($assigned->isEmpty()) {
            return [
                'categories' => CourseCategory::query()
                    ->where('institute_id', $instituteId)
                    ->where('subject_type', $subjectType)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'modes' => Course::query()
                    ->where('institute_id', $instituteId)
                    ->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType))
                    ->whereNotNull('mode')
                    ->distinct()
                    ->orderBy('mode')
                    ->pluck('mode')
                    ->all(),
            ];
        }

        $categoryIds = $assigned->pluck('course.category_id')->filter()->unique();

        return [
            'categories' => CourseCategory::query()
                ->where('institute_id', $instituteId)
                ->whereIn('id', $categoryIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'modes' => $assigned->pluck('course.mode')->filter()->unique()->values()->all(),
        ];
    }

    /**
     * Funnel categories tenant-isolated, domain-filtered.
     */
    private function subjectCategoriesBySubjectType(int $instituteId, string $subjectType)
    {
        $assigned = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->pluck('subject_id');

        $categoryIds = Subject::query()
            ->where('subject_type', $subjectType)
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->when($assigned->isNotEmpty(), fn ($query) => $query->whereIn('id', $assigned))
            ->pluck('category_id')
            ->filter()
            ->unique();

        if ($categoryIds->isEmpty()) {
            return CourseCategory::query()
                ->where('institute_id', $instituteId)
                ->where('subject_type', $subjectType)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Distinct batch shifts tenant-isolated, domain-filtered.
     */
    private function batchShiftsBySubjectType(int $instituteId, string $subjectType): array
    {
        $shifts = Batch::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId, $subjectType)))
            ->whereNotNull('shift')
            ->distinct()
            ->orderBy('shift')
            ->pluck('shift')
            ->all();

        if (empty($shifts)) {
            // No fallback leak to global catalog — tenant empty means no shifts
            return [];
        }

        return $shifts;
    }
}
