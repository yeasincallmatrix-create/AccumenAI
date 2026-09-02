<?php

namespace App\Services\Accounting;

use App\Models\AccountingSetting;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

/**
 * One-call accounting bootstrap for an institute.
 *
 * setupForInstitute() runs the full foundation in a transaction:
 *   1. Chart of Accounts template (groups + base accounts)
 *   2. Default payment methods (Cash / Bank Transfer / Mobile Banking / Card)
 *   3. Current fiscal year (calendar default)
 *   4. Default accounting_settings
 *
 * Idempotent — safe to call during onboarding and on every install/upgrade.
 * Settings are read/written through getSetting()/setSetting().
 */
class AccountingSetupService
{
    private const COUNTRY_TO_CURRENCY = [
        'Bangladesh' => 'BDT',
        'United States' => 'USD',
        'USA' => 'USD',
        'India' => 'INR',
        'Pakistan' => 'PKR',
    ];

    private const DEFAULT_SETTINGS = [
        'ar_ap_mode' => 'derive',
        'statement_lock' => true,
        'money_precision' => '19,4',
        'invoice_auto_post' => false,
        'fiscal_year_start' => 1,
        'fx_gain_account_code' => '4900',
        'fx_loss_account_code' => '5900',
        'fx_unrealized_gain_account_code' => '4901',
        'fx_unrealized_loss_account_code' => '5901',
        'fx_revaluation_policy' => 'period_end',
        'fx_rounding_policy' => 'half_up',
    ];

    public function __construct(
        private readonly ChartOfAccountService $coaService,
    ) {}

    /**
     * Bootstrap the full accounting foundation for an institute.
     */
    public function setupForInstitute(int $instituteId, ?int $branchId = null, ?int $createdBy = null): void
    {
        DB::transaction(function () use ($instituteId, $branchId, $createdBy) {
            $this->coaService->installGroupsAndAccounts($instituteId, $branchId, $createdBy);
            $this->seedPaymentMethods($instituteId, $branchId, $createdBy);
            $this->ensureCurrentFiscalYear($instituteId, $branchId, $createdBy);
            $this->ensureDefaultSettings($instituteId, $branchId, $createdBy);
        });
    }

    /**
     * Seed the standard payment methods, linking Cash and Bank Transfer to
     * their template accounts.
     */
    private function seedPaymentMethods(int $instituteId, ?int $branchId, ?int $createdBy): void
    {
        $cash = $this->coaService->accountByCode($instituteId, '1001', $branchId);
        $bank = $this->coaService->accountByCode($instituteId, '1002', $branchId);

        $methods = [
            ['name' => 'Cash', 'coa_id' => $cash?->id],
            ['name' => 'Bank Transfer', 'coa_id' => $bank?->id],
            ['name' => 'Mobile Banking (bKash/Nagad)', 'coa_id' => null],
            ['name' => 'Card', 'coa_id' => null],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->firstOrCreate(
                [
                    'institute_id' => $instituteId,
                    'branch_id' => $branchId,
                    'name' => $method['name'],
                ],
                [
                    'coa_id' => $method['coa_id'],
                    'is_system' => true,
                    'is_active' => true,
                    'created_by' => $createdBy,
                ],
            );
        }
    }

    /**
     * Ensure the institute has a current (calendar) fiscal year and that it is
     * flagged is_current.
     */
    private function ensureCurrentFiscalYear(int $instituteId, ?int $branchId, ?int $createdBy): void
    {
        $today = now()->toDateString();

        $existing = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if ($existing !== null) {
            if (! $existing->is_current) {
                FiscalYear::query()
                    ->where('institute_id', $instituteId)
                    ->where('branch_id', $branchId)
                    ->where('id', '!=', $existing->id)
                    ->update(['is_current' => false]);
                $existing->update(['is_current' => true]);
            }

            return;
        }

        $year = (int) now()->format('Y');

        $fiscalYear = FiscalYear::query()->firstOrCreate(
            [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'name' => 'FY '.$year,
            ],
            [
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'open',
                'is_current' => true,
                'created_by' => $createdBy,
            ],
        );

        if ($fiscalYear->wasRecentlyCreated) {
            FiscalYear::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('id', '!=', $fiscalYear->id)
                ->update(['is_current' => false]);
        }
    }

    /**
     * Ensure default accounting settings exist, resolving the base currency
     * from the institute's country.
     */
    private function ensureDefaultSettings(int $instituteId, ?int $branchId, ?int $createdBy): void
    {
        $settings = self::DEFAULT_SETTINGS;

        if ($this->getSetting($instituteId, 'base_currency', null, $branchId) === null) {
            $settings['base_currency'] = $this->resolveBaseCurrency($instituteId);
        }

        foreach ($settings as $key => $value) {
            if ($this->getSetting($instituteId, $key, null, $branchId) !== null) {
                continue;
            }

            AccountingSetting::query()->create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'settings_key' => $key,
                'settings_value' => $value,
                'created_by' => $createdBy,
            ]);
        }
    }

    /**
     * Read a setting value for an institute (branch_id NULL = institute-wide).
     */
    public function getSetting(int $instituteId, string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        $setting = AccountingSetting::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('settings_key', $key)
            ->first();

        return $setting?->settings_value ?? $default;
    }

    /**
     * Write a setting value for an institute.
     */
    public function setSetting(int $instituteId, string $key, mixed $value, ?int $branchId = null, ?int $updatedBy = null): void
    {
        AccountingSetting::query()->updateOrCreate(
            [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'settings_key' => $key,
            ],
            [
                'settings_value' => $value,
                'updated_by' => $updatedBy,
            ],
        );
    }

    private function resolveBaseCurrency(int $instituteId): string
    {
        $country = Institute::query()->whereKey($instituteId)->value('country');

        $code = self::COUNTRY_TO_CURRENCY[$country] ?? 'USD';

        return Currency::query()->where('code', $code)->value('code') ?? 'USD';
    }
}
