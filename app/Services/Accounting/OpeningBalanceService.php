<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\OpeningBalance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opening balances for a fiscal year.
 *
 * The opening_balances table holds the opening position (per account) at the
 * start of a fiscal year. Reports (trial balance, balance sheet) aggregate
 * these rows alongside posted journal entries. Rows are keyed uniquely by
 * (institute, branch, fiscal_year, coa) and are upserted here; every write is
 * audit-logged.
 */
class OpeningBalanceService
{
    public function __construct(private readonly AccountingAuditService $audit) {}

    /**
     * Active asset/liability/equity accounts with their existing opening
     * balances for a fiscal year (branch-aware).
     *
     * @return Collection<int, object>
     */
    public function accountsWithBalances(int $instituteId, ?int $branchId, FiscalYear $year): Collection
    {
        $existing = OpeningBalance::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->get()
            ->keyBy('coa_id');

        return ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->where('is_active', true)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->ordered()
            ->get(['id', 'code', 'name', 'type'])
            ->map(function ($account) use ($existing) {
                $row = $existing->get($account->id);
                $account->debit = (float) ($row->debit ?? 0);
                $account->credit = (float) ($row->credit ?? 0);
                $account->balance = round((float) ($row->debit ?? 0) - (float) ($row->credit ?? 0), 4);

                return $account;
            });
    }

