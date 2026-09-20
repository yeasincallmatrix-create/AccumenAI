<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\RatioAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RatioAnalysisController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        protected RatioAnalysisService $ratios,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $asOf = $request->query('as_of') ?: now()->toDateString();
        $from = $request->query('from') ?: now()->startOfYear()->format('Y-m-d');

        return view('institute.finance.reports.ratio-analysis', [
            'institute' => $institute,
            'as_of_date' => $asOf,
            'from_date' => $from,
        ] + $this->ratios->computeAll(
            $institute->id,
            $asOf,
            $from,
            $this->actingBranchId($request)
        ));
    }
}
