<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\OpeningBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fiscal year & accounting period management.
 *
 * A fiscal year owns its monthly (or custom) accounting periods. Posting only
 * lands in an open period; closing a period is an audit event and new postings
 * are rejected. Reopening is allowed while the parent fiscal year is still
 * open.
 *
 * STEP 12 — period closing & fiscal-year end closing. Closing a period now also
 * guards against open drafts; closing a fiscal year posts a closing journal
 * (income/expense swept to Retained Earnings via JournalPostingService), locks
 * every period, closes the year, carries balance-sheet balances forward into
 * the next fiscal year and records every step in the audit trail.
 */
class AccountingPeriodService
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly JournalPostingService $posting,
        private readonly FinancialReportService $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFiscalYear(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): FiscalYear
    {
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $overlap = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date']);

        if ($overlap->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'This fiscal year overlaps an existing fiscal year.',
            ]);
        }

        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $year = FiscalYear::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => 'open',
                'is_current' => true,
                'created_by' => $actorId,
            ]);

            FiscalYear::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('id', '!=', $year->id)
                ->update(['is_current' => false]);

            $this->createMonthlyPeriods($year, $actorId);

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'fiscal_year',
                'entity_id' => $year->id,
                'after_payload' => ['name' => $year->name, 'start' => $year->start_date, 'end' => $year->end_date],
            ]);

            return $year->load('periods');
        });
    }

    public function createMonthlyPeriods(FiscalYear $year, ?int $actorId = null): int
    {
        $start = Carbon::parse($year->start_date);
        $end = Carbon::parse($year->end_date);

        $created = 0;
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $periodStart = $cursor->copy()->max($start);
            $periodEnd = $cursor->copy()->endOfMonth()->min($end);

            $name = $cursor->format('F Y');

            $exists = AccountingPeriod::query()
                ->where('institute_id', $year->institute_id)
                ->where('branch_id', $year->branch_id)
                ->where('fiscal_year_id', $year->id)
                ->where('name', $name)
                ->exists();

            if (! $exists) {
                AccountingPeriod::create([
                    'institute_id' => $year->institute_id,
                    'branch_id' => $year->branch_id,
                    'fiscal_year_id' => $year->id,
                    'name' => $name,
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'status' => 'open',
                    'created_by' => $actorId,
                ]);
                $created++;
            }

            $cursor->addMonth();
        }

        return $created;
    }

    /**
     * Assert a period may be closed: it belongs to the institute, is open and
     * carries no draft journals. Drafts must be posted or voided first so a
     * closed period can never be changed later.
     */
    public function validateCanClose(AccountingPeriod $period, int $instituteId, ?int $actorId = null): void
    {
        if ((int) $period->institute_id !== (int) $instituteId) {
            throw ValidationException::withMessages([
                'period' => 'This period does not belong to the institute.',
            ]);
        }

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'period' => 'Only open periods can be closed.',
            ]);
        }

        $hasDrafts = Journal::query()
            ->where('institute_id', $instituteId)
            ->where('period_id', $period->id)
            ->where('status', 'draft')
            ->exists();

        if ($hasDrafts) {
            throw ValidationException::withMessages([
                'period' => 'Cannot close this period while it still contains draft journals.',
            ]);
        }
    }

    public function closePeriod(AccountingPeriod $period, int $instituteId, ?int $actorId = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $instituteId, $actorId) {
            $this->validateCanClose($period, $instituteId, $actorId);

            $period->forceFill([
                'status' => 'closed',
                'closed_by' => $actorId,
                'closed_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            $this->audit->log($instituteId, [
                'branch_id' => $period->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'close',
                'entity_type' => 'accounting_period',
                'entity_id' => $period->id,
                'after_payload' => ['name' => $period->name],
            ]);

            return $period;
        });
    }

    /**
     * Resolve the open/current fiscal year and its open period covering a date.
     * Used by the finance dashboard and the opening-balances screen.
     *
     * @return array{year: ?FiscalYear, period: ?AccountingPeriod}
     */
    public function current(int $instituteId, ?int $branchId, ?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        $year = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'open')
            ->where('is_current', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if ($year === null) {
            return ['year' => null, 'period' => null];
        }

        $period = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        return ['year' => $year, 'period' => $period];
    }

    /**
     * Resolve the fiscal year and its period covering a date for a scope,
     * regardless of open/closed status. Unlike current() this returns the
     * actual covering rows so the dashboard can show a real OPEN/CLOSED status
     * instead of a misleading "no open period" warning.
     *
     * @return array{year: ?FiscalYear, period: ?AccountingPeriod}
     */
    public function covering(int $instituteId, ?int $branchId, ?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        $year = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->orderByDesc('is_current')
            ->first();

        if ($year === null) {
            return ['year' => null, 'period' => null];
        }

        $period = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        return ['year' => $year, 'period' => $period];
    }

    /**
     * Return the open period covering a date for a scope, or throw. Used to
     * pre-validate a posting before it reaches the journal engine.
     */
    public function validatePostingDate(int $instituteId, ?int $branchId, string $date): AccountingPeriod
    {
        $year = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'open')
            ->where('is_current', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages([
                'journal_date' => 'No open fiscal year covers this date.',
            ]);
        }

        $period = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NOT NULL DESC')
            ->first();

        if ($period === null) {
            throw ValidationException::withMessages([
                'journal_date' => 'No open period covers this date. Open or create the covering period first.',
            ]);
        }

        return $period;
    }

    public function reopenPeriod(AccountingPeriod $period, int $instituteId, ?int $actorId = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $instituteId, $actorId) {
            if ((int) $period->institute_id !== (int) $instituteId) {
                throw ValidationException::withMessages([
                    'period' => 'This period does not belong to the institute.',
                ]);
            }

            if ($period->isOpen()) {
                throw ValidationException::withMessages([
                    'period' => 'This period is already open.',
                ]);
            }

            if ($period->fiscalYear !== null && $period->fiscalYear->isClosed()) {
                throw ValidationException::withMessages([
                    'period' => 'A period cannot be reopened after its fiscal year is closed.',
                ]);
            }

            $period->forceFill([
                'status' => 'open',
                'closed_by' => null,
                'closed_at' => null,
                'updated_by' => $actorId,
            ])->save();

            $this->audit->log($instituteId, [
                'branch_id' => $period->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'reopen',
                'entity_type' => 'accounting_period',
                'entity_id' => $period->id,
                'after_payload' => ['name' => $period->name],
            ]);

            return $period;
        });
    }

    /**
     * Net income for a fiscal year (posted, non-reversal entries only).
     */
    public function fiscalYearNetIncome(FiscalYear $year, int $instituteId, ?int $branchId): float
    {
        $statement = $this->reports->incomeStatement(
            $instituteId,
            $branchId,
            $year->start_date->toDateString(),
            $year->end_date->toDateString(),
        );

        return $statement['net'];
    }

    /**
     * Close a fiscal year:
     *   1. requires a subsequent fiscal year to exist (the carry-forward home);
     *   2. posts a closing journal via JournalPostingService sweeping income to
     *      expenses with the net result moving to Retained Earnings (code 3002);
     *   3. closes every open period of the year;
     *   4. marks the year closed and no longer current;
     *   5. carries balance-sheet balances forward as next year's opening balances;
     *   6. records the close in the audit trail.
     *
     * All inside one transaction so a failure leaves the year untouched.
     */
    public function closeFiscalYear(FiscalYear $year, int $instituteId, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($year, $instituteId, $actorId) {
            if ((int) $year->institute_id !== (int) $instituteId) {
                throw ValidationException::withMessages([
                    'year' => 'This fiscal year does not belong to the institute.',
                ]);
            }

            if ($year->isClosed()) {
                throw ValidationException::withMessages([
                    'year' => 'Only open fiscal years can be closed.',
                ]);
            }

            $nextYear = FiscalYear::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $year->branch_id)
                ->whereDate('start_date', '>', $year->end_date->toDateString())
                ->orderBy('start_date')
                ->first();

            if ($nextYear === null) {
                throw ValidationException::withMessages([
                    'year' => 'Create the next fiscal year before closing this one.',
                ]);
            }

            $net = $this->fiscalYearNetIncome($year, $instituteId, $year->branch_id);

            $closingJournal = $this->postClosingJournal($year, $instituteId, $net, $actorId);

            $this->periodsFor($year)->where('status', 'open')->get()->each(function (AccountingPeriod $period) use ($instituteId, $actorId) {
                $period->forceFill([
                    'status' => 'closed',
                    'closed_by' => $actorId,
                    'closed_at' => now(),
                    'updated_by' => $actorId,
                ])->save();

                $this->audit->log($instituteId, [
                    'branch_id' => $period->branch_id,
                    'actor_type' => 'user',
                    'actor_id' => $actorId,
                    'action' => 'close',
                    'entity_type' => 'accounting_period',
                    'entity_id' => $period->id,
                    'after_payload' => ['name' => $period->name],
                ]);
            });

            $year->forceFill([
                'status' => 'closed',
                'is_current' => false,
                'closed_by' => $actorId,
                'closed_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            $carried = $this->carryForwardOpeningBalances($year, $nextYear, $instituteId, $actorId);

            $this->audit->log($instituteId, [
                'branch_id' => $year->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'close',
                'entity_type' => 'fiscal_year',
                'entity_id' => $year->id,
                'after_payload' => [
                    'name' => $year->name,
                    'net_income' => $net,
                    'closing_journal' => $closingJournal->journal_no,
                    'periods_closed' => $this->periodsFor($year)->count(),
                ],
            ]);

            return [
                'year' => $year->fresh(),
                'net_income' => $net,
                'closing_journal' => $closingJournal,
                'carried_forward' => $carried,
                'next_year' => $nextYear->fresh(),
            ];
        });
    }

    /**
     * Reopen a previously closed fiscal year: restores the year to open/current
     * and reopens all of its periods. Guarded: if the following year already
     * contains postings, reopening the earlier year would create overlapping
     * history, so it is rejected.
     */
    public function reopenFiscalYear(FiscalYear $year, int $instituteId, ?int $actorId = null): FiscalYear
    {
        return DB::transaction(function () use ($year, $instituteId, $actorId) {
            if ((int) $year->institute_id !== (int) $instituteId) {
                throw ValidationException::withMessages([
                    'year' => 'This fiscal year does not belong to the institute.',
                ]);
            }

            if (! $year->isClosed()) {
                throw ValidationException::withMessages([
                    'year' => 'Only closed fiscal years can be reopened.',
                ]);
            }

            $nextPosted = FiscalYear::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $year->branch_id)
                ->whereDate('start_date', '>', $year->end_date->toDateString())
                ->whereHas('journals', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))
                ->exists();

            if ($nextPosted) {
                throw ValidationException::withMessages([
                    'year' => 'The following fiscal year already contains postings; reopening this year is not allowed.',
                ]);
            }

            $year->forceFill([
                'status' => 'open',
                'is_current' => true,
                'closed_by' => null,
                'closed_at' => null,
                'updated_by' => $actorId,
            ])->save();

            FiscalYear::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $year->branch_id)
                ->where('id', '!=', $year->id)
                ->update(['is_current' => false]);

            $reopened = 0;

            $this->periodsFor($year)->get()->each(function (AccountingPeriod $period) use ($actorId, &$reopened) {
                if (! $period->isOpen()) {
                    $period->forceFill([
                        'status' => 'open',
                        'closed_by' => null,
                        'closed_at' => null,
                        'updated_by' => $actorId,
                    ])->save();
                    $reopened++;
                }
            });

            $this->audit->log($instituteId, [
                'branch_id' => $year->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'reopen',
                'entity_type' => 'fiscal_year',
                'entity_id' => $year->id,
                'after_payload' => ['name' => $year->name, 'periods_reopened' => $reopened],
            ]);

            return $year->fresh();
        });
    }

    // ------------------------------------------------------------- Internals

    /**
     * Periods belonging to a fiscal year (unscoped query — the caller owns the
     * institute/branch context via the year).
     */
    private function periodsFor(FiscalYear $year)
    {
        return AccountingPeriod::query()
            ->where('institute_id', $year->institute_id)
            ->where('fiscal_year_id', $year->id);
    }

    /**
     * Post the year-end closing journal via JournalPostingService: debit every
     * income account by its credit balance, credit every expense account by its
     * debit balance, and balance the difference against Retained Earnings
     * (code 3002) — credited for a profit, debited for a loss. Uses the
     * journal engine, so balance, institute, branch and account ownership are
     * validated exactly like any other posting.
     */
    private function postClosingJournal(FiscalYear $year, int $instituteId, float $net, ?int $actorId): Journal
    {
        $statement = $this->reports->incomeStatement(
            $instituteId,
            $year->branch_id,
            $year->start_date->toDateString(),
            $year->end_date->toDateString(),
        );

        $retainedEarnings = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $year->branch_id)
            ->where('code', '3002')
            ->whereNull('deleted_at')
            ->value('id');

        if ($retainedEarnings === null) {
            throw ValidationException::withMessages([
                'year' => 'Retained Earnings (code 3002) is missing from the chart of accounts.',
            ]);
        }

        $period = AccountingPeriod::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $year->end_date->toDateString())
            ->whereDate('end_date', '>=', $year->end_date->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($period === null) {
            throw ValidationException::withMessages([
                'year' => 'No open period covers the fiscal year end; open the final period before closing the year.',
            ]);
        }

        $entries = [];

        foreach ($statement['income'] as $row) {
            $entries[] = ['coa_id' => $row->coa_id, 'debit' => round((float) $row->balance, 4), 'credit' => 0];
        }

        foreach ($statement['expense'] as $row) {
            $entries[] = ['coa_id' => $row->coa_id, 'debit' => 0, 'credit' => round((float) $row->balance, 4)];
        }

        if (abs($net) > 0.0001) {
            $entries[] = [
                'coa_id' => (int) $retainedEarnings,
                'debit' => $net < 0 ? round(abs($net), 4) : 0,
                'credit' => $net > 0 ? round($net, 4) : 0,
            ];
        }

        if (count($entries) < 2) {
            throw ValidationException::withMessages([
                'year' => 'The fiscal year has no income or expense activity to close.',
            ]);
        }

        return $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $year->branch_id,
            'journal_date' => $year->end_date->toDateString(),
            'period_id' => $period->id,
            'type' => 'adjustment',
            'description' => 'Year-end closing entry for '.$year->name,
            'currency_id' => (int) $this->defaultCurrencyId($instituteId),
            'entries' => $entries,
        ], $actorId);
    }

    /**
     * Carry balance-sheet balances (asset/liability/equity) as at the end of
     * the closing year into the next year's opening balances. Income and
     * expense accounts are already zeroed by the closing journal. Existing
     * opening balances for the next year are replaced.
     */
    private function carryForwardOpeningBalances(FiscalYear $year, FiscalYear $nextYear, int $instituteId, ?int $actorId): int
    {
        $tb = $this->reports->trialBalance(
            $instituteId,
            $year->branch_id,
            $year->end_date->toDateString(),
            (int) $year->id,
        );

        $carried = 0;

        foreach ($tb as $row) {
            if (! in_array($row->type, ['asset', 'liability', 'equity'], true)) {
                continue;
            }

            $balance = round((float) $row->balance, 4);

            if (abs($balance) <= 0.0001) {
                continue;
            }

            OpeningBalance::query()->updateOrCreate(
                [
                    'institute_id' => $instituteId,
                    'branch_id' => $year->branch_id,
                    'fiscal_year_id' => $nextYear->id,
                    'coa_id' => $row->coa_id,
                ],
                [
                    'debit' => $balance > 0 ? $balance : 0,
                    'credit' => $balance < 0 ? abs($balance) : 0,
                    'source' => 'carry_forward',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ],
            );

            $carried++;
        }

        return $carried;
    }

    private function defaultCurrencyId(int $instituteId): ?int
    {
        $base = AccountingSetting::query()
            ->where('institute_id', $instituteId)
            ->whereNull('branch_id')
            ->where('settings_key', 'base_currency')
            ->value('settings_value');

        return Currency::query()->where('code', $base)->value('id')
            ?? Currency::query()->orderBy('code')->value('id');
    }
}
