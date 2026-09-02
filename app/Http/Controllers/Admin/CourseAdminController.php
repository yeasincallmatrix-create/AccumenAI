<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseRequest;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteSubject;
use App\Models\Notification;
use App\Models\Training\Enrollment;
use App\Models\Subject;
use App\Models\SubjectRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseAdminController extends Controller
{
    public const ASSIGNMENT_COLUMNS = [
        'serial', 'code', 'course', 'category', 'level', 'fee', 'discount', 'admission_fee',
        'exam_fee', 'certificate_fee', 'duration', 'weekly_classes', 'total_classes',
        'class_duration', 'mode', 'batch_capacity', 'status', 'assignment', 'action',
    ];

    public const COURSES_INDEX_COLUMNS = [
        'serial', 'code', 'course', 'category', 'level', 'fee', 'subjects', 'batches', 'discount',
        'admission_fee', 'exam_fee', 'certificate_fee', 'duration', 'weekly_classes',
        'total_classes', 'class_duration', 'mode', 'batch_capacity', 'status', 'assignment', 'action',
    ];

    public const REQUESTS_COLUMNS = [
        'serial', 'institute', 'course', 'requested_by', 'status', 'requested_at', 'review_note',
        'reviewed_by', 'reviewed_at', 'updated_at', 'action',
    ];

    public const SUBJECTS_COLUMNS = [
        'serial', 'name', 'code', 'type', 'category', 'institute', 'status',
    ];

    public const SUBJECT_REQUESTS_COLUMNS = [
        'serial', 'institute', 'subject', 'code', 'category', 'requested_by', 'status',
        'requested_at', 'review_note', 'reviewed_by', 'reviewed_at', 'action',
    ];

    public const BATCHES_COLUMNS = [
        'serial', 'batch', 'institute', 'course', 'shift', 'schedule', 'capacity', 'status',
    ];

    public function index(Request $request): View
    {
        $type = $request->query('type') ?: 'professional';

        $coursesQuery = Course::query()
            ->with(['category', 'subjects'])
            ->withCount('batches')
            ->withCount('instituteAssignments as institutes_count')
            ->whereNull('deleted_at')
            ->whereHas('category', fn ($query) => $query->where('subject_type', $type))
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('course_code', 'like', "%{$q}%");
                });
            })
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('category_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name');

        $courses = (clone $coursesQuery)->paginate(20)->withQueryString();

        $allCourses = (clone $coursesQuery)->get();

        $subjectsCount = Subject::query()->whereNull('deleted_at')->where('subject_type', 'professional')->count();

        $visibleColumns = $request->user()->preference('courses_index_columns', self::COURSES_INDEX_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::COURSES_INDEX_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.index', [
            'courses' => $courses,
            'allCourses' => $allCourses,
            'categories' => CourseCategory::query()->orderBy('name')->get(['id', 'name']),
            'visibleColumns' => $visibleColumns,
            'subjectsCount' => $subjectsCount,
            'batchesCount' => Batch::query()->where('status', '!=', 'archived')->count(),
            'archiveCount' => Batch::query()->where('status', 'archived')->count(),
            'assignmentCount' => InstituteCourse::query()->count(),
            'requestsCount' => CourseRequest::query()->where('status', 'pending')->count(),
            'filters' => [
                'q' => $request->query('q'),
                'category_id' => $request->query('category_id'),
                'type' => $type,
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

        $columns = array_values(array_intersect(self::COURSES_INDEX_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('courses_index_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function show(Request $request, Course $course): View
    {
        $course->load(['category', 'subCategory', 'subjects', 'institute']);

        $instituteId = $request->query('institute_id');

        $enrollments = Enrollment::query()
            ->with(['student', 'batch', 'institute'])
            ->whereHas('batch', fn ($q) => $q->where('course_id', $course->id))
            ->when($instituteId, fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $assignedInstitutes = Institute::query()
            ->whereNull('deleted_at')
            ->whereHas('instituteCourses', fn ($query) => $query->where('course_id', $course->id))
            ->orderBy('name')
            ->get(['id', 'name', 'country']);

        return view('admin.courses.show', [
            'course' => $course,
            'enrollments' => $enrollments,
            'assignedInstitutes' => $assignedInstitutes,
            'selectedInstituteId' => $instituteId,
        ]);
    }

    public function assignment(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'country']);

        $selectedInstitute = $institutes->firstWhere('id', (int) $request->query('institute_id'))
            ?? $institutes->first();
        $selectedInstituteId = $selectedInstitute?->id;

        $query = Course::query()
            ->with('category')
            ->whereNull('deleted_at')
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('course_code', 'like', "%{$q}%");
                });
            })
            ->when($request->query('category_id'), fn ($query, $id) => $query->where('category_id', (int) $id))
            ->when($request->query('type'), fn ($query, $type) => $query->whereHas('category', fn ($query) => $query->where('subject_type', $type)))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($selectedInstituteId, function ($query) use ($selectedInstituteId) {
                $query->withExists([
                    'instituteAssignments as is_assigned' => fn ($query) => $query->where('institute_id', $selectedInstituteId),
                ]);
            });

        $courses = (clone $query)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $allCourses = (clone $query)->orderBy('name')->get();

        $assignedCount = $selectedInstituteId
            ? InstituteCourse::query()->where('institute_id', $selectedInstituteId)->count()
            : 0;
        $totalCourses = Course::query()->whereNull('deleted_at')->count();

        $visibleColumns = $request->user()->preference('assignment_columns', self::ASSIGNMENT_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::ASSIGNMENT_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.assignment', [
            'institutes' => $institutes,
            'selectedInstitute' => $selectedInstitute,
            'courses' => $courses,
            'allCourses' => $allCourses,
            'categories' => CourseCategory::query()->orderBy('name')->get(['id', 'name']),
            'assignedCount' => $assignedCount,
            'notAssignedCount' => max($totalCourses - $assignedCount, 0),
            'coursesCount' => $totalCourses,
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->count(),
            'batchesCount' => Batch::query()->count(),
            'assignmentCount' => InstituteCourse::query()->count(),
            'requestsCount' => CourseRequest::query()->where('status', 'pending')->count(),
            'visibleColumns' => $visibleColumns,
            'filters' => [
                'q' => $request->query('q'),
                'category_id' => $request->query('category_id'),
                'type' => $request->query('type'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveAssignmentColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::ASSIGNMENT_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('assignment_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function assignmentAssign(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        InstituteCourse::firstOrCreate(
            ['institute_id' => $data['institute_id'], 'course_id' => $data['course_id']],
            ['assigned_by' => $request->user()->id]
        );

        $this->syncCategorySubjects(
            $data['institute_id'],
            Course::findOrFail($data['course_id'])?->category_id,
            $request->user()->id
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course assigned successfully.',
                'data' => $data,
            ]);
        }

        return redirect()
            ->route('admin.courses.assignment', ['institute_id' => $data['institute_id'], 'industry' => 'education'])
            ->with('status', 'Course assigned successfully.');
    }

    public function assignmentRemove(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        InstituteCourse::query()
            ->where('institute_id', $data['institute_id'])
            ->where('course_id', $data['course_id'])
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course removed successfully.',
                'data' => $data,
            ]);
        }

        return redirect()
            ->route('admin.courses.assignment', ['institute_id' => $data['institute_id'], 'industry' => 'education'])
            ->with('status', 'Course removed successfully.');
    }

    public function requests(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = CourseRequest::query()
            ->with(['institute', 'course', 'requestedBy', 'reviewedBy'])
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->whereHas('institute', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('course', fn ($query) => $query
                            ->where('name', 'like', "%{$q}%")
                            ->orWhere('course_code', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id');

        $items = (clone $query)->paginate(20)->withQueryString();

        $allItems = (clone $query)->get();

        $pendingCount = CourseRequest::query()->where('status', 'pending')->count();

        $visibleColumns = $request->user()->preference('requests_columns', self::REQUESTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::REQUESTS_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.requests', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'pendingCount' => $pendingCount,
            'coursesCount' => Course::query()->whereNull('deleted_at')->count(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->count(),
            'batchesCount' => Batch::query()->count(),
            'assignmentCount' => InstituteCourse::query()->count(),
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'visibleColumns' => $visibleColumns,
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveRequestsColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::REQUESTS_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('requests_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function subjects(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $type = $request->query('type') ?: 'professional';

        $query = Subject::query()
            ->with(['category', 'institute'])
            ->whereNull('deleted_at')
            ->where('subject_type', $type)
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

        $allItems = (clone $query)->get();

        $visibleColumns = $request->user()->preference('subjects_columns', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.subjects', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'categories' => CourseCategory::query()->orderBy('name')->get(['id', 'name']),
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'coursesCount' => Course::query()->whereNull('deleted_at')->whereHas('category', fn ($c) => $c->where('subject_type', 'professional'))->count(),
            'subjectsCount' => $items->total(),
            'batchesCount' => Batch::query()->where('status', '!=', 'archived')->count(),
            'archiveCount' => Batch::query()->where('status', 'archived')->count(),
            'assignmentCount' => InstituteCourse::query()->count(),
            'requestsCount' => CourseRequest::query()->where('status', 'pending')->count(),
            'filters' => [
                'q' => $request->query('q'),
                'category_id' => $request->query('category_id'),
                'institute_id' => $request->query('institute_id'),
                'type' => $type,
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
        $request->user()->setPreference('subjects_columns', $columns);

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

        $allItems = (clone $query)->get();

        $visibleColumns = $request->user()->preference('batches_columns', self::BATCHES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::BATCHES_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.batches', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'coursesCount' => Course::query()->whereNull('deleted_at')->count(),
            'batchesCount' => $items->total(),
            'archiveCount' => Batch::query()->where('status', 'archived')->count(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->where('subject_type', 'professional')->count(),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function archive(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Batch::query()
            ->with(['institute:id,name', 'course:id,name'])
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

        $allItems = (clone $query)->get();

        $visibleColumns = $request->user()->preference('batches_columns', self::BATCHES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::BATCHES_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.archive', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'visibleColumns' => $visibleColumns,
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'coursesCount' => Course::query()->whereNull('deleted_at')->count(),
            'batchesCount' => Batch::query()->where('status', '!=', 'archived')->count(),
            'archiveCount' => $items->total(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->where('subject_type', 'professional')->count(),
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
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
        $request->user()->setPreference('batches_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function requestAction(Request $request, CourseRequest $courseRequest): RedirectResponse|JsonResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'note'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $action = $request->input('action');
        $admin = $request->user();

        if ($action === 'note') {
            $courseRequest->update(['review_note' => $request->input('review_note')]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Review note updated.',
                ]);
            }

            return redirect()->route('admin.courses.requests', ['industry' => 'education'])->with('status', 'Review note updated.');
        }

        if ($courseRequest->status !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request was already reviewed.',
                ], 422);
            }

            return redirect()->route('admin.courses.requests', ['industry' => 'education'])->with('status', 'This request was already reviewed.');
        }

        if ($action === 'approve') {
            InstituteCourse::firstOrCreate(
                ['institute_id' => $courseRequest->institute_id, 'course_id' => $courseRequest->course_id],
                ['assigned_by' => $admin->id]
            );

            $this->syncCategorySubjects(
                $courseRequest->institute_id,
                $courseRequest->course?->category_id,
                $admin->id
            );

            $courseRequest->update([
                'status' => 'approved',
                'review_note' => $request->input('review_note'),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->notifyInstitute(
                $courseRequest->institute_id,
                'course_request',
                'Course request approved',
                'Your request for '.($courseRequest->course->name ?? 'a course').' has been approved.'
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Request approved and course assigned.',
                ]);
            }

            return redirect()->route('admin.courses.requests', ['industry' => 'education'])->with('status', 'Request approved and course assigned.');
        }

        $courseRequest->update([
            'status' => 'rejected',
            'review_note' => $request->input('review_note'),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyInstitute(
            $courseRequest->institute_id,
            'course_request',
            'Course request rejected',
            'Your request for '.($courseRequest->course->name ?? 'a course').' was rejected.'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Request rejected.',
            ]);
        }

        return redirect()->route('admin.courses.requests', ['industry' => 'education'])->with('status', 'Request rejected.');
    }

    public function subjectRequests(Request $request): View
    {
        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = SubjectRequest::query()
            ->with(['institute', 'category', 'requestedBy', 'reviewedBy'])
            ->when($request->query('q'), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('short_name', 'like', "%{$q}%")
                        ->orWhere('subject_code', 'like', "%{$q}%")
                        ->orWhereHas('institute', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($request->query('institute_id'), fn ($query, $id) => $query->where('institute_id', (int) $id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id');

        $items = (clone $query)->paginate(20)->withQueryString();

        $allItems = (clone $query)->get();

        $pendingCount = SubjectRequest::query()->where('status', 'pending')->count();

        $visibleColumns = $request->user()->preference('subject_requests_columns', self::SUBJECT_REQUESTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECT_REQUESTS_COLUMNS, (array) $visibleColumns));

        return view('admin.courses.subject_requests', [
            'items' => $items,
            'allItems' => $allItems,
            'institutes' => $institutes,
            'pendingCount' => $pendingCount,
            'subjectRequestsCount' => $pendingCount,
            'coursesCount' => Course::query()->whereNull('deleted_at')->count(),
            'subjectsCount' => Subject::query()->whereNull('deleted_at')->count(),
            'batchesCount' => Batch::query()->count(),
            'archiveCount' => Batch::query()->where('status', 'archived')->count(),
            'assignmentCount' => InstituteCourse::query()->count(),
            'requestsCount' => CourseRequest::query()->where('status', 'pending')->count(),
            'selectedInstituteId' => (int) $request->query('institute_id'),
            'visibleColumns' => $visibleColumns,
            'filters' => [
                'q' => $request->query('q'),
                'institute_id' => $request->query('institute_id'),
                'status' => $request->query('status'),
            ],
        ]);
    }

    public function saveSubjectRequestsColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::SUBJECT_REQUESTS_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('subject_requests_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function subjectRequestsAction(Request $request, SubjectRequest $subjectRequest): RedirectResponse|JsonResponse
    {
        $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'note'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $action = $request->input('action');
        $admin = $request->user();

        if ($action === 'note') {
            $subjectRequest->update(['review_note' => $request->input('review_note')]);

            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => 'Review note updated.'])
                : redirect()->route('admin.courses.subjects-requests', ['industry' => 'education'])->with('status', 'Review note updated.');
        }

        if ($subjectRequest->status !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This request was already reviewed.',
                ], 422);
            }

            return redirect()->route('admin.courses.subjects-requests', ['industry' => 'education'])->with('status', 'This request was already reviewed.');
        }

        if ($action === 'approve') {
            $code = $subjectRequest->subject_code;
            if (blank($code)) {
                do {
                    $code = 'SUB'.mt_rand(100000, 999999);
                } while (Subject::query()->where('subject_code', $code)->exists());
            }

            $subject = Subject::create([
                'institute_id' => $subjectRequest->institute_id,
                'category_id' => $subjectRequest->category_id,
                'subject_type' => $subjectRequest->subject_type ?: 'professional',
                'subject_code' => $code,
                'name' => $subjectRequest->name,
                'slug' => Str::slug($subjectRequest->name.'-'.strtolower(Str::random(6))),
                'short_name' => $subjectRequest->short_name,
                'description' => $subjectRequest->description,
                'status' => 'active',
            ]);

            $isAcademic = $subjectRequest->subject_type === 'academic';

            InstituteSubject::firstOrCreate(
                ['institute_id' => $subjectRequest->institute_id, 'subject_id' => $subject->id],
                ['assigned_by' => $admin->id, 'status' => 'active', 'is_custom' => $isAcademic]
            );

            $subjectRequest->update([
                'status' => 'approved',
                'review_note' => $request->input('review_note'),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->notifyInstitute(
                $subjectRequest->institute_id,
                'subject_request',
                'Subject request approved',
                'Your subject "'.$subjectRequest->name.'" has been approved and is now available.'
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Request approved and subject created.',
                ]);
            }

            return redirect()->route('admin.courses.subjects-requests', ['industry' => 'education'])->with('status', 'Request approved and subject created.');
        }

        $subjectRequest->update([
            'status' => 'rejected',
            'review_note' => $request->input('review_note'),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyInstitute(
            $subjectRequest->institute_id,
            'subject_request',
            'Subject request rejected',
            'Your request for "'.$subjectRequest->name.'" was rejected.'
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Request rejected.',
            ]);
        }

        return redirect()->route('admin.courses.subjects-requests', ['industry' => 'education'])->with('status', 'Request rejected.');
    }

    protected function syncCategorySubjects(int $instituteId, ?int $categoryId, int $assignedBy): void
    {
        if ($categoryId === null) {
            return;
        }

        Subject::query()
            ->where('category_id', $categoryId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->each(fn ($subjectId) => InstituteSubject::firstOrCreate(
                ['institute_id' => $instituteId, 'subject_id' => $subjectId],
                ['assigned_by' => $assignedBy]
            ));
    }

    protected function notifyInstitute(int $instituteId, string $category, string $title, string $message): void
    {
        Notification::create([
            'scope' => 'institute',
            'institute_id' => $instituteId,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'created_by_type' => 'platform_admin',
            'created_by_id' => auth('platform_admin')->id(),
            'created_at' => now(),
        ]);
    }
}
