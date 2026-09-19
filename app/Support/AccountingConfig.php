<?php

namespace App\Support;

use App\Models\AccountingSetting;

class AccountingConfig
{
    public static function accountCode(string $key): string
    {
        $instituteId = self::resolveInstituteId();
        if ($instituteId) {
            $override = AccountingSetting::query()
                ->where('institute_id', $instituteId)
                ->whereNull('branch_id')
                ->where('settings_key', "account_codes.{$key}")
                ->value('settings_value');
            if ($override) {
                return (string) $override;
            }
        }

        $code = config("accounting.account_codes.{$key}");
        if ($code === null) {
            throw new \RuntimeException("Account code '{$key}' is not configured in config/accounting.php.");
        }

        return $code;
    }

    public static function allAccountCodes(): array
    {
        return config('accounting.account_codes', []);
    }

    public static function paymentMethod(string $key): ?array
    {
        return config("accounting.payment_methods.{$key}");
    }

    public static function paymentMethods(): array
    {
        return config('accounting.payment_methods', []);
    }

    public static function paymentMethodKeys(): array
    {
        return array_keys(config('accounting.payment_methods', []));
    }

    public static function isBankLike(string $key): bool
    {
        return (bool) (config("accounting.payment_methods.{$key}.is_bank_like") ?? false);
    }

    public static function defaultCurrency(): string
    {
        $instituteId = self::resolveInstituteId();
        if ($instituteId) {
            $setting = AccountingSetting::query()
                ->where('institute_id', $instituteId)
                ->whereNull('branch_id')
                ->where('settings_key', 'base_currency')
                ->value('settings_value');
            if ($setting) {
                return (string) $setting;
            }
        }

        return config('accounting.default_currency', 'BDT');
    }

    private static function resolveInstituteId(): ?int
    {
        return TenantContext::id();
    }
}