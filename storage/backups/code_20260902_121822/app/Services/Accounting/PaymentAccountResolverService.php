<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\PaymentMethod;

/**
 * Shared payment-method → cash/bank account resolution (STEP 14).
 *
 * The single source of truth for the account a money movement hits:
 *   - a linked payment method's default posting account
 *     (PaymentMethod.coa_id) wins;
 *   - otherwise the legacy method string maps bank-like methods
 *     (bank/bkash/nagad) to a bank account and the rest to cash;
 *   - falls back to the institute's cash account when no bank account
 *     is configured.
 *
 * Used by PaymentService (receipt debit) and PurchaseAccountingService
 * (supplier-payment / expense credit) so the mapping is never duplicated.
 */
class PaymentAccountResolverService
{
    public function resolve(int $instituteId, ?int $branchId, ?int $paymentMethodId, ?string $paymentMethod): int
    {
        if ($paymentMethodId !== null) {
            $method = PaymentMethod::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id'))
                ->find($paymentMethodId);

            if ($method !== null && $method->coa_id !== null) {
                return (int) $method->coa_id;
            }
        }

        $isBank = in_array($paymentMethod, ['bank', 'bkash', 'nagad'], true);

        $account = ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where($isBank ? 'is_bank' : 'is_cash', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        if ($account === null && $isBank) {
            $account = ChartOfAccount::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('is_cash', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->first();
        }

        if ($account === null) {
            throw new \RuntimeException('No cash or bank account is configured for this institute.');
        }

        return (int) $account->id;
    }
}
