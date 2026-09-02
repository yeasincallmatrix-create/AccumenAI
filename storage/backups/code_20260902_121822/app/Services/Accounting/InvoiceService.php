<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FeeHead;
use App\Models\Installment;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use App\Models\Party;
use App\Models\Student;
use App\Services\Inventory\InventoryStockService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invoice lifecycle (industry-neutral AR documents).
 *
 * An invoice is created with items, optional installments and optional tax
 * group. On creation a SALE journal is posted (debit Accounts Receivable /
 * credit the item income accounts) so AR derives from the ledger; if the
 * institute keeps invoice_auto_post=false the journal is kept as a draft until
 * explicitly posted. Status is computed from paid/due amounts.
 *
 * Cancel is only allowed while nothing has been paid; it reverses the sale
 * journal (or voids the draft) and marks the invoice cancelled.
 */
class InvoiceService
{
    public function __construct(
        private readonly JournalPostingService $posting,
        private readonly AccountingAuditService $audit,
        private readonly AccountingSetupService $settings,
        private readonly PartyService $parties,
        private readonly NotificationService $notifications,
        private readonly InventoryStockService $inventoryStock,
        private readonly FxConversionService $fx,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): Invoice
    {
        $data = $this->validate($instituteId, $branchId, $data);

        // Step 34: student-only invoices (party_id NULL) are linked to the
        // student's customer party so AR derives into party balances. Never
        // fails the invoice — linking is best-effort and idempotent.
        if (filled($data['student_id'] ?? null) && blank($data['party_id'] ?? null)) {
            $partyId = $this->resolveStudentParty($instituteId, $branchId, (int) $data['student_id'], $actorId);
            if ($partyId !== null) {
                $data['party_id'] = $partyId;
            }
        }

        $totalAmount = 0.0;
        foreach ($data['items'] as $item) {
            $totalAmount += (float) $item['amount'];
        }
        $totalAmount = round($totalAmount, 4);
        $discount = round((float) ($data['discount'] ?? 0), 4);
        $payable = round($totalAmount - $discount, 4);

        if ($payable < 0) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot exceed the invoice total.',
            ]);
        }

        // STEP 19 FX: resolve the invoice's exchange rate against the base
        // currency. Same-currency invoices keep rate 1 and no base conversion.
        $fxMeta = $this->resolveInvoiceFx($instituteId, $branchId, $data, $payable);

        $invoice = DB::transaction(function () use ($instituteId, $branchId, $data, $actorId, $totalAmount, $discount, $payable, $fxMeta) {
            $invoice = (new Invoice())->forceFill([
                'institute_id' => $instituteId,
                'student_id' => $data['student_id'] ?? null,
                'party_id' => $data['party_id'] ?? null,
                'enrollment_id' => $data['enrollment_id'] ?? null,
                'invoice_number' => $this->allocateInvoiceNumber($instituteId),
                'invoice_type' => $data['invoice_type'],
                'total_amount' => $totalAmount,
                'discount' => $discount,
                'payable_amount' => $payable,
                'paid_amount' => 0,
                'due_amount' => $payable,
                'status' => $payable <= 0 ? 'paid' : 'unpaid',
                'due_date' => $data['due_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'exchange_rate' => $fxMeta['exchange_rate'],
                'base_payable_amount' => $fxMeta['base_payable_amount'],
                'tax_group_id' => $data['tax_group_id'] ?? null,
                'invoice_meta' => array_filter([
                    'note' => $data['note'] ?? null,
                ], fn ($value) => $value !== null),
                'created_by' => $actorId,
            ]);
            $invoice->save();

            foreach ($data['items'] as $index => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'amount' => round((float) $item['amount'], 4),
                    'coa_id' => $item['coa_id'] ?? null,
                    'fee_head_id' => $item['fee_head_id'] ?? null,
                    'tax_group_id' => $item['tax_group_id'] ?? null,
                    'inventory_item_id' => $item['inventory_item_id'] ?? null,
                ]);
            }

            $this->issueInventoryStock($invoice, $data['items'], $instituteId, $branchId, $actorId, $data['warehouse_id'] ?? null);

            if (! empty($data['installments'])) {
                $this->createInstallments($invoice, $data['installments'], $actorId);
            }

            if ($payable > 0) {
                $journal = $this->posting->create(
                    $this->saleJournalPayload($invoice, $totalAmount, $discount, $payable, $branchId, $fxMeta),
                    $actorId,
                    postNow: $this->settings->getSetting($instituteId, 'invoice_auto_post', false, $branchId) === true,
                );

                $invoice->forceFill(['journal_id' => $journal->id])->save();
            }

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'create',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
                'after_payload' => ['invoice_number' => $invoice->invoice_number, 'payable' => $payable],
            ]);

            return $invoice->load('items');
        });

        $this->notifyInvoiceCreated($invoice, $actorId);
        $this->notifyAdmins($invoice, $actorId);

        return $invoice;
    }

    private function notifyAdmins(Invoice $invoice, ?int $actorId): void
    {
        try {
            $instituteId = (int) $invoice->institute_id;
            if ($instituteId <= 0) {
                return;
            }

            Notification::create([
                'scope' => 'institute',
                'institute_id' => $instituteId,
                'category' => 'finance_invoice',
                'title' => 'Invoice created',
                'message' => 'Invoice '.$invoice->invoice_number.' created for '.number_format((float) $invoice->payable_amount, 2).'.',
                'link_url' => route('finance.invoices.show', $invoice->id),
                'created_by_type' => 'institute_user',
                'created_by_id' => $actorId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification.admin_invoice_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify the student attached to an invoice. Runs inside the safe
     * NotificationService pipeline and can never fail the invoice creation.
     */
    private function notifyInvoiceCreated(Invoice $invoice, ?int $actorId): void
    {
        if ($invoice->student_id === null) {
            return;
        }

        $student = Student::query()->withoutGlobalScope('institute')->find($invoice->student_id);
        if ($student === null) {
            return;
        }

        $this->notifications->send('finance.invoice_created', $student, [
            'student_name' => $student->full_name ?: $student->first_name,
            'reg_no' => $student->reg_no,
            'amount' => number_format((float) $invoice->payable_amount, 2),
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date?->toDateString(),
        ], [
            'actor_type' => 'institute_user',
            'actor_id' => $actorId,
            'link' => route('students.show', $student->id),
        ]);
    }

    /**
     * Inventory sale hook (STEP 16): issue stock + post the COGS journal for
     * every invoice item carrying an inventory_item_id. Non-inventory invoices
     * are untouched — the sale journal (Dr AR / Cr income) is identical either
     * way, so existing non-inventory flows are unaffected.
     */
    private function issueInventoryStock(Invoice $invoice, array $items, int $instituteId, ?int $branchId, ?int $actorId, ?int $warehouseId): void
    {
        $inventoryLines = [];

        foreach ($items as $item) {
            if (empty($item['inventory_item_id'])) {
                continue;
            }

            $inventoryItem = InventoryItem::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->find((int) $item['inventory_item_id']);

            if ($inventoryItem === null) {
                throw ValidationException::withMessages([
                    'items.*.inventory_item_id' => 'The selected inventory item does not belong to this institute.',
                ]);
            }

            $inventoryLines[] = [
                'item_id' => $inventoryItem->id,
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_cost' => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
            ];
        }

        if ($inventoryLines === []) {
            return;
        }

        $warehouseId = $warehouseId ?? InventoryWarehouse::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'No warehouse is configured for this institute.',
            ]);
        }

        $this->inventoryStock->saleIssue(
            $instituteId,
            $branchId,
            (int) $warehouseId,
            'invoice',
            $invoice->id,
            $inventoryLines,
            $actorId,
            ['reason' => 'Invoice '.$invoice->invoice_number],
        );
    }

    /**
     * Restore inventory stock (and reverse the COGS journal) when an invoice
     * that issued stock is cancelled.
     */
    private function restockCancelledInventory(Invoice $invoice, int $instituteId, ?int $actorId): void
    {
        $hasInventory = InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('inventory_item_id')
            ->exists();

        if (! $hasInventory) {
            return;
        }

        $this->inventoryStock->returnForReference(
            $instituteId,
            $invoice->branch_id,
            'in',
            'invoice',
            $invoice->id,
            $actorId,
            ['reason' => 'Invoice '.$invoice->invoice_number.' cancelled'],
        );
    }

    /**
     * Post a draft sale journal for an invoice (invoice_auto_post=false flow).
     */
    public function postJournal(Invoice $invoice, int $instituteId, ?int $branchId, ?int $actorId = null): Invoice
    {
        if ($invoice->journal_id === null) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice has no sale journal to post.',
            ]);
        }

        $this->posting->post($invoice->journal, $instituteId, $actorId);

        return $invoice->fresh();
    }

    /**
     * Re-create the sale journal for an existing invoice (Step 37 waivers).
     *
     * Used after an accounting-safe adjustment (e.g. an approved waiver raised
     * the invoice discount): the previous sale journal must already have been
     * reversed (posted) or voided (draft); this method then writes a fresh sale
     * journal reflecting the current amounts, respecting invoice_auto_post.
     */
    public function rebuildSaleJournal(Invoice $invoice, ?int $branchId = null, ?int $actorId = null): Invoice
    {
        $payable = round((float) $invoice->payable_amount, 4);
        $total = round((float) $invoice->total_amount, 4);

        if ($payable <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'A sale journal cannot be created for a fully discounted invoice.',
            ]);
        }

        // STEP 19 FX: reconstruct fxMeta from the invoice's stored FX fields.
        // Recompute base_payable_amount from the CURRENT payable_amount
        // (waivers/modifications update payable but not base_payable_amount).
        $baseCurrencyId = $this->fx->baseCurrencyId((int) $invoice->institute_id, $branchId);
        $invoiceCurrencyId = $invoice->currency_id !== null ? (int) $invoice->currency_id : null;
        $isForeign = $invoiceCurrencyId !== null && $invoiceCurrencyId !== $baseCurrencyId;
        $rate = $invoice->exchange_rate ?? '1.00000000';
        $basePayable = $isForeign
            ? $this->fx->convert(number_format($payable, 4, '.', ''), $rate)
            : number_format($payable, 4, '.', '');
        $fxMeta = [
            'exchange_rate' => $rate,
            'base_payable_amount' => $basePayable,
            'currency_id' => $invoiceCurrencyId,
            'is_foreign' => $isForeign,
        ];

        $journal = $this->posting->create(
            $this->saleJournalPayload($invoice, $total, (float) $invoice->discount, $payable, $branchId, $fxMeta),
            $actorId,
            postNow: $this->settings->getSetting($invoice->institute_id, 'invoice_auto_post', false, $branchId) === true,
        );

        $invoice->forceFill(['journal_id' => $journal->id])->save();

        return $invoice->fresh();
    }

    /**
     * Cancel an unpaid invoice: reverse its sale journal (or void a draft) and
     * mark it cancelled. Partially paid invoices must be settled first.
     */
    public function cancel(Invoice $invoice, int $instituteId, ?int $actorId = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $instituteId, $actorId) {
            if ($invoice->paid_amount > 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Partially paid invoices cannot be cancelled; reverse the payments first.',
                ]);
            }

            if ($invoice->journal_id !== null) {
                $journal = $invoice->journal;

                if ($journal->status === 'posted') {
                    $this->posting->reverse($journal, $instituteId, $actorId, 'Invoice '.$invoice->invoice_number.' cancelled');
                } elseif ($journal->status === 'draft') {
                    $this->posting->void($journal, $instituteId, $actorId);
                }
            }

            $this->restockCancelledInventory($invoice, $instituteId, $actorId);

            $invoice->forceFill(['status' => 'cancelled'])->save();

            $this->audit->log($instituteId, [
                'branch_id' => $invoice->branch_id,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'update',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
                'after_payload' => ['invoice_number' => $invoice->invoice_number, 'status' => 'cancelled'],
            ]);

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(int $instituteId, ?int $branchId, array $data): array
    {
        $validator = validator($data, [
            'party_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'enrollment_id' => ['nullable', 'integer'],
            'invoice_type' => ['required', 'in:admission,course_fee,exam_fee,certificate_fee,other'],
            'due_date' => ['nullable', 'date'],
            'currency_id' => ['nullable', 'integer'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'tax_group_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'warehouse_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.coa_id' => ['nullable', 'integer'],
            'items.*.fee_head_id' => ['nullable', 'integer'],
            'items.*.inventory_item_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'installments' => ['nullable', 'array', 'max:12'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            'installments.*.due_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        if (filled($data['party_id'] ?? null)) {
            $party = Party::query()->where('institute_id', $instituteId)->find((int) $data['party_id']);

            if ($party === null || ! $party->isCustomer()) {
                throw ValidationException::withMessages([
                    'party_id' => 'The selected party is not a customer of this institute.',
                ]);
            }
        }

        if (filled($data['currency_id'] ?? null)) {
            $currency = Currency::query()->where('id', (int) $data['currency_id'])->first();
            if ($currency === null) {
                throw ValidationException::withMessages([
                    'currency_id' => 'The selected currency does not exist.',
                ]);
            }
            if (! $currency->is_active) {
                throw ValidationException::withMessages([
                    'currency_id' => 'The selected currency is not active.',
                ]);
            }
        }

        if (filled($data['items.*.coa_id'] ?? null)) {
            $coaIds = array_unique(array_filter(array_column($data['items'], 'coa_id')));
            if ($coaIds !== []) {
                $owned = ChartOfAccount::query()
                    ->where('institute_id', $instituteId)
                    ->whereIn('id', $coaIds)
                    ->pluck('id')
                    ->all();

                $foreign = array_values(array_diff($coaIds, $owned));
                if ($foreign !== []) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more item accounts do not belong to this institute.',
                    ]);
                }
            }
        }

        if (filled($data['items.*.fee_head_id'] ?? null)) {
            $headIds = array_unique(array_filter(array_column($data['items'], 'fee_head_id')));
            if ($headIds !== []) {
                $owned = FeeHead::query()
                    ->where('institute_id', $instituteId)
                    ->whereIn('id', $headIds)
                    ->pluck('id')
                    ->all();

                $foreign = array_values(array_diff($headIds, $owned));
                if ($foreign !== []) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more fee heads do not belong to this institute.',
                    ]);
                }
            }
        }

        if (filled($data['installments'] ?? null)) {
            $installmentSum = array_sum(array_column($data['installments'], 'amount'));
            $payable = round(array_sum(array_column($data['items'], 'amount')) - (float) ($data['discount'] ?? 0), 4);

            if (abs($installmentSum - $payable) > 0.01) {
                throw ValidationException::withMessages([
                    'installments' => 'Installments must total the invoice payable amount.',
                ]);
            }
        }

        return $data;
    }

    /**
     * STEP 19 FX: resolve the invoice exchange rate + base payable.
     *
     * Same-currency (or no-currency) invoices keep rate 1 and base = payable.
     * Foreign invoices resolve the rate deterministically (explicit rate first,
     * then the effective tenant rate for today) and convert the payable to the
     * base currency with BCMath. A missing rate fails safely.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array{exchange_rate: string, base_payable_amount: string, currency_id: ?int, is_foreign: bool}
     */
    private function resolveInvoiceFx(int $instituteId, ?int $branchId, array $data, float $payable): array
    {
        $baseCurrencyId = $this->fx->baseCurrencyId($instituteId, $branchId);
        $currencyId = filled($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null;

        if ($currencyId === null || $currencyId === $baseCurrencyId) {
            return [
                'exchange_rate' => '1.00000000',
                'base_payable_amount' => number_format($payable, 4, '.', ''),
                'currency_id' => $currencyId,
                'is_foreign' => false,
            ];
        }

        $resolved = $this->fx->resolveRate(
            $instituteId,
            $branchId,
            $currencyId,
            $baseCurrencyId,
            now()->toDateString(),
            filled($data['exchange_rate'] ?? null) ? (string) $data['exchange_rate'] : null,
        );

        $basePayable = $this->fx->convert(number_format($payable, 4, '.', ''), $resolved['rate']);

        return [
            'exchange_rate' => $resolved['rate'],
            'base_payable_amount' => $basePayable,
            'currency_id' => $currencyId,
            'is_foreign' => true,
        ];
    }

    /**
     * @param  array{exchange_rate: string, base_payable_amount: string, currency_id: ?int, is_foreign: bool}  $fxMeta
     *
     * @return array<string, mixed>
     */
    private function saleJournalPayload(Invoice $invoice, float $totalAmount, float $discount, float $payable, ?int $branchId, array $fxMeta): array
    {
        $coaService = app(ChartOfAccountService::class);
        $receivable = $coaService->accountByCode($invoice->institute_id, '1100', $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $invoice->institute_id)
                ->where('branch_id', $branchId)
                ->where('is_receivable', true)
                ->first();

        if ($receivable === null) {
            throw new \RuntimeException('No receivable account is configured for this institute.');
        }

        // STEP 19: debit/credit stay the authoritative BASE-currency amounts;
        // foreign amounts + rate are preserved as additive line metadata.
        $basePayable = (float) $fxMeta['base_payable_amount'];
        $rate = $fxMeta['exchange_rate'];
        $lineCurrency = $fxMeta['is_foreign'] ? $fxMeta['currency_id'] : null;

        $entries = [];
        $entries[] = [
            'coa_id' => $receivable->id,
            'party_id' => $invoice->party_id,
            'currency_id' => $lineCurrency,
            'foreign_debit' => $fxMeta['is_foreign'] ? $payable : 0,
            'foreign_credit' => 0,
            'exchange_rate' => $rate,
            'debit' => $basePayable,
            'credit' => 0,
            'memo' => 'Invoice '.$invoice->invoice_number,
        ];

        $credited = 0.0;
        $itemCount = $invoice->items->count();
        foreach ($invoice->items as $index => $item) {
            $isLast = $index === $itemCount - 1;
            $amount = (float) $item->amount;
            $proportion = $payable / max($totalAmount, 0.0001);
            $foreignCredit = $isLast
                ? round($payable - $credited, 4)
                : round($amount * $proportion, 4);

            $credited += $foreignCredit;

            $baseCredit = $fxMeta['is_foreign']
                ? (float) $this->fx->convert(number_format($foreignCredit, 4, '.', ''), $rate)
                : $foreignCredit;

            // Keep the base side exactly balanced: the last income line absorbs
            // any conversion rounding difference.
            if ($isLast) {
                $priorBase = array_sum(array_map(
                    fn ($line) => (float) $line['credit'],
                    array_slice($entries, 1),
                ));
                $baseCredit = round($basePayable - $priorBase, 4);
            }

            $entries[] = [
                'coa_id' => $item->coa_id ?? $this->defaultIncomeAccount($invoice->institute_id, $branchId),
                'party_id' => null,
                'currency_id' => $lineCurrency,
                'foreign_debit' => 0,
                'foreign_credit' => $fxMeta['is_foreign'] ? $foreignCredit : 0,
                'exchange_rate' => $rate,
                'debit' => 0,
                'credit' => $baseCredit,
                'memo' => $item->description,
            ];
        }

        return [
            'institute_id' => $invoice->institute_id,
            'branch_id' => $branchId,
            'journal_date' => now()->toDateString(),
            'currency_id' => $invoice->currency_id ?? $this->resolveCurrencyId($invoice->institute_id, $branchId),
            'exchange_rate' => $rate,
            'type' => 'sale',
            'ref_type' => 'invoice',
            'ref_id' => $invoice->id,
            'description' => 'Sale invoice '.$invoice->invoice_number,
            'entries' => $entries,
        ];
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

    private function defaultIncomeAccount(int $instituteId, ?int $branchId): int
    {
        $coa = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('type', 'income')
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        if ($coa === null) {
            throw new \RuntimeException('No income account is configured for this institute.');
        }

        return $coa->id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $installments
     */
    private function createInstallments(Invoice $invoice, array $installments, ?int $actorId): void
    {
        foreach ($installments as $index => $row) {
            $inst = (new Installment())->forceFill([
                'institute_id' => $invoice->institute_id,
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'installment_no' => $index + 1,
                'amount' => round((float) $row['amount'], 4),
                'paid_amount' => 0,
                'due_date' => $row['due_date'],
                'status' => 'pending',
            ]);
            $inst->save();
        }
    }

    /**
     * Resolve the customer party for a student invoice, creating the party the
     * first time (tracked via party_meta.student_id) and reusing it afterwards.
     * Returns null when the student is unknown or a duplicate phone makes
     * creation impossible — in which case the invoice proceeds without a party.
     */
    private function resolveStudentParty(int $instituteId, ?int $branchId, int $studentId, ?int $actorId): ?int
    {
        $student = Student::withoutGlobalScopes()
            ->where('id', $studentId)
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->first();

        if ($student === null) {
            return null;
        }

        $party = Party::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('party_meta->student_id', $studentId)
            ->first();

        if ($party !== null) {
            return (int) $party->id;
        }

        try {
            $party = $this->parties->create($instituteId, $branchId, [
                'type' => 'customer',
                'name' => $student->full_name ?: 'Student #'.$student->id,
                'phone' => $student->phone ?: $student->guardian_phone,
                'email' => $student->email,
                'party_meta' => ['student_id' => $studentId, 'source' => 'student'],
            ], $actorId);
        } catch (ValidationException) {
            return null;
        }

        return (int) $party->id;
    }

    private function allocateInvoiceNumber(int $instituteId): string
    {
        $taken = fn (string $no) => Invoice::query()
            ->where('institute_id', $instituteId)
            ->where('invoice_number', $no)
            ->exists();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a unique invoice number.');
    }
}
