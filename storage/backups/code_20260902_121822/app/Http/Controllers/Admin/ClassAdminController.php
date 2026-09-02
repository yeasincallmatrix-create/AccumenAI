<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Institute;
use App\Models\Subject;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Classes & Subjects" admin section.
 *
 * A class is an academic course, i.e. a course whose category subject_type
 * is 'academic'. Tabs: Classes, Academic Subjects, Batches, Archive.
 */
class ClassAdminController extends Controller
{
    public const CLASSES_COLUMNS = [
        'serial', 'code', 'class', 'category', 'level', 'fee', 'subjects', 'batches', 'discount',
        'admission_fee', 'exam_fee', 'certificate_fee', 'duration', 'mode', 'status', 'assignment',
    ];

    public const SUBJECTS_COLUMNS = [
        'serial', 'name', 'code', 'type', 'category', 'institute', 'status',
    ];

    public const BATCHES_COLUMNS = [
        'serial', 'batch', 'institute', 'class', 'shift', 'schedule', 'capacity', 'status',
    ];

    public function index(Request $request): View
    {
        $query = Course::query()
            ->with(['category', 'subjects'])
            ->withCount('batches')
            ->withCount('instituteAssignments as institutes_count')
            ->whereNull('deleted_at')
            ->whereHas('category', fn (Builder $q) => $q->where('subject_type', 'academic'))
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('course_code', 'like', "%{$q}%");
                });
            })
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('category_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name');

        $classes = (clone $query)->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('class_index_columns', self::CLASSES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::CLASSES_COLUMNS, (array) $visibleColumns));

        return view('admin.classes.index', [
            'classes' => $classes,
            'categories' => CourseCategory::query()->where('subject_type', 'academic')->orderBy('name')->get(['id', 'name']),
            'visibleColumns' => $visibleColumns,
            'classesCount' => $classes->total(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->where('subject_type', 'academic')->count(),
            'batchesCount' => Batch::query()->where('status', '!=', 'archived')->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'archiveCount' => Batch::query()->where('status', 'archived')->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'filters' => [
                'q' => $request->query('q'),
                'category_id' => $request->query('category_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveIndexColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::CLASSES_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('class_index_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function subjects(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Subject::query()
            ->with(['category', 'institute'])
            ->whereNull('deleted_at')
            ->where('subject_type', 'academic')
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('short_name', 'like', "%{$q}%")
                        ->orWhere('subject_code', 'like', "%{$q}%");
                });
            })
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('category_id', (int) $id))
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name');

        $items = (clone $query)->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('class_subjects_columns', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        return view('admin.classes.subjects', [
            'items' => $items,
            'institutes' => $institutes,
            'categories' => CourseCategory::query()->where('subject_type', 'academic')->orderBy('name')->get(['id', 'name']),
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'classesCount' => Course::query()->whereNull('deleted_at')->whereHas('category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'subjectsCount' => $items->total(),
            'batchesCount' => Batch::query()->where('status', '!=', 'archived')->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'archiveCount' => Batch::query()->where('status', 'archived')->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'filters' => [
                'q' => $request->query('q'),
                'category_id' => $request->query('category_id'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveSubjectsColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::SUBJECTS_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('class_subjects_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function batches(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Batch::query()
            ->with(['institute:id,name', 'course:id,name'])
            ->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))
            ->where('status', '!=', 'archived')
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('batch_code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhereHas('course', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at', 'desc');

        $items = (clone $query)->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('class_batches_columns', self::BATCHES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::BATCHES_COLUMNS, (array) $visibleColumns));

        return view('admin.classes.batches', [
            'items' => $items,
            'institutes' => $institutes,
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'classesCount' => Course::query()->whereNull('deleted_at')->whereHas('category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'batchesCount' => $items->total(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->where('subject_type', 'academic')->count(),
            'archiveCount' => Batch::query()->where('status', 'archived')->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveBatchesColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::BATCHES_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('class_batches_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function archive(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Batch::query()
            ->with(['institute:id,name', 'course:id,name'])
            ->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))
            ->where('status', 'archived')
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('batch_code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhereHas('course', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->orderBy('created_at', 'desc');

        $items = (clone $query)->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('class_batches_columns', self::BATCHES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::BATCHES_COLUMNS, (array) $visibleColumns));

        return view('admin.classes.archive', [
            'items' => $items,
            'institutes' => $institutes,
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'classesCount' => Course::query()->whereNull('deleted_at')->whereHas('category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'batchesCount' => Batch::query()->where('status', '!=', 'archived')->whereHas('course.category', fn (Builder $q) => $q->where('subject_type', 'academic'))->count(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->where('subject_type', 'academic')->count(),
            'archiveCount' => $items->total(),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
            ],
        ]);
    }
}
