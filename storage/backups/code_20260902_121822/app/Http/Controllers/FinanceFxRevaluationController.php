<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\FxRevaluation;
use App\Services\Accounting\FxRevaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FX revaluation (STEP 19). Gated by fx.revaluation.run.
 * Run revaluation, list past revaluations, reverse posted revaluations.
 */
class FinanceFxRevaluationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly FxRevaluationService $revaluationService,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $revaluations = FxRevaluation::query()
            ->where('institute_id', $institute->id)
            ->when($this->actingBranchId($request) !== null, fn ($q) => $q->where('branch_id', $this->actingBranchId($request)))
            ->with(['journal', 'currency'])
            ->latest('as_of_date')
            ->paginate(25);

        return view('institute.finance.fx-revaluations.index', [
            'institute' => $institute,
            'revaluations' => $revaluations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'as_of_date' => ['required', 'date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'closing_rate' => ['nullable', 'numeric', 'min:0.000001'],
        ]);

        $results = $this->revaluationService->run(
            $institute->id,
            $this->actingBranchId($request),
            $validated,
            $this->actorId($request),
        );

        $count = count($results);

        return redirect()->route('finance.fx-revaluations.index')
            ->with('success', "{$count} revaluation adjustment(s) posted.");
    }

    public function reverse(Request $request, FxRevaluation $fxRevaluation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->revaluationService->reverse(
            $fxRevaluation,
            $institute->id,
            $this->actorId($request),
            $request->input('reason', 'FX revaluation reversed'),
        );

        return redirect()->route('finance.fx-revaluations.index')
            ->with('success', 'FX revaluation reversed.');
    }
}
