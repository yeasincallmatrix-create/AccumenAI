<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HrEmployee;
use App\Models\HrKpi;
use App\Models\HrPerformancePeriod;
use App\Models\HrPerformanceReview;
use App\Services\HrPerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrPerformanceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrPerformanceService $performance) {}

    private function can(Request $request, array $perms): bool
    {
        foreach ($perms as $p) {
            if ($request->user()->hasPermission($p)) {
                return true;
            }
        }

        return false;
    }

    public function periods(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $periods = HrPerformancePeriod::where('institute_id', $institute->id)
            ->when($this->actingBranchId($request), fn ($q) => $q->where('branch_id', $this->actingBranchId($request)))
            ->orderByDesc('start_date')->paginate(20);

        return view('hr.performance.periods', [
            'institute' => $institute, 'periods' => $periods,
            'branches' => $this->branchOptions($institute->id),
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'closed'])],
        ]);
        $branchId = $this->actingBranchId($request) ?? $data['branch_id'] ?? null;
        $this->performance->createPeriod($data, $institute->id, $branchId, $this->actorId($request));

        return back()->with('status', 'Review period created.');
    }

    public function closePeriod(Request $request, HrPerformancePeriod $period): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->performance->closePeriod($period, $institute->id, $this->actorId($request));

        return back()->with('status', 'Period closed.');
    }

    public function kpis(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $kpis = HrKpi::where('institute_id', $institute->id)
            ->when($this->actingBranchId($request), fn ($q) => $q->where('branch_id', $this->actingBranchId($request)))
            ->ordered()->paginate(20);

        return view('hr.performance.kpis', [
            'institute' => $institute, 'kpis' => $kpis,
            'branches' => $this->branchOptions($institute->id),
        ]);
    }

    public function storeKpi(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target' => ['nullable', 'string', 'max:150'],
            'measurement' => ['nullable', 'string', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $branchId = $this->actingBranchId($request) ?? $data['branch_id'] ?? null;
        $this->performance->createKpi($data, $institute->id, $branchId, $this->actorId($request));

        return back()->with('status', 'KPI created.');
    }

    public function reviews(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $query = HrPerformanceReview::with(['employee', 'period', 'reviewer'])
            ->where('institute_id', $institute->id)
            ->when($this->actingBranchId($request), fn ($q) => $q->where('branch_id', $this->actingBranchId($request)));
        if (filled($request->query('period_id'))) {
            $query->where('period_id', (int) $request->query('period_id'));
        }
        if (filled($request->query('employee_id'))) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }
        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        $reviews = $query->orderByDesc('review_date')->paginate(20)->withQueryString();
        $periods = HrPerformancePeriod::where('institute_id', $institute->id)->where('status', '!=', 'closed')->orderByDesc('start_date')->get();
        $employees = HrEmployee::where('institute_id', $institute->id)->when($this->actingBranchId($request), fn ($q) => $q->where('branch_id', $this->actingBranchId($request)))->orderBy('display_name')->limit(200)->get(['id', 'display_name', 'employee_code']);

        return view('hr.performance.reviews', [
            'institute' => $institute, 'reviews' => $reviews,
            'periods' => $periods, 'employees' => $employees,
            'statuses' => HrPerformanceReview::STATUSES,
        ]);
    }

    public function createReview(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $periods = HrPerformancePeriod::where('institute_id', $institute->id)->where('status', 'active')->orderByDesc('start_date')->get();
        $employees = HrEmployee::where('institute_id', $institute->id)->when($this->actingBranchId($request), fn ($q) => $q->where('branch_id', $this->actingBranchId($request)))->orderBy('display_name')->limit(200)->get(['id', 'display_name', 'employee_code']);
        $kpis = HrKpi::where('institute_id', $institute->id)->where('is_active', true)->ordered()->get();

        return view('hr.performance.form', ['institute' => $institute, 'periods' => $periods, 'employees' => $employees, 'kpis' => $kpis, 'branches' => $this->branchOptions($institute->id)]);
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'period_id' => ['required', 'integer', 'exists:hr_performance_periods,id'],
            'reviewer_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'review_date' => ['required', 'date'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(HrPerformanceReview::STATUSES)],
            'kpis' => ['nullable', 'array'],
            'kpis.*.kpi_id' => ['nullable', 'integer', 'exists:hr_kpis,id'],
            'kpis.*.name' => ['required', 'string', 'max:150'],
            'kpis.*.target' => ['nullable', 'string', 'max:150'],
            'kpis.*.measurement' => ['nullable', 'string', 'max:100'],
            'kpis.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'kpis.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'kpis.*.max_score' => ['nullable', 'numeric', 'min:1', 'max:1000'],
        ]);
        $branchId = $this->actingBranchId($request);
        $this->performance->createReview($data, $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('hr.performance.reviews')->with('status', 'Review created.');
    }

    public function showReview(Request $request, HrPerformanceReview $review): View
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $review->institute_id !== (int) $institute->id, 404);
        if ($this->actingBranchId($request) && $review->branch_id && (int) $review->branch_id !== (int) $this->actingBranchId($request)) {
            abort(404);
        }
        $review->load(['employee', 'period', 'reviewer', 'kpis']);

        return view('hr.performance.show', [
            'institute' => $institute, 'review' => $review,
            'canReview' => $this->can($request, ['hr.performance.review', 'hr.performance.manage']),
            'canApprove' => $this->can($request, ['hr.performance.approve', 'hr.performance.manage']),
        ]);
    }

    public function evaluate(Request $request, HrPerformanceReview $review): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'role' => ['required', Rule::in(['self', 'manager', 'hr'])],
            'self_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'manager_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hr_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'overall_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(HrPerformanceReview::STATUSES)],
            'promotion_recommendation' => ['nullable', 'string', 'max:50'],
            'training_recommendation' => ['nullable', 'string', 'max:500'],
            'improvement_plan' => ['nullable', 'string', 'max:2000'],
            'recognition' => ['nullable', 'string', 'max:500'],
            'kpi_scores' => ['nullable', 'array'],
            'kpi_scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'kpi_scores.*.comments' => ['nullable', 'string', 'max:1000'],
        ]);
        $role = $data['role'];
        unset($data['role']);
        $this->performance->evaluate($review, $data, $institute->id, $this->actorId($request), $role);

        return back()->with('status', 'Evaluation saved ('.$role.').');
    }

    public function approve(Request $request, HrPerformanceReview $review): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);
        $this->performance->approve($review, $institute->id, $this->actorId($request), $data['decision']);

        return back()->with('status', 'Review '.$data['decision'].'.');
    }

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $totalReviews = HrPerformanceReview::where('institute_id', $institute->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $pending = HrPerformanceReview::where('institute_id', $institute->id)->whereIn('status', ['pending', 'submitted', 'manager_review', 'hr_review'])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $approved = HrPerformanceReview::where('institute_id', $institute->id)->where('status', 'approved')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $avgScore = HrPerformanceReview::where('institute_id', $institute->id)->whereNotNull('overall_score')->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->avg('overall_score');

        return view('hr.performance.dashboard', ['institute' => $institute, 'stats' => compact('totalReviews', 'pending', 'approved', 'avgScore')]);
    }

    private function branchOptions(int $instituteId)
    {
        $acting = $this->actingBranchId(request());

        return Branch::query()->where('institute_id', $instituteId)->where('status', 'active')->when($acting !== null, fn ($q) => $q->whereKey($acting))->orderBy('name')->get(['id', 'name']);
    }
}
