<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Accounting\ProgressiveContract;
use App\Models\Party;
use App\Models\TaxGroup;
use App\Services\Accounting\ProgressiveInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProgressiveContractController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ProgressiveInvoiceService $service,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = ProgressiveContract::query()
            ->with('party')
            ->where('institute_id', $institute->id);

        if (filled($q = $request->query('q'))) {
            $query->where(fn ($qq) => $qq
                ->where('contract_number', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%"));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $contracts = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.finance.progressive-contracts.index', [
            'institute' => $institute,
            'contracts' => $contracts,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $parties = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxGroups = TaxGroup::query()
            ->where('institute_id', $institute->id)
            ->orderBy('name')
            ->get();

        return view('institute.finance.progressive-contracts.create', [
            'institute' => $institute,
            'parties' => $parties,
            'taxGroups' => $taxGroups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'party_id' => 'required|exists:parties,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_value' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'retention_percentage' => 'nullable|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'expected_end_date' => 'nullable|date|after_or_equal:start_date',
            'tax_group_id' => 'nullable|exists:tax_groups,id',
            'tax_method' => 'nullable|in:inclusive,exclusive',
            'notes' => 'nullable|string',
        ]);

        $validated['institute_id'] = $institute->id;
        $validated['branch_id'] = $this->actingBranchId($request);
        $validated['currency'] = $validated['currency'] ?? 'BDT';
        $validated['retention_percentage'] = $validated['retention_percentage'] ?? 0;

        $contract = $this->service->createContract($validated);

        return redirect()
            ->route('finance.progressive-contracts.show', $contract)
            ->with('success', "Contract {$contract->contract_number} created.");
    }

    public function show(Request $request, ProgressiveContract $progressive_contract): View
    {
        $this->authorizeAccess($request, $progressive_contract);
        $progressive_contract->load(['party', 'invoices.items']);
        $summary = $this->service->getContractSummary($progressive_contract);

        return view('institute.finance.progressive-contracts.show', [
            'contract' => $progressive_contract,
            'summary' => $summary,
        ]);
    }

    public function edit(Request $request, ProgressiveContract $progressive_contract): View
    {
        $this->authorizeAccess($request, $progressive_contract);
        $institute = $this->requireInstitute($request);

        $parties = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $taxGroups = TaxGroup::query()
            ->where('institute_id', $institute->id)
            ->orderBy('name')
            ->get();

        return view('institute.finance.progressive-contracts.edit', [
            'progressive_contract' => $progressive_contract,
            'parties' => $parties,
            'taxGroups' => $taxGroups,
        ]);
    }

    public function update(Request $request, ProgressiveContract $progressive_contract): RedirectResponse
    {
        $this->authorizeAccess($request, $progressive_contract);

        if (!$progressive_contract->isActive()) {
            return back()->with('error', 'Only active contracts can be edited.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'expected_end_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $progressive_contract->update($validated);

        return back()->with('success', 'Contract updated.');
    }

    public function destroy(Request $request, ProgressiveContract $progressive_contract): RedirectResponse
    {
        $this->authorizeAccess($request, $progressive_contract);

        if ($progressive_contract->invoices()->exists()) {
            return back()->with('error', 'Cannot delete contract with invoices.');
        }

        $progressive_contract->delete();

        return redirect()
            ->route('finance.progressive-contracts.index')
            ->with('success', 'Contract deleted.');
    }

    public function createInvoiceForm(Request $request, ProgressiveContract $progressive_contract): View
    {
        $this->authorizeAccess($request, $progressive_contract);

        if (!$progressive_contract->isActive()) {
            return back()->with('error', 'Contract is not active.');
        }

        return view('institute.finance.progressive-contracts.create-invoice', [
            'contract' => $progressive_contract,
        ]);
    }

    public function storeInvoice(Request $request, ProgressiveContract $progressive_contract): RedirectResponse
    {
        $this->authorizeAccess($request, $progressive_contract);

        $validated = $request->validate([
            'billing_method' => 'required|in:percentage,amount,milestone',
            'progress_value' => 'required|numeric|min:0.01',
            'milestone_name' => 'nullable|string|max:200',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'line_description' => 'nullable|string|max:500',
            'is_final' => 'nullable|boolean',
        ]);

        try {
            $invoice = $this->service->createProgressInvoice($progressive_contract, $validated);

            return redirect()
                ->route('finance.progressive-contracts.show', $progressive_contract)
                ->with('success', "Progress invoice {$invoice->invoice_number} created.");
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, ProgressiveContract $progressive_contract): RedirectResponse
    {
        $this->authorizeAccess($request, $progressive_contract);

        $request->validate(['reason' => 'required|string|min:5|max:500']);

        try {
            $this->service->cancelContract($progressive_contract, $request->input('reason'));

            return back()->with('success', 'Contract cancelled.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function authorizeAccess(Request $request, ProgressiveContract $contract): void
    {
        $institute = $this->requireInstitute($request);
        abort_unless($contract->institute_id === $institute->id, 403, 'Unauthorized.');
    }
}
