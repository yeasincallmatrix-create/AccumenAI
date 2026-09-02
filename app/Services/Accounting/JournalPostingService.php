<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Party;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Double-entry posting engine.
 *
 * A journal is created as a `draft`, then `post()`ed once its entries balance:
 *   sum(debit) = sum(credit),  no line with both debit and credit,  >= 2 lines.
 * Posted journals can be `reverse()`d (new reversing journal referencing the
 * original via reversal_of) or, while still a draft, `void()`ed. Originals are
 * never hard-deleted, preserving the audit trail.
 *
 * Every mutation is transactional and audited via AccountingAuditService.
 */
class JournalPostingService
{
    public const BALANCE_EPSILON = 0.00005;

    public function __construct(
        private readonly AccountingAuditService $audit,
    ) {}

    /**
     * Create a journal. With post_now=false the journal stays draft; with
     * post_now=true (default) it is validated and posted immediately.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $actorId = null, bool $postNow = true): Journal
    {
        $data = $this->validate($data);

        $journal = DB::transaction(function () use ($data, $actorId, $postNow) {
            $fiscalYear = $this->resolveFiscalYear($data);

            $journal = Journal::create([
                'institute_id' => $data['institute_id'],
                'branch_id' => $data['branch_id'],
                'journal_no' => $this->allocateJournalNo($data['institute_id'], $data['branch_id']),
                'journal_date' => $data['journal_date'],
                'fiscal_year_id' => $fiscalYear->id,
                'period_id' => $data['period_id'] ?? $this->coveringOpenPeriod($fiscalYear, $data),
                'type' => $data['type'],
                'ref_type' => $data['ref_type'] ?? null,
                'ref_id' => $data['ref_id'] ?? null,
                'currency_id' => $data['currency_id'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'status' => 'draft',
                'description' => $data['description'] ?? null,
                'source' => $data['source'] ?? 'app',
                'created_by' => $actorId,
            ]);

            foreach ($data['entries'] as $line) {
                $journal->entries()->create([
                    'institute_id' => $data['institute_id'],
                    'branch_id' => $data['branch_id'],
                    'coa_id' => $line['coa_id'],
                    'party_id' => $line['party_id'] ?? null,
                    'currency_id' => $line['currency_id'] ?? null,
                    'journal_date' => $data['journal_date'],
                    'foreign_debit' => $line['foreign_debit'] ?? 0,
                    'foreign_credit' => $line['foreign_credit'] ?? 0,
                    'exchange_rate' => $line['exchange_rate'] ?? ($data['exchange_rate'] ?? 1),
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['memo'] ?? null,
                    'line_meta' => $line['line_meta'] ?? null,
                    'created_by' => $actorId,
                ]);
            }

            $this->audit->log($data['institute_id'], [
                'branch_id' => $data['branch_id'],
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'journal',
                'entity_id' => $journal->id,
                'after_payload' => ['journal_no' => $journal->journal_no, 'type' => $journal->type],
            ]);

            if ($postNow) {
                $this->post($journal, $data['institute_id'], $actorId);
            }

            return $journal->load('entries');
        });

        if ($postNow && isset($journal) && $journal->status === 'posted') {
            \App\Events\JournalPosted::dispatch($journal, $journal->institute_id, $journal->branch_id, $actorId ?? 0);
        }

        return $journal;
    }

    /**
     * Validate + post an existing draft journal.
     */
    public function post(Journal $journal, ?int $instituteId = null, ?int $actorId = null): Journal
    {
        if ($journal->status !== 'draft') {
            throw new \LogicException('Only draft journals can be posted.');
        }

        $this->assertJournalInInstitute($journal, $instituteId);
        $this->assertBalanced($journal->entries);
        $this->assertPeriodOpenForPosting($journal);

        return DB::transaction(function () use ($journal, $actorId) {
            $journal->forceFill([
                'status' => 'posted',
                'posted_by' => $actorId,
                'posted_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            $this->audit->log($journal->institute_id, [
                'branch_id' => $journal->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'post',
                'entity_type' => 'journal',
                'entity_id' => $journal->id,
                'after_payload' => ['journal_no' => $journal->journal_no],
            ]);

            return $journal;
        });
    }

