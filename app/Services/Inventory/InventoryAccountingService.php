<?php

namespace App\Services\Inventory;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Journal;
use App\Models\Party;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PaymentAccountResolverService;
use Illuminate\Validation\ValidationException;

/**
 * Inventory <-> Accounting bridge (STEP 16).
 *
 * Resolves the CoA accounts for inventory events (asset / COGS / sales /
 * adjustment income-expense / wastage) following the existing conventions:
 * an item or category override wins, otherwise the TEMPLATE default code is
 * looked up via accountByCode. All journals are posted through
 * JournalPostingService so balance, ownership, fiscal-period locking,
 * immutability and duplicate-posting rules apply unchanged.
 *
 * Every inventory event must land in an OPEN period; posting is delegated to
 * the engine so closed/locked periods reject automatically.
 */
class InventoryAccountingService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingSetupService $settings,
        private readonly AccountingAuditService $audit,
        private readonly PaymentAccountResolverService $paymentAccounts,
        private readonly ChartOfAccountService $chartOfAccounts,
    ) {}

    public function inventoryAssetAccount(InventoryItem|InventoryCategory $holder, int $instituteId, ?int $branchId): ChartOfAccount
    {
        $override = $holder instanceof InventoryItem
            ? $holder->inventory_account_id ?? ($holder->category?->inventory_account_id ?? null)
            : $holder->inventory_account_id;

        return $this->resolveAccount($instituteId, $branchId, $override, '1200', 'inventory account');
    }

    public function cogsAccount(InventoryItem|InventoryCategory $holder, int $instituteId, ?int $branchId): ChartOfAccount
    {
        $override = $holder instanceof InventoryItem
            ? $holder->cogs_account_id ?? ($holder->category?->cogs_account_id ?? null)
            : $holder->cogs_account_id;

        return $this->resolveAccount($instituteId, $branchId, $override, '5007', 'COGS account');
    }

    public function salesAccount(InventoryItem|InventoryCategory $holder, int $instituteId, ?int $branchId): ChartOfAccount
    {
        $override = $holder instanceof InventoryItem
            ? $holder->sales_account_id ?? ($holder->category?->sales_account_id ?? null)
            : $holder->sales_account_id;

        return $this->resolveAccount($instituteId, $branchId, $override, '4003', 'merchandise sales account');
    }

    public function expenseAccount(InventoryItem|InventoryCategory $holder, int $instituteId, ?int $branchId): ChartOfAccount
    {
        $override = $holder instanceof InventoryItem
            ? $holder->expense_account_id ?? ($holder->category?->expense_account_id ?? null)
            : $holder->expense_account_id;

        return $this->resolveAccount($instituteId, $branchId, $override, '5006', 'expense account');
    }

    public function adjustmentIncomeAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, null, '4005', 'inventory adjustment income account');
    }

    public function adjustmentExpenseAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, null, '5008', 'inventory adjustment expense account');
    }

    public function wastageAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        return $this->resolveAccount($instituteId, $branchId, null, '5009', 'inventory wastage account');
    }

    /**
     * Purchase receipt journal — Dr Inventory Asset (per line) / Cr AP (party)
     * or Cr cash/bank when the purchase is paid immediately.
     *
     * @param  array<int, array{item: InventoryItem, quantity: float, unit_cost: float}>  $lines
     * @param  array<string, mixed>  $options
     */
    public function purchaseReceiptJournal(
        int $instituteId,
        ?int $branchId,
        Party $supplier,
        array $lines,
        ?int $actorId = null,
        ?string $journalDate = null,
        ?string $description = null,
        ?string $refType = 'inventory_receipt',
        ?int $refId = null,
        array $options = [],
    ): Journal {
        $this->assertSupplier($supplier, $instituteId, $branchId);

        $entries = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $item = $line['item'];
            $cost = round((float) $line['unit_cost'] * (float) $line['quantity'], 4);
            $total += $cost;
            $inventoryAccount = $this->inventoryAssetAccount($item, $instituteId, $branchId);

            $entries[] = [
                'coa_id' => $inventoryAccount->id,
                'party_id' => null,
                'debit' => $cost,
                'credit' => 0,
                'memo' => 'Inventory receipt: '.$item->name,
                'line_meta' => ['item_id' => $item->id, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost']],
            ];
        }

        $total = round($total, 4);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lines' => 'The receipt value must be greater than zero.']);
        }

        $payableAccountId = $this->payableAccount($instituteId, $branchId);

        if (($options['paid_immediately'] ?? false) === true) {
            $cashAccountId = $this->paymentAccounts->resolve(
                $instituteId,
                $branchId,
                $options['payment_method_id'] ?? null,
                $options['payment_method'] ?? 'cash',
            );
            $entries[] = [
                'coa_id' => $cashAccountId,
                'party_id' => null,
                'debit' => 0,
                'credit' => $total,
                'memo' => 'Cash/bank payment for inventory receipt',
            ];
        } else {
            $entries[] = [
                'coa_id' => $payableAccountId,
                'party_id' => $supplier->id,
                'debit' => 0,
                'credit' => $total,
                'memo' => 'Supplier bill for inventory receipt',
            ];
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'purchase',
            'ref_type' => $refType,
            'ref_id' => $refId,
            'description' => $description ?? 'Inventory receipt from '.$supplier->name,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'inventory_receipt',
            'entity_id' => $journal->id,
            'after_payload' => ['supplier' => $supplier->name, 'amount' => $total, 'journal' => $journal->journal_no],
        ]);

        return $journal;
    }

    /**
     * COGS journal for a sale issue — Dr COGS (per line) / Cr Inventory Asset (per line).
     *
     * @param  array<int, array{item: InventoryItem, quantity: float, unit_cost: float}>  $lines
     * @param  array<string, mixed>  $options
     */
    public function cogsJournal(
        int $instituteId,
        ?int $branchId,
        array $lines,
        ?int $actorId = null,
        ?string $journalDate = null,
        ?string $description = null,
        ?string $refType = 'inventory_issue',
        ?int $refId = null,
        array $options = [],
    ): Journal {
        $entries = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $item = $line['item'];
            $cost = round((float) $line['unit_cost'] * (float) $line['quantity'], 4);
            $total += $cost;
            $cogsAccount = $this->cogsAccount($item, $instituteId, $branchId);
            $inventoryAccount = $this->inventoryAssetAccount($item, $instituteId, $branchId);

            $entries[] = [
                'coa_id' => $cogsAccount->id,
                'party_id' => null,
                'debit' => $cost,
                'credit' => 0,
                'memo' => 'COGS: '.$item->name,
                'line_meta' => ['item_id' => $item->id, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost']],
            ];
            $entries[] = [
                'coa_id' => $inventoryAccount->id,
                'party_id' => null,
                'debit' => 0,
                'credit' => $cost,
                'memo' => 'Inventory issued: '.$item->name,
                'line_meta' => ['item_id' => $item->id, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost']],
            ];
        }

        $total = round($total, 4);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lines' => 'The issue value must be greater than zero.']);
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'adjustment',
            'ref_type' => $refType,
            'ref_id' => $refId,
            'description' => $description ?? 'Cost of goods sold',
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'inventory_issue',
            'entity_id' => $journal->id,
            'after_payload' => ['amount' => $total, 'journal' => $journal->journal_no],
        ]);

        return $journal;
    }

    /**
     * Stock adjustment journal.
     *
     *   adjustment_in   -> Dr Inventory / Cr Inventory Adjustment Income (4005)
     *   adjustment_out  -> Dr Inventory Adjustment Expense (5008) / Cr Inventory
     *   wastage_out     -> Dr Inventory Wastage (5009) / Cr Inventory
     *
     * @param  array<int, array{item: InventoryItem, quantity: float, unit_cost: float, difference: float}>  $lines
     * @param  array<string, mixed>  $options
     */
    public function adjustmentJournal(
        int $instituteId,
        ?int $branchId,
        string $type,
        array $lines,
        ?int $actorId = null,
        ?string $journalDate = null,
        ?string $description = null,
        ?string $refType = 'inventory_adjustment',
        ?int $refId = null,
        array $options = [],
    ): ?Journal {
        $debits = [];
        $credits = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $difference = round((float) $line['difference'], 4);
            if (abs($difference) < 0.00005) {
                continue;
            }

            $value = round(abs($difference) * (float) $line['unit_cost'], 4);
            $total += $value;
            $item = $line['item'];
            $inventoryAccount = $this->inventoryAssetAccount($item, $instituteId, $branchId);

            if ($difference > 0) {
                $debits[] = [
                    'coa_id' => $inventoryAccount->id,
                    'party_id' => null,
                    'debit' => $value,
                    'credit' => 0,
                    'memo' => 'Stock gain: '.$item->name,
                    'line_meta' => ['item_id' => $item->id, 'difference' => $difference, 'unit_cost' => $line['unit_cost']],
                ];
            } else {
                $credits[] = [
                    'coa_id' => $inventoryAccount->id,
                    'party_id' => null,
                    'debit' => 0,
                    'credit' => $value,
                    'memo' => 'Stock loss: '.$item->name,
                    'line_meta' => ['item_id' => $item->id, 'difference' => $difference, 'unit_cost' => $line['unit_cost']],
                ];
            }
        }

        $total = round($total, 4);

        if ($total <= 0 || ($debits === [] && $credits === [])) {
            return null;
        }

        // Surplus is credited against the adjustment income account; deficit is
        // debited against the adjustment expense (or wastage) account.
        $incomeAccount = $type === 'wastage_out'
            ? null
            : $this->adjustmentIncomeAccount($instituteId, $branchId);
        $expenseAccount = $type === 'wastage_out'
            ? $this->wastageAccount($instituteId, $branchId)
            : $this->adjustmentExpenseAccount($instituteId, $branchId);

        $entries = [...$debits, ...$credits];

        if ($debits !== []) {
            $entries[] = [
                'coa_id' => $incomeAccount->id,
                'party_id' => null,
                'debit' => 0,
                'credit' => round(array_sum(array_column($debits, 'debit')), 4),
                'memo' => 'Stock gain counter',
            ];
        }

        if ($credits !== []) {
            $entries[] = [
                'coa_id' => $expenseAccount->id,
                'party_id' => null,
                'debit' => round(array_sum(array_column($credits, 'credit')), 4),
                'credit' => 0,
                'memo' => 'Stock loss counter',
            ];
        }

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'adjustment',
            'ref_type' => $refType,
            'ref_id' => $refId,
            'description' => $description ?? 'Inventory adjustment ('.$type.')',
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'inventory_adjustment_journal',
            'entity_id' => $journal->id,
            'after_payload' => ['type' => $type, 'amount' => $total, 'journal' => $journal->journal_no],
        ]);

        return $journal;
    }

    public function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $code = $this->settings->getSetting($instituteId, 'base_currency', null, $branchId);
        $currency = Currency::query()->where('code', $code)->first();

        return $currency !== null ? (int) $currency->id : 1;
    }

    /**
     * Reverse a journal belonging to the institute (delegates to the engine's
     * reversal convention; returns null when no posted journal matches).
     */
    public function reverseJournal(
        int $instituteId,
        ?int $branchId,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        ?int $actorId = null,
        ?string $reason = null,
    ): ?Journal {
        if ($referenceType === null || $referenceId === null) {
            return null;
        }

        $journal = Journal::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('type', $type)
            ->where('ref_type', $referenceType)
            ->where('ref_id', $referenceId)
            ->where('status', 'posted')
            ->first();

        if ($journal === null) {
            return null;
        }

        return $this->posting->reverse($journal, $instituteId, $actorId, $reason);
    }

    private function resolveAccount(int $instituteId, ?int $branchId, ?int $overrideId, string $fallbackCode, string $label): ChartOfAccount
    {
        if ($overrideId !== null) {
            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('id', $overrideId)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->where('is_active', true)
                ->first();

            if ($account !== null) {
                return $account;
            }
        }

        $account = $this->chartOfAccounts->accountByCode($instituteId, $fallbackCode, $branchId);

        if ($account === null || ! (bool) $account->is_active) {
            throw ValidationException::withMessages([
                'account' => 'No active '.$label.' is configured for this institute. Run chart-of-account setup first.',
            ]);
        }

        return $account;
    }

    private function payableAccount(int $instituteId, ?int $branchId): int
    {
        $account = $this->chartOfAccounts->accountByCode($instituteId, '2001', $branchId);

        if ($account === null) {
            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('is_payable', true)
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->first();
        }

        if ($account === null) {
            throw ValidationException::withMessages([
                'payable_account' => 'No accounts payable account is configured for this institute.',
            ]);
        }

        return (int) $account->id;
    }

    private function assertSupplier(Party $supplier, int $instituteId, ?int $branchId): void
    {
        $owned = Party::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereKey($supplier->id)
            ->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'party_id' => 'The selected supplier does not belong to this institute.',
            ]);
        }

        if (! $supplier->isSupplier()) {
            throw ValidationException::withMessages([
                'party_id' => 'The selected party is not a supplier.',
            ]);
        }
    }
}
