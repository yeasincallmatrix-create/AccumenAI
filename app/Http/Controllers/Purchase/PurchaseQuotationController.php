<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\PurchaseQuotation;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchaseQuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PurchaseQuotationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly PurchaseQuotationService $quotationService,
        private readonly PurchaseOrderService $orderService,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = PurchaseQuotation::with(['supplier', 'currency'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $hasReferenceNumber = Schema::hasColumn('purchase_quotations', 'reference_number');
            $hasReference = Schema::hasColumn('purchase_quotations', 'reference');
            $query->where(function ($qq) use ($q, $hasReferenceNumber, $hasReference) {
                $qq->where('quotation_number', 'like', "%{$q}%")
                    ->orWhereHas('supplier', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                if ($hasReferenceNumber) {
                    $qq->orWhere('reference_number', 'like', "%{$q}%");
                }
                if ($hasReference) {
                    $qq->orWhere('reference', 'like', "%{$q}%");
                }
            });
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('quotation_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('quotation_date', '<=', $request->input('to'));
        }

        // purchase_quotations has no warehouse_id column — warehouse filter is optional/no-op for quotations
        // kept for spec compatibility but only applied if column exists
        if (($request->filled('warehouse') || $request->filled('warehouse_id')) && Schema::hasColumn('purchase_quotations', 'warehouse_id')) {
            $wh = $request->input('warehouse_id') ?? $request->input('warehouse');
            $query->where('warehouse_id', $wh);
        }

        $quotations = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('purchase.quotations.index', [
            'institute' => $institute,
            'quotations' => $quotations,
            'statuses' => PurchaseQuotation::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('purchase.quotations.form', [
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

        $quotation = $this->quotationService->createDraft($institute->id, $branchId, $data, $this->actorId($request));

        return redirect()->route('purchase.quotations.show', $quotation)->with('status', 'Purchase Quotation '.$quotation->quotation_number.' created.');
    }

    public function show(Request $request, PurchaseQuotation $quotation): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $quotation->load(['supplier', 'currency', 'lines.inventoryItem', 'lines.taxGroup', 'convertedOrder', 'branch', 'institute']);

        return view('purchase.quotations.show', [
            'institute' => $institute,
            'quotation' => $quotation,
        ]);
    }

    public function edit(Request $request, PurchaseQuotation $quotation): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        abort_if(! $quotation->isDraft(), 422, 'Only draft quotations can be edited.');

        $quotation->load(['lines']);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('purchase.quotations.form', [
            'institute' => $institute,
            'quotation' => $quotation,
            'currencies' => $currencies,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        abort_if(! $quotation->isDraft(), 422, 'Only draft quotations can be edited.');

        $data = $this->validateData($request);

        $this->quotationService->updateDraft($quotation, $data, $this->actorId($request));

        return redirect()->route('purchase.quotations.show', $quotation)->with('status', 'Purchase Quotation updated.');
    }

    public function send(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotationService->send($quotation, $this->actorId($request));

        return back()->with('status', 'Purchase Quotation sent.');
    }

    public function accept(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotationService->accept($quotation, $this->actorId($request));

        return back()->with('status', 'Purchase Quotation accepted.');
    }

    public function reject(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotationService->reject($quotation, $this->actorId($request));

        return back()->with('status', 'Purchase Quotation rejected.');
    }

    public function cancel(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotationService->cancel($quotation, $this->actorId($request));

        return back()->with('status', 'Purchase Quotation cancelled.');
    }

    public function expire(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $this->quotationService->expire($quotation, $this->actorId($request));

        return back()->with('status', 'Purchase Quotation expired.');
    }

    public function convert(Request $request, PurchaseQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScope($quotation, $request);

        $overrides = $request->validate([
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);

        // Normalize overrides for service: accept both reference and reference_number, terms and terms_conditions
        if (isset($overrides['reference']) && ! isset($overrides['reference_number'])) {
            $overrides['reference_number'] = $overrides['reference'];
        }
        if (isset($overrides['terms']) && ! isset($overrides['terms_conditions'])) {
            $overrides['terms_conditions'] = $overrides['terms'];
        }

        $actorId = $this->actorId($request) ?? 0;

        $order = $this->orderService->createFromQuotation($quotation, $overrides, (int) $actorId);

        return redirect()->route('purchase.orders.show', $order)->with('status', 'Purchase Order '.$order->order_number.' created from quotation.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:parties,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'quotation_date' => ['required', 'date'],
            'validity_date' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'reference' => ['nullable', 'string', 'max:80'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
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
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_group_id' => ['nullable', 'integer', 'exists:tax_groups,id'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function assertBranchScope(PurchaseQuotation $quotation, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $quotation->branch_id !== null && (int) $quotation->branch_id !== $branchId) {
            abort(404);
        }
    }
}
