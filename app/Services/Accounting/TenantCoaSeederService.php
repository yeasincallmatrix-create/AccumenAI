<?php

namespace App\Services\Accounting;

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
}
