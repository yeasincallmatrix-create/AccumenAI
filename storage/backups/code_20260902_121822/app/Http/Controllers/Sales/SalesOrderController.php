<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SalesOrderService $orders,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = SalesOrder::with(['customer', 'currency', 'quotation'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('order_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('quotation', fn ($c) => $c->where('quotation_number', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->input('to'));
        }

        $orders = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('sales.orders.index', [
            'institute' => $institute,
            'orders' => $orders,
            'statuses' => SalesOrder::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        $prefillQuotation = null;
        $prefillData = null;
        if ($request->filled('quotation_id')) {
            $qId = (int) $request->input('quotation_id');
            $branchId = $this->actingBranchId($request);
            $q = SalesQuotation::withoutGlobalScopes()->where('institute_id', $institute->id)->where('id', $qId)->first();
            if ($q && $q->status === SalesQuotation::STATUS_ACCEPTED && $q->converted_to_order_id === null) {
                if ($branchId === null || $q->branch_id === null || (int) $q->branch_id === $branchId) {
                    $q->load('lines');
                    $prefillQuotation = $q;
                    $prefillData = [
                        'customer_id' => $q->customer_id,
                        'currency_id' => $q->currency_id,
                        'payment_terms' => $q->payment_terms,
                        'notes' => $q->notes,
                        'terms_conditions' => $q->terms_conditions,
                        'discount_type' => $q->discount_type,
                        'lines' => $q->lines->map(fn ($l) => [
                            'inventory_item_id' => $l->inventory_item_id,
                            'description' => $l->description,
                            'quantity' => $l->quantity,
                            'unit' => $l->unit,
                            'unit_price' => $l->unit_price,
                            'discount_amount' => $l->discount_amount,
                            'discount_type' => $l->discount_type,
                            'tax_group_id' => $l->tax_group_id,
                        ])->toArray(),
                    ];
                }
            }
        }

        return view('sales.orders.form', [
            'institute' => $institute,
            'order' => null,
            'currencies' => $currencies,
            'isEdit' => false,
            'prefillQuotation' => $prefillQuotation,
            'prefillData' => $prefillData,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        // If quotation_id provided, treat as conversion
        if ($request->filled('quotation_id')) {
            $data = $request->validate([
                'quotation_id' => ['required', 'integer', 'exists:sales_quotations,id'],
                'order_date' => ['required', 'date'],
                'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
                'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
                'payment_terms' => ['nullable', 'string', 'max:40'],
                'billing_address' => ['nullable', 'string', 'max:2000'],
                'shipping_address' => ['nullable', 'string', 'max:2000'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'terms_conditions' => ['nullable', 'string', 'max:5000'],
            ]);

            $quotation = SalesQuotation::withoutGlobalScopes()->where('id', $data['quotation_id'])->firstOrFail();
            abort_if($quotation->institute_id !== $institute->id, 404);
            $this->assertBranchScopeForQuotation($quotation, $request);

            $order = $this->orders->createFromQuotation($quotation, [
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
            ], $this->actorId($request));

            return redirect()->route('sales.orders.show', $order)->with('status', 'Sales Order '.$order->order_number.' created from quotation '.$quotation->quotation_number.'.');
        }

        $data = $this->validateData($request);
        $order = $this->orders->createDraft($institute->id, $branchId, $data, $this->actorId($request));

        return redirect()->route('sales.orders.show', $order)->with('status', 'Sales Order '.$order->order_number.' created.');
    }

    public function convert(Request $request, SalesQuotation $quotation): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($quotation->institute_id !== $institute->id, 404);
        $this->assertBranchScopeForQuotation($quotation, $request);

        $data = $request->validate([
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->orders->createFromQuotation($quotation, $data, $this->actorId($request));

        return redirect()->route('sales.orders.show', $order)->with('status', 'Converted quotation to order '.$order->order_number);
    }

    public function show(Request $request, SalesOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $order->load(['customer', 'currency', 'lines.inventoryItem', 'lines.taxGroup', 'quotation', 'branch', 'institute']);

        return view('sales.orders.show', [
            'institute' => $institute,
            'order' => $order,
        ]);
    }

    public function edit(Request $request, SalesOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);
        abort_if(! $order->canEdit(), 422, 'Only draft or rejected orders can be edited.');

        $order->load(['lines']);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('sales.orders.form', [
            'institute' => $institute,
            'order' => $order,
            'currencies' => $currencies,
            'isEdit' => true,
            'prefillQuotation' => null,
            'prefillData' => null,
        ]);
    }

    public function update(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $data = $this->validateData($request);
        $this->orders->updateDraft($order, $data, $this->actorId($request));

        return redirect()->route('sales.orders.show', $order)->with('status', 'Sales Order updated.');
    }

    public function submit(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->submit($order, $this->actorId($request));

        return back()->with('status', 'Order submitted for approval.');
    }

    public function approve(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->approve($order, $this->actorId($request));

        return back()->with('status', 'Order approved.');
    }

    public function reject(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->reject($order, $this->actorId($request));

        return back()->with('status', 'Order rejected.');
    }

    public function cancel(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->cancel($order, $this->actorId($request));

        return back()->with('status', 'Order cancelled.');
    }

    public function processing(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->markProcessing($order, $this->actorId($request));

        return back()->with('status', 'Order moved to processing.');
    }

    public function readyForDelivery(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->markReadyForDelivery($order, $this->actorId($request));

        return back()->with('status', 'Order ready for delivery.');
    }

    public function complete(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->complete($order, $this->actorId($request));

        return back()->with('status', 'Order completed.');
    }

    public function print(Request $request, SalesOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $order->load(['customer', 'currency', 'lines.inventoryItem', 'lines.taxGroup', 'branch', 'institute', 'quotation']);

        return view('sales.orders.print', [
            'institute' => $institute,
            'order' => $order,
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:parties,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'payment_terms' => ['nullable', 'string', 'max:40'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
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

    private function assertBranchScope(SalesOrder $order, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function assertBranchScopeForQuotation(SalesQuotation $quotation, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $quotation->branch_id !== null && (int) $quotation->branch_id !== $branchId) {
            abort(404);
        }
    }
}
