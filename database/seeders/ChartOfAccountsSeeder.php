<?php

namespace Database\Seeders;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use Illuminate\Database\Seeder;

/**
 * Seeds the minimal chart of accounts an institute needs:
 * 1001 Cash in Hand (asset/cash) and 4001 Tuition Income (income).
 *
 * Institute-scoped and idempotent (firstOrCreate on institute+code).
 * No-op when no institute id is given so global seeding never
 * creates orphan tenant rows.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function __construct(
        protected ?int $instituteId = null,
        protected ?int $branchId = null,
    ) {}

    public function run(): void
    {
        if ($this->instituteId === null) {
            return;
        }

        if (Institute::whereKey($this->instituteId)->doesntExist()) {
            return;
        }

        $this->account('1000', 'Cash', 'asset', ['is_cash' => true]);
        $this->account('4001', 'Tuition Income', 'income');
    }

    protected function account(string $code, string $name, string $type, array $flags = []): ChartOfAccount
    {
        $group = AccountGroup::firstOrCreate(
            [
                'institute_id' => $this->instituteId,
                'branch_id' => $this->branchId,
                'code' => $type === 'income' ? '4000' : '1000',
            ],
            [
                'name' => $type === 'income' ? 'Income' : 'Assets',
                'category' => $type === 'income' ? 'income' : 'asset',
            ]
        );

        return ChartOfAccount::firstOrCreate(
            ['institute_id' => $this->instituteId, 'code' => $code],
            array_merge([
                'branch_id' => $this->branchId,
                'account_group_id' => $group->id,
                'name' => $name,
                'type' => $type,
                'is_active' => true,
                'is_system' => true,
            ], $flags)
        );
    }
}
