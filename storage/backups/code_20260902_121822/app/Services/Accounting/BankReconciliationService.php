<?php

namespace App\Services\Accounting;

use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

/**
 * STEP 71 — Bank Reconciliation Service.
 *
 * Auto-matches bank statement lines with journal entries by:
 * 1. Reference number
 * 2. Amount
 * 3. Date
 */
class BankReconciliationService
{
    /**
     * Auto-match statement lines to posted journal entries.
     *
     * Priority: reference > amount+date > amount alone.
     *
     * @return array{matched: int, unmatched: int}
     */
    public function autoMatch(BankStatement $statement, int $actorId): array
    {
        $lines = $statement->lines()
            ->whereDoesntHave('reconciliations', fn ($q) => $q->where('status', 'matched'))
            ->get();

        $bankAccount = $statement->bank_account_id;
        $matched = 0;
        $unmatched = 0;

        foreach ($lines as $line) {
            if ($this->findAndMatch($line, $bankAccount, $actorId)) {
                $matched++;
            } else {
                $unmatched++;
            }
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * Find a matching journal entry for a statement line and create the link.
     */
    private function findAndMatch(BankStatementLine $line, int $bankAccountId, int $actorId): bool
    {
        $query = Journal::query()
            ->where('institute_id', $line->institute_id)
            ->where('status', 'posted')
            ->whereNull('reversal_of')
            ->whereHas('entries', function ($q) use ($bankAccountId) {
                $q->where('coa_id', $bankAccountId);
            })
            ->whereDoesntHave('reconciliations', function ($q) {
                $q->where('status', 'matched');
            });

        // Priority 1: Match by reference number
        if ($line->reference !== null) {
            $journal = $query->where('journal_no', $line->reference)
                ->orWhere('ref_type', $line->reference)
                ->first();

            if ($journal !== null) {
                return $this->createReconciliation($line, $journal, $actorId);
            }
        }

        // Priority 2: Match by amount + date (within 3 days)
        $amount = (float) $line->amount;
        $journal = $query->whereHas('entries', function ($q) use ($amount, $line) {
            if ($line->type === 'deposit') {
                $q->where('coa_id', $line->statement->bank_account_id)
                  ->where('debit', $amount);
            } else {
                $q->where('coa_id', $line->statement->bank_account_id)
                  ->where('credit', $amount);
            }
        })
        ->whereDate('journal_date', $line->transaction_date)
        ->first();

        if ($journal !== null) {
            return $this->createReconciliation($line, $journal, $actorId);
        }

        // Priority 3: Match by amount alone (date within 7 days)
        $journal = $query->whereHas('entries', function ($q) use ($amount, $line) {
            if ($line->type === 'deposit') {
                $q->where('coa_id', $line->statement->bank_account_id)
                  ->where('debit', $amount);
            } else {
                $q->where('coa_id', $line->statement->bank_account_id)
                  ->where('credit', $amount);
            }
        })
        ->whereDate('journal_date', '>=', $line->transaction_date->copy()->subDays(7))
        ->whereDate('journal_date', '<=', $line->transaction_date->copy()->addDays(7))
        ->first();

        if ($journal !== null) {
            return $this->createReconciliation($line, $journal, $actorId);
        }

        return false;
    }

    private function createReconciliation(BankStatementLine $line, Journal $journal, int $actorId): bool
    {
        BankReconciliation::create([
            'statement_line_id' => $line->id,
            'journal_id' => $journal->id,
            'status' => 'matched',
            'matched_by' => $actorId,
            'matched_at' => now(),
        ]);

        return true;
    }

    /**
     * Ignore a statement line (manual decision that it doesn't match).
     */
    public function ignore(BankStatementLine $line, int $actorId): void
    {
        BankReconciliation::updateOrCreate(
            ['statement_line_id' => $line->id, 'status' => '!=', 'ignored'],
            [
                'status' => 'ignored',
                'matched_by' => $actorId,
                'matched_at' => now(),
            ]
        );
    }

    /**
     * Get reconciliation status summary for a statement.
     *
     * @return array{total: int, matched: int, unmatched: int, ignored: int}
     */
    public function summary(BankStatement $statement): array
    {
        $total = $statement->lines()->count();
        $lineIds = $statement->lines()->pluck('id');
        $matched = BankReconciliation::whereIn('statement_line_id', $lineIds)->where('status', 'matched')->count();
        $ignored = BankReconciliation::whereIn('statement_line_id', $lineIds)->where('status', 'ignored')->count();

        return [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $total - $matched,
            'ignored' => $ignored,
        ];
    }
}
