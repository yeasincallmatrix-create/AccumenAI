<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\InstituteSetting;
use App\Models\Notification;
use App\Models\Student;
use App\Services\CertificateApprovalModeService;
use App\Services\Notification\NotificationService;
use App\Services\StudentAcademicCertificateRequestService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public const CERTIFICATES_COLUMNS = ['serial', 'certificate_no', 'student', 'course', 'batch', 'issue_date', 'status'];

    public function __construct(
        private readonly StudentAcademicCertificateRequestService $certificateRequests,
        private readonly CertificateApprovalModeService $approvalModeService,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');
        $branchId = $request->query('branch_id');

        $certificates = Certificate::query()
            ->with('student', 'course', 'batch', 'type')
            ->when(BranchContext::enabled(), function ($query) {
                return $query->whereHas('student', fn ($q) => $q->where('branch_id', BranchContext::id()));
            })
            ->when($branchId, function ($query) use ($branchId) {
                return $query->whereHas('student', fn ($q) => $q->where('branch_id', $branchId));
            })
            ->when($q !== '', function ($query) use ($q) {
                return $query->where(function ($where) use ($q) {
                    return $where->where('certificate_number', 'like', "%{$q}%")
                        ->orWhereHas('student', fn ($student) => $student->search($q))
                        ->orWhereHas('course', fn ($course) => $course->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $visibleColumns = $request->user()->preference('columns_certificates', self::CERTIFICATES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::CERTIFICATES_COLUMNS, (array) $visibleColumns));

        $instituteId = $request->user()->institute_id;
        $approvalMode = $this->approvalModeService->getMode($instituteId);
        $isProfessionalCert = \App\Support\InstituteDomain::isProfessional(\App\Models\Institute::find($instituteId));
        $trainingBatches = $isProfessionalCert ? \App\Models\Batch::where('institute_id', $instituteId)->whereIn('status', ['completed','ongoing','upcoming'])->orderBy('name')->get(['id','name','batch_code']) : collect();
        $selectedCertBatchId = $isProfessionalCert ? (int) ($request->query('batch_id') ?? $trainingBatches->first()?->id) : null;
        $certTrainees = collect();
        if ($isProfessionalCert && $selectedCertBatchId) {
            $enrolls = \App\Models\Training\Enrollment::where('batch_id', $selectedCertBatchId)->where('institute_id', $instituteId)->with('student')->get();
            $certTrainees = $enrolls->map(function($enr) use ($selectedCertBatchId) {
                $traineeId = $enr->trainee_id ?? $enr->student_id;
                // Student ID for Attendance/Certificate FK — for Training, trainee_id is users.id but attendance/certificates use students.id
                // We use traineeId directly; if a Student record exists with matching email we could map, but fallback is traineeId
                $studentId = $traineeId;

                // 1. REAL attendance percentage
                $presentCount = \App\Models\Attendance::where('batch_id', $selectedCertBatchId)
                    ->where('student_id', $studentId)
                    ->where('status', 'present')
                    ->count();
                $totalDays = \App\Models\Attendance::where('batch_id', $selectedCertBatchId)
                    ->where('student_id', $studentId)
                    ->count();
                $attendance = $totalDays > 0 ? (int) round(($presentCount / $totalDays) * 100) : 0;

                // 2. Check ALL exams for this batch (not just first)
                $exams = \App\Models\Exam::where('batch_id', $selectedCertBatchId)->get();
                $allPassed = true;
                if ($exams->isEmpty()) {
                    $allPassed = false;
                } else {
                    foreach ($exams as $exam) {
                        $hasPass = \App\Models\ExamResult::where('exam_id', $exam->id)
                            ->where('student_id', $studentId)
                            ->where('result_status', 'pass')
                            ->exists();
                        if (!$hasPass) {
                            $allPassed = false;
                            break;
                        }
                    }
                }
                $examStatus = $allPassed ? 'pass' : 'fail';

                return (object)[
                    'enrollment' => $enr,
                    'trainee' => $enr->trainee ?? $enr->student,
                    'trainee_id' => $traineeId,
                    'student_id' => $studentId,
                    'attendance' => $attendance,
                    'exam_status' => $examStatus,
                    'eligible' => $attendance >= 80 && $examStatus === 'pass',
                ];
            });
        }

        return view('certificates.index', [
            'certificates' => $certificates,
            'q' => $q,
            'status' => $status,
            'branchId' => $branchId,
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'visibleColumns' => $visibleColumns,
            'certificateTypes' => CertificateType::where('institute_id', $instituteId)->where('is_active', true)->orderBy('name')->get(),
            'certificateApprovalMode' => $approvalMode,
            'isAdminControlled' => $approvalMode === InstituteSetting::CERTIFICATE_APPROVAL_ADMIN,
            'isProfessional' => $isProfessionalCert,
            'trainingBatches' => $trainingBatches,
            'selectedCertBatchId' => $selectedCertBatchId,
            'certTrainees' => $certTrainees,
        ]);
    }

    /**
     * Submit a certificate request for an eligible graduate (Step 35).
     *
     * The request is created as `pending` and the platform registry approves /
     * rejects / revokes it through the existing admin flow. Guarded by
     * permission:certificates.manage on the route; the student is resolved
     * through the tenant + branch scoped Student model (cross-tenant /
     * cross-branch → 404).
     */
    public function request(Request $request, Student $student): RedirectResponse
    {
        try {
            $this->certificateRequests->createForStudent($student, (int) $request->user()->id);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Certificate request submitted for {$student->full_name} — awaiting review.");
    }

    /**
     * Resubmit a rejected certificate request back to pending review.
     */
    public function resubmit(Certificate $certificate): RedirectResponse
    {
        abort_unless($certificate->institute_id === auth()->user()->institute_id, 403);

        try {
            $this->certificateRequests->resubmit($certificate);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Certificate request resubmitted for review.');
    }

    /**
     * Handle certificate approval action (approve/reject) by institute admin.
     *
     * Only available when certificate_approval_mode = 'admin'.
     * When mode is 'super_admin', institute users cannot approve/reject.
     */
    public function action(Request $request, Certificate $certificate): RedirectResponse
    {
        $instituteId = $request->user()->institute_id;

        abort_unless($certificate->institute_id === $instituteId, 403);

        abort_unless($this->approvalModeService->isAdminControlled($instituteId), 403);

        $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action = $request->input('action');
        $admin = $request->user();
        $reason = $request->input('reason');

        if ($action === 'approve') {
            $certificate->update([
                'status' => 'active',
                'certificate_number' => $certificate->certificate_number ?? Certificate::numberFor($certificate),
                'issue_date' => $certificate->issue_date ?? now()->toDateString(),
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            $this->notifyInstitute(
                $instituteId,
                'certificate',
                'Certificate approved',
                'Certificate for '.($certificate->student->full_name ?? 'student').' has been approved.'
            );

            app(NotificationService::class)->send('education.certificate_approved', $certificate->student, [
                'student_name' => $certificate->student->full_name ?: $certificate->student->first_name,
                'reg_no' => $certificate->student->reg_no,
                'course_name' => $certificate->course?->name,
                'certificate_number' => $certificate->certificate_number,
            ], [
                'actor_type' => 'institute_user',
                'actor_id' => $admin->id,
                'link' => route('students.show', $certificate->student_id),
            ]);

            return back()->with('status', 'Certificate approved and issued.');
        }

        $certificate->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);

        $this->notifyInstitute(
            $instituteId,
            'certificate',
            'Certificate rejected',
            'Certificate for '.($certificate->student->full_name ?? 'student').' was rejected.'
        );

        return back()->with('status', 'Certificate rejected.');
    }

    protected function notifyInstitute(int $instituteId, string $category, string $title, string $message): void
    {
        Notification::create([
            'scope' => 'institute',
            'institute_id' => $instituteId,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'created_by_type' => 'institute_user',
            'created_by_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