    /**
     * Upsert opening balances for a fiscal year. Zero/blank lines are ignored;
     * the remaining lines must balance (total debit === total credit).
     *
     * @param  array<int, array{coa_id: int, debit: float, credit: float}>  $entries
     */
    public function upsert(int $instituteId, ?int $branchId, FiscalYear $year, array $entries, ?int $actorId = null): int
    {
        $entries = $this->validate($instituteId, $branchId, $entries);

        return DB::transaction(function () use ($instituteId, $branchId, $year, $entries, $actorId) {
            foreach ($entries as $line) {
                $coaId = (int) $line['coa_id'];
                $debit = round((float) $line['debit'], 4);
                $credit = round((float) $line['credit'], 4);

                $existing = OpeningBalance::query()
                    ->where('institute_id', $instituteId)
                    ->where('fiscal_year_id', $year->id)
                    ->where('coa_id', $coaId)
                    ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
                    ->first();

                if ($existing !== null) {
                    $existing->forceFill([
                        'debit' => $debit,
                        'credit' => $credit,
                        'updated_by' => $actorId,
                    ])->save();
                } else {
                    OpeningBalance::create([
                        'institute_id' => $instituteId,
                        'branch_id' => $branchId,
                        'fiscal_year_id' => $year->id,
                        'coa_id' => $coaId,
                        'debit' => $debit,
                        'credit' => $credit,
                        'source' => 'manual',
                        'created_by' => $actorId,
                    ]);
                }
            }

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'opening_balance',
                'entity_id' => $year->id,
                'after_payload' => ['fiscal_year' => $year->name, 'accounts' => count($entries)],
            ]);

            return count($entries);
        });
    }

    /**
     * Record (or replace) the opening balance of one account from the New
     * Account popup or the list page's Edit Opening Balance popup. Unlike
     * upsert(), the batch total-debit === total-credit guard cannot apply
     * here: the counterpart lines for this fiscal year are entered later on
     * the Opening Balances page, so a single non-zero row is a valid interim
     * state.
     */
    public function upsertSingle(int $instituteId, ?int $branchId, FiscalYear $year, int $coaId, float $debit, float $credit, ?int $actorId = null): void
    {
        $debit = round($debit, 4);
        $credit = round($credit, 4);

        if ($debit < 0 || $credit < 0) {
            throw ValidationException::withMessages([
                'form.opening_balance' => 'Opening balance amounts cannot be negative.',
            ]);
        }

        if ($debit === 0.0 && $credit === 0.0) {
            return;
        }

        // Tenant-owned rows plus globals in this institute's visible
        // namespace (universal or industry-tagged): opening balances are
        // tenant data even when they reference a shared global account.
        $account = ChartOfAccount::visible($instituteId)
            ->where('id', $coaId)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->first(['id', 'type']);

        if ($account === null) {
            throw ValidationException::withMessages([
                'form' => 'The account is not available to this institute.',
            ]);
        }

        // Real-life rule: only balance sheet accounts (asset, liability,
        // equity) carry an opening position — cash/bank/payables/capital
        // etc. P&L accounts start fresh each period.
        if (! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
            throw ValidationException::withMessages([
                'form' => 'Opening balances apply to asset, liability, or equity (balance sheet) accounts only.',
            ]);
        }

        DB::transaction(function () use ($instituteId, $branchId, $year, $coaId, $debit, $credit, $actorId) {
            $existing = OpeningBalance::query()
                ->where('institute_id', $instituteId)
                ->where('fiscal_year_id', $year->id)
                ->where('coa_id', $coaId)
                ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'debit' => $debit,
                    'credit' => $credit,
                    'updated_by' => $actorId,
                ])->save();
            } else {
                OpeningBalance::create([
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'fiscal_year_id' => $year->id,
                    'coa_id' => $coaId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'source' => 'manual',
                    'created_by' => $actorId,
                ]);
            }

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'opening_balance',
                'entity_id' => $year->id,
                'after_payload' => ['fiscal_year' => $year->name, 'coa_id' => $coaId, 'debit' => $debit, 'credit' => $credit],
            ]);
        });
    }

    /**
     * Clear the opening balance of one account (blank amount in the list
     * page's Edit Opening Balance popup). The row is hard-deleted on
     * purpose: the unique key (institute, branch, fiscal_year, coa)
     * ignores deleted_at, so a soft-deleted row would block any future
     * write for the same key. Returns whether a row was removed.
     */
    public function removeSingle(int $instituteId, ?int $branchId, FiscalYear $year, int $coaId, ?int $actorId): bool
    {
        $owned = ChartOfAccount::visible($instituteId)
            ->where('id', $coaId)
            ->where(fn ($query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'form' => 'The account is not available to this institute.',
            ]);
        }

        $existing = OpeningBalance::query()
            ->where('institute_id', $instituteId)
            ->where('fiscal_year_id', $year->id)
            ->where('coa_id', $coaId)
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->first();

        if ($existing === null) {
            return false;
        }

        $existing->forceDelete();

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'delete',
            'entity_type' => 'opening_balance',
            'entity_id' => $year->id,
            'after_payload' => ['fiscal_year' => $year->name, 'coa_id' => $coaId, 'cleared' => true],
        ]);

        return true;
    }

    /**
     * @param  array<int, array{coa_id: int, debit: float, credit: float}>  $entries
     * @return array<int, array{coa_id: int, debit: float, credit: float}>
     */
    private function validate(int $instituteId, ?int $branchId, array $entries): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $normalized = [];

        foreach ($entries as $line) {
            $coaId = (int) ($line['coa_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), 4);
            $credit = round((float) ($line['credit'] ?? 0), 4);

            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages([
                    'entries' => 'Opening balance amounts cannot be negative.',
                ]);
            }

            if ($debit === 0.0 && $credit === 0.0) {
                continue;
            }

            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('id', $coaId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->first(['id', 'type']);

            if ($account === null) {
                throw ValidationException::withMessages([
                    'entries' => 'One or more accounts do not belong to this institute or its branch.',
                ]);
            }

            // Same real-life rule as the popups: P&L accounts never carry
            // an opening balance, even via a tampered HTTP payload.
            if (! in_array($account->type, ['asset', 'liability', 'equity'], true)) {
                throw ValidationException::withMessages([
                    'entries' => 'Opening balances apply to asset, liability, or equity (balance sheet) accounts only.',
                ]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
            $normalized[] = ['coa_id' => $coaId, 'debit' => $debit, 'credit' => $credit];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'entries' => 'Provide at least one non-zero opening balance.',
            ]);
        }

        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw ValidationException::withMessages([
                'entries' => 'Opening balances must balance (total debit equals total credit).',
            ]);
        }

        return $normalized;
    }
}
