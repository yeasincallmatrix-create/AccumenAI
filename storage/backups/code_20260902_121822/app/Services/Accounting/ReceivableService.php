<?php

namespace App\Services\Accounting;

use App\Models\Party;
use Illuminate\Support\Collection;

/**
 * STEP 72 — Accounts Receivable Service.
 *
 * Provides customer statement and aging reports using the existing
 * ReceivablesPayablesService as the data engine.
 */
class ReceivableService
{
    public function __construct(
        private readonly ReceivablesPayablesService $arp,
    ) {}

    /**
     * Customer statement: invoices, payments, due amount, due date.
     *
     * @return array{party: Party, balance: float, aging: array, transactions: Collection}
     */
    public function customerStatement(int $instituteId, int $partyId, ?string $asOfDate = null): array
    {
        $party = Party::query()
            ->where('institute_id', $instituteId)
            ->where('id', $partyId)
            ->firstOrFail();

        $asOf = $asOfDate ?? now()->toDateString();

        $balance = $this->arp->partyBalance($party, $asOf);
        $aging = $this->arp->aging($party, $asOf, 'receivable');

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
            'balance' => (float) $balance['receivable'],
            'aging' => $aging,
            'transactions' => $transactions,
        ];
    }

    /**
     * Receivables aging report for all customers.
     *
     * @return array{customers: Collection, totals: array}
     */
    public function receivablesAging(int $instituteId, ?int $branchId = null, ?string $asOfDate = null): array
    {
        $customers = $this->arp->customerBalancesWithAging($instituteId, $branchId, $asOfDate);
        $totals = $this->arp->totals($instituteId, $branchId, $asOfDate);

        return [
            'customers' => $customers,
            'totals' => $totals,
        ];
    }
}