    /**
     * Reverse a posted journal. Creates a new posted journal with swapped
     * debit/credit lines linked via reversal_of and marks the original
     * `reversed`.
     */
    public function reverse(Journal $journal, ?int $instituteId = null, ?int $actorId = null, ?string $reason = null): Journal
    {
        if ($journal->status !== 'posted') {
            throw new \LogicException('Only posted journals can be reversed.');
        }

        if ($journal->reversal_of !== null) {
            throw new \LogicException('A reversal journal cannot be reversed.');
        }

        $this->assertJournalInInstitute($journal, $instituteId);

        $entries = $journal->entries()->get();

        return DB::transaction(function () use ($journal, $actorId, $reason, $entries) {
            $reversal = Journal::create([
                'institute_id' => $journal->institute_id,
                'branch_id' => $journal->branch_id,
                'journal_no' => $this->allocateJournalNo($journal->institute_id, $journal->branch_id),
                'journal_date' => now()->toDateString(),
                'fiscal_year_id' => $journal->fiscal_year_id,
                'period_id' => $journal->period_id,
                'type' => $journal->type,
                'ref_type' => 'reversal',
                'ref_id' => $journal->id,
                'currency_id' => $journal->currency_id,
                'exchange_rate' => $journal->exchange_rate,
                'status' => 'posted',
                'description' => 'Reversal of '.$journal->journal_no.($reason !== null ? ": {$reason}" : ''),
                'source' => 'app',
                'posted_by' => $actorId,
                'posted_at' => now(),
                'reversal_of' => $journal->id,
                'created_by' => $actorId,
            ]);

            foreach ($entries as $entry) {
                $reversal->entries()->create([
                    'institute_id' => $reversal->institute_id,
                    'branch_id' => $reversal->branch_id,
                    'coa_id' => $entry->coa_id,
                    'party_id' => $entry->party_id,
                    'currency_id' => $entry->currency_id,
                    'journal_date' => $reversal->journal_date,
                    'foreign_debit' => $entry->foreign_credit,
                    'foreign_credit' => $entry->foreign_debit,
                    'exchange_rate' => $entry->exchange_rate,
                    'debit' => $entry->credit,
                    'credit' => $entry->debit,
                    'memo' => $entry->memo,
                    'line_meta' => $entry->line_meta,
                    'created_by' => $actorId,
                ]);
            }

            $journal->forceFill([
                'status' => 'reversed',
                'reversed_by' => $actorId,
                'reversed_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            $this->audit->log($journal->institute_id, [
                'branch_id' => $journal->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'reverse',
                'entity_type' => 'journal',
                'entity_id' => $journal->id,
                'after_payload' => ['reversal_no' => $reversal->journal_no, 'reason' => $reason],
            ]);

            return $reversal->load('entries');
        });
    }

