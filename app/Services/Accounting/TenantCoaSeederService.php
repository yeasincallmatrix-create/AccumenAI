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

        // F-005: leaves hang off TENANT-owned anchors (cloned on demand under
        // tenant category roots) — never off the shared global rows, so every
        // tenant owns its whole tree and new tenants cannot re-create the
        // cross-tenant links this service used to write.
        $reanchor = app(CoaReanchorService::class);

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

            $anchorId = $reanchor->ensureAnchor($instituteId, (string) $parentCode);

            foreach ($children as $child) {
                [$code, $name] = $child;
                $extra = $child[2] ?? [];

                // Respect the child's OWN industry tag: a healthcare-only leaf
                // (e.g. 4000.5 Discount Allowed) under a universal parent must
                // not land in an education/training/retail tenant's tree.
                $templateRow = CoaTemplate::findByCode((string) $code);
                $childIndustries = $templateRow[6] ?? null;
                if (! empty($childIndustries)) {
                    if (! $tenantIndustry || ! in_array($tenantIndustry, $childIndustries)) {
                        continue;
                    }
                }

                $exists = ChartOfAccount::withoutGlobalScope('institute')
                    ->where('institute_id', $instituteId)
                    ->where('code', $code)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Propagate the template's rich flags (is_cash, is_bank,
                // is_receivable, is_payable, cash_flow_category) so the
                // onboarding write-set matches installGroupsAndAccounts().
                // childrenByParent() only carries CHILDREN_FLAGS (is_bank on
                // 1100.1); the rest lives on the canonical row's [7].
                $flags = $templateRow[7] ?? [];

                ChartOfAccount::withoutGlobalScope('institute')->create(array_merge([
                    'institute_id' => $instituteId,
                    'code' => $code,
                    'name' => $name,
                    'parent_id' => $anchorId,
                    'account_group_id' => $parent->account_group_id,
                    'type' => $parent->type,
                    'is_header' => false,
                    'is_postable' => true,
                    'is_system' => false,
                    'industries' => $childIndustries ?: ($parent->industries ?: null),
                    'is_active' => true,
                ], $flags, $extra));

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
