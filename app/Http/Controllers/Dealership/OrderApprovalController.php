<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\OrderApproval;
use App\Models\Dealership\SrOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderApprovalController extends Controller
{
    use ResolvesInstitute;

    public function approve(Request $request, SrOrder $order): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate(['note' => ['nullable', 'string']]);

        if ($order->status !== 'submitted') {
            abort(422, 'Only submitted orders can be approved.');
        }

        $order->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        OrderApproval::create([
            'sr_order_id' => $order->id,
            'action' => 'approved',
            'actor_id' => auth()->id(),
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Order approved.');
    }

    public function reject(Request $request, SrOrder $order): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate(['note' => ['nullable', 'string']]);

        if ($order->status !== 'submitted') {
            abort(422, 'Only submitted orders can be rejected.');
        }

        $order->update(['status' => 'rejected']);

        OrderApproval::create([
            'sr_order_id' => $order->id,
            'action' => 'rejected',
            'actor_id' => auth()->id(),
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Order rejected.');
    }
}