    /**
     * Void a draft journal (no financial effect yet).
     */
    public function void(Journal $journal, ?int $instituteId = null, ?int $actorId = null): void
    {
        if ($journal->status !== 'draft') {
            throw new \LogicException('Only draft journals can be voided.');
        }

        $this->assertJournalInInstitute($journal, $instituteId);

        DB::transaction(function () use ($journal, $actorId) {
            $journal->forceFill([
                'status' => 'void',
                'updated_by' => $actorId,
            ])->save();

            $this->audit->log($journal->institute_id, [
                'branch_id' => $journal->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'void',
                'entity_type' => 'journal',
                'entity_id' => $journal->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        $validator = validator($data, [
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'journal_date' => ['required', 'date'],
            'type' => ['required', 'in:sale,purchase,receipt,payment,journal,contra,opening,adjustment'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'ref_type' => ['nullable', 'string', 'max:40'],
            'ref_id' => ['nullable', 'integer'],
            'period_id' => ['nullable', 'integer', 'exists:accounting_periods,id'],
            'source' => ['nullable', 'in:app,ai,sync,migration,import'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'entries.*.party_id' => ['nullable', 'integer', 'exists:parties,id'],
            'entries.*.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'entries.*.foreign_debit' => ['nullable', 'numeric', 'min:0'],
            'entries.*.foreign_credit' => ['nullable', 'numeric', 'min:0'],
            'entries.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'entries.*.debit' => ['required', 'numeric', 'min:0'],
            'entries.*.credit' => ['required', 'numeric', 'min:0'],
            'entries.*.memo' => ['nullable', 'string', 'max:255'],
            'entries.*.line_meta' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $this->assertBranchBelongsToInstitute($data['institute_id'], $data['branch_id']);
        $this->assertLinesWellFormed($data['entries']);
        $this->assertCoaBelongsToInstitute($data['institute_id'], $data['branch_id'], $data['entries']);
        $this->assertPartiesBelongToInstitute($data['institute_id'], $data['branch_id'], $data['entries']);

        return $data;
    }

    /**
     * Reject a journal that does not belong to the given institute. Pass null
     * to skip the check (callers without tenant context).
     */
    private function assertJournalInInstitute(Journal $journal, ?int $instituteId): void
    {
        if ($instituteId !== null && (int) $journal->institute_id !== (int) $instituteId) {
            throw new \LogicException('This journal does not belong to the given institute.');
        }
    }

    /**
     * Reject posting a draft whose period or fiscal year is no longer open.
     * A draft created in an open period must still be posted before the period
     * (or its year) is closed; otherwise the ledger could gain entries in a
     * locked period.
     */
    private function assertPeriodOpenForPosting(Journal $journal): void
    {
        $year = $journal->fiscalYear;

        if ($year !== null && $year->isClosed()) {
            throw ValidationException::withMessages([
                'period_id' => 'Posting is not allowed in a closed fiscal year.',
            ]);
        }

        if ($journal->period_id !== null) {
            $period = AccountingPeriod::query()
                ->where('institute_id', $journal->institute_id)
                ->where('id', $journal->period_id)
                ->first();

            if ($period !== null && ! $period->isOpen()) {
                throw ValidationException::withMessages([
                    'period_id' => 'Posting is not allowed in a closed or locked period.',
                ]);
            }
        }
    }

    /**
     * The open period inside a fiscal year that covers the journal date. When
     * the fiscal year has no periods configured, the period stays null
     * (backward compatible with period-less setups). Once periods exist, the
     * posting must land in the open period that actually contains its date; if
     * that period is closed, posting is rejected.
     */
    private function coveringOpenPeriod(FiscalYear $fiscalYear, array $data): ?int
    {
        $hasPeriods = AccountingPeriod::query()
            ->where('institute_id', $data['institute_id'])
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where(fn ($query) => $query
                ->where('branch_id', $data['branch_id'])
                ->orWhereNull('branch_id'))
            ->exists();

        if (! $hasPeriods) {
            return null;
        }

        $periodId = AccountingPeriod::query()
            ->where('institute_id', $data['institute_id'])
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $data['journal_date'])
            ->whereDate('end_date', '>=', $data['journal_date'])
            ->where(fn ($query) => $query
                ->where('branch_id', $data['branch_id'])
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->value('id');

        if ($periodId === null) {
            throw ValidationException::withMessages([
                'period_id' => 'Posting is not allowed in a closed or locked period.',
            ]);
        }

        return (int) $periodId;
    }

    /**
     * The acting branch (if any) must belong to the same institute.
     */
    private function assertBranchBelongsToInstitute(int $instituteId, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        $exists = Branch::query()
            ->where('institute_id', $instituteId)
            ->where('id', $branchId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'branch_id' => 'The branch does not belong to this institute.',
            ]);
        }
    }

    /**
     * Re-assert the balance invariant for a journal's existing entry set.
     */
    private function assertBalanced($entries): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($entries as $line) {
            $totalDebit = bcadd($totalDebit, (string) $line->debit, 8);
            $totalCredit = bcadd($totalCredit, (string) $line->credit, 8);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw new \LogicException("Journal does not balance: debit {$totalDebit} vs credit {$totalCredit}.");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function assertLinesWellFormed(array $entries): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($entries as $index => $line) {
            $debit = (string) $line['debit'];
            $credit = (string) $line['credit'];

            if (bccomp($debit, '0', 4) > 0 && bccomp($credit, '0', 4) > 0) {
                throw ValidationException::withMessages([
                    "entries.$index" => 'A journal line cannot carry both a debit and a credit.',
                ]);
            }

            if (bccomp($debit, '0', 4) === 0 && bccomp($credit, '0', 4) === 0) {
                throw ValidationException::withMessages([
                    "entries.$index" => 'A journal line must have a non-zero amount.',
                ]);
            }

            $totalDebit = bcadd($totalDebit, $debit, 8);
            $totalCredit = bcadd($totalCredit, $credit, 8);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw ValidationException::withMessages([
                'entries' => "Journal does not balance: debit {$totalDebit} vs credit {$totalCredit}.",
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function assertCoaBelongsToInstitute(int $instituteId, ?int $branchId, array $entries): void
    {
        $coaIds = array_unique(array_column($entries, 'coa_id'));

        $owned = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $coaIds)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->pluck('id')
            ->all();

        $foreign = array_values(array_diff($coaIds, $owned));

        if ($foreign !== []) {
            throw ValidationException::withMessages([
                'entries' => 'One or more accounts do not belong to this institute or its branch.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function assertPartiesBelongToInstitute(int $instituteId, ?int $branchId, array $entries): void
    {
        $partyIds = array_values(array_unique(array_filter(array_column($entries, 'party_id'))));

        if ($partyIds === []) {
            return;
        }

        $owned = Party::query()
            ->where('institute_id', $instituteId)
            ->whereIn('id', $partyIds)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->pluck('id')
            ->all();

        $foreign = array_values(array_diff($partyIds, $owned));

        if ($foreign !== []) {
            throw ValidationException::withMessages([
                'entries' => 'One or more parties do not belong to this institute or its branch.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveFiscalYear(array $data): FiscalYear
    {
        if (isset($data['period_id'])) {
            $period = AccountingPeriod::query()
                ->where('institute_id', $data['institute_id'])
                ->where('id', $data['period_id'])
                ->where(fn ($query) => $query
                    ->where('branch_id', $data['branch_id'])
                    ->orWhereNull('branch_id'))
                ->with('fiscalYear')
                ->first();

            if ($period === null) {
                throw new ModelNotFoundException('Accounting period not found.');
            }

            if (! $period->isOpen()) {
                throw ValidationException::withMessages([
                    'period_id' => 'Posting is not allowed in a closed or locked period.',
                ]);
            }

            return $period->fiscalYear;
        }

        $fiscalYear = FiscalYear::query()
            ->where('institute_id', $data['institute_id'])
            ->where('status', 'open')
            ->where('is_current', true)
            ->whereDate('start_date', '<=', $data['journal_date'])
            ->whereDate('end_date', '>=', $data['journal_date'])
            ->where(fn ($query) => $query
                ->where('branch_id', $data['branch_id'])
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if ($fiscalYear === null) {
            throw ValidationException::withMessages([
                'journal_date' => 'No open fiscal year covers this date. Configure a fiscal year first.',
            ]);
        }

        return $fiscalYear;
    }

    private function allocateJournalNo(int $instituteId, ?int $branchId): string
    {
        $taken = fn (string $no) => Journal::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('journal_no', $no)
            ->exists();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'J-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a unique journal number.');
    }
}
