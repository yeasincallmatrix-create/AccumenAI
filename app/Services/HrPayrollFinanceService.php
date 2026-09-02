<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\HrPayroll;
use App\Models\HrPayrollPeriod;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrPayrollFinanceService
{
    public function __construct(
        private readonly JournalPostingService $journals,
        private readonly ChartOfAccountService $coaService,
    ) {}

    /**
     * Ensure payroll CoAs exist (creates if missing).
     * Returns [expenseId, payableId, taxPayableId, deductionPayableId]
     */
    public function ensurePayrollAccounts(int $instituteId, ?int $branchId): array
    {
        $expense = $this->accountByCode($instituteId, $branchId, '5001', 'Salary & Wages', 'expense');
        $payable = $this->accountByCode($instituteId, $branchId, '2005', 'Salary Payable', 'liability', true);
        $tax = $this->accountByCode($instituteId, $branchId, '2110', 'Payroll Tax Payable', 'liability', true);
        $deduct = $this->accountByCode($instituteId, $branchId, '2120', 'Other Payroll Deductions Payable', 'liability', true);

        return [(int) $expense->id, (int) $payable->id, (int) $tax->id, (int) $deduct->id];
    }

    private function accountByCode(int $instituteId, ?int $branchId, string $code, string $name, string $type, bool $isPayable = false): ChartOfAccount
    {
        $existing = ChartOfAccount::where('institute_id', $instituteId)
            ->where('code', $code)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->first();
        if ($existing) {
            return $existing;
        }

        // Use ChartOfAccountService if available, otherwise create directly
        try {
            return $this->coaService->accountByCode($instituteId, $code, $branchId) ?? $this->createCoa($instituteId, $branchId, $code, $name, $type, $isPayable);
        } catch (\Throwable $e) {
            return $this->createCoa($instituteId, $branchId, $code, $name, $type, $isPayable);
        }
    }

    private function createCoa(int $instituteId, ?int $branchId, string $code, string $name, string $type, bool $isPayable): ChartOfAccount
    {
        // Find or create account group for type
        $group = DB::table('account_groups')->where('category', $type)->where(function ($q) use ($instituteId) {
            $q->where('institute_id', $instituteId);
        })->first();
        $groupId = $group?->id;

        if (! $groupId) {
            $groupId = DB::table('account_groups')->insertGetId([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'code' => strtoupper(substr($type, 0, 3)).'-GRP',
                'name' => ucfirst($type).' Group',
                'category' => $type,
                'is_system' => false,
                'sort_order' => 900,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ChartOfAccount::create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'account_group_id' => $groupId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_payable' => $isPayable,
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    private function baseCurrencyId(): int
    {
        $currency = Currency::where('is_base', true)->first() ?? Currency::orderBy('code')->first();
        if (! $currency) {
            throw ValidationException::withMessages(['currency' => 'No currency configured.']);
        }

        return $currency->id;
    }

    /**
     * Post accrual journal for a payslip (salary expense).
     * Transaction-safe, balanced, prevents duplicate via payroll->journal_id check outside.
     */
    public function postAccrual(HrPayroll $payroll, ?int $actorId = null): HrPayroll
    {
        if ($payroll->journal_id !== null) {
            throw ValidationException::withMessages(['payroll' => 'Payroll already has an accrual journal.']);
        }
        if ($payroll->isFinalized() === false && $payroll->status !== 'draft') {
            // Allow draft -> approved transition to post
        }

        [$expenseId, $payableId, $taxPayableId, $deductPayableId] = $this->ensurePayrollAccounts($payroll->institute_id, $payroll->branch_id);

        $currencyId = $payroll->currency_id ?? $this->baseCurrencyId();
        $gross = (float) $payroll->gross_earnings;
        $tax = 0;
        $otherDed = 0;
        // Extract tax/deduction from deductions_snapshot for separate credit lines
        foreach ($payroll->deductions_snapshot ?? [] as $d) {
            $code = $d['code'] ?? '';
            $amt = (float) ($d['amount'] ?? 0);
            if (in_array($code, ['tax', '2110'], true) || stripos($d['name'] ?? '', 'tax') !== false) {
                $tax += $amt;
            } else {
                $otherDed += $amt;
            }
        }
        // Fallback: if snapshot empty, use total_deductions split proportionally? Keep simple: otherDed = total_deductions - tax
        if (empty($payroll->deductions_snapshot) && (float) $payroll->total_deductions > 0) {
            $otherDed = (float) $payroll->total_deductions - $tax;
        }

        $net = (float) $payroll->net_salary;

        // Ensure gross = net + tax + otherDed (adjust for rounding)
        $sumCredits = $net + $tax + $otherDed;
        if (abs($gross - $sumCredits) > 0.01) {
            // Adjust otherDed to balance
            $otherDed = $gross - $net - $tax;
            if ($otherDed < 0) {
                $otherDed = 0;
            }
        }

        $entries = [];
        $entries[] = ['coa_id' => $expenseId, 'debit' => round($gross, 2), 'credit' => 0, 'memo' => 'Salary expense '.$payroll->payslip_no];
        $entries[] = ['coa_id' => $payableId, 'debit' => 0, 'credit' => round($net, 2), 'memo' => 'Salary payable '.$payroll->payslip_no];
        if ($tax > 0.005) {
            $entries[] = ['coa_id' => $taxPayableId, 'debit' => 0, 'credit' => round($tax, 2), 'memo' => 'Payroll tax '.$payroll->payslip_no];
        }
        if ($otherDed > 0.005) {
            $entries[] = ['coa_id' => $deductPayableId, 'debit' => 0, 'credit' => round($otherDed, 2), 'memo' => 'Other deductions '.$payroll->payslip_no];
        }

        // Ensure at least 2 lines and balanced
        if (count($entries) < 2) {
            throw ValidationException::withMessages(['entries' => 'Journal requires at least 2 lines.']);
        }

        $journal = $this->journals->create([
            'institute_id' => $payroll->institute_id,
            'branch_id' => $payroll->branch_id,
            'journal_date' => $payroll->period->end_date->format('Y-m-d'),
            'type' => 'journal',
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'description' => 'Payroll accrual '.$payroll->payslip_no.' - '.$payroll->employee->display_name,
            'ref_type' => 'payroll',
            'ref_id' => $payroll->id,
            'entries' => $entries,
        ], $actorId, true);

        $payroll->update(['journal_id' => $journal->id]);

        return $payroll->fresh();
    }

    /**
     * Post payment journal (salary payable -> cash/bank).
     */
    public function postPayment(HrPayroll $payroll, ?int $actorId = null, ?string $paymentMethod = null): HrPayroll
    {
        if ($payroll->payment_journal_id !== null) {
            throw ValidationException::withMessages(['payroll' => 'Payroll already paid.']);
        }
        if ($payroll->journal_id === null) {
            throw ValidationException::withMessages(['payroll' => 'Accrual journal must be posted before payment.']);
        }

        [$expenseId, $payableId, $taxPayableId, $deductPayableId] = $this->ensurePayrollAccounts($payroll->institute_id, $payroll->branch_id);
        $currencyId = $payroll->currency_id ?? $this->baseCurrencyId();
        $net = (float) $payroll->net_salary;

        // Resolve cash/bank account
        $cashCoaId = $this->resolveCashAccount($payroll->institute_id, $payroll->branch_id, $paymentMethod);

        $entries = [
            ['coa_id' => $payableId, 'debit' => round($net, 2), 'credit' => 0, 'memo' => 'Salary payment '.$payroll->payslip_no],
            ['coa_id' => $cashCoaId, 'debit' => 0, 'credit' => round($net, 2), 'memo' => 'Cash/Bank '.$payroll->payslip_no],
        ];

        $journal = $this->journals->create([
            'institute_id' => $payroll->institute_id,
            'branch_id' => $payroll->branch_id,
            'journal_date' => now()->toDateString(),
            'type' => 'payment',
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'description' => 'Payroll payment '.$payroll->payslip_no,
            'ref_type' => 'payroll_payment',
            'ref_id' => $payroll->id,
            'entries' => $entries,
        ], $actorId, true);

        $payroll->update(['payment_journal_id' => $journal->id, 'status' => 'paid', 'paid_by' => $actorId, 'paid_at' => now()]);

        return $payroll->fresh();
    }

    private function resolveCashAccount(int $instituteId, ?int $branchId, ?string $method): int
    {
        // Try to use PaymentAccountResolver pattern: is_cash or is_bank
        $coa = ChartOfAccount::where('institute_id', $instituteId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_cash', true)->orWhere('is_bank', true);
            })
            ->orderBy('code')
            ->first();

        if ($coa) {
            return (int) $coa->id;
        }

        // Fallback to cash 1001 or bank 1002 via ensure
        try {
            $cash = $this->accountByCode($instituteId, $branchId, '1001', 'Cash in Hand', 'asset');

            return (int) $cash->id;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['account' => 'No cash/bank account found for payroll payment. Configure chart of accounts.']);
        }
    }

    public function reverseAccrual(HrPayroll $payroll, ?int $actorId = null, ?string $reason = null): void
    {
        if ($payroll->journal_id === null) {
            return;
        }
        $journal = Journal::find($payroll->journal_id);
        if ($journal && $journal->status === 'posted') {
            $this->journals->reverse($journal, $payroll->institute_id, $actorId, $reason ?? 'Payroll cancelled '.$payroll->payslip_no);
        }
    }

    public function reversePayment(HrPayroll $payroll, ?int $actorId = null, ?string $reason = null): void
    {
        if ($payroll->payment_journal_id === null) {
            return;
        }
        $journal = Journal::find($payroll->payment_journal_id);
        if ($journal && $journal->status === 'posted') {
            $this->journals->reverse($journal, $payroll->institute_id, $actorId, $reason ?? 'Payroll payment reversed '.$payroll->payslip_no);
        }
    }

    /**
     * Reconciliation for a payroll period / institute / branch scope.
     * Returns payroll totals vs journal totals and outstanding.
     */
    public function reconciliation(int $instituteId, ?int $branchId = null, ?int $periodId = null): array
    {
        $payrollQuery = HrPayroll::where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($periodId !== null, fn ($q) => $q->where('payroll_period_id', $periodId))
            ->whereNotIn('status', ['cancelled', 'void']);

        $payrollTotal = (float) (clone $payrollQuery)->sum('net_salary');
        $grossTotal = (float) (clone $payrollQuery)->sum('gross_earnings');
        $deductionTotal = (float) (clone $payrollQuery)->sum('total_deductions');
        $paidTotal = (float) (clone $payrollQuery)->where('status', 'paid')->sum('net_salary');
        $payableTotal = $payrollTotal; // payable is net for all non-cancelled
        $outstanding = $payableTotal - $paidTotal;

        // Journal totals: sum of posted payroll accrual journals (type journal, ref payroll) + payment journals
        $journalIds = (clone $payrollQuery)->whereNotNull('journal_id')->pluck('journal_id')->all();
        $journalTotal = 0;
        if ($journalIds !== []) {
            $journalTotal = (float) JournalEntry::whereIn('journal_id', $journalIds)
                ->where('debit', '>', 0)
                ->sum('debit');
        }
        // Reconciliation status: matched if payroll gross == journal debit total (within epsilon)
        $matched = abs($grossTotal - $journalTotal) < 0.01;

        // Salary payable balance from ledger: credit of payable accounts minus debit
        // Use chart accounts 2005, 2110, 2120 if exist
        $payableBalance = null;
        try {
            $payableCodes = ['2005', '2110', '2120'];
            $coas = ChartOfAccount::where('institute_id', $instituteId)
                ->whereIn('code', $payableCodes)
                ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
                ->pluck('id')->all();
            if ($coas !== []) {
                $credits = (float) JournalEntry::whereIn('coa_id', $coas)->whereIn('journal_id', function ($q) use ($instituteId) {
                    $q->select('id')->from('journals')->where('institute_id', $instituteId)->where('status', 'posted');
                })->sum('credit');
                $debits = (float) JournalEntry::whereIn('coa_id', $coas)->whereIn('journal_id', function ($q) use ($instituteId) {
                    $q->select('id')->from('journals')->where('institute_id', $instituteId)->where('status', 'posted');
                })->sum('debit');
                $payableBalance = $credits - $debits;
            }
        } catch (\Throwable $e) {
            $payableBalance = null;
        }

        return [
            'payroll_total' => round($payrollTotal, 2),
            'gross_total' => round($grossTotal, 2),
            'deduction_total' => round($deductionTotal, 2),
            'journal_total' => round($journalTotal, 2),
            'salary_payable' => round($payableTotal, 2),
            'paid_amount' => round($paidTotal, 2),
            'outstanding_salary' => round($outstanding, 2),
            'payable_ledger_balance' => $payableBalance !== null ? round($payableBalance, 2) : null,
            'finance_reconciliation_status' => $matched ? 'matched' : 'mismatch',
            'matched' => $matched,
        ];
    }

    /**
     * Ensure accounting period covering payroll period is open, otherwise reject.
     * Throws ValidationException if closed.
     */
    public function assertPeriodOpenForPayroll(HrPayrollPeriod $period): void
    {
        $fiscalYear = FiscalYear::where('institute_id', $period->institute_id)
            ->where('is_current', true)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $period->end_date)
            ->whereDate('end_date', '>=', $period->end_date)
            ->first();
        if (! $fiscalYear) {
            // Fallback to JournalPostingService covering check via exception
            return;
        }
        $hasPeriods = AccountingPeriod::where('institute_id', $period->institute_id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->exists();
        if (! $hasPeriods) {
            return;
        }

        $openPeriod = AccountingPeriod::where('institute_id', $period->institute_id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $period->end_date)
            ->whereDate('end_date', '>=', $period->end_date)
            ->where(fn ($q) => $q->where('branch_id', $period->branch_id)->orWhereNull('branch_id'))
            ->first();

        if (! $openPeriod) {
            throw ValidationException::withMessages(['period_id' => 'Payroll posting is not allowed in a closed accounting period.']);
        }
    }
}
