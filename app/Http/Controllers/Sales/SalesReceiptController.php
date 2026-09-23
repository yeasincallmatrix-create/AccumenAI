<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\AuthorizesPermission;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\CashMemo;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesReceiptController extends Controller
{
    use AuthorizesPermission;
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $this->requirePermission('sales.receipts.view', 'sales.view', 'sales.manage');
        $institute = $this->requireInstitute($request);

        $receipts = CashMemo::query()
            ->where('institute_id', $institute->id)
            ->with(['party'])
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to_date')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.receipts.index', [
            'institute' => $institute,
            'receipts' => $receipts,
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('sales.receipts.create', 'sales.manage');
        $institute = $this->requireInstitute($request);

        $customers = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('sales.receipts.create', [
            'institute' => $institute,
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('sales.receipts.create', 'sales.manage');
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,bank,other'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        CashMemo::create([
            'institute_id' => $institute->id,
            'memo_number' => 'RC-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'party_id' => $validated['party_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'description' => $validated['description'] ?? null,
            'created_by' => $this->actorId($request),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('sales.receipts.index')
            ->with('status', 'Sales receipt created successfully.');
    }
}
