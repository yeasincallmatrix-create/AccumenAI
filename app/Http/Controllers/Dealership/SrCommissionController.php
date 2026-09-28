<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\SrCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SrCommissionController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $commissions = SrCommission::query()
            ->when($request->filled('sr'), fn ($q) => $q->where('sales_force_id', $request->input('sr')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.commissions.index', [
            'institute' => $institute,
            'commissions' => $commissions,
            'filters' => $request->only(['sr', 'status']),
            'statuses' => config('dealership.commission_statuses', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'sales_force_id' => ['required', 'integer', 'min:1'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        SrCommission::create([
            'sales_force_id' => $data['sales_force_id'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'base_amount' => $data['base_amount'],
            'commission_rate' => $data['commission_rate'],
            'commission_amount' => round($data['base_amount'] * $data['commission_rate'] / 100, 2),
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('dealership.commissions.index')->with('success', 'Commission recorded.');
    }

    public function approve(Request $request, SrCommission $commission): RedirectResponse
    {
        $this->requireInstitute($request);

        if ($commission->status !== 'pending') {
            abort(422, 'Only pending commissions can be approved.');
        }

        $commission->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Commission approved.');
    }
}
