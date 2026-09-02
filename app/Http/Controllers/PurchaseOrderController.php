<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuotation;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly PurchaseOrderService $orders,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = PurchaseOrder::with(['supplier', 'warehouse', 'currency'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('order_number', 'like', "%{$q}%")
                    ->orWhere('reference_number', 'like', "%{$q}%")
                    ->orWhereHas('supplier', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->input('to'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('branch_id') && $branchId === null) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $orders = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('purchase.orders.index', [
            'institute' => $institute,
            'orders' => $orders,
            'statuses' => PurchaseOrder::STATUSES,
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
            $q = PurchaseQuotation::withoutGlobalScopes()->where('institute_id', $institute->id)->where('id', $qId)->first();
            if ($q && $q->status === PurchaseQuotation::STATUS_ACCEPTED && $q->converted_to_order_id === null) {
                if ($branchId === null || $q->branch_id === null || (int) $q->branch_id === $branchId) {
                    $q->load('lines');
                    $prefillQuotation = $q;
                    $prefillData = [
                        'supplier_id' => $q->supplier_id,
                        'currency_id' => $q->currency_id,
                        'warehouse_id' => null,
                        'notes' => $q->notes,
                        'terms_conditions' => $q->terms_conditions,
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

        return view('purchase.orders.form', [
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

        if ($request->filled('quotation_id')) {
            $data = $request->validate([
                'quotation_id' => ['required', 'integer', 'exists:purchase_quotations,id'],
                'order_date' => ['required', 'date'],
                'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
                'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
                'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
                'reference_number' => ['nullable', 'string', 'max:80'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'terms_conditions' => ['nullable', 'string', 'max:5000'],
            ]);

            $quotation = PurchaseQuotation::withoutGlobalScopes()->where('id', $data['quotation_id'])->firstOrFail();
            abort_if($quotation->institute_id !== $institute->id, 404);
            $this->assertBranchScopeForQuotation($quotation, $request);

            $order = $this->orders->createFromQuotation($quotation, [
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
            ], $this->actorId($request));

            return redirect()->route('purchase.orders.show', $order)->with('status', 'Purchase Order '.$order->order_number.' created from quotation '.$quotation->quotation_number.'.');
        }

        $data = $this->validateData($request);
        $order = $this->orders->create($data, $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('purchase.orders.show', $order)->with('status', 'Purchase Order '.$order->order_number.' created.');
    }

    public function show(Request $request, PurchaseOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $order->load(['supplier', 'warehouse', 'currency', 'lines.inventoryItem', 'lines.taxGroup', 'branch', 'institute']);

        return view('purchase.orders.show', [
            'institute' => $institute,
            'order' => $order,
        ]);
    }

    public function edit(Request $request, PurchaseOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);
        abort_if(! $order->canEdit(), 422, 'Only draft orders can be edited.');

        $order->load(['lines']);
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();

        return view('purchase.orders.form', [
            'institute' => $institute,
            'order' => $order,
            'currencies' => $currencies,
            'isEdit' => true,
            'prefillQuotation' => null,
            'prefillData' => null,
        ]);
    }

    public function update(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $data = $this->validateData($request);
        $this->orders->update($order, $data, $this->actorId($request));

        return redirect()->route('purchase.orders.show', $order)->with('status', 'Purchase Order updated.');
    }

    public function submit(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->submit($order, $this->actorId($request));

        return back()->with('status', 'Order submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->approve($order, $this->actorId($request));

        return back()->with('status', 'Order approved.');
    }

    public function reject(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->reject($order, $this->actorId($request));

        return back()->with('status', 'Order rejected.');
    }

    public function cancel(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->cancel($order, $this->actorId($request));

        return back()->with('status', 'Order cancelled.');
    }

    public function close(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $this->orders->close($order, $this->actorId($request));

        return back()->with('status', 'Order closed.');
    }

    public function print(Request $request, PurchaseOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $order->load(['supplier', 'warehouse', 'currency', 'lines.inventoryItem', 'lines.taxGroup', 'branch', 'institute']);

        return view('purchase.orders.print', [
            'institute' => $institute,
            'order' => $order,
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:parties,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'reference_number' => ['nullable', 'string', 'max:80'],
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
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_group_id' => ['nullable', 'integer', 'exists:tax_groups,id'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function assertBranchScope(PurchaseOrder $order, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function assertBranchScopeForQuotation(PurchaseQuotation $quotation, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $quotation->branch_id !== null && (int) $quotation->branch_id !== $branchId) {
            abort(404);
        }
    }
}
