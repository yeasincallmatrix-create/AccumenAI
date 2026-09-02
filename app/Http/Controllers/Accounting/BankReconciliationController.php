<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 78 — Bank Reconciliation UI Controller.
 */
class BankReconciliationController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly BankReconciliationService $reconciliationSvc,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $bankAccounts = ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->where('is_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('institute.accounting.bank-reconciliation.index', [
            'institute' => $institute,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function statements(Request $request, int $accountId): View
    {
        $institute = $this->requireInstitute($request);

        $account = ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->where('id', $accountId)
            ->firstOrFail();

        $statements = BankStatement::query()
            ->where('institute_id', $institute->id)
            ->where('bank_account_id', $accountId)
            ->orderByDesc('statement_date')
            ->get();

        return view('institute.accounting.bank-reconciliation.statements', [
            'institute' => $institute,
            'account' => $account,
            'statements' => $statements,
        ]);
    }

    public function storeStatement(Request $request, int $accountId): \Illuminate\Http\RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'statement_date' => 'required|date',
        ]);

        $branchId = $this->actingBranchId($request);

        BankStatement::create(array_merge($validated, [
            'institute_id' => $institute->id,
            'bank_account_id' => $accountId,
            'branch_id' => $branchId,
            'status' => 'imported',
        ]));

        return redirect()->route('accounting.bank-reconciliation.statements', ['accountId' => $accountId])
            ->with('success', 'Bank statement created.');
    }

    public function show(Request $request, int $statementId): View
    {
        $institute = $this->requireInstitute($request);

        $statement = BankStatement::query()
            ->where('institute_id', $institute->id)
            ->where('id', $statementId)
            ->with('bankAccount')
            ->firstOrFail();

        $lines = BankStatementLine::query()
            ->where('institute_id', $institute->id)
            ->where('statement_id', $statementId)
            ->orderByDesc('transaction_date')
            ->get();

        $summary = $this->reconciliationSvc->summary($statement);

        return view('institute.accounting.bank-reconciliation.show', [
            'institute' => $institute,
            'statement' => $statement,
            'lines' => $lines,
            'summary' => $summary,
        ]);
    }

    public function autoMatch(Request $request, int $statementId): \Illuminate\Http\RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $statement = BankStatement::query()
            ->where('institute_id', $institute->id)
            ->where('id', $statementId)
            ->firstOrFail();

        $actorId = $request->user()->id;
        $result = $this->reconciliationSvc->autoMatch($statement, $actorId);

        return redirect()->route('accounting.bank-reconciliation.show', ['statementId' => $statementId])
            ->with('success', "Matched {$result['matched']} transaction(s). {$result['unmatched']} unmatched.");
    }

    public function storeLine(Request $request, int $statementId): \Illuminate\Http\RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:deposit,withdrawal',
            'reference' => 'nullable|string|max:255',
        ]);

        BankStatementLine::create(array_merge($validated, [
            'institute_id' => $institute->id,
            'statement_id' => $statementId,
        ]));

        return redirect()->route('accounting.bank-reconciliation.show', ['statementId' => $statementId])
            ->with('success', 'Statement line added.');
    }

    public function destroyLine(Request $request, int $lineId): \Illuminate\Http\RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $line = BankStatementLine::query()
            ->where('institute_id', $institute->id)
            ->where('id', $lineId)
            ->firstOrFail();

        $statementId = $line->statement_id;
        $line->delete();

        return redirect()->route('accounting.bank-reconciliation.show', ['statementId' => $statementId])
            ->with('success', 'Line removed.');
    }
}
