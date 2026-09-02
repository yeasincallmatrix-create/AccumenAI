<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrLeaveApplication;
use App\Models\HrPayrollPeriod;
use App\Models\HrRequisition;
use App\Models\HrVacancy;
use App\Services\HrDocumentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * HR section landing / dashboard — industry-neutral.
 *
 * Summaries are tenant+branch scoped automatically via HrEmployee global scopes (BranchContext pinned through SetTenantContext).
 */
class HrDashboardController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrDocumentService $hrDocs) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $docStats = $this->hrDocs->dashboardStats((int) $institute->id, $branchId);

        $base = HrEmployee::query();
        $total = (clone $base)->count();
        $active = (clone $base)->where('employment_status', 'active')->count();
        $inactive = (clone $base)->where('employment_status', 'inactive')->count();
        $suspended = (clone $base)->where('employment_status', 'suspended')->count();

        $byDepartment = (clone $base)
            ->selectRaw('department_id, COUNT(*) as c')
            ->groupBy('department_id')
            ->pluck('c', 'department_id')
            ->all();

        $byBranch = (clone $base)
            ->selectRaw('branch_id, COUNT(*) as c')
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->pluck('c', 'branch_id')
            ->all();

        $recent = (clone $base)->with(['department', 'designation', 'branch'])->latest('id')->limit(5)->get();

        // HR-9 pending approvals & signals
        $pendingLeaves = HrLeaveApplication::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->where('status','pending')->count();
        $pendingCorrections = HrAttendanceCorrection::where('institute_id',$institute->id)->where('status','pending')->count();
        // Filter corrections by branch via employee branch if not direct branch_id? Keep simple count
        $attendanceExceptions = HrAttendance::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->whereIn('status',['absent','late'])->where('attendance_date', today()->toDateString())->count();
        $pendingPayrolls = HrPayrollPeriod::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->whereIn('status',['draft','processing'])->count();
        $pendingRequisitions = HrRequisition::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->where('status','pending_approval')->count();
        $openVacancies = HrVacancy::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->whereIn('status',['published','approved'])->count();
        $pendingPerformance = \App\Models\HrPerformanceReview::where('institute_id',$institute->id)->whereIn('status',['pending','manager_review'])->count();
        $pendingTraining = \App\Models\HrTrainingEnrollment::where('institute_id',$institute->id)->where('status','enrolled')->count();
        $pendingDocs = \App\Models\Document::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->where('documentable_type', HrEmployee::class)->where('verification_status','pending_verification')->count();

        $hrStats = [
            'pending_leaves' => $pendingLeaves,
            'pending_corrections' => $pendingCorrections,
            'attendance_exceptions' => $attendanceExceptions,
            'pending_payrolls' => $pendingPayrolls,
            'pending_requisitions' => $pendingRequisitions,
            'open_vacancies' => $openVacancies,
            'pending_performance' => $pendingPerformance,
            'pending_training' => $pendingTraining,
            'pending_documents' => $pendingDocs,
        ];

        return view('hr.dashboard', [
            'institute' => $institute,
            'summary' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'suspended' => $suspended,
                'departments' => HrDepartment::query()->count(),
                'designations' => HrDesignation::query()->count(),
                'by_department' => $byDepartment,
                'by_branch' => $byBranch,
            ],
            'recent' => $recent,
            'docStats' => $docStats,
            'hrStats' => $hrStats,
        ]);
    }
}
