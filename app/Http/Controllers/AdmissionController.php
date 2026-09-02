<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdmissionApplicationRequest;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\AdmissionWorkflowService;
use App\Services\EducationCrmIntegrationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admission / application intake for the General Education student lifecycle.
 *
 * An application IS a students row driven by admission_status — there is no
 * parallel entity, so conversion to a full student is completing the profile +
 * approving. Institute identity comes only from the authenticated user; every
 * referenced entity (branch, course, academic year) is validated against the
 * actor's institute. Tenant + branch isolation is inherited from the Student
 * global scopes, so a branch-restricted user only ever reaches applicants of
 * their own branch.
 */
class AdmissionController extends Controller
{
    public function __construct(
        private readonly AdmissionWorkflowService $workflow,
        private readonly EducationCrmIntegrationService $crmIntegration,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = $request->user()->institute_id;

        $admissions = Student::query()
            ->with(['branch', 'appliedCourse', 'appliedAcademicYear'])
            ->search($request->query('q'))
            ->when($request->query('admission_status'), fn ($query, $status) => $query->where('admission_status', $status))
            ->when($request->query('course_id'), fn ($query, $courseId) => $query->where('applied_course_id', (int) $courseId))
            ->when($request->query('academic_year_id'), fn ($query, $yearId) => $query->where('applied_academic_year_id', (int) $yearId))
            ->when($request->query('branch_id'), fn ($query, $branchId) => $query->where('branch_id', (int) $branchId))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admissions.index', [
            'admissions' => $admissions,
            'q' => $request->query('q'),
            'admissionStatus' => $request->query('admission_status'),
            'courseId' => $request->query('course_id'),
            'academicYearId' => $request->query('academic_year_id'),
            'branchId' => $request->query('branch_id'),
            'branches' => $this->branches($instituteId),
            'courses' => $this->courses($instituteId),
            'academicYears' => $this->academicYears($instituteId),
            'statuses' => Student::ADMISSION_STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $instituteId = $request->user()->institute_id;

        return view('admissions.form', [
            'student' => new Student(['institute_id' => $instituteId, 'admission_status' => Student::ADMISSION_STATUS_DRAFT]),
            'branches' => $this->branches($instituteId),
            'courses' => $this->courses($instituteId),
            'academicYears' => $this->academicYears($instituteId),
            'countries' => config('countries'),
        ]);
    }

    public function store(AdmissionApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $instituteId = $user->institute_id;

        $data = $request->validated();
        $data['institute_id'] = $instituteId;
        $data['student_id_number'] = Student::nextStudentNumber($instituteId);
        $data['admission_date'] = $data['application_date'];
        $data['created_by'] = $user->id;

        // Path A: Owner/Admin creates directly → auto-approve
        // Path B: Staff/Teacher creates → submit for approval
        $canDirectApprove = $user instanceof InstituteUser
            && AdmissionWorkflowService::userCanDirectlyApprove($user);

        if ($canDirectApprove) {
            $data['admission_status'] = Student::ADMISSION_STATUS_APPROVED;
            $data['status'] = Student::STATUS_ACTIVE;
        } else {
            $data['admission_status'] = Student::ADMISSION_STATUS_SUBMITTED;
            $data['status'] = Student::STATUS_ACTIVE;
        }

        try {
            $student = Student::create($data);
        } catch (QueryException $e) {
            $data['student_id_number'] = (string) ((int) $data['student_id_number'] + 1);
            $student = Student::create($data);
        }

        $student->update([
            'application_number' => 'AP-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT),
        ]);

        if ($canDirectApprove) {
            $student->update(['approved_by' => $user->id, 'approved_at' => now()]);
        } else {
            // Staff/Teacher: notify approvers
            $this->workflow->notifyPendingApproval($student, (int) $user->id, $instituteId);
        }

        // CRM integration (best-effort, never fails admission)
        try {
            if ($user->hasPermission('crm.create')) {
                $this->crmIntegration->ensureStudentCrmLink($student, $student->branch_id, (int) $user->id);
                $this->crmIntegration->captureAdmissionLead($student, $student->branch_id, (int) $user->id);
            }
        } catch (\Throwable $e) {
            Log::warning('Education→CRM admission integration skipped.', ['student_id' => $student->id, 'error' => $e->getMessage()]);
        }

        return redirect()
            ->route('admissions.show', $student)
            ->with('status', 'Admission application '.$student->application_number.' created.');
    }

