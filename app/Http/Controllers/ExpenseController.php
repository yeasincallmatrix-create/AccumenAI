<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Accounting\ChartOfAccount;
use App\Models\Accounting\Expense;
use App\Models\Accounting\TaxGroup;
use App\Models\Party;
use App\Services\Accounting\BillableExpenseBillingService;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        protected ExpenseService $expenseService,
        protected BillableExpenseBillingService $billingService,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = Expense::where('institute_id', $institute->id)
            ->with(['customer', 'expenseAccount']);

        if (filled($q = $request->input('q'))) {
            $query->where(fn ($qq) => $qq->where('expense_number', 'like', "%{$q}%")
                ->orWhere('vendor_name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }
        if ($request->filled('category')) {
            $query->where('expense_category', $request->input('category'));
        }
        if ($request->filled('billing_status')) {
            $query->where('billing_status', $request->input('billing_status'));
        }
        if ($request->boolean('only_billable')) {
            $query->where('is_billable', true);
        }

        $expenses = $query->orderByDesc('expense_date')->paginate(20)->withQueryString();

        return view('institute.finance.expenses.index', [
            'institute' => $institute,
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.expenses.create', array_merge(
            ['institute' => $institute],
            $this->formData($institute->id)
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'expense_date' => 'required|date',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'expense_account_id' => 'required|exists:chart_of_accounts,id',
            'vendor_name' => 'nullable|string|max:200',
            'reference_number' => 'nullable|string|max:50',
            'expense_category' => 'required|string|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'tax_amount' => 'nullable|numeric|min:0',
            'tax_group_id' => 'nullable|exists:tax_groups,id',
            'is_billable' => 'boolean',
            'customer_id' => 'nullable|required_if:is_billable,1|exists:parties,id',
            'markup_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['institute_id'] = $institute->id;
        $validated['branch_id'] = $this->actingBranchId($request);

        $expense = $this->expenseService->create($validated, $this->actorId($request) ?? auth()->id());

        return redirect()->route('finance.expenses.show', $expense)
            ->with('success', "Expense {$expense->expense_number} created.");
    }

    public function show(Expense $expense): View
    {
        $this->authorizeExpenseAccess($expense);
        $expense->load(['customer', 'expenseAccount', 'paymentAccount', 'journalEntry', 'billedInvoice']);

        return view('institute.finance.expenses.show', [
            'institute' => \App\Models\Institute::find($expense->institute_id),
            'expense' => $expense,
        ]);
    }

    public function edit(Expense $expense): View
    {
        $this->authorizeExpenseAccess($expense);
        if ($expense->isBilled()) {
            return back()->with('error', 'Cannot edit a billed expense.');
        }

        return view('institute.finance.expenses.edit', array_merge(
            [
                'institute' => \App\Models\Institute::find($expense->institute_id),
                'expense' => $expense,
            ],
            $this->formData($expense->institute_id)
        ));
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeExpenseAccess($expense);

        $validated = $request->validate([
            'expense_date' => 'required|date',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'expense_account_id' => 'required|exists:chart_of_accounts,id',
            'vendor_name' => 'nullable|string|max:200',
            'reference_number' => 'nullable|string|max:50',
            'expense_category' => 'required|string|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'tax_amount' => 'nullable|numeric|min:0',
            'is_billable' => 'boolean',
            'customer_id' => 'nullable|required_if:is_billable,1|exists:parties,id',
            'markup_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $this->expenseService->update($expense, $validated, $this->actorId($request) ?? auth()->id());

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorizeExpenseAccess($expense);
        try {
            $this->expenseService->delete($expense);
            return redirect()->route('finance.expenses.index')->with('success', 'Expense deleted.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function billableDashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $unbilled = Expense::where('institute_id', $institute->id)
            ->where('is_billable', true)
            ->where('billing_status', 'unbilled')
            ->with('customer')
            ->get();

        $byCustomer = $unbilled->groupBy('customer_id')->map(fn ($group) => [
            'customer' => $group->first()->customer,
            'count' => $group->count(),
            'total' => $group->sum(fn ($e) => (float) ($e->billable_amount ?? $e->amount)),
            'expenses' => $group,
        ]);

        return view('institute.finance.expenses.billable-dashboard', [
            'institute' => $institute,
            'byCustomer' => $byCustomer,
        ]);
    }

    public function markBillable(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeExpenseAccess($expense);
        $request->validate([
            'customer_id' => 'required|exists:parties,id',
            'markup_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $this->expenseService->markBillable(
            $expense,
            $request->customer_id,
            (float) ($request->markup_percentage ?? 0)
        );

        return back()->with('success', 'Expense marked as billable.');
    }

    public function bulkMarkBillable(Request $request): RedirectResponse
    {
        $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:expenses,id',
            'customer_id' => 'required|exists:parties,id',
            'markup_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $count = $this->expenseService->bulkMarkBillable(
            $request->expense_ids,
            $request->customer_id,
            (float) ($request->markup_percentage ?? 0)
        );

        return back()->with('success', "Marked {$count} expenses as billable.");
    }

    public function previewInvoice(Request $request)
    {
        $request->validate([
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:expenses,id',
        ]);

        $preview = $this->billingService->previewTotals($request->expense_ids);

        return response()->json($preview);
    }

    public function generateInvoice(Request $request): RedirectResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:parties,id',
            'expense_ids' => 'required|array',
            'expense_ids.*' => 'exists:expenses,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'notes' => 'nullable|string',
        ]);

        try {
            $invoice = $this->billingService->generateInvoice(
                $request->customer_id,
                $request->expense_ids,
                $request->only(['invoice_date', 'due_date', 'notes']),
                $this->actorId($request) ?? auth()->id()
            );

            return redirect()->route('finance.invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_number} generated from expenses.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }
    }

    protected function formData(int $instituteId): array
    {
        return [
            'paymentAccounts' => ChartOfAccount::where('institute_id', $instituteId)
                ->where(fn ($q) => $q->where('is_bank', true)->orWhere('is_cash', true))
                ->orderBy('code')->get(),
            'expenseAccounts' => ChartOfAccount::where('institute_id', $instituteId)
                ->where('type', 'expense')
                ->orderBy('code')->get(),
            'customers' => Party::where('institute_id', $instituteId)
                ->whereIn('type', ['customer', 'both'])
                ->orderBy('name')->get(),
            'taxGroups' => TaxGroup::where('institute_id', $instituteId)->get(),
            'categories' => Expense::CATEGORIES,
        ];
    }

    protected function authorizeExpenseAccess(Expense $expense): void
    {
        $institute = request()->user()?->institute_id;
        abort_unless($expense->institute_id === $institute, 403);
    }
}
