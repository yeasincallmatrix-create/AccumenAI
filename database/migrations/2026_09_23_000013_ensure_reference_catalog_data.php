<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Currencies + country-currency maps (CurrencySeeder is idempotent).
        if (app('db')->getSchemaBuilder()->hasTable('currencies')) {
            $count = \App\Models\Currency::query()->count();
            if ($count === 0) {
                (new \Database\Seeders\CurrencySeeder)->run();
            }
        }

        // Industry taxonomy from config/industry_rules.php (idempotent).
        if (app('db')->getSchemaBuilder()->hasTable('industries')) {
            $count = \App\Models\Industry::query()->count();
            if ($count === 0) {
                (new \Database\Seeders\IndustryTaxonomySeeder)->run();
            }
        }

        // Landing-page CMS catalog (orphan seeder — never wired before).
        if (app('db')->getSchemaBuilder()->hasTable('home_pages')) {
            $count = \App\Models\HomePage::query()->count();
            if ($count === 0) {
                (new \Database\Seeders\HomePageSeeder)->run();
            }
        }

        // Shared CRM lead-source catalog (no dedicated seeder; TestCase inline).
        if (app('db')->getSchemaBuilder()->hasTable('crm_lead_sources')) {
            foreach ([
                ['slug' => 'walk_in',  'name' => 'Walk-in',   'display_order' => 1],
                ['slug' => 'referral', 'name' => 'Referral',  'display_order' => 2],
                ['slug' => 'website',  'name' => 'Website',   'display_order' => 3],
                ['slug' => 'phone',    'name' => 'Phone',     'display_order' => 4],
                ['slug' => 'email',    'name' => 'Email',     'display_order' => 5],
                ['slug' => 'social',   'name' => 'Social Media', 'display_order' => 6],
                ['slug' => 'other',    'name' => 'Other',     'display_order' => 7],
            ] as $row) {
                if (! \App\Models\CrmLeadSource::where('slug', $row['slug'])->exists()) {
                    \App\Models\CrmLeadSource::create($row + ['status' => 'active']);
                }
            }
        }
    }

    public function down(): void
    {
        // Data backfill only — intentionally a no-op.
    }
};
