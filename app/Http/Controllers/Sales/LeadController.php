<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\Party;
use App\Models\SalesQuotation;
use App\Services\Sales\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly QuotationService $quotations,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = CrmLead::with(['status', 'source', 'assignedUser'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($qq) use ($search) {
                $qq->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->input('status_id'));
        }

        $leads = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $statuses = CrmLeadStatus::where('status', 'active')->orderBy('display_order')->get();
        $sources = CrmLeadSource::where('status', 'active')->orderBy('display_order')->get();

        return view('sales.leads.index', [
            'institute' => $institute,
            'leads' => $leads,
            'statuses' => $statuses,
            'sources' => $sources,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $statuses = CrmLeadStatus::where('status', 'active')->orderBy('display_order')->get();
        $sources = CrmLeadSource::where('status', 'active')->orderBy('display_order')->get();

        return view('sales.leads.form', [
            'institute' => $institute,
            'lead' => null,
            'statuses' => $statuses,
            'sources' => $sources,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'interest_summary' => ['nullable', 'string', 'max:2000'],
            'value_amount' => ['nullable', 'numeric', 'min:0'],
            'status_id' => ['nullable', 'integer', 'exists:crm_lead_statuses,id'],
            'source_id' => ['nullable', 'integer', 'exists:crm_lead_sources,id'],
        ]);

        $lead = CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'interest_summary' => $data['interest_summary'] ?? null,
            'value_amount' => $data['value_amount'] ?? null,
            'status_id' => $data['status_id'] ?? CrmLeadStatus::where('slug', CrmLeadStatus::SLUG_NEW)->value('id'),
            'source_id' => $data['source_id'] ?? null,
            'created_by' => $this->actorId($request),
        ]);

        return redirect()->route('sales.leads.show', $lead)->with('status', 'Lead ' . $lead->displayName() . ' created.');
    }

    public function show(Request $request, CrmLead $lead): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($lead->institute_id !== $institute->id, 404);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $lead->branch_id !== null && (int) $lead->branch_id !== $branchId) {
            abort(404);
        }

        $lead->load(['status', 'source', 'assignedUser', 'contact', 'organization']);

        $statuses = CrmLeadStatus::where('status', 'active')->orderBy('display_order')->get();
        $sources = CrmLeadSource::where('status', 'active')->orderBy('display_order')->get();

        return view('sales.leads.show', [
            'institute' => $institute,
            'lead' => $lead,
            'statuses' => $statuses,
            'sources' => $sources,
        ]);
    }

    public function convertToQuotation(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($lead->institute_id !== $institute->id, 404);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $lead->branch_id !== null && (int) $lead->branch_id !== $branchId) {
            abort(404);
        }

        $party = Party::create([
            'institute_id' => $institute->id,
            'branch_id' => $lead->branch_id,
            'type' => 'customer',
            'name' => $lead->displayName(),
            'phone' => $lead->phone,
            'email' => $lead->email,
            'is_active' => true,
            'created_by' => $this->actorId($request),
        ]);

        $lead->update([
            'status_id' => CrmLeadStatus::where('slug', CrmLeadStatus::SLUG_WON)->value('id'),
            'converted_at' => now(),
            'updated_by' => $this->actorId($request),
        ]);

        $quotation = $this->quotations->createDraft(
            $institute->id,
            $branchId,
            [
                'customer_id' => $party->id,
                'quotation_date' => now()->toDateString(),
                'validity_date' => now()->addDays(30)->toDateString(),
                'currency_id' => \App\Models\Currency::where('is_active', true)->value('id'),
                'notes' => 'Converted from lead: ' . $lead->displayName(),
                'lines' => [
                    ['description' => $lead->interest_summary ?? 'Lead conversion', 'quantity' => 1, 'unit_price' => $lead->value_amount ?? 0, 'discount_amount' => 0],
                ],
            ],
            $this->actorId($request)
        );

        return redirect()->route('sales.quotations.show', $quotation)->with('status', 'Lead converted to Quotation ' . $quotation->quotation_number . '.');
    }
}
