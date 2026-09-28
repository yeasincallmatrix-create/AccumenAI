<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\SrCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SrCollectionController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $collections = SrCollection::query()->latest()->paginate(20);

        return view('dealership.collections.index', [
            'institute' => $institute,
            'collections' => $collections,
            'methods' => config('dealership.collection_methods', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'sales_force_id' => ['required', 'integer', 'min:1'],
            'sr_order_id' => ['nullable', 'integer', 'min:1'],
            'method' => ['required', 'in:cash,cheque,bank_transfer,mobile_banking'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'collected_on' => ['required', 'date'],
        ]);

        do {
            $receiptNo = 'RC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (SrCollection::where('receipt_no', $receiptNo)->exists());

        SrCollection::create([
            'receipt_no' => $receiptNo,
            'customer_id' => $data['customer_id'],
            'sales_force_id' => $data['sales_force_id'],
            'sr_order_id' => $data['sr_order_id'] ?? null,
            'method' => $data['method'],
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'collected_on' => $data['collected_on'],
            'status' => 'pending',
        ]);

        return redirect()->route('dealership.collections.index')->with('success', 'Collection recorded.');
    }
}
