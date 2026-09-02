<?php

namespace App\Http\Controllers\FixedAsset;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetCategoryController extends \App\Http\Controllers\Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $categories = AssetCategory::query()
            ->where('institute_id', $institute->id)
            ->where(fn ($q) => $q->where('branch_id', $this->actingBranchId($request))->orWhereNull('branch_id'))
            ->withCount('assets')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('institute.fixed-assets.categories.index', [
            'institute' => $institute,
            'categories' => $categories,
            'category' => null,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.fixed-assets.categories.index', [
            'institute' => $institute,
            'categories' => collect(),
            'category' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'default_depreciation_method' => ['nullable', 'string', 'max:40'],
            'default_useful_life_months' => ['nullable', 'integer', 'min:1'],
            'default_residual_value_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        AssetCategory::create(array_merge($validated, [
            'institute_id' => $institute->id,
            'branch_id' => $this->actingBranchId($request),
            'is_active' => $validated['is_active'] ?? true,
        ]));

        return redirect()
            ->route('fixed_assets.categories.index')
            ->with('status', 'Category "'.$validated['name'].'" created.');
    }

    public function show(Request $request, AssetCategory $category): View
    {
        $institute = $this->requireInstitute($request);

        $category->loadCount('assets');

        return view('institute.fixed-assets.categories.index', [
            'institute' => $institute,
            'categories' => collect([$category]),
            'category' => $category,
        ]);
    }

    public function edit(Request $request, AssetCategory $category): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.fixed-assets.categories.index', [
            'institute' => $institute,
            'categories' => AssetCategory::where('institute_id', $institute->id)->orderBy('name')->paginate(25),
            'category' => $category,
        ]);
    }

    public function update(Request $request, AssetCategory $category): RedirectResponse
    {
        $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'default_depreciation_method' => ['nullable', 'string', 'max:40'],
            'default_useful_life_months' => ['nullable', 'integer', 'min:1'],
            'default_residual_value_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($validated);

        return redirect()
            ->route('fixed_assets.categories.index')
            ->with('status', 'Category "'.$validated['name'].'" updated.');
    }
}
