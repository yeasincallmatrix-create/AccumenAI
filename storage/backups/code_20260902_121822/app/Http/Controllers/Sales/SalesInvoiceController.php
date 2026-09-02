<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly SalesInvoiceService $invoices) {}

    public function createForOrder(Request $request, SalesOrder $order): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $order->load(['lines.inventoryItem', 'customer', 'currency', 'deliveries.lines']);
        $remaining = $this->invoices->remainingForOrder($order);
        $invoices = $this->invoices->invoicesForOrder($order)->load('payments');

        // Eligible deliveries for invoicing (confirmed)
        $deliveries = \App\Models\SalesDelivery::where('order_id', $order->id)
            ->where('institute_id', $institute->id)
            ->where('status', SalesDelivery::STATUS_CONFIRMED)
            ->orderByDesc('id')->get();

        return view('sales.invoices.create', [
            'institute' => $institute,
            'order' => $order,
            'remaining' => $remaining,
            'invoices' => $invoices,
            'deliveries' => $deliveries,
        ]);
    }

    public function storeForOrder(Request $request, SalesOrder $order): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($order->institute_id !== $institute->id, 404);
        $this->assertBranchScope($order, $request);

        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'delivery_id' => ['nullable', 'integer', 'exists:sales_deliveries,id'],
            'lines' => ['nullable', 'array'],
            'lines.*' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Build quantities map: filter zero/empty
        $quantities = [];
        if (! empty($data['lines']) && is_array($data['lines'])) {
            foreach ($data['lines'] as $lineId => $qty) {
                if ($qty === null || $qty === '') {
                    continue;
                }
                $qty = (float) $qty;
                if ($qty > 0.00005) {
                    $quantities[(int) $lineId] = $qty;
                }
            }
        }

        $deliveryId = $data['delivery_id'] ?? null;
        if ($deliveryId !== null) {
            $deliveryId = (int) $deliveryId;
        }

        $invoice = $this->invoices->createFromOrder(
            $institute->id,
            $branchId,
            $order->id,
            $deliveryId,
            $quantities,
            $this->actorId($request),
            $data['note'] ?? null,
        );

        return redirect()->route('sales.orders.show', $order)
            ->with('status', 'Invoice '.$invoice->invoice_number.' created (Payable '.number_format((float) $invoice->payable_amount,2).').');
    }

    public function storeForDelivery(Request $request, SalesDelivery $delivery): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($delivery->institute_id !== $institute->id, 404);
        $this->assertBranchScopeDelivery($delivery, $request);

        $invoice = $this->invoices->createFromDelivery(
            $institute->id,
            $this->actingBranchId($request),
            $delivery->id,
            $this->actorId($request),
        );

        return redirect()->route('sales.deliveries.show', $delivery)
            ->with('status', 'Invoice '.$invoice->invoice_number.' created from delivery.');
    }

    private function assertBranchScope(SalesOrder $order, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $order->branch_id !== null && (int) $order->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function assertBranchScopeDelivery(SalesDelivery $delivery, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $delivery->branch_id !== null && (int) $delivery->branch_id !== $branchId) {
            abort(404);
        }
    }
}
