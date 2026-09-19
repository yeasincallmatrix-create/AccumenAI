<?php

namespace Database\Factories;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['asset', 'liability', 'equity', 'income', 'expense']);

        return [
            'institute_id' => 1,
            'branch_id' => null,
            'account_group_id' => null, // resolved in configure() below
            'parent_id' => null,
            'code' => $this->faker->unique()->numerify('9####'),
            'name' => ucfirst($type) . ' Account ' . $this->faker->unique()->word(),
            'type' => $type,
            'cash_flow_category' => null,
            'is_cash' => false,
            'is_bank' => false,
            'is_receivable' => false,
            'is_payable' => false,
            'is_active' => true,
            'is_system' => false,
            'currency_id' => null,
            'legacy_head_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ChartOfAccount $account) {
            // account_group_id is NOT NULL + real FK: create a group on demand.
            if ($account->account_group_id === null) {
                $account->account_group_id = AccountGroup::firstOrCreate(
                    [
                        'institute_id' => $account->institute_id,
                        'branch_id' => $account->branch_id,
                        'code' => 'GRP-' . substr(uniqid(), -8),
                    ],
                    [
                        'name' => 'Default ' . ucfirst($account->type ?? 'asset') . ' Group',
                        'category' => $account->type ?? 'asset',
                    ]
                )->id;
            }
        });
    }

    public function cash(): static
    {
        return $this->state(fn () => ['type' => 'asset', 'is_cash' => true]);
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => 'income']);
    }
}
