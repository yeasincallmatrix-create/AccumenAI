<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\SalesQuotation;
use App\Services\Sales\QuotationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class QuotationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly QuotationService $quotations,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = SalesQuotation::with(['customer', 'currency'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('quotation_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('quotation_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('quotation_date', '<=', $request->input('to'));
        }

        $quotations = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('sales.quotations.index', [
            'institute' => $institute,
            'quotations' => $quotations,
            'statuses' => SalesQuotation::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('sales.quotations.form', [
            'institute' => $institute,
            'quotation' => null,
            'currencies' => $currencies,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $this->validateData($request);

        $quotation = $this->quotations->createDraft($institute->id, $branchId, $data, $this->actorId($request));

        return redirect()->route('sales.quotations.show', $quotation)->with('status', 'Quotation ' . $quotation->quotation_number . ' created.');
    }

    public function show(Request $request, SalesQuotation $quotation): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $quotation->load(['customer', 'currency', 'lines.inventoryItem', 'lines.taxGroup']);

        return view('sales.quotations.show', [
            'institute' => $institute,
            'quotation' => $quotation,
        ]);
    }

    public function edit(Request $request, SalesQuotation $quotation): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        abort_if(! $quotation->isDraft(), 422, 'Only draft quotations can be edited.');

        $quotation->load(['lines']);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('sales.quotations.form', [
            'institute' => $institute,
            'quotation' => $quotation,
            'currencies' => $currencies,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $data = $this->validateData($request);

        $this->quotations->updateDraft($quotation, $data, $this->actorId($request));

        return redirect()->route('sales.quotations.show', $quotation)->with('status', 'Quotation updated.');
    }

    public function send(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotations->send($quotation, $this->actorId($request));

        return back()->with('status', 'Quotation sent.');
    }

    public function accept(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotations->accept($quotation, $this->actorId($request));

        return back()->with('status', 'Quotation accepted.');
    }

    public function reject(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotations->reject($quotation, $this->actorId($request));

        return back()->with('status', 'Quotation rejected.');
    }

    public function cancel(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotations->cancel($quotation, $this->actorId($request));

        return back()->with('status', 'Quotation cancelled.');
    }

    public function expire(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotations->expire($quotation, $this->actorId($request));

        return back()->with('status', 'Quotation expired.');
    }

    public function print(Request $request, SalesQuotation $quotation): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $quotation->load(['customer', 'currency', 'lines.inventoryItem', 'lines.taxGroup', 'branch', 'institute']);

        return view('sales.quotations.print', [
            'institute' => $institute,
            'quotation' => $quotation,
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:parties,id'],
            'quotation_date' => ['required', 'date'],
            'validity_date' => ['required', 'date', 'after_or_equal:quotation_date'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'payment_terms' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'discount_type' => ['nullable', 'in:fixed,percent'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_type' => ['nullable', 'in:fixed,percent'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_group_id' => ['nullable', 'integer', 'exists:tax_groups,id'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function assertBranchScope(SalesQuotation $quotation, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $quotation->branch_id !== null && (int) $quotation->branch_id !== $branchId) {
            abort(404);
        }
    }
}
