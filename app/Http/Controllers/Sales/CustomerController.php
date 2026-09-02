<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['customer', 'both'])
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $customers = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('sales.customers.index', [
            'institute' => $institute,
            'customers' => $customers,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('sales.customers.form', [
            'institute' => $institute,
            'customer' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        Party::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'type' => 'customer',
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'tin' => $data['tin'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? null,
            'is_active' => true,
            'created_by' => $this->actorId($request),
        ]);

        return redirect()->route('sales.customers.manage.index')->with('status', 'Customer created successfully.');
    }

    public function show(Request $request, Party $customer): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($customer->institute_id !== $institute->id, 404);
        abort_unless($customer->isCustomer(), 404);

        $this->assertBranchScope($customer, $request);

        $customer->load(['customerGroup', 'billingCurrency']);

        $quotations = $customer->quotations()->latest()->limit(5)->get();
        $orders = $customer->orders()->latest()->limit(5)->get();
        $invoices = $customer->invoices()->latest()->limit(5)->get();

        return view('sales.customers.show', [
            'institute' => $institute,
            'customer' => $customer,
            'quotations' => $quotations,
            'orders' => $orders,
            'invoices' => $invoices,
        ]);
    }

    public function edit(Request $request, Party $customer): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($customer->institute_id !== $institute->id, 404);
        abort_unless($customer->isCustomer(), 404);

        $this->assertBranchScope($customer, $request);

        return view('sales.customers.form', [
            'institute' => $institute,
            'customer' => $customer,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Party $customer): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($customer->institute_id !== $institute->id, 404);
        abort_unless($customer->isCustomer(), 404);

        $this->assertBranchScope($customer, $request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'tin' => $data['tin'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => $this->actorId($request),
        ]);

        return redirect()->route('sales.customers.manage.show', $customer)->with('status', 'Customer updated successfully.');
    }

    private function assertBranchScope(Party $customer, Request $request): void
    {
        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $customer->branch_id !== null && (int) $customer->branch_id !== $branchId) {
            abort(404);
        }
    }
}
