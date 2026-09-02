<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseRequest;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\Exam;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Result;
use App\Models\Student;
use App\Models\User;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Support\BranchContext;
use App\Support\IndustryRules;
use App\Support\Workspace;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (Auth::guard('institute_user')->check()) {
            return $this->instituteDashboard();
        }

        if (Auth::guard('web')->check()) {
            return $this->workspaceDashboard();
        }

        return $this->platformDashboard();
    }

    protected function instituteDashboard()
    {
        $user = Auth::guard('institute_user')->user();
        $institute = $user instanceof InstituteUser ? Institute::find($user->institute_id) : null;
        $isEducation = \App\Support\InstituteDomain::isAcademic($institute);

        if (! $isEducation) {
            return $this->cleanStudentDashboard($institute);
        }

        $stats = [
            'students' => Student::count(),
            'runningBatches' => Batch::where('status', 'running')->count(),
            'batches' => Batch::count(),
            'assignedCourses' => InstituteCourse::count(),
        ];

        $recentAdmissions = Student::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'full_name', 'student_id_number', 'admission_date', 'status']);

        $user = Auth::guard('institute_user')->user();
        $branchId = BranchContext::id();

        // Step 34: surface CRM + Finance summaries on the education dashboard,
        // gated by the same permissions that gate the CRM / Finance modules.
        $crmSummary = null;
        if ($user instanceof InstituteUser && $user->hasPermission('crm.view')) {
            $wonId = (int) CrmLeadStatus::query()->where('slug', CrmLeadStatus::SLUG_WON)->value('id');
            $lostId = (int) CrmLeadStatus::query()->where('slug', CrmLeadStatus::SLUG_LOST)->value('id');

            $crmSummary = [
                'contacts' => CrmContact::count(),
                'leads' => CrmLead::count(),
                'open_leads' => CrmLead::query()->whereNotIn('status_id', [$wonId, $lostId])->count(),
                'won_leads' => CrmLead::query()->where('status_id', $wonId)->count(),
            ];
        }

        $financeSummary = null;
        if ($user instanceof InstituteUser && $user->hasPermission('finance.view')) {
            $arp = app(ReceivablesPayablesService::class);
            $totals = $arp->totals($user->institute_id, $branchId);
            $income = app(FinancialReportService::class)->incomeStatement($user->institute_id, $branchId);

            $financeSummary = [
                'receivable' => $totals['receivable'],
                'payable' => $totals['payable'],
                'net_income' => $income['net'],
            ];
        }

        return view('dashboard', [
            'stats' => $stats,
            'recentAdmissions' => $recentAdmissions,
            'crmSummary' => $crmSummary,
            'financeSummary' => $financeSummary,
        ]);
    }

    protected function platformDashboard()
    {
        $country = request()->query('country');
        $country = is_string($country) && array_key_exists($country, config('countries', [])) ? $country : null;

        $industry = request()->query('industry');
        $industry = is_string($industry) && array_key_exists($industry, IndustryRules::industries($country)) ? $industry : null;

        $subIndustry = request()->query('sub_industry');
        $subIndustry = is_string($subIndustry)
            && $industry !== null
            && array_key_exists($subIndustry, IndustryRules::subIndustries($country ?? '', $industry))
            ? $subIndustry
            : null;

        $instituteIds = Institute::query()
            ->when($country, fn ($query) => $query->where('country', $country))
            ->when($industry, fn ($query) => $query->where('industry', $industry))
            ->when($subIndustry, fn ($query) => $query->where('sub_industry', $subIndustry))
            ->pluck('id');

        $stats = [
            'institutes' => $instituteIds->count(),
            'students' => Student::whereIn('institute_id', $instituteIds)->count(),
            'courses' => Course::whereIn('institute_id', $instituteIds)->count(),
            'batches' => Batch::whereIn('institute_id', $instituteIds)->count(),
            'instituteUsers' => InstituteUser::whereIn('institute_id', $instituteIds)->count(),
            'exams' => Exam::whereIn('institute_id', $instituteIds)->count(),
            'results' => Result::whereIn('institute_id', $instituteIds)->count(),
            'certificates' => Certificate::whereIn('institute_id', $instituteIds)->count(),
        ];

        $latestStudents = Student::with('institute')
            ->whereIn('institute_id', $instituteIds)
            ->latest('id')
            ->limit(5)
            ->get();

        $institutes = Institute::withCount('students')
            ->when($country, fn ($query) => $query->where('country', $country))
            ->when($industry, fn ($query) => $query->where('industry', $industry))
            ->when($subIndustry, fn ($query) => $query->where('sub_industry', $subIndustry))
            ->get();

        $pendingCourseRequests = CourseRequest::query()
            ->with(['institute', 'course', 'requestedBy'])
            ->where('status', 'pending')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('dashboard', compact('stats', 'latestStudents', 'institutes', 'country', 'industry', 'subIndustry', 'pendingCourseRequests') + [
            'industries' => IndustryRules::industries($country),
            'subIndustries' => $industry !== null ? IndustryRules::subIndustries($country ?? '', $industry) : [],
        ]);
    }

    /**
     * Global account inside an active organization workspace.
     */
    protected function workspaceDashboard()
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User, 403);
        // Incomplete onboarding (OTP verified but org/address not done) — block dashboard and resume same step
        if (\App\Http\Controllers\Auth\RegistrationFlowController::isOnboardingIncomplete($user)) {
            $resume = \App\Http\Controllers\Auth\RegistrationFlowController::resumeRouteForUser($user) ?? 'register.organization';
            return redirect()->route($resume);
        }

        $membership = Workspace::membership();

        abort_if($membership === null, 403, 'No active organization selected.');

        $institute = $membership->institution;
        $isEducation = \App\Support\InstituteDomain::isAcademic($institute);
        if (! $isEducation) {
            return $this->cleanStudentDashboard($institute, true);
        }

        $stats = [
            'students' => Student::count(),
            'runningBatches' => Batch::where('status', 'running')->count(),
            'batches' => Batch::count(),
            'assignedCourses' => InstituteCourse::count(),
        ];

        $recentAdmissions = Student::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'full_name', 'student_id_number', 'admission_date', 'status']);

        return view('dashboard', [
            'stats' => $stats,
            'recentAdmissions' => $recentAdmissions,
            'institute' => $membership->institution,
            'workspaceMode' => true,
        ]);
    }

    protected function cleanStudentDashboard(?Institute $institute, bool $workspaceMode = false)
    {
        $user = Auth::guard('institute_user')->user() ?? Auth::guard('web')->user();
        $industry = $institute?->industry ?? 'other';
        // Restaurant / hospitality — no student concept, show clean hospitality dashboard
        if (in_array($industry, ['restaurant', 'hotels'], true)) {
            return view('dashboard', [
                'isEducation' => false,
                'isCleanStudent' => false,
                'isHospitality' => true,
                'user' => $user,
                'institute' => $institute,
                'workspaceMode' => $workspaceMode,
            ]);
        }

        $studentQuery = Student::query();
        $recent = Student::query()->with('branch')->latest('id')->limit(8)
            ->get(['id','full_name','student_id_number','phone','branch_id','status','admission_date','photo']);

        $stats = [
            'total' => (clone $studentQuery)->count(),
            'active' => (clone $studentQuery)->where('status','active')->count(),
            'newThisMonth' => (clone $studentQuery)->whereMonth('admission_date', now()->month)->whereYear('admission_date', now()->year)->count(),
            'completed' => (clone $studentQuery)->where('status','completed')->count(),
        ];

        $byStatus = Student::query()
            ->selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total','status');

        $byBranch = Student::query()->with('branch:id,name')
            ->selectRaw("branch_id, COUNT(*) as total")
            ->groupBy('branch_id')
            ->get();

        return view('dashboard', [
            'isEducation' => false,
            'isCleanStudent' => true,
            'user' => $user,
            'institute' => $institute,
            'stats' => $stats,
            'recentStudents' => $recent,
            'byStatus' => $byStatus,
            'byBranch' => $byBranch,
            'workspaceMode' => $workspaceMode,
        ]);
    }
}
