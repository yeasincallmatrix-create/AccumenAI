<?php

namespace App\Services\Accounting;

use App\Models\AccountingSetting;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class TenantCoaSeederService
{
    public function seedForTenant(int $instituteId): int
    {
        $tenantIndustry = DB::table('institutes')->where('id', $instituteId)->value('industry');
        $created = 0;

        foreach (CoaTemplate::childrenByParent() as $parentCode => $children) {
            $parent = ChartOfAccount::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('code', $parentCode)
                ->where('is_header', true)
                ->first();

            if (! $parent) {
                continue;
            }

            $parentIndustries = $parent->industries
                ? (is_array($parent->industries) ? $parent->industries : json_decode($parent->industries, true))
                : [];

            if (! empty($parentIndustries)) {
                if (! $tenantIndustry || ! in_array($tenantIndustry, $parentIndustries)) {
                    continue;
                }
            }

            foreach ($children as $child) {
                [$code, $name] = $child;
                $extra = $child[2] ?? [];

                $exists = ChartOfAccount::withoutGlobalScope('institute')
                    ->where('institute_id', $instituteId)
                    ->where('code', $code)
                    ->exists();

                if ($exists) {
                    continue;
                }

                ChartOfAccount::withoutGlobalScope('institute')->create(array_merge([
                    'institute_id' => $instituteId,
                    'code' => $code,
                    'name' => $name,
                    'parent_id' => $parent->id,
                    'account_group_id' => $parent->account_group_id,
                    'type' => $parent->type,
                    'is_header' => false,
                    'is_postable' => true,
                    'is_system' => false,
                    'industries' => $parent->industries ?: null,
                    'is_active' => true,
                ], $extra));

                $created++;
            }
        }

        return $created;
    }

    /**
     * Provision the full accounting stack for a new institute in one
     * transaction: CoA children + fiscal year + 12 monthly periods +
     * payment methods + default settings. Any failure rolls back all.
     */
    public function seedFullProvisioning(int $instituteId): array
    {
        return DB::transaction(function () use ($instituteId) {
            $setup = app(AccountingSetupService::class);

            $coa = $this->seedForTenant($instituteId);
            $setup->ensureCurrentFiscalYear($instituteId, null, null);
            $periods = $setup->createMonthlyPeriods($instituteId);
            $setup->seedPaymentMethods($instituteId, null, null);
            $setup->ensureDefaultSettings($instituteId, null, null);

            $settings = AccountingSetting::query()
                ->where('institute_id', $instituteId)
                ->count();

            return [
                'coa_created' => $coa,
                'periods_created' => $periods,
                'settings_count' => $settings,
            ];
        });
    }
}
