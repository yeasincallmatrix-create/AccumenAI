<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\BrandTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandTargetController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $targets = BrandTarget::query()
            ->when($request->filled('brand'), fn ($q) => $q->where('brand_id', $request->input('brand')))
            ->when($request->filled('period'), fn ($q) => $q->where('period_type', $request->input('period')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.brand_targets.index', [
            'institute' => $institute,
            'targets' => $targets,
            'filters' => $request->only(['brand', 'period']),
            'periods' => config('dealership.period_types', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'min:1'],
            'period_type' => ['required', 'in:monthly,quarterly,yearly'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'target_amount' => ['required', 'numeric', 'min:0'],
        ]);

        BrandTarget::create([
            'brand_id' => $data['brand_id'],
            'period_type' => $data['period_type'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'target_amount' => $data['target_amount'],
        ]);

        return redirect()->route('dealership.brand_targets.index')->with('success', 'Brand target saved.');
    }
}
