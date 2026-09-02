<?php

namespace App\Services\Tax;

use App\Models\TaxRate;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;

class TaxAccountingService
{
    public function __construct(
        private readonly JournalPostingService $journal,
        private readonly ChartOfAccountService $coa,
    ) {}

    public function salesTaxJournal(
        int $instituteId,
        ?int $branchId,
        float $taxAmount,
        ?int $partyId = null,
        ?string $journalDate = null,
        ?int $actorId = null,
        ?string $description = null,
    ): \App\Models\Journal {
        $outputAccountId = $this->coa->accountByCode($instituteId, '2100', $branchId)?->id;
        $clearingAccountId = $this->coa->accountByCode($instituteId, '2102', $branchId)?->id;

        if ($outputAccountId === null || $clearingAccountId === null) {
            throw new \InvalidArgumentException('Tax accounts (2100/2102) not found. Run accounting setup first.');
        }

        $date = $journalDate ?? now()->toDateString();

        return $this->journal->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $date,
            'type' => 'journal',
            'currency_id' => 1,
            'description' => $description ?? 'Sales tax collected',
            'entries' => [
                [
                    'coa_id' => $clearingAccountId,
                    'debit' => round($taxAmount, 4),
                    'credit' => 0,
                    'memo' => 'Tax clearing',
                ],
                [
                    'coa_id' => $outputAccountId,
                    'debit' => 0,
                    'credit' => round($taxAmount, 4),
                    'memo' => 'Output VAT payable',
                ],
            ],
        ], $actorId, false);
    }

    public function purchaseTaxJournal(
        int $instituteId,
        ?int $branchId,
        float $taxAmount,
        ?int $partyId = null,
        ?string $journalDate = null,
        ?int $actorId = null,
        ?string $description = null,
    ): \App\Models\Journal {
        $inputAccountId = $this->coa->accountByCode($instituteId, '1201', $branchId)?->id;
        $clearingAccountId = $this->coa->accountByCode($instituteId, '2102', $branchId)?->id;

        if ($inputAccountId === null || $clearingAccountId === null) {
            throw new \InvalidArgumentException('Tax accounts (1201/2102) not found. Run accounting setup first.');
        }

        $date = $journalDate ?? now()->toDateString();

        return $this->journal->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $date,
            'type' => 'journal',
            'currency_id' => 1,
            'description' => $description ?? 'Input VAT paid',
            'entries' => [
                [
                    'coa_id' => $inputAccountId,
                    'debit' => round($taxAmount, 4),
                    'credit' => 0,
                    'memo' => 'Input VAT receivable',
                ],
                [
                    'coa_id' => $clearingAccountId,
                    'debit' => 0,
                    'credit' => round($taxAmount, 4),
                    'memo' => 'Tax clearing',
                ],
            ],
        ], $actorId, false);
    }

    public function withholdingTaxJournal(
        int $instituteId,
        ?int $branchId,
        float $taxAmount,
        ?int $partyId = null,
        ?string $journalDate = null,
        ?int $actorId = null,
        ?string $description = null,
    ): \App\Models\Journal {
        $whAccountId = $this->coa->accountByCode($instituteId, '2101', $branchId)?->id;

        if ($whAccountId === null) {
            throw new \InvalidArgumentException('Withholding Tax Payable account (2101) not found.');
        }

        $date = $journalDate ?? now()->toDateString();

        return $this->journal->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $date,
            'type' => 'journal',
            'currency_id' => 1,
            'description' => $description ?? 'Withholding tax',
            'entries' => [
                [
                    'coa_id' => $whAccountId,
                    'debit' => 0,
                    'credit' => round($taxAmount, 4),
                    'memo' => 'WHT payable',
                ],
                [
                    'coa_id' => $whAccountId,
                    'debit' => round($taxAmount, 4),
                    'credit' => 0,
                    'memo' => 'WHT deduction',
                ],
            ],
        ], $actorId, false);
    }

    public function clearTaxAccount(
        int $instituteId,
        ?int $branchId,
        float $amount,
        bool $isInput = true,
        ?string $journalDate = null,
        ?int $actorId = null,
        ?string $description = null,
    ): \App\Models\Journal {
        $clearingAccountId = $this->coa->accountByCode($instituteId, '2102', $branchId)?->id;
        $taxAccountId = $this->coa->accountByCode($instituteId, $isInput ? '1201' : '2100', $branchId)?->id;

        if ($clearingAccountId === null || $taxAccountId === null) {
            throw new \InvalidArgumentException('Tax accounts not found. Run accounting setup first.');
        }

        $date = $journalDate ?? now()->toDateString();

        return $this->journal->create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'journal_date' => $date,
            'type' => 'journal',
            'currency_id' => 1,
            'description' => $description ?? 'Tax clearing entry',
            'entries' => [
                [
                    'coa_id' => $taxAccountId,
                    'debit' => $isInput ? 0 : round($amount, 4),
                    'credit' => $isInput ? round($amount, 4) : 0,
                    'memo' => 'Tax clearing',
                ],
                [
                    'coa_id' => $clearingAccountId,
                    'debit' => $isInput ? round($amount, 4) : 0,
                    'credit' => $isInput ? 0 : round($amount, 4),
                    'memo' => 'Tax clearing',
                ],
            ],
        ], $actorId, false);
    }
}
