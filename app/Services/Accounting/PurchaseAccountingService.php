<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Journal;
use App\Models\Party;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase, expense and accounts-payable accounting (STEP 15).
 *
 * AP is fully DERIVED from posted journals through the existing
 * ReceivablesPayablesService — no AP balance tables are created. Suppliers are
 * the existing unified Party records (type supplier|both) managed by
 * PartyService; there are no dedicated purchase/expense business models
 * because the application architecture does not have them. A purchase is a
 * `purchase` journal (Dr expense / Cr AP), a supplier payment is a `payment`
 * journal (Dr AP / Cr cash-bank), a cash expense is a `journal` (Dr expense /
 * Cr cash-bank), and a purchase cancellation/return is the existing journal
 * reversal convention (reversal_of). Every posting reuses JournalPostingService
 * so balance, COA/party/branch ownership, fiscal-period locking, immutability
 * and duplicate-posting rules are all enforced by the engine.
 *
 * Account resolution follows the existing COA conventions: AP = code 2001 (or
 * first is_payable account), expense = caller-supplied account or first active
 * expense account, money-out side = shared STEP 14 payment-method resolver.
 * Input tax is only booked when the caller supplies an explicit tax account —
 * the base COA template defines no input-tax account.
 */
class PurchaseAccountingService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingSetupService $settings,
        private readonly AccountingAuditService $audit,
        private readonly PaymentAccountResolverService $paymentAccounts,
        private readonly ReceivablesPayablesService $payables,
    ) {}

    /**
     * Post a supplier purchase / unpaid expense (bill).
     *
     * Dr expense account (+ optional input tax) / Cr Accounts Payable (party).
     *
     * @param  array<string, mixed>  $options
     */
    public function postPurchase(
        int $instituteId,
        ?int $branchId,
        Party $supplier,
        float $amount,
        ?int $expenseAccountId = null,
        ?int $taxAccountId = null,
        ?float $taxAmount = null,
        ?int $actorId = null,
        ?string $journalDate = null,
        ?string $description = null,
        ?string $refType = 'purchase',
        ?int $refId = null,
        array $options = [],
    ): Journal {
        $this->assertSupplier($supplier, $instituteId, $branchId);

        $amount = round($amount, 4);
        $tax = round((float) ($taxAmount ?? 0), 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The purchase amount must be greater than zero.',
            ]);
        }

        if ($tax < 0) {
            throw ValidationException::withMessages([
                'tax_amount' => 'The input tax amount cannot be negative.',
            ]);
        }

        if ($tax > 0 && $taxAccountId === null) {
            throw ValidationException::withMessages([
                'tax_account_id' => 'An input tax account is required when booking input tax.',
            ]);
        }

        $expenseAccountId = $expenseAccountId ?? $this->defaultExpenseAccount($instituteId, $branchId);
        $payableAccountId = $this->payableAccount($instituteId, $branchId);

        $entries = [
            [
                'coa_id' => $expenseAccountId,
                'party_id' => null,
                'debit' => $amount,
                'credit' => 0,
                'memo' => $description ?? 'Purchase from '.$supplier->name,
            ],
        ];

        if ($tax > 0) {
            $entries[] = [
                'coa_id' => $taxAccountId,
                'party_id' => null,
                'debit' => $tax,
                'credit' => 0,
                'memo' => 'Input tax',
            ];
        }

        $entries[] = [
            'coa_id' => $payableAccountId,
            'party_id' => $supplier->id,
            'debit' => 0,
            'credit' => round($amount + $tax, 4),
            'memo' => 'Supplier bill from '.$supplier->name,
        ];

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'purchase',
            'ref_type' => $refType,
            'ref_id' => $refId,
            'description' => $description ?? 'Purchase from '.$supplier->name,
            'entries' => $entries,
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'purchase',
            'entity_id' => $journal->id,
            'after_payload' => [
                'supplier' => $supplier->name,
                'amount' => $amount,
                'tax' => $tax,
                'journal' => $journal->journal_no,
            ],
        ]);

        return $journal;
    }

    /**
     * Post a supplier payment (Dr AP / Cr cash-bank).
     *
     * Overpayment beyond the supplier's derived payable is rejected, mirroring
     * the invoice-receipt convention.
     */
    public function postSupplierPayment(
        int $instituteId,
        ?int $branchId,
        Party $supplier,
        float $amount,
        ?int $paymentMethodId = null,
        ?string $paymentMethod = 'cash',
        ?int $actorId = null,
        ?string $journalDate = null,
        ?string $description = null,
        ?int $refId = null,
        array $options = [],
    ): Journal {
        $this->assertSupplier($supplier, $instituteId, $branchId);

        $amount = round($amount, 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The payment amount must be greater than zero.',
            ]);
        }

        $payableAccountId = $this->payableAccount($instituteId, $branchId);
        $cashAccountId = $this->paymentAccounts->resolve($instituteId, $branchId, $paymentMethodId, $paymentMethod);

        $journal = DB::transaction(function () use (
            $instituteId, $branchId, $supplier, $amount,
            $payableAccountId, $cashAccountId, $actorId,
            $journalDate, $description, $refId, $options
        ) {
            ChartOfAccount::query()
                ->where('id', $payableAccountId)
                ->lockForUpdate()
                ->first();

            $outstanding = round($this->payables->partyBalance($supplier)['payable'], 4);

            if ($amount > $outstanding + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => "Overpayment rejected: the supplier's payable is {$outstanding}.",
                ]);
            }

            return $this->posting->create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'journal_date' => $journalDate ?? now()->toDateString(),
                'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
                'type' => 'payment',
                'ref_type' => 'supplier_payment',
                'ref_id' => $refId,
                'description' => $description ?? 'Payment to supplier '.$supplier->name,
                'entries' => [
                    [
                        'coa_id' => $payableAccountId,
                        'party_id' => $supplier->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => 'Payment to supplier '.$supplier->name,
                    ],
                    [
                        'coa_id' => $cashAccountId,
                        'party_id' => null,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => 'Supplier payment',
                    ],
                ],
            ], $actorId);
        });

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'supplier_payment',
            'entity_id' => $journal->id,
            'after_payload' => [
                'supplier' => $supplier->name,
                'amount' => $amount,
                'journal' => $journal->journal_no,
            ],
        ]);

        return $journal;
    }

    /**
     * Post a cash expense (Dr expense / Cr cash-bank). When a supplier is
     * given the expense line is attributed to them for reporting; no AP is
     * created because the money leaves immediately.
     */
    public function postExpense(
        int $instituteId,
        ?int $branchId,
        float $amount,
        ?int $expenseAccountId = null,
        ?int $paymentMethodId = null,
        ?string $paymentMethod = 'cash',
        ?Party $supplier = null,
        ?int $actorId = null,
        ?string $journalDate = null,
        ?string $description = null,
        ?int $refId = null,
        array $options = [],
    ): Journal {
        if ($supplier !== null) {
            $this->assertSupplier($supplier, $instituteId, $branchId);
        }

        $amount = round($amount, 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The expense amount must be greater than zero.',
            ]);
        }

        $expenseAccountId = $expenseAccountId ?? $this->defaultExpenseAccount($instituteId, $branchId);
        $cashAccountId = $this->paymentAccounts->resolve($instituteId, $branchId, $paymentMethodId, $paymentMethod);

        $journal = $this->posting->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $journalDate ?? now()->toDateString(),
            'currency_id' => $options['currency_id'] ?? $this->resolveCurrencyId($instituteId, $branchId),
            'type' => 'journal',
            'ref_type' => 'expense',
            'ref_id' => $refId,
            'description' => $description ?? 'Expense payment',
            'entries' => [
                [
                    'coa_id' => $expenseAccountId,
                    'party_id' => $supplier?->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => $description ?? 'Expense',
                ],
                [
                    'coa_id' => $cashAccountId,
                    'party_id' => null,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Expense payment',
                ],
            ],
        ], $actorId);

        $this->audit->log($instituteId, [
            'branch_id' => $branchId,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'create',
            'entity_type' => 'expense',
            'entity_id' => $journal->id,
            'after_payload' => [
                'amount' => $amount,
                'journal' => $journal->journal_no,
            ],
        ]);

        return $journal;
    }

    /**
     * Reverse a posted purchase/payment/expense journal using the engine's
     * reversal convention (original → reversed, reversal_of points back).
     * Posted journals are never deleted or edited.
     */
    public function reversePurchase(Journal $journal, ?int $instituteId = null, ?int $actorId = null, ?string $reason = null): Journal
    {
        $reversal = $this->posting->reverse($journal, $instituteId, $actorId, $reason);

        $this->audit->log((int) $journal->institute_id, [
            'branch_id' => $journal->branch_id,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'action' => 'reverse',
            'entity_type' => 'purchase',
            'entity_id' => $journal->id,
            'after_payload' => [
                'reversal' => $reversal->journal_no,
                'reason' => $reason,
            ],
        ]);

        return $reversal;
    }

    // -------------------------------------------------------- AP queries

    /**
     * Derived AP balance for a single supplier (delegates to the AR/AP engine).
     *
     * @return array{receivable: float, payable: float, net: float}
     */
    public function supplierBalance(Party $supplier, ?string $asOfDate = null): array
    {
        return $this->payables->partyBalance($supplier, $asOfDate);
    }

    /**
     * Derived AP aging buckets for a supplier.
     *
     * @return array{current: float, 31_60: float, 61_90: float, 91_plus: float}
     */
    public function supplierAging(Party $supplier, ?string $asOfDate = null): array
    {
        return $this->payables->aging($supplier, $asOfDate, 'payable');
    }

    /**
     * @return Collection<int, object>
     */
    public function supplierBalances(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->payables->supplierBalances($instituteId, $branchId, $asOfDate);
    }

    /**
     * @return Collection<int, object>
     */
    public function supplierBalancesWithAging(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): Collection
    {
        return $this->payables->supplierBalancesWithAging($instituteId, $branchId, $asOfDate);
    }

    /**
     * @return array{receivable: float, payable: float, net: float}
     */
    public function apTotals(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        return $this->payables->totals($instituteId, $branchId, $asOfDate);
    }

    // ------------------------------------------------------------ Internals

    private function assertSupplier(Party $supplier, int $instituteId, ?int $branchId): void
    {
        if ((int) $supplier->institute_id !== (int) $instituteId) {
            throw ValidationException::withMessages([
                'supplier' => 'The supplier does not belong to this institute.',
            ]);
        }

        if (! $supplier->isSupplier()) {
            throw ValidationException::withMessages([
                'supplier' => 'The selected party is not a supplier.',
            ]);
        }

        if (! $supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier' => 'The supplier is inactive.',
            ]);
        }

        if ($branchId !== null && $supplier->branch_id !== null && (int) $supplier->branch_id !== (int) $branchId) {
            throw ValidationException::withMessages([
                'supplier' => 'The supplier does not belong to this branch.',
            ]);
        }
    }

    private function payableAccount(int $instituteId, ?int $branchId): int
    {
        $account = app(ChartOfAccountService::class)->accountByCode($instituteId, '2001', $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('is_payable', true)
                ->orderBy('code')
                ->first();

        if ($account === null) {
            throw new \RuntimeException('No payable account is configured for this institute.');
        }

        return (int) $account->id;
    }

    private function defaultExpenseAccount(int $instituteId, ?int $branchId): int
    {
        $account = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        if ($account === null) {
            throw new \RuntimeException('No expense account is configured for this institute.');
        }

        return (int) $account->id;
    }

    private function resolveCurrencyId(int $instituteId, ?int $branchId): int
    {
        $code = $this->settings->getSetting($instituteId, 'base_currency', null, $branchId);

        if ($code !== null) {
            $currency = Currency::query()->where('code', $code)->first();

            if ($currency !== null) {
                return (int) $currency->id;
            }
        }

        return (int) (Currency::query()->orderBy('code')->value('id'));
    }
}
