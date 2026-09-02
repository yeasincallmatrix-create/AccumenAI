<?php

namespace App\Http\Controllers\FixedAsset;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AssetDepreciationRun;
use App\Services\FixedAsset\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetDepreciationController extends \App\Http\Controllers\Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly FixedAssetService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $runs = AssetDepreciationRun::query()
            ->where('institute_id', $institute->id)
            ->where(fn ($q) => $q->where('branch_id', $this->actingBranchId($request))->orWhereNull('branch_id'))
            ->with('journal')
            ->orderByDesc('period_start')
            ->paginate(25)
            ->withQueryString();

        return view('institute.fixed-assets.depreciation.index', [
            'institute' => $institute,
            'runs' => $runs,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $this->service->runDepreciation(
            $institute->id,
            $this->actingBranchId($request),
            $validated['period_start'],
            $validated['period_end'],
            $this->actorId($request),
        );

        return redirect()
            ->route('fixed_assets.depreciation.index')
            ->with('status', 'Depreciation run posted for '.$validated['period_start'].' to '.$validated['period_end'].'.');
    }

    public function show(Request $request, AssetDepreciationRun $run): View
    {
        $institute = $this->requireInstitute($request);

        $run->load('journal');

        return view('institute.fixed-assets.depreciation.show', [
            'institute' => $institute,
            'run' => $run,
        ]);
    }
}
