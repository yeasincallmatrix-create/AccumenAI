<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndustrySubcategoryController extends Controller
{
    public function index(Request $request): View
    {
        $subcategories = DB::table('industry_subcategories')
            ->when($request->filled('industry'), fn ($q, $i) => $q->where('industry_key', $i))
            ->when($request->filled('search'), fn ($q, $s) => $q->where('name', 'LIKE', "%{$s}%"))
            ->orderBy('industry_key')
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        $moduleCounts = DB::table('subcategory_default_modules')
            ->select('subcategory_id', DB::raw('COUNT(*) as c'))
            ->groupBy('subcategory_id')
            ->pluck('c');

        $industries = $this->industryKeys();

        return view('admin.industry-subcategories.index', compact('subcategories', 'moduleCounts', 'industries'));
    }

    public function show(int $id): View
    {
        $subcategory = DB::table('industry_subcategories')->where('id', $id)->first();
        abort_if(! $subcategory, 404);

        $modules = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $id)
            ->orderByRaw("FIELD(category,'mandatory','default','optional')")
            ->orderBy('module_key')
            ->get();

        $moduleNames = DB::table('module_registry')->pluck('name', 'key');

        return view('admin.industry-subcategories.show', compact('subcategory', 'modules', 'moduleNames'));
    }

    public function create(): View
    {
        return view('admin.industry-subcategories.create', [
            'industries' => $this->industryKeys(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'industry_key' => ['required', 'string', 'max:60', Rule::in($this->industryKeys())],
            'subcategory_key' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('industry_subcategories', 'subcategory_key')
                    ->where('industry_key', $request->input('industry_key')),
            ],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|between:0,9999',
            'is_active' => 'nullable|boolean',
        ], [
            'subcategory_key.regex' => 'Sub-category key must be lowercase letters, numbers, underscores.',
        ]);

        DB::table('industry_subcategories')->insert($validated + [
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.industry-subcategories.index')
            ->with('success', "Sub-category \"{$validated['name']}\" created.");
    }

    public function edit(int $id): View
    {
        $subcategory = DB::table('industry_subcategories')->where('id', $id)->first();
        abort_if(! $subcategory, 404);

        return view('admin.industry-subcategories.edit', [
            'subcategory' => $subcategory,
            'industries' => $this->industryKeys(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $subcategory = DB::table('industry_subcategories')->where('id', $id)->first();
        abort_if(! $subcategory, 404);

        $validated = $request->validate([
            'industry_key' => ['required', 'string', 'max:60', Rule::in($this->industryKeys())],
            'subcategory_key' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('industry_subcategories', 'subcategory_key')
                    ->where('industry_key', $request->input('industry_key'))
                    ->where('id', '!=', $id),
            ],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:60',
            'sort_order' => 'nullable|integer|between:0,9999',
            'is_active' => 'nullable|boolean',
        ], [
            'subcategory_key.regex' => 'Sub-category key must be lowercase letters, numbers, underscores.',
        ]);

        DB::table('industry_subcategories')->where('id', $id)->update($validated + [
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('admin.industry-subcategories.index')
            ->with('success', "Sub-category \"{$validated['name']}\" updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $subcategory = DB::table('industry_subcategories')->where('id', $id)->first();
        abort_if(! $subcategory, 404);

        DB::transaction(function () use ($id) {
            DB::table('subcategory_default_modules')->where('subcategory_id', $id)->delete();
            DB::table('industry_subcategories')->where('id', $id)->delete();
        });

        return redirect()
            ->route('admin.industry-subcategories.index')
            ->with('success', "Sub-category \"{$subcategory->name}\" and its module mappings deleted.");
    }

    /**
     * Valid industry keys come from config('industry-modules') (minus the
     * 'core' pseudo-entry) — keeps UI in sync with Layer 2 config.
     */
    private function industryKeys(): array
    {
        return array_values(array_diff(array_keys(config('industry-modules', [])), ['core']));
    }
}
