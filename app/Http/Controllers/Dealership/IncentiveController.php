<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\Incentive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncentiveController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $incentives = Incentive::query()
            ->when($request->filled('sr'), fn ($q) => $q->where('sales_force_id', $request->input('sr')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.incentives.index', [
            'institute' => $institute,
            'incentives' => $incentives,
            'filters' => $request->only(['sr', 'status']),
            'statuses' => config('dealership.commission_statuses', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'sales_force_id' => ['required', 'integer', 'min:1'],
            'rule_name' => ['required', 'string', 'max:120'],
            'rule_type' => ['required', 'in:flat,percentage,tiered'],
            'threshold_amount' => ['required', 'numeric', 'min:0'],
            'incentive_amount' => ['required', 'numeric', 'min:0'],
            'earned_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['rule_type'] === 'flat' && (float) $data['threshold_amount'] <= 0) {
            return back()->withErrors(['threshold_amount' => 'Flat incentives require a positive threshold.'])->withInput();
        }

        Incentive::create([
            'sales_force_id' => $data['sales_force_id'],
            'rule_name' => $data['rule_name'],
            'rule_type' => $data['rule_type'],
            'threshold_amount' => $data['threshold_amount'],
            'incentive_amount' => $data['incentive_amount'],
            'earned_on' => $data['earned_on'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('dealership.incentives.index')->with('success', 'Incentive recorded.');
    }
}
