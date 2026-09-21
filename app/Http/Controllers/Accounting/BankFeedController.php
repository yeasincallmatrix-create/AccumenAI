<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankRule;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Services\Accounting\BankFeedMatchingService;
use App\Services\Accounting\BankStatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankFeedController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        protected BankStatementImportService $importService,
        protected BankFeedMatchingService $matchingService,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $bankAccounts = ChartOfAccount::where('institute_id', $institute->id)
            ->where('is_bank', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $statements = BankStatement::where('institute_id', $institute->id)
            ->orderByDesc('statement_date')
            ->paginate(20);

        return view('institute.accounting.bank-feed.index', [
            'institute' => $institute,
            'bankAccounts' => $bankAccounts,
            'statements' => $statements,
        ]);
    }

    public function upload(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $bankAccounts = ChartOfAccount::where('institute_id', $institute->id)
            ->where('is_bank', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('institute.accounting.bank-feed.upload', [
            'institute' => $institute,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,ofx,qfx|max:10240',
            'bank_account_id' => 'required|exists:chart_of_accounts,id',
            'statement_date' => 'nullable|date',
            'opening_balance' => 'nullable|numeric',
            'closing_balance' => 'nullable|numeric',
        ]);

        $filePath = $request->file('file')->store('bank-statements', 'local');
        $fullPath = storage_path('app/' . $filePath);

        try {
            $result = $this->importService->import($fullPath, [
                'institute_id' => $institute->id,
                'branch_id' => $this->actingBranchId($request),
                'bank_account_id' => $validated['bank_account_id'],
                'statement_date' => $validated['statement_date'] ?? now()->toDateString(),
                'opening_balance' => $validated['opening_balance'] ?? 0,
                'closing_balance' => $validated['closing_balance'] ?? 0,
            ]);

            if ($result['status'] === 'duplicate') {
                return back()->with('warning', 'This statement has already been imported (duplicate detected).');
            }

            return redirect()
                ->route('accounting.bank-feed.statement', $result['statement_id'])
                ->with('success', "Imported {$result['lines_created']} lines.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function statement(Request $request, BankStatement $statement): View
    {
        $institute = $this->requireInstitute($request);
        abort_unless($statement->institute_id === $institute->id, 403);

        $statement->load(['lines' => fn ($q) => $q->orderBy('transaction_date')]);

        $accounts = ChartOfAccount::where('institute_id', $institute->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $summary = [
            'total' => $statement->lines->count(),
            'unmatched' => $statement->lines->where('category_status', 'unmatched')->count(),
            'auto_matched' => $statement->lines->where('category_status', 'auto_matched')->count(),
            'rule_matched' => $statement->lines->where('category_status', 'rule_matched')->count(),
            'manual_matched' => $statement->lines->where('category_status', 'manual_matched')->count(),
            'ignored' => $statement->lines->where('category_status', 'ignored')->count(),
        ];

        return view('institute.accounting.bank-feed.statement', [
            'institute' => $institute,
            'statement' => $statement,
            'accounts' => $accounts,
            'summary' => $summary,
        ]);
    }

    public function autoMatch(Request $request, BankStatement $statement): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($statement->institute_id === $institute->id, 403);

        $count = $this->matchingService->autoMatch($statement->id);

        return back()->with('success', "Auto-matched {$count} lines.");
    }

    public function applyRules(Request $request, BankStatement $statement): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($statement->institute_id === $institute->id, 403);

        $count = $this->matchingService->applyRules($statement->id);

        return back()->with('success', "Applied rules to {$count} lines.");
    }

    public function categorize(Request $request, BankStatementLine $line): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($line->institute_id === $institute->id, 403);

        $validated = $request->validate([
            'account_id' => 'required|exists:chart_of_accounts,id',
            'party_id' => 'nullable|exists:parties,id',
            'narration' => 'nullable|string|max:500',
            'create_rule' => 'nullable|boolean',
        ]);

        $actorId = auth()->id() ?? null;

        $this->matchingService->categorize(
            $line,
            $validated['account_id'],
            $validated['party_id'] ?? null,
            $validated['narration'] ?? null,
            $actorId
        );

        if (($validated['create_rule'] ?? false) && $line->description) {
            BankRule::create([
                'institute_id' => $line->institute_id,
                'name' => 'Auto: ' . substr($line->description, 0, 100),
                'priority' => 100,
                'pattern_field' => 'description',
                'pattern_type' => 'contains',
                'pattern_value' => substr($line->description, 0, 100),
                'action_type' => 'categorize',
                'account_id' => $validated['account_id'],
                'is_active' => true,
            ]);
        }

        return back()->with('success', 'Line categorized.');
    }

    public function bulkCategorize(Request $request, BankStatement $statement): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($statement->institute_id === $institute->id, 403);

        $validated = $request->validate([
            'line_ids' => 'required|array',
            'line_ids.*' => 'exists:bank_statement_lines,id',
            'account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        $actorId = auth()->id() ?? null;
        $count = $this->matchingService->bulkCategorize(
            $validated['line_ids'],
            $validated['account_id'],
            $actorId
        );

        return back()->with('success', "Categorized {$count} lines.");
    }

    public function bulkIgnore(Request $request, BankStatement $statement): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($statement->institute_id === $institute->id, 403);

        $validated = $request->validate([
            'line_ids' => 'required|array',
            'line_ids.*' => 'exists:bank_statement_lines,id',
        ]);

        $count = $this->matchingService->bulkIgnore($validated['line_ids']);

        return back()->with('success', "Ignored {$count} lines.");
    }

    public function rules(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $rules = BankRule::where('institute_id', $institute->id)
            ->orderBy('priority')
            ->get();

        $accounts = ChartOfAccount::where('institute_id', $institute->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('institute.accounting.bank-feed.rules', [
            'institute' => $institute,
            'rules' => $rules,
            'accounts' => $accounts,
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'priority' => 'nullable|integer|min:1|max:9999',
            'pattern_field' => 'required|in:description,reference,counterparty',
            'pattern_type' => 'required|in:contains,starts_with,ends_with,exact,regex',
            'pattern_value' => 'required|string|max:500',
            'amount_operator' => 'nullable|in:=,>,<,>=,<=,between',
            'amount_min' => 'nullable|numeric',
            'amount_max' => 'nullable|numeric',
            'direction' => 'nullable|in:deposit,withdrawal,any',
            'action_type' => 'required|in:categorize,suggest_je,set_party,ignore',
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'narration' => 'nullable|string|max:500',
        ]);

        $validated['institute_id'] = $institute->id;
        $validated['branch_id'] = $this->actingBranchId($request);
        $validated['priority'] = $validated['priority'] ?? 100;
        $validated['is_active'] = true;
        $validated['times_applied'] = 0;

        BankRule::create($validated);

        return back()->with('success', 'Rule created.');
    }

    public function updateRule(Request $request, BankRule $rule): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($rule->institute_id === $institute->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'priority' => 'nullable|integer|min:1|max:9999',
            'pattern_field' => 'required|in:description,reference,counterparty',
            'pattern_type' => 'required|in:contains,starts_with,ends_with,exact,regex',
            'pattern_value' => 'required|string|max:500',
            'amount_operator' => 'nullable|in:=,>,<,>=,<=,between',
            'amount_min' => 'nullable|numeric',
            'amount_max' => 'nullable|numeric',
            'direction' => 'nullable|in:deposit,withdrawal,any',
            'action_type' => 'required|in:categorize,suggest_je,set_party,ignore',
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'narration' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $rule->update($validated);

        return back()->with('success', 'Rule updated.');
    }

    public function destroyRule(Request $request, BankRule $rule): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_unless($rule->institute_id === $institute->id, 403);

        $rule->delete();

        return back()->with('success', 'Rule deleted.');
    }
}
