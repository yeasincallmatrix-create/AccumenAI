<?php

namespace App\Http\Controllers\FixedAsset;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AssetDisposal;
use App\Models\FixedAsset;
use App\Services\FixedAsset\DepreciationEngine;
use App\Services\FixedAsset\FixedAssetReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetReportController extends \App\Http\Controllers\Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly FixedAssetReportService $reportService,
        private readonly DepreciationEngine $engine,
    ) {}

    public function register(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $status = $request->query('status');
        $register = $this->reportService->register($institute->id, $this->actingBranchId($request), $status, 50);
        $byCategory = $this->reportService->byCategory($institute->id, $this->actingBranchId($request));

        return view('institute.fixed-assets.reports.register', [
            'institute' => $institute,
            'register' => $register,
            'byCategory' => $byCategory,
            'status' => $status,
        ]);
    }

    public function depreciation(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $assets = FixedAsset::query()
            ->where('institute_id', $institute->id)
            ->where(fn ($q) => $q->where('branch_id', $this->actingBranchId($request))->orWhereNull('branch_id'))
            ->where('is_depreciable', true)
            ->whereIn('status', ['active', 'capitalized'])
            ->with('category')
            ->orderBy('asset_code')
            ->get();

        $schedules = [];
        foreach ($assets as $asset) {
            $schedules[$asset->id] = [
                'asset' => $asset,
                'schedule' => $this->engine->schedule($asset),
            ];
        }

        return view('institute.fixed-assets.reports.depreciation', [
            'institute' => $institute,
            'schedules' => $schedules,
        ]);
    }

    public function disposal(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $disposals = AssetDisposal::query()
            ->where('institute_id', $institute->id)
            ->where(fn ($q) => $q->where('branch_id', $this->actingBranchId($request))->orWhereNull('branch_id'))
            ->with('asset')
            ->orderByDesc('disposal_date')
            ->paginate(25)
            ->withQueryString();

        return view('institute.fixed-assets.reports.register', [
            'institute' => $institute,
            'register' => collect(),
            'byCategory' => [],
            'status' => null,
            'disposals' => $disposals,
        ]);
    }
}
