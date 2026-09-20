<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use Illuminate\Database\Seeder;

class GlobalAccountGroupsSeeder extends Seeder
{
    /**
     * Global account groups — shared by all tenants.
     * - institute_id = NULL
     * - branch_id = NULL
     * - is_system = 1 (marks as platform-managed)
     *
     * Extracted from ChartOfAccountService::CATEGORIES (lines 20-26).
     */
    public function run(): void
    {
        // Shape: [category, code, name, sort_order] per CATEGORIES const.
        $groups = [
            [
                'category' => 'asset',
                'code' => '1',
                'name' => 'Assets',
                'sort_order' => 10,
            ],
            [
                'category' => 'liability',
                'code' => '2',
                'name' => 'Liabilities',
                'sort_order' => 20,
            ],
            [
                'category' => 'equity',
                'code' => '3',
                'name' => 'Equity',
                'sort_order' => 30,
            ],
            [
                'category' => 'income',
                'code' => '4',
                'name' => 'Income',
                'sort_order' => 40,
            ],
            [
                'category' => 'expense',
                'code' => '5',
                'name' => 'Expenses',
                'sort_order' => 50,
            ],
        ];

        $created = 0;
        $existing = 0;

        foreach ($groups as $data) {
            $group = AccountGroup::firstOrCreate(
                [
                    'institute_id' => null,
                    'branch_id' => null,
                    'code' => $data['code'],
                ],
                array_merge($data, [
                    'institute_id' => null,
                    'branch_id' => null,
                    'is_system' => 1,
                ])
            );

            $group->wasRecentlyCreated ? $created++ : $existing++;
        }

        $this->command->info("Global Groups: {$created} created, {$existing} existed");
    }
}
