<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\AuthorizesPermission;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Expense;
use App\Models\Party;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use AuthorizesPermission;
    use ResolvesInstitute;

    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): View
    {
        $this->requirePermission('purchase.expenses.view', 'purchase.view', 'purchase.manage');
        $institute = $this->requireInstitute($request);

        $expenses = Expense::query()
            ->where('institute_id', $institute->id)
            ->with(['customer', 'expenseAccount'])
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('expense_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('expense_date', '<=', $request->input('to_date')))
            ->when($request->filled('vendor_id'), function ($q) use ($request) {
                // expenses stores vendor_name text, not party_id — filter by name if provided
            })
            ->orderByDesc('expense_date')
            ->paginate(20)
            ->withQueryString();

        return view('purchase.expenses.index', [
            'institute' => $institute,
            'expenses' => $expenses,
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('purchase.expenses.create', 'purchase.manage');
        $institute = $this->requireInstitute($request);

        $vendors = Party::query()
            ->where('institute_id', $institute->id)
            ->whereIn('type', ['supplier', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('purchase.expenses.create', [
            'institute' => $institute,
            'vendors' => $vendors,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('purchase.expenses.create', 'purchase.manage');
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $actorId = $this->actorId($request);

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'expense_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'expense_category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $expense = $this->expenses->create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'paid_by_user_id' => $actorId,
            'payment_account_id' => $validated['payment_account_id'],
            'expense_date' => $validated['expense_date'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'expense_category' => $validated['expense_category'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'tax_amount' => $validated['tax_amount'] ?? 0,
            'expense_account_id' => $validated['expense_account_id'],
            'notes' => null,
        ], (int) $actorId);

        return redirect()
            ->route('purchase.expenses.index')
            ->with('status', 'Expense '.$expense->expense_number.' recorded successfully.');
    }
}
