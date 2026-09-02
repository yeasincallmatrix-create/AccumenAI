<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * P2-6 — Seeds country-specific pass mark defaults.
 * Populates country_pass_mark_defaults from config/pass_marks.php.
 * Idempotent: uses updateOrInsert, safe to re-run.
 */
class PassMarkDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('pass_marks', []);

        foreach ($config as $countryCode => $defaults) {
            $countryCode = strtoupper($countryCode);
            if ($countryCode === 'GLOBAL') {
                $countryCode = 'GLOBAL';
            }

            // Default entry
            if (isset($defaults['default'])) {
                DB::table('country_pass_mark_defaults')->updateOrInsert(
                    ['country_code' => $countryCode, 'component_type' => 'default', 'component_name' => null],
                    ['pass_percentage' => $defaults['default'], 'updated_at' => now(), 'created_at' => now()]
                );
            }

            // Theory/practical splits
            foreach (['theory', 'practical'] as $type) {
                if (isset($defaults[$type])) {
                    DB::table('country_pass_mark_defaults')->updateOrInsert(
                        ['country_code' => $countryCode, 'component_type' => $type, 'component_name' => null],
                        ['pass_percentage' => $defaults[$type], 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }

            // Per-component overrides
            if (! empty($defaults['components']) && is_array($defaults['components'])) {
                foreach ($defaults['components'] as $compName => $pct) {
                    DB::table('country_pass_mark_defaults')->updateOrInsert(
                        ['country_code' => $countryCode, 'component_type' => 'component', 'component_name' => strtolower($compName)],
                        ['pass_percentage' => $pct, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
        }

        // Ensure explicit BD practical default exists for viva/lab
        $this->command?->info('PassMarkDefaults seeded: '.DB::table('country_pass_mark_defaults')->count().' rows');
    }
}
