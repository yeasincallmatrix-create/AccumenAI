<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustryTagSeeder extends Seeder
{
    /**
     * Canonical industry tags — single source of truth.
     * Re-applied on every run (self-healing).
     *
     * Design:
     * - Tagged accounts: education/training_center tuition/admission
     * - Universal accounts: NULL industries (visible to all)
     * - Defends against external tag mutation by forcing canonical state
     */
    public const TAGGED = [
        '4001' => ['education', 'training_center'],
        '4002' => ['education', 'training_center'],
    ];

    public const MUST_BE_UNIVERSAL = [
        '4003',  // Merchandise Sales
        '4010',  // Gain on Disposal
        '5007',  // COGS
    ];

    public function run(): void
    {
        // Step 1: Apply canonical tags
        foreach (self::TAGGED as $code => $industries) {
            $updated = DB::table('chart_of_accounts')
                ->whereNull('institute_id')
                ->where('code', $code)
                ->update([
                    'industries' => json_encode($industries),
                    'updated_at' => now(),
                ]);

            if ($updated) {
                $this->command->info('  Tagged '.$code.' → '.implode(',', $industries));
            }
        }

        // Step 2: Null out stale tags on universal accounts (self-heal)
        $nulled = DB::table('chart_of_accounts')
            ->whereNull('institute_id')
            ->whereIn('code', self::MUST_BE_UNIVERSAL)
            ->whereNotNull('industries')
            ->update([
                'industries' => null,
                'updated_at' => now(),
            ]);

        if ($nulled) {
            $this->command->warn('  Cleared stale tags on '.$nulled.' universal accounts');
        }

        $this->command->info('Industry tags canonicalized');
    }
}
