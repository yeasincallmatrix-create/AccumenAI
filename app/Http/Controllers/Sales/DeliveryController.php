<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Services\Sales\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DeliveryController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly DeliveryService $deliveries,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = SalesDelivery::with(['order', 'customer', 'warehouse'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('delivery_number', 'like', "%{$q}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$q}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('delivery_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('delivery_date', '<=', $request->input('to'));
        }

        $deliveries = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('sales.deliveries.index', [
            'institute' => $institute,
            'deliveries' => $deliveries,
            'statuses' => SalesDelivery::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $orderId = $request->input('order_id');
        $order = null;
        if ($orderId) {
            $order = SalesOrder::withoutGlobalScopes()->where('institute_id', $institute->id)->where('id', $orderId)->first();
            if ($order && $branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== (int) $branchId) {
                $order = null;
            }
        }

        $orders = SalesOrder::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->whereIn('status', [SalesOrder::STATUS_APPROVED, SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_READY_FOR_DELIVERY])
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('sales.deliveries.form', [
            'institute' => $institute,
            'orders' => $orders,
            'selectedOrder' => $order,
            'warehouses' => \App\Models\InventoryWarehouse::withoutGlobalScopes()->where('institute_id', $institute->id)->where('is_active', true)->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'delivery_date' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_line_id' => ['required', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.delivery_quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $delivery = $this->deliveries->createDelivery($institute->id, $branchId, (int) $data['order_id'], $data, $this->actorId($request));

        return redirect()->route('sales.deliveries.show', $delivery)->with('status', 'Delivery ' . $delivery->delivery_number . ' created.');
    }

    public function show(Request $request, SalesDelivery $delivery): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($delivery->institute_id !== $institute->id, 404);
        $this->assertBranchScope($delivery, $request);

        $delivery->load(['order.lines', 'customer', 'warehouse', 'lines.orderLine.inventoryItem', 'lines.inventoryItem']);

        return view('sales.deliveries.show', [
            'institute' => $institute,
            'delivery' => $delivery,
        ]);
    }

    public function confirm(Request $request, SalesDelivery $delivery): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($delivery->institute_id !== $institute->id, 404);
        $this->assertBranchScope($delivery, $request);

        $this->deliveries->confirmDelivery($delivery, $this->actorId($request));

        return back()->with('status', 'Delivery confirmed and inventory updated.');
    }

    public function cancel(Request $request, SalesDelivery $delivery): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($delivery->institute_id !== $institute->id, 404);
        $this->assertBranchScope($delivery, $request);

        $this->deliveries->cancelDelivery($delivery, $this->actorId($request));

        return back()->with('status', 'Delivery cancelled.');
    }

    public function print(Request $request, SalesDelivery $delivery): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($delivery->institute_id !== $institute->id, 404);
        $this->assertBranchScope($delivery, $request);

        $delivery->load(['order', 'customer', 'warehouse', 'lines.orderLine', 'lines.inventoryItem', 'branch', 'institute']);

        return view('sales.deliveries.print', [
            'institute' => $institute,
            'delivery' => $delivery,
        ]);
    }

    private function assertBranchScope(SalesDelivery $delivery, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $delivery->branch_id !== null && (int) $delivery->branch_id !== $branchId) {
            abort(404);
        }
    }
}
