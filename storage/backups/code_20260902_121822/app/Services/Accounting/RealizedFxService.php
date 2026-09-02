<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Invoice;

/**
 * Realized FX gain/loss on settlement (STEP 19).
 *
 * When a foreign-currency invoice is settled, the cash side is valued at the
 * settlement rate while the AR side is relieved at the invoice's original
 * (carrying) rate. The difference is a realized FX gain or loss, posted
 * through the same journal as the receipt via configurable FX accounts
 * (accounting_settings: fx_gain_account_code / fx_loss_account_code).
 *
 * All math is BCMath string arithmetic; the result is rounded at the
 * DECIMAL(19,4) accounting boundary.
 */
class RealizedFxService
{
    public function __construct(
        private readonly AccountingSetupService $settings,
        private readonly FxConversionService $fx,
    ) {}

    /**
     * Compute the realized FX difference for settling a foreign amount of an
     * invoice at a settlement rate.
     *
     * @return array{difference: string, is_gain: bool, is_loss: bool}
     */
    public function compute(Invoice $invoice, string $foreignAmount, string $settlementRate): array
    {
        $carryingRate = $this->carryingRate($invoice);

        $carryingValue = $this->fx->convert($foreignAmount, $carryingRate);
        $settlementValue = $this->fx->convert($foreignAmount, $settlementRate);

        $difference = bcsub($settlementValue, $carryingValue, FxConversionService::MONEY_SCALE);

        return [
            'difference' => $difference,
            'is_gain' => bccomp($difference, '0', FxConversionService::MONEY_SCALE) > 0,
            'is_loss' => bccomp($difference, '0', FxConversionService::MONEY_SCALE) < 0,
        ];
    }

    /**
     * The rate the invoice's AR was recorded at (never rewritten after
     * posting; legacy invoices without a rate carry 1).
     */
    public function carryingRate(Invoice $invoice): string
    {
        $rate = $invoice->exchange_rate !== null ? (string) $invoice->exchange_rate : '1.00000000';

        return bccomp($rate, '0', FxConversionService::INTERNAL_SCALE) > 0
            ? $rate
            : '1.00000000';
    }

    /**
     * The extra journal line realizing the FX difference (gain → credit the FX
     * gain account; loss → debit the FX loss account). Null when the
     * difference is zero.
     *
     * @return array<string, mixed>|null
     */
    public function journalLine(int $instituteId, ?int $branchId, array $computed, string $memo): ?array
    {
        if (! $computed['is_gain'] && ! $computed['is_loss']) {
            return null;
        }

        $amount = (float) $this->fx->round(
            $computed['is_gain'] ? $computed['difference'] : bcmul($computed['difference'], '-1', FxConversionService::MONEY_SCALE),
            FxConversionService::MONEY_SCALE,
        );

        if ($computed['is_gain']) {
            return [
                'coa_id' => $this->gainAccount($instituteId, $branchId)->id,
                'party_id' => null,
                'debit' => 0,
                'credit' => $amount,
                'memo' => $memo,
            ];
        }

        return [
            'coa_id' => $this->lossAccount($instituteId, $branchId)->id,
            'party_id' => null,
            'debit' => $amount,
            'credit' => 0,
            'memo' => $memo,
        ];
    }

    public function gainAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        $code = $this->settings->getSetting($instituteId, 'fx_gain_account_code', '4900', $branchId);

        return $this->accountByCode($instituteId, $branchId, (string) $code, 'FX gain account');
    }

    public function lossAccount(int $instituteId, ?int $branchId): ChartOfAccount
    {
        $code = $this->settings->getSetting($instituteId, 'fx_loss_account_code', '5900', $branchId);

        return $this->accountByCode($instituteId, $branchId, (string) $code, 'FX loss account');
    }

    private function accountByCode(int $instituteId, ?int $branchId, string $code, string $label): ChartOfAccount
    {
        $account = app(ChartOfAccountService::class)->accountByCode($instituteId, $code, $branchId)
            ?? ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

        if ($account === null) {
            throw new \RuntimeException("No {$label} (code {$code}) is configured for this institute.");
        }

        return $account;
    }
}
