<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Services\Accounting\CoaTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlobalChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $groupMap = [
            'asset' => 1, 'liability' => 2, 'equity' => 3,
            'income' => 4, 'expense' => 5,
        ];

        // Canonical chart-of-accounts registry (single source of truth).
        $accounts = CoaTemplate::globalRows();

        DB::transaction(function () use ($accounts, $groupMap) {
            // Pass 1: headers (is_system=1)
            foreach ($accounts as $row) {
                [$code, $name, $parentCode, $isHeader, $isPostable, $type, $industries] = $row;
                if (! $isHeader) {
                    continue;
                }

                $account = ChartOfAccount::withoutGlobalScope('institute')
                    ->firstOrNew(['code' => $code, 'institute_id' => null]);
                $account->forceFill([
                    'name' => $name,
                    'parent_id' => null,
                    'is_header' => true,
                    'is_postable' => false,
                    'is_system' => true,
                    'type' => $type,
                    'account_group_id' => $groupMap[$type] ?? null,
                    'industries' => $industries ?: null,
                    'is_active' => true,
                ])->save();
            }

            // Pass 2: leaves (is_system=1 for defaults)
            foreach ($accounts as $row) {
                [$code, $name, $parentCode, $isHeader, $isPostable, $type, $industries] = $row;
                if ($isHeader) {
                    continue;
                }

                $parentId = $parentCode
                    ? ChartOfAccount::withoutGlobalScope('institute')
                        ->whereNull('institute_id')->where('code', $parentCode)->value('id')
                    : null;

                $account = ChartOfAccount::withoutGlobalScope('institute')
                    ->firstOrNew(['code' => $code, 'institute_id' => null]);
                $account->forceFill([
                    'name' => $name,
                    'parent_id' => $parentId,
                    'is_header' => false,
                    'is_postable' => true,
                    'is_system' => true,
                    'type' => $type,
                    'account_group_id' => $groupMap[$type] ?? null,
                    'industries' => $industries ?: null,
                    'is_active' => true,
                ])->save();
            }
        });

        $this->command->info('✅ Global COA seeded: '.count($accounts));
    }
}
