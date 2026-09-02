<?php

namespace App\Services\Accounting;

/**
 * STEP 70 — Ledger Reconciliation.
 *
 * Validates that the Account Ledger ending balance for each account
 * matches the Trial Balance figure for that account.
 */
class LedgerReconciliationService
{
    public function __construct(
        private readonly FinancialReportService $reports,
    ) {}

    /**
     * Validate account ledger ending balance equals trial balance account balance.
     *
     * Returns an array of mismatches. Empty array = fully reconciled.
     *
     * @return array<int, array{coa_id: int, code: string, name: string, ledger_closing: float, trial_balance: float, difference: float}>
     */
    public function reconcile(int $instituteId, ?int $branchId, ?string $asOfDate = null, ?int $fiscalYearId = null): array
    {
        $trialBalance = $this->reports->trialBalance($instituteId, $branchId, $asOfDate, $fiscalYearId)
            ->keyBy('coa_id');

        $mismatches = [];

        foreach ($trialBalance as $coaId => $tbRow) {
            $ledger = $this->reports->accountLedger($instituteId, $branchId, $coaId, null, $asOfDate, $fiscalYearId);

            $ledgerClosing = $ledger['closing'];
            $trialBalanceBalance = round((float) $tbRow->debit - (float) $tbRow->credit, 4);
            $difference = round($ledgerClosing - $trialBalanceBalance, 4);

            if (abs($difference) > 0.0001) {
                $mismatches[] = [
                    'coa_id' => $coaId,
                    'code' => $tbRow->code,
                    'name' => $tbRow->name,
                    'ledger_closing' => $ledgerClosing,
                    'trial_balance' => $trialBalanceBalance,
                    'difference' => $difference,
                ];
            }
        }

        return $mismatches;
    }
}
