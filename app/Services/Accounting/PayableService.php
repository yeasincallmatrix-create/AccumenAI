<?php

namespace App\Services\Accounting;

use App\Models\Party;
use Illuminate\Support\Collection;

/**
 * STEP 73 — Accounts Payable Service.
 *
 * Provides supplier statement and aging reports using the existing
 * ReceivablesPayablesService as the data engine.
 */
class PayableService
{
    public function __construct(
        private readonly ReceivablesPayablesService $arp,
    ) {}

    /**
     * Supplier statement: transactions, balance, aging.
     *
     * @return array{party: Party, balance: float, aging: array, transactions: Collection}
     */
    public function supplierStatement(int $instituteId, int $partyId, ?string $asOfDate = null): array
    {
        $party = Party::query()
            ->where('institute_id', $instituteId)
            ->where('id', $partyId)
            ->firstOrFail();

        $asOf = $asOfDate ?? now()->toDateString();

        $balance = $this->arp->partyBalance($party, $asOf);
        $aging = $this->arp->aging($party, $asOf, 'payable');

        $transactions = $party->journalEntries()
            ->whereHas('journal', fn ($q) => $q->where('status', 'posted')->whereNull('reversal_of'))
            ->whereDate('journal_entries.journal_date', '<=', $asOf)
            ->join('journals as j', 'j.id', '=', 'journal_entries.journal_id')
            ->select(
                'journal_entries.journal_date',
                'j.journal_no',
                'j.description',
                'journal_entries.debit',
                'journal_entries.credit',
            )
            ->orderBy('journal_entries.journal_date')
            ->orderBy('j.id')
            ->get();

        return [
            'party' => $party,
            'balance' => (float) $balance['payable'],
            'aging' => $aging,
            'transactions' => $transactions,
        ];
    }

    /**
     * Payables aging report for all suppliers.
     *
     * @return array{suppliers: Collection, totals: array}
     */
    public function payablesAging(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        $suppliers = $this->arp->supplierBalancesWithAging($instituteId, $branchId, $asOfDate);
        $totals = $this->arp->totals($instituteId, $branchId, $asOfDate);

        return [
            'suppliers' => $suppliers,
            'totals' => $totals,
        ];
    }
}
