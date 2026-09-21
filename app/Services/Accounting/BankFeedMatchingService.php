<?php

namespace App\Services\Accounting;

use App\Models\BankReconciliation;
use App\Models\BankRule;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankFeedMatchingService
{
    public function __construct(
        protected JournalPostingService $journalService,
    ) {}

    public function autoMatch(int $statementId, int $dateWindowDays = 7): int
    {
        $statement = BankStatement::findOrFail($statementId);
        $bankAccountId = $statement->bank_account_id;

        $lines = BankStatementLine::where('statement_id', $statementId)
            ->where('category_status', 'unmatched')
            ->get();

        $matched = 0;

        foreach ($lines as $line) {
            $result = $this->matchLineToJournal($line, $bankAccountId, $dateWindowDays);
            if ($result) {
                $matched++;
            }
        }

        return $matched;
    }

    protected function matchLineToJournal(BankStatementLine $line, int $bankAccountId, int $dateWindowDays): bool
    {
        $lineAmount = (float) $line->amount;
        $lineDate = $line->transaction_date->format('Y-m-d');

        $dateStart = date('Y-m-d', strtotime($lineDate . " -{$dateWindowDays} days"));
        $dateEnd = date('Y-m-d', strtotime($lineDate . " +{$dateWindowDays} days"));

        // Priority 1: Match by reference number
        if ($line->reference) {
            $match = DB::table('journals')
                ->where('institute_id', $line->institute_id)
                ->where(function ($q) use ($line) {
                    $q->where('journal_no', $line->reference)
                      ->orWhere('ref_type', $line->reference);
                })
                ->where('status', 'posted')
                ->first();

            if ($match) {
                return $this->createReconciliation($line, $match->id);
            }
        }

        // Priority 2: Match by amount + exact date
        $isDeposit = $line->type === 'deposit';
        $match = DB::table('journal_entries')
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journal_entries.coa_id', $bankAccountId)
            ->where('journal_entries.journal_date', $lineDate)
            ->where('journals.status', 'posted')
            ->where(function ($q) use ($lineAmount, $isDeposit) {
                if ($isDeposit) {
                    $q->where('journal_entries.debit', $lineAmount);
                } else {
                    $q->where('journal_entries.credit', $lineAmount);
                }
            })
            ->select('journal_entries.*', 'journals.journal_no')
            ->first();

        if ($match) {
            return $this->createReconciliation($line, $match->journal_id);
        }

        // Priority 3: Match by amount alone within date window
        $match = DB::table('journal_entries')
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journal_entries.coa_id', $bankAccountId)
            ->whereBetween('journal_entries.journal_date', [$dateStart, $dateEnd])
            ->where('journals.status', 'posted')
            ->where(function ($q) use ($lineAmount, $isDeposit) {
                if ($isDeposit) {
                    $q->where('journal_entries.debit', $lineAmount);
                } else {
                    $q->where('journal_entries.credit', $lineAmount);
                }
            })
            ->select('journal_entries.*', 'journals.journal_no')
            ->first();

        if ($match) {
            return $this->createReconciliation($line, $match->journal_id);
        }

        return false;
    }

    protected function createReconciliation(BankStatementLine $line, int $journalId): bool
    {
        $exists = BankReconciliation::where('statement_line_id', $line->id)
            ->where('status', 'matched')
            ->exists();

        if ($exists) return false;

        BankReconciliation::create([
            'institute_id' => $line->institute_id,
            'statement_line_id' => $line->id,
            'journal_id' => $journalId,
            'status' => 'matched',
            'matched_by' => null,
            'matched_at' => now(),
        ]);

        $line->update([
            'matched_je_id' => $journalId,
            'category_status' => 'auto_matched',
            'match_confidence' => 80,
            'matched_at' => now(),
        ]);

        return true;
    }

    public function applyRules(int $statementId): int
    {
        $statement = BankStatement::findOrFail($statementId);

        $rules = BankRule::where('institute_id', $statement->institute_id)
            ->active()
            ->orderBy('priority')
            ->get();

        $lines = BankStatementLine::where('statement_id', $statementId)
            ->where('category_status', 'unmatched')
            ->get();

        $categorized = 0;

        foreach ($lines as $line) {
            foreach ($rules as $rule) {
                if ($rule->matches($line)) {
                    $updates = ['rule_id' => $rule->id];

                    if ($rule->action_type === 'categorize' && $rule->account_id) {
                        $updates['categorized_account_id'] = $rule->account_id;
                        $updates['category_status'] = 'rule_matched';
                        $updates['matched_at'] = now();
                    } elseif ($rule->action_type === 'ignore') {
                        $updates['category_status'] = 'ignored';
                        $updates['matched_at'] = now();
                    } else {
                        continue;
                    }

                    $line->update($updates);
                    $rule->increment('times_applied');
                    $categorized++;
                    break;
                }
            }
        }

        return $categorized;
    }

    public function categorize(BankStatementLine $line, int $accountId, ?int $partyId, ?string $narration, int $actorId): void
    {
        DB::transaction(function () use ($line, $accountId, $partyId, $narration, $actorId) {
            $statement = $line->statement;
            $bankAccountId = $statement->bank_account_id;
            $isDeposit = $line->type === 'deposit';

            $currency = Currency::where('code', 'BDT')->first();
            $currencyId = $currency?->id ?? 1;

            $entries = [
                [
                    'coa_id' => $isDeposit ? $bankAccountId : $accountId,
                    'debit' => $line->amount,
                    'credit' => 0,
                    'party_id' => $partyId,
                ],
                [
                    'coa_id' => $isDeposit ? $accountId : $bankAccountId,
                    'debit' => 0,
                    'credit' => $line->amount,
                    'party_id' => $partyId,
                ],
            ];

            $journal = $this->journalService->create([
                'institute_id' => $line->institute_id,
                'branch_id' => $statement->branch_id,
                'journal_date' => $line->transaction_date->format('Y-m-d'),
                'type' => 'journal',
                'currency_id' => $currencyId,
                'description' => $narration ?? "Bank feed: {$line->description}",
                'entries' => $entries,
                'source' => 'app',
            ], $actorId, true);

            $line->update([
                'categorized_account_id' => $accountId,
                'matched_je_id' => $journal->id,
                'category_status' => 'manual_matched',
                'match_confidence' => 100,
                'matched_at' => now(),
            ]);

            BankReconciliation::create([
                'institute_id' => $line->institute_id,
                'statement_line_id' => $line->id,
                'journal_id' => $journal->id,
                'status' => 'matched',
                'matched_by' => $actorId,
                'matched_at' => now(),
            ]);
        });
    }

    public function bulkCategorize(array $lineIds, int $accountId, int $actorId): int
    {
        $count = 0;
        foreach (BankStatementLine::whereIn('id', $lineIds)->get() as $line) {
            $this->categorize($line, $accountId, null, null, $actorId);
            $count++;
        }

        return $count;
    }

    public function bulkIgnore(array $lineIds): int
    {
        return BankStatementLine::whereIn('id', $lineIds)->update([
            'category_status' => 'ignored',
            'matched_at' => now(),
        ]);
    }
}
