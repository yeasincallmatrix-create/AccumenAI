<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\InstituteCourse;
use App\Models\InstituteSubject;
use App\Models\Subject;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Classes & Subjects" for institute users (education industry).
 *
 * A class is an academic course, i.e. a course whose category subject_type
 * is 'academic'. The section lists academic courses, academic subjects and
 * the archive (batches whose status is 'archived').
 */
class ClassController extends Controller
{
    public const CLASSES_COLUMNS = ['serial', 'code', 'class', 'category', 'mode', 'fee', 'subjects', 'batches', 'status'];

    public const SUBJECTS_COLUMNS = ['serial', 'name', 'code', 'category', 'status'];

    public const BATCHES_COLUMNS = ['serial', 'code', 'name', 'class', 'shift', 'start', 'seats', 'status'];

    public const ARCHIVE_COLUMNS = ['serial', 'code', 'name', 'class', 'shift', 'start', 'seats', 'status', 'action'];

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');
        $branchId = $request->query('branch_id');
        $categoryId = $request->query('category_id');
        $mode = $request->query('mode');
        $sort = $request->query('sort');
        $instituteId = $request->user()->institute_id;

        $classes = InstituteCourse::query()
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType('academic')))
            ->with([
                'course.category' => fn ($q) => $q->withoutGlobalScope('institute'),
                'course.batches',
                'course.subjects',
            ])
            ->when($branchId, function ($query) use ($branchId) {
                return $query->whereHas('course.batches', fn ($batch) => $batch->where('branch_id', $branchId));
            })
            ->when($q !== '', function ($query) use ($q) {
                return $query->whereHas('course', function ($course) use ($q) {
                    return $course->where('name', 'like', "%{$q}%")
                        ->orWhere('course_code', 'like', "%{$q}%");
                });
            })
            ->when($status, function ($query) use ($status) {
                return $query->whereHas('course', fn ($course) => $course->where('status', $status));
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                return $query->whereHas('course', fn ($course) => $course->where('category_id', $categoryId));
            })
            ->when($mode, function ($query) use ($mode) {
                return $query->whereHas('course', fn ($course) => $course->where('mode', $mode));
            })
            ->when($request->query('created_before'), function ($query, $date) {
                return $query->where('created_at', '<', $date);
            })
            ->when($request->query('created_after'), function ($query, $date) {
                return $query->where('created_at', '>', $date);
            })
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('id', 'asc'))
            ->when($sort === 'latest', fn ($query) => $query->orderBy('id', 'desc'))
            ->when($sort === null, fn ($query) => $query->orderBy('id'))
            ->paginate(15)
            ->withQueryString();

        $visibleColumns = $request->user()->preference('columns_classes', self::CLASSES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::CLASSES_COLUMNS, (array) $visibleColumns));

        $courseOptions = $this->courseOptionsBySubjectType($instituteId, 'academic');

        return view('classes.index', [
            'classes' => $classes,
            'q' => $q,
            'status' => $status,
            'categoryId' => $categoryId,
            'mode' => $mode,
            'branchId' => $branchId,
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'filterCategories' => $courseOptions['categories'],
            'filterModes' => $courseOptions['modes'],
            'visibleColumns' => $visibleColumns,
            'classesCount' => $classes->total(),
            'subjectsCount' => $this->subjectsCount($instituteId, 'academic'),
            'batchesCount' => $this->batchesCount($instituteId, 'academic'),
            'archiveCount' => $this->archiveCount($instituteId, 'academic'),
        ]);
    }

    public function subjects(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');
        $instituteId = $request->user()->institute_id;

        $subjects = $this->subjectQuery($instituteId, 'academic')
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
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $visibleColumns = $request->user()->preference('columns_class_subjects', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        return view('classes.subjects', [
            'subjects' => $subjects,
            'q' => $q,
            'categoryId' => $categoryId,
            'status' => $status,
            'filterCategories' => $this->subjectCategoriesBySubjectType($instituteId, 'academic'),
            'requestCategories' => CourseCategory::query()
                ->withoutGlobalScope('institute')
                ->where('subject_type', 'academic')
                ->orderBy('name')
                ->get(['id', 'name']),
            'visibleColumns' => $visibleColumns,
            'classesCount' => $this->coursesCount($instituteId, 'academic'),
            'subjectsCount' => $subjects->total(),
            'batchesCount' => $this->batchesCount($instituteId, 'academic'),
            'archiveCount' => $this->archiveCount($instituteId, 'academic'),
        ]);
    }

    public function batches(Request $request): View
    {
        $instituteId = $request->user()->institute_id;

        $batches = Batch::query()
            ->with('course:id,name')
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType('academic')))
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

        $visibleColumns = $request->user()->preference('columns_class_batches', self::BATCHES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::BATCHES_COLUMNS, (array) $visibleColumns));

        return view('classes.batches', [
            'batches' => $batches,
            'q' => $request->query('q'),
            'courseId' => $request->query('course_id'),
            'branchId' => $request->query('branch_id'),
            'status' => $request->query('status'),
            'shift' => $request->query('shift'),
            'filterShifts' => $this->batchShiftsBySubjectType($instituteId, 'academic'),
            'courses' => $this->academicCourses($instituteId),
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'visibleColumns' => $visibleColumns,
            'classesCount' => $this->coursesCount($instituteId, 'academic'),
            'subjectsCount' => $this->subjectsCount($instituteId, 'academic'),
            'batchesCount' => $batches->total(),
            'archiveCount' => $this->archiveCount($instituteId, 'academic'),
        ]);
    }

    public function archive(Request $request): View
    {
        $instituteId = $request->user()->institute_id;

        $batches = Batch::query()
            ->with('course:id,name')
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType('academic')))
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
                        ->orWhere('batch_code', 'like', "%{$search}%");
                });
            })
            ->when($request->query('sort') === 'oldest', fn (Builder $q) => $q->orderBy('start_date', 'asc'))
            ->when($request->query('sort') === 'latest', fn (Builder $q) => $q->orderBy('start_date', 'desc'))
            ->when($request->query('sort') === null, fn (Builder $q) => $q->latest('id'))
            ->paginate(20)
            ->withQueryString();

        $visibleColumns = $request->user()->preference('columns_class_archive', self::ARCHIVE_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::ARCHIVE_COLUMNS, (array) $visibleColumns));

        return view('classes.archive', [
            'batches' => $batches,
            'q' => $request->query('q'),
            'courseId' => $request->query('course_id'),
            'branchId' => $request->query('branch_id'),
            'shift' => $request->query('shift'),
            'filterShifts' => $this->batchShiftsBySubjectType($instituteId, 'academic'),
            'courses' => $this->academicCourses($instituteId),
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'visibleColumns' => $visibleColumns,
            'classesCount' => $this->coursesCount($instituteId, 'academic'),
            'subjectsCount' => $this->subjectsCount($instituteId, 'academic'),
            'batchesCount' => $this->batchesCount($instituteId, 'academic'),
            'archiveCount' => $batches->total(),
        ]);
    }

    /**
     * Category ids for a subject type. The category catalog is shared across
     * institutes, so the per-institute tenant scope is intentionally bypassed.
     */
    private function categoryIdsBySubjectType(string $subjectType): array
    {
        return CourseCategory::query()
            ->withoutGlobalScope('institute')
            ->where('subject_type', $subjectType)
            ->pluck('id')
            ->all();
    }

    /**
     * Academic subjects available to the institute (fallback: whole academic catalog).
     */
    private function subjectQuery(int $instituteId, string $subjectType): EloquentBuilder
    {
        $assigned = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->pluck('subject_id');

        return Subject::query()
            ->where('subject_type', $subjectType)
            ->whereNull('deleted_at')
            ->when($assigned->isNotEmpty(), fn ($query) => $query->whereIn('id', $assigned));
    }

    /**
     * Academic courses assigned to the institute (fallback: whole academic catalog).
     */
    private function academicCourses(int $instituteId)
    {
        $courses = InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType('academic')))
            ->with('course:id,name')
            ->get()
            ->pluck('course')
            ->filter()
            ->values();

        if ($courses->isNotEmpty()) {
            return $courses;
        }

        return Course::query()
            ->whereIn('category_id', $this->categoryIdsBySubjectType('academic'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function coursesCount(int $instituteId, string $subjectType): int
    {
        return InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType)))
            ->count();
    }

    private function subjectsCount(int $instituteId, string $subjectType): int
    {
        return $this->subjectQuery($instituteId, $subjectType)->count();
    }

    private function batchesCount(int $instituteId, string $subjectType): int
    {
        return Batch::query()
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType)))
            ->where('status', '!=', 'archived')
            ->where('institute_id', $instituteId)
            ->count();
    }

    private function archiveCount(int $instituteId, string $subjectType): int
    {
        return Batch::query()
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType)))
            ->where('status', 'archived')
            ->where('institute_id', $instituteId)
            ->count();
    }

    /**
     * Funnel options (categories + modes) scoped to the institute's own
     * assigned courses for the subject type. Falls back to the whole catalog
     * so a brand-new institute still gets options (mirrors the list fallback).
     */
    private function courseOptionsBySubjectType(int $instituteId, string $subjectType): array
    {
        $assigned = InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType)))
            ->with('course:id,category_id,mode')
            ->get();

        if ($assigned->isEmpty()) {
            return [
                'categories' => CourseCategory::query()
                    ->withoutGlobalScope('institute')
                    ->where('subject_type', $subjectType)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'modes' => Course::query()
                    ->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType))
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
                ->withoutGlobalScope('institute')
                ->whereIn('id', $categoryIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'modes' => $assigned->pluck('course.mode')->filter()->unique()->values()->all(),
        ];
    }

    /**
     * Funnel categories scoped to the institute's own subjects for the type.
     */
    private function subjectCategoriesBySubjectType(int $instituteId, string $subjectType)
    {
        $assigned = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->pluck('subject_id');

        $categoryIds = Subject::query()
            ->where('subject_type', $subjectType)
            ->whereNull('deleted_at')
            ->when($assigned->isNotEmpty(), fn ($query) => $query->whereIn('id', $assigned))
            ->pluck('category_id')
            ->filter()
            ->unique();

        if ($categoryIds->isEmpty()) {
            return CourseCategory::query()
                ->withoutGlobalScope('institute')
                ->where('subject_type', $subjectType)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return CourseCategory::query()
            ->withoutGlobalScope('institute')
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Distinct batch shifts across the institute's batches (funnel options).
     */
    private function batchShiftsBySubjectType(int $instituteId, string $subjectType): array
    {
        $shifts = Batch::query()
            ->where('institute_id', $instituteId)
            ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType)))
            ->whereNotNull('shift')
            ->distinct()
            ->orderBy('shift')
            ->pluck('shift')
            ->all();

        if (empty($shifts)) {
            return Batch::query()
                ->whereHas('course', fn (Builder $q) => $q->whereIn('category_id', $this->categoryIdsBySubjectType($subjectType)))
                ->whereNotNull('shift')
                ->distinct()
                ->orderBy('shift')
                ->pluck('shift')
                ->all();
        }

        return $shifts;
    }
}
