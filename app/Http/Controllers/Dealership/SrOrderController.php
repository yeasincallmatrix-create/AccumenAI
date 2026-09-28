<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\CreditLimit;
use App\Models\Dealership\OrderApproval;
use App\Models\Dealership\SrOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SrOrderController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $orders = SrOrder::query()
            ->when($request->filled('sr'), fn ($q) => $q->where('sales_force_id', $request->input('sr')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.orders.index', [
            'institute' => $institute,
            'orders' => $orders,
            'filters' => $request->only(['sr', 'status', 'from', 'to']),
            'statuses' => config('dealership.order_statuses', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'sales_force_id' => ['required', 'integer', 'min:1'],
            'channel' => ['nullable', 'in:general,retail,wholesale,sub_dealer'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $subtotal = collect($data['items'])->sum(fn ($i) => $i['qty'] * $i['unit_price']);
        $discount = (float) ($data['discount'] ?? 0);
        $total = max(0, $subtotal - $discount);

        $limit = CreditLimit::where('customer_id', $data['customer_id'])->first();
        if ($limit && $limit->is_blocked) {
            return back()->withErrors(['customer_id' => 'Customer is blocked for credit.'])->withInput()->with('institute', $institute->id);
        }
        if ($limit && (float) $limit->credit_limit > 0) {
            $outstanding = (float) SrOrder::where('customer_id', $data['customer_id'])
                ->whereIn('status', ['submitted', 'approved'])
                ->sum('total');
            if ($outstanding + $total > (float) $limit->credit_limit) {
                return back()->withErrors(['customer_id' => 'Order exceeds customer credit limit.'])->withInput();
            }
        }

        $order = DB::transaction(function () use ($data, $subtotal, $discount, $total) {
            do {
                $orderNo = 'SO-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            } while (SrOrder::where('order_no', $orderNo)->exists());

            $order = SrOrder::create([
                'order_no' => $orderNo,
                'customer_id' => $data['customer_id'],
                'sales_force_id' => $data['sales_force_id'],
                'channel' => $data['channel'] ?? 'general',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => 'draft',
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['qty'] * $item['unit_price'],
                ]);
            }

            $order->update(['status' => 'submitted', 'submitted_at' => now()]);

            OrderApproval::create([
                'sr_order_id' => $order->id,
                'action' => 'submitted',
                'actor_id' => auth()->id(),
            ]);

            return $order;
        });

        return redirect()->route('dealership.orders.show', $order)->with('success', 'Order submitted.');
    }

    public function show(Request $request, SrOrder $order): View
    {
        $institute = $this->requireInstitute($request);

        $order->load(['items', 'approvals' => fn ($q) => $q->latest()]);

        return view('dealership.orders.show', [
            'institute' => $institute,
            'order' => $order,
        ]);
    }
}