    public function show(Request $request, Student $student): View
    {
        $student->load(['branch', 'appliedCourse', 'appliedAcademicYear', 'preferredBatch', 'crmLead', 'crmContact']);

        return view('admissions.show', [
            'student' => $student,
            'nextStatuses' => AdmissionWorkflowService::manualNextStatuses($student),
        ]);
    }

    public function edit(Request $request, Student $student): View
    {
        $instituteId = $request->user()->institute_id;

        return view('admissions.form', [
            'student' => $student,
            'branches' => $this->branches($instituteId),
            'courses' => $this->courses($instituteId),
            'academicYears' => $this->academicYears($instituteId),
            'countries' => config('countries'),
        ]);
    }

    public function update(AdmissionApplicationRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()
            ->route('admissions.show', $student)
            ->with('status', 'Admission application '.$student->application_number.' updated.');
    }

    public function transition(Request $request, Student $student): RedirectResponse
    {
        $allowed = AdmissionWorkflowService::manualNextStatuses($student);

        $target = (string) $request->input('status');
        $needsReason = in_array($target, [Student::ADMISSION_STATUS_REJECTED, Student::ADMISSION_STATUS_CANCELLED], true);

        $data = $request->validate([
            'status' => ['required', Rule::in($allowed)],
            'reason' => [$needsReason ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        $student = $this->workflow->transition(
            $student,
            $data['status'],
            $data['reason'] ?? null,
            (int) $request->user()->id,
            $request->user()->institute_id,
        );

        return back()->with('status', "Admission moved to {$student->admission_status}.");
    }

    /**
     * Pending admissions queue — only visible to users with admission.approve.
     */
    public function pending(Request $request): View
    {
        $instituteId = $request->user()->institute_id;

        $pending = Student::query()
            ->with(['branch', 'appliedCourse', 'preferredBatch', 'creator'])
            ->whereIn('admission_status', [
                Student::ADMISSION_STATUS_SUBMITTED,
                Student::ADMISSION_STATUS_UNDER_REVIEW,
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admissions.pending', [
            'admissions' => $pending,
        ]);
    }

    /**
     * Review page for a single pending admission.
     */
    public function review(Request $request, Student $student): View
    {
        $student->load(['branch', 'appliedCourse', 'appliedAcademicYear', 'preferredBatch', 'admissionAssignedUser', 'creator']);

        return view('admissions.review', [
            'student' => $student,
        ]);
    }

    /**
     * Approve a pending admission.
     */
    public function approve(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user();

        $student = $this->workflow->approve(
            $student,
            (int) $user->id,
            $user->institute_id,
        );

        return redirect()
            ->route('admissions.show', $student)
            ->with('status', 'Admission '.$student->application_number.' approved.');
    }

    /**
     * Reject a pending admission.
     */
    public function reject(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $student = $this->workflow->reject(
            $student,
            (int) $user->id,
            $user->institute_id,
            $data['reason'],
        );

        return redirect()
            ->route('admissions.pending')
            ->with('status', 'Admission '.$student->application_number.' rejected.');
    }

    /**
     * Count of pending admissions for badge display.
     */
    public static function pendingCount(int $instituteId): int
    {
        return Student::query()
            ->where('institute_id', $instituteId)
            ->whereIn('admission_status', [
                Student::ADMISSION_STATUS_SUBMITTED,
                Student::ADMISSION_STATUS_UNDER_REVIEW,
            ])
            ->count();
    }

    private function branches(int $instituteId): Collection
    {
        return Branch::where('institute_id', $instituteId)->orderBy('name')->get();
    }

    private function courses(int $instituteId): Collection
    {
        return InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->with('course')
            ->get()
            ->map(fn (InstituteCourse $assignment) => $assignment->course)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function academicYears(int $instituteId): Collection
    {
        return AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->orderByDesc('code')
            ->get();
    }
}
