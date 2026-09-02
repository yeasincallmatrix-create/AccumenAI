<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseCurriculum;
use App\Models\Exam;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteSubject;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Training\Enrollment;
use App\Models\Subject;
use App\Models\TeacherAcademicAssignment;
use App\Services\Education\BatchLifecycleService;
use App\Support\InstituteDomain;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BatchController extends Controller
{
    private const SHIFTS = ['morning', 'day', 'evening', 'weekend', 'online'];

    private const STATUSES = ['upcoming', 'ongoing', 'completed', 'cancelled', 'archived'];

    public const BATCH_COLUMNS = ['serial', 'code', 'name', 'course', 'shift', 'start', 'seats', 'status', 'action'];

    public function __construct(private readonly BatchLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $query = Batch::query()
            ->with(['course:id,name', 'course.subjects:id,name', 'academicYear:id,name,code'])
            ->withCount(['exams as attended_exams' => fn ($q) => $q->whereHas('results')])
            ->when($request->query('course_id'), function (Builder $q, $courseId) {
                $q->where('course_id', $courseId);
            })
            ->when($request->query('branch_id'), function (Builder $q, $branchId) {
                $q->where('branch_id', $branchId);
            })
            ->when($request->query('academic_year_id'), function (Builder $q, $yearId) {
                $q->where('academic_year_id', (int) $yearId);
            })
            ->when($request->query('instructor_id'), function (Builder $q, $instructorId) {
                $q->whereIn('id', TeacherAcademicAssignment::query()
                    ->where('institute_user_id', (int) $instructorId)
                    ->where('status', 'active')
                    ->whereNotNull('batch_id')
                    ->pluck('batch_id'));
            })
            ->when($request->query('status'), function (Builder $q, $status) {
                $q->where('status', $status);
            })
            ->when($request->query('q'), function (Builder $q, $search) {
                $q->where(function (Builder $w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('batch_code', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $batches = (clone $query)->paginate(20)->withQueryString();
        $allBatches = (clone $query)->get();

        $instructors = InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $statusCounts = $allBatches->groupBy('status')->map->count();
        $capacity = (int) $allBatches->sum('seat_capacity');

        // Seat occupancy is the authoritative count of active enrollments, not
        // the legacy seat_filled counter (which can drift on older data).
        $filled = (int) Enrollment::query()
            ->whereIn('batch_id', $allBatches->pluck('id'))
            ->where('status', 'active')
            ->count();

        return view('batches.index', [
            'batches' => $batches,
            'allBatches' => $allBatches,
            'courses' => $this->courses($instituteId),
            'q' => $request->query('q'),
            'courseId' => $request->query('course_id'),
            'status' => $request->query('status'),
            'branchId' => $request->query('branch_id'),
            'academicYearId' => $request->query('academic_year_id'),
            'instructorId' => $request->query('instructor_id'),
            'academicYears' => AcademicYear::query()
                ->where('institute_id', $instituteId)
                ->orderByDesc('code')
                ->get(['id', 'name', 'code']),
            'instructors' => $instructors,
            'summaryStats' => [
                'total' => $allBatches->count(),
                'ongoing' => ($statusCounts['ongoing'] ?? 0) + ($statusCounts['running'] ?? 0),
                'running' => ($statusCounts['ongoing'] ?? 0) + ($statusCounts['running'] ?? 0),
                'upcoming' => $statusCounts['upcoming'] ?? 0,
                'completed' => $statusCounts['completed'] ?? 0,
                'cancelled' => $statusCounts['cancelled'] ?? 0,
                'archived' => $statusCounts['archived'] ?? 0,
                'capacity' => $capacity,
                'filled' => $filled,
                'available' => max(0, $capacity - $filled),
                'instructors' => $instructors->count(),
            ],
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'coursesCount' => Course::query()
                ->where('institute_id', $instituteId)
                ->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId))
                ->count(),
            'subjectsCount' => Subject::query()
                ->where('institute_id', $instituteId)
                ->whereNull('deleted_at')
                ->where('subject_type', InstituteDomain::subjectTypeFor(Institute::find($instituteId)))
                ->count(),
            'batchesCount' => Batch::query()
                ->where('institute_id', $instituteId)
                ->where('status', '!=', 'archived')
                ->count(),
            'archiveCount' => Batch::query()
                ->where('institute_id', $instituteId)
                ->where('status', 'archived')
                ->count(),
            'visibleColumns' => array_values(array_intersect(
                self::BATCH_COLUMNS,
                (array) $request->user()->preference('columns_batches', self::BATCH_COLUMNS)
            )),
            'editData' => collect($batches->items())->mapWithKeys(fn (Batch $batch) => [
                $batch->id => [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'name' => $batch->name,
                    'course_id' => $batch->course_id,
                    'academic_year_id' => $batch->academic_year_id,
                    'teacher_id' => $batch->teacher_id,
                    'shift' => $batch->shift,
                    'start_date' => $batch->start_date ? $batch->start_date->format('Y-m-d') : null,
                    'end_date' => $batch->end_date ? $batch->end_date->format('Y-m-d') : null,
                    'seat_capacity' => $batch->seat_capacity,
                    'status' => $batch->status,
                    'attendance_threshold' => $batch->attendance_threshold ?? 80,
                ],
            ])->all(),
            'sendExamSubjects' => collect($batches->items())->mapWithKeys(fn (Batch $batch) => [
                $batch->id => $batch->course?->subjects?->map(fn (Subject $subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ])->values()->all() ?? [],
            ])->all(),
        ]);
    }

    public function show(Batch $batch): View
    {
        $batch->load([
            'course:id,name,course_code',
            'course.subjects:id,name',
            'branch:id,name',
            'academicYear:id,name,code',
            'teacher.user:id,name',
            'room:id,name',
            'enrollments.student:id,student_id_number,roll_number,first_name,last_name,phone,guardian_phone,status',
            'enrollments.student.branch:id,name',
            'trainingEnrollments.trainee:id,student_id_number,roll_number,first_name,last_name,phone,guardian_phone,status',
            'trainingEnrollments.trainee.branch:id,name',
        ]);

        // Merge legacy and new enrollments for display (handles both tables, deduplicates by student)
        $legacyEnrollments = $batch->enrollments;
        $trainingMapped = $batch->trainingEnrollments->map(function ($e) {
            // Alias training fields to legacy view expectations
            $e->setAttribute('roll_number', $e->roll_no);
            $e->setAttribute('student_id', $e->trainee_id);
            if ($e->relationLoaded('trainee') && $e->trainee) {
                $e->setRelation('student', $e->trainee);
            }
            return $e;
        });
        $mergedEnrollments = collect($legacyEnrollments)->merge(collect($trainingMapped))->unique(fn ($e) => $e->student->id ?? $e->student_id ?? $e->id)->values()->sortBy('roll_number');

        $exams = Exam::query()
            ->where('batch_id', $batch->id)
            ->with('course:id,name')
            ->withCount('results')
            ->latest('id')
            ->get();

        $instituteId = $batch->institute_id;
        $assignedSubjects = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->pluck('subject_id');

        $instructors = TeacherAcademicAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('status', 'active')
            ->with('teacher:id,first_name,last_name,phone')
            ->orderBy('id')
            ->get();

        $derivedType = InstituteDomain::subjectTypeFor(Institute::find($instituteId));
        $availableSubjects = Subject::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $derivedType)
            ->whereNull('deleted_at')
            ->when($assignedSubjects->isNotEmpty(), fn ($q) => $q->whereIn('id', $assignedSubjects))
            ->when($batch->course?->category_id, fn ($q) => $q->where('category_id', $batch->course->category_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Subject $subject) => ['id' => $subject->id, 'name' => $subject->name])
            ->values()
            ->all();

        return view('batches.show', [
            'batch' => $batch,
            'enrollments' => $mergedEnrollments,
            'exams' => $exams,
            'courses' => $this->courses($batch->institute_id),
            'academicYears' => AcademicYear::query()
                ->where('institute_id', $instituteId)
                ->orderByDesc('code')
                ->get(['id', 'name', 'code']),
            'instructors' => $instructors,
            'allowedTransitions' => $this->lifecycle->allowedTransitions($batch),
            'availableSeats' => $this->lifecycle->availableSeats($batch),
            'sendExamSubjects' => [
                $batch->id => $batch->course?->subjects?->map(fn (Subject $subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ])->values()->all() ?? [],
            ],
            'subjectOptions' => $availableSubjects,
            'attachedSubjectIds' => $batch->course?->subjects?->pluck('id')->map(fn ($id) => (int) $id)->values()->all() ?? [],
            'subjectCourse' => $batch->course,
            'transferTargets' => Batch::query()
                ->where('course_id', $batch->course_id)
                ->where('id', '!=', $batch->id)
                ->where('status', '!=', 'cancelled')
                ->orderBy('name')
                ->get(['id', 'name', 'batch_code']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $data = $this->validated($request);

        // Capacity validation is handled in validated() via seat_capacity, but also check against existing enrollments if needed (create has no existing)
        if (isset($data['seat_capacity']) && $data['seat_capacity'] < 1) {
            throw ValidationException::withMessages(['seat_capacity' => 'Capacity must be at least 1.']);
        }

        try {
            $batch = $this->lifecycle->create((int) $user->institute_id, $data, (int) $user->id);
        } catch (ValidationException $e) {
            return $this->handleLifecycleException($e, $request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('batches.created'),
                'data' => ['id' => $batch->id, 'redirect' => route('batches.show', $batch->id)],
            ]);
        }

        return redirect()->route('batches.show', $batch->id)->with('status', 'Batch created. Add trainees now!');
    }

    public function update(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        // Status changes do NOT cascade to ExamResult or Certificate records.
        // Those records are linked via enrollments, not directly to Batch status.
        // Only the batches.status column is updated; existing results/certificates are preserved.
        $user = $request->user();

        $data = $this->validated($request, true);

        // Capacity cannot be less than current enrollment count
        if (isset($data['seat_capacity']) && $data['seat_capacity'] !== null) {
            $currentCount = Enrollment::where('batch_id', $batch->id)->where('status', 'active')->count();
            if ((int) $data['seat_capacity'] < $currentCount) {
                throw ValidationException::withMessages(['seat_capacity' => "Capacity cannot be less than the current enrollment count ({$currentCount})."]);
            }
        }

        try {
            $this->lifecycle->update($batch, $data, (int) $user->id);
        } catch (ValidationException $e) {
            return $this->handleLifecycleException($e, $request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('batches.updated'),
                'data' => ['id' => $batch->id],
            ]);
        }

        return redirect()->route('batches.index')->with('status', mawa_lang('batches.updated'));
    }

    public function destroy(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        $attended = Exam::query()
            ->where('batch_id', $batch->id)
            ->whereHas('results')
            ->exists();

        if ($attended) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('batches.cannot_delete_attended'),
                ], 422);
            }

            return back()->withErrors(['batch' => mawa_lang('batches.cannot_delete_attended')]);
        }

        $user = $request->user();

        $this->lifecycle->remove($batch, (int) $user->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('batches.deleted'),
                'data' => ['id' => $batch->id],
            ]);
        }

        return redirect()->route('batches.index')->with('status', mawa_lang('batches.deleted'));
    }

    public function archive(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $batch, 'archived');
    }

    public function unarchive(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $batch, 'ongoing');
    }

    public function changeStatus(Request $request, Batch $batch, ?string $force = null): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        // Backward compatibility: normalize legacy 'running' to 'ongoing'
        if ($force === 'running') {
            $force = 'ongoing';
        }
        if ($request->has('status') && $request->input('status') === 'running') {
            $request->merge(['status' => 'ongoing']);
        }

        if ($force !== null) {
            $status = $force;
        } else {
            $status = $request->validate([
                'status' => ['required', Rule::in(BatchLifecycleService::STATUSES)],
            ])['status'];
        }

        try {
            $this->lifecycle->changeStatus($batch, $status, (int) $user->id);
        } catch (ValidationException $e) {
            return $this->handleLifecycleException($e, $request);
        }

        $message = match ($status) {
            'ongoing', 'running' => mawa_lang('batches.started'),
            'completed' => mawa_lang('batches.completed'),
            'cancelled' => mawa_lang('batches.cancelled'),
            'archived' => mawa_lang('batches.archived'),
            default => mawa_lang('batches.updated'),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $batch->id],
            ]);
        }

        return redirect()->back()->with('status', $message);
    }

    /**
     * Transfer an enrolled student to another batch of the same institute.
     * The source enrollment is marked 'transferred' and a fresh active
     * enrollment is created in the target batch.
     */
    public function transferStudent(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        $instituteId = $request->user()->institute_id;

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'target_batch_id' => [
                'required',
                'integer',
                Rule::exists('batches', 'id')->where('institute_id', $instituteId),
            ],
        ]);

        if ((int) $data['target_batch_id'] === (int) $batch->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('batches.save_failed'),
                ], 422);
            }

            return back()->withErrors(['target_batch_id' => mawa_lang('batches.transfer_confirm')]);
        }

        $target = Batch::findOrFail((int) $data['target_batch_id']);

        $enrollment = Enrollment::query()
            ->where('institute_id', $instituteId)
            ->where('batch_id', $batch->id)
            ->where('student_id', (int) $data['student_id'])
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('batches.save_failed'),
                ], 422);
            }

            return back()->withErrors(['student_id' => mawa_lang('batches.save_failed')]);
        }

        try {
            $this->lifecycle->transferBetween($enrollment, $target, $instituteId, (int) $request->user()->id);
        } catch (ValidationException $e) {
            $message = $e->errors()['target_batch_id'][0] ?? mawa_lang('batches.save_failed');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return back()->withErrors(['target_batch_id' => $message]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('batches.transferred'),
                'data' => ['batch_id' => $batch->id],
            ]);
        }

        return redirect()->route('batches.show', $batch)->with('status', mawa_lang('batches.transferred'));
    }

    /**
     * Remove a student from this batch. The enrollment row is soft-removed
     * by marking it 'dropped' and the batch seat counter is freed up.
     */
    public function removeStudent(Request $request, Batch $batch, Student $student): RedirectResponse|JsonResponse
    {
        $instituteId = $request->user()->institute_id;

        $enrollment = Enrollment::query()
            ->where('institute_id', $instituteId)
            ->where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $enrollment) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('batches.save_failed'),
                ], 422);
            }

            return back()->withErrors(['student_id' => mawa_lang('batches.save_failed')]);
        }

        $this->lifecycle->dropStudent($enrollment, (int) $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('batches.removed'),
                'data' => ['batch_id' => $batch->id],
            ]);
        }

        return redirect()->route('batches.show', $batch)->with('status', mawa_lang('batches.removed'));
    }

    /**
     * Shared validation for create/update. Batch codes are generated server-side.
     *
     * A submitted curriculum_id must be the active version of the chosen course.
     * On create, the active curriculum is auto-assigned when none is given; on
     * update, an omitted curriculum_id preserves the batch's existing version
     * (existing batches are never silently rewired to a newer version).
     */
    private function validated(Request $request, bool $preserveOnMissing = false): array
    {
        // Backward compatibility: normalize legacy 'running' to 'ongoing' before validation
        if ($request->has('status') && $request->input('status') === 'running') {
            $request->merge(['status' => 'ongoing']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'curriculum_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer', Rule::exists('institute_users', 'id')],
            'shift' => ['required', Rule::in(self::SHIFTS)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'attendance_threshold' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $instituteId = (int) $request->user()->institute_id;
        $courseId = (int) $data['course_id'];

        if (filled($data['curriculum_id'] ?? null)) {
            $curriculum = CourseCurriculum::query()
                ->where('institute_id', $instituteId)
                ->where('course_id', $courseId)
                ->where('status', CourseCurriculum::STATUS_ACTIVE)
                ->find((int) $data['curriculum_id']);

            if ($curriculum === null) {
                throw ValidationException::withMessages([
                    'curriculum_id' => 'The selected curriculum is not the active version of this course.',
                ]);
            }

            $data['curriculum_id'] = $curriculum->id;
        } elseif (! $preserveOnMissing) {
            $active = CourseCurriculum::query()
                ->where('institute_id', $instituteId)
                ->where('course_id', $courseId)
                ->where('status', CourseCurriculum::STATUS_ACTIVE)
                ->latest('version')
                ->first();

            $data['curriculum_id'] = $active?->id;
        }

        return $data;
    }

    /**
     * Service-level rule violations (cross-tenant course / year, invalid status
     * transitions) are surfaced exactly like request validation errors.
     */
    private function handleLifecycleException(ValidationException $e, Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return back()->withInput()->withErrors($e->errors());
    }

    /**
     * Courses this institute can assign a batch to. Falls back to tenant-owned
     * courses when the institute hasn't assembled an assignment list — never
     * leaks other tenants' catalog data.
     */
    private function courses(int $instituteId): Collection
    {
        $courses = InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->with('course:id,name')
            ->get()
            ->pluck('course')
            ->filter()
            ->values();

        if ($courses->isNotEmpty()) {
            return $courses;
        }

        return Course::query()
            ->where('institute_id', $instituteId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Category ids for the active domain subject type — tenant-scoped and
     * domain-derived via InstituteDomain (no global bypass).
     */
    private function categoryIdsBySubjectType(int $instituteId): array
    {
        $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));

        return CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $derived)
            ->pluck('id')
            ->all();
    }
}
