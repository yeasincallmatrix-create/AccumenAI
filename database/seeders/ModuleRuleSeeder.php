<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleRuleSeeder extends Seeder
{
    public function run(): void
    {
        // country_code = NULL → global rules (RuleEngineService priority:
        // tenant override > country > global > default).
        $bdRules = [
            [
                'country_code' => 'BD',
                'module_key' => 'tax.vat',
                'rule_key' => 'rate',
                'rule_value' => '{"rate": 15.00}',
                'description' => 'BD VAT rate 15%',
            ],
            [
                'country_code' => 'BD',
                'module_key' => 'medical.pharmacy',
                'rule_key' => 'expiry_tracking',
                'rule_value' => '{"enabled": true, "days": 30}',
                'description' => 'BD pharmacy expiry rule',
            ],
            [
                'country_code' => 'BD',
                'module_key' => 'education.fees',
                'rule_key' => 'fiscal_year',
                'rule_value' => '{"start": "07-01", "end": "06-30"}',
                'description' => 'BD fiscal year July-June',
            ],
        ];

        $globalRules = [
            [
                'country_code' => null,
                'module_key' => 'sales.invoice',
                'rule_key' => 'auto_post',
                'rule_value' => '{"enabled": true}',
                'description' => 'Global invoice auto-post',
            ],
            [
                'country_code' => null,
                'module_key' => 'common.numbering',
                'rule_key' => 'format',
                'rule_value' => '{"prefix": "INV", "padding": 5}',
                'description' => 'Global numbering format',
            ],
        ];

        $count = 0;

        foreach ([$bdRules, $globalRules] as $group) {
            foreach ($group as $rule) {
                DB::table('module_rules')->updateOrInsert(
                    [
                        'country_code' => $rule['country_code'],
                        'module_key' => $rule['module_key'],
                        'rule_key' => $rule['rule_key'],
                    ],
                    [
                        'rule_value' => $rule['rule_value'],
                        'description' => $rule['description'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        $this->command->info('Module rules seeded: ' . $count . ' (3 BD + 2 global)');
    }
}
