<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\SrTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SrTargetController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $targets = SrTarget::query()
            ->when($request->filled('sr'), fn ($q) => $q->where('sales_force_id', $request->input('sr')))
            ->when($request->filled('period'), fn ($q) => $q->where('period_type', $request->input('period')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.targets.index', [
            'institute' => $institute,
            'targets' => $targets,
            'filters' => $request->only(['sr', 'period']),
            'periods' => config('dealership.period_types', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'sales_force_id' => ['required', 'integer', 'min:1'],
            'period_type' => ['required', 'in:monthly,quarterly,yearly'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['period_start'] > $data['period_end']) {
            return back()->withErrors(['period_end' => 'Period start must not be after period end.'])->withInput();
        }

        SrTarget::create([
            'sales_force_id' => $data['sales_force_id'],
            'period_type' => $data['period_type'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'target_amount' => $data['target_amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('dealership.targets.index')->with('success', 'Target saved.');
    }
}
