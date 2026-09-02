<?php

namespace App\Http\Controllers\FixedAsset;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AssetCategory;
use App\Models\AssetLocation;
use App\Models\FixedAsset;
use App\Services\FixedAsset\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FixedAssetController extends \App\Http\Controllers\Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly FixedAssetService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = FixedAsset::query()
            ->where('institute_id', $institute->id)
            ->where(fn ($q) => $q->where('branch_id', $this->actingBranchId($request))->orWhereNull('branch_id'))
            ->with(['category', 'location']);

        if (filled($q = $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('asset_code', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('category_id'))) {
            $query->where('category_id', $request->query('category_id'));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('location_id'))) {
            $query->where('location_id', $request->query('location_id'));
        }

        $assets = $query->orderBy('asset_code')->paginate(25)->withQueryString();

        return view('institute.fixed-assets.assets.index', [
            'institute' => $institute,
            'assets' => $assets,
            'categories' => AssetCategory::where('institute_id', $institute->id)->orderBy('name')->get(),
            'locations' => AssetLocation::where('institute_id', $institute->id)->orderBy('name')->get(),
            'statuses' => FixedAsset::STATUSES,
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.fixed-assets.assets.index', [
            'institute' => $institute,
            'categories' => AssetCategory::where('institute_id', $institute->id)->orderBy('name')->get(),
            'locations' => AssetLocation::where('institute_id', $institute->id)->orderBy('name')->get(),
            'asset' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'capitalization_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:80'],
            'responsible_person' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->create(
            $institute->id,
            $this->actingBranchId($request),
            $validated,
            $this->actorId($request),
        );

        return redirect()
            ->route('fixed_assets.assets.index')
            ->with('status', 'Asset "'.$validated['name'].'" created.');
    }

    public function show(Request $request, FixedAsset $asset): View
    {
        $institute = $this->requireInstitute($request);

        $asset->load(['category', 'location', 'costComponents', 'transfers', 'disposals', 'impairments', 'revaluations', 'depreciationEntries', 'methodChanges']);

        return view('institute.fixed-assets.assets.show', [
            'institute' => $institute,
            'asset' => $asset,
        ]);
    }

    public function edit(Request $request, FixedAsset $asset): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.fixed-assets.assets.index', [
            'institute' => $institute,
            'asset' => $asset,
            'categories' => AssetCategory::where('institute_id', $institute->id)->orderBy('name')->get(),
            'locations' => AssetLocation::where('institute_id', $institute->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'capitalization_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:80'],
            'responsible_person' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->update($asset, $validated, $this->actorId($request));

        return redirect()
            ->route('fixed_assets.assets.show', $asset)
            ->with('status', 'Asset "'.$validated['name'].'" updated.');
    }

    public function capitalize(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->capitalize($asset, null, $this->actorId($request));

        return redirect()
            ->route('fixed_assets.assets.show', $asset)
            ->with('status', 'Asset "'.$asset->name.'" capitalized and activated.');
    }
}
