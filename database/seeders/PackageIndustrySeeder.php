<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageIndustrySeeder extends Seeder
{
    /**
     * Default package ↔ industry offer matrix (industry tiers only):
     * free (fail-closed fallback, never removed) + the industry's
     * vertical starter/growth/enterprise packages.
     *
     * Legacy basic/advanced/premium are intentionally NOT offered
     * (Phase 2 unmap). Missing packages (e.g. verticals not yet seeded)
     * are skipped with a warning.
     *
     * @var array<string, array<int, string>>
     */
    protected array $mappings = [
        'healthcare' => ['free', 'medical_starter', 'medical_growth', 'medical_enterprise'],
        'education' => ['free', 'education_starter', 'education_growth', 'education_enterprise'],
        'training_center' => ['free', 'training_center_starter', 'training_center_growth', 'training_center_enterprise'],
        'retail' => ['free', 'retail_starter', 'retail_growth', 'retail_enterprise'],
        'manufacturing' => ['free', 'manufacturing_starter', 'manufacturing_growth', 'manufacturing_enterprise'],
        'real_estate' => ['free', 'real_estate_starter', 'real_estate_growth', 'real_estate_enterprise'],
        'restaurant' => ['free', 'restaurant_starter', 'restaurant_growth', 'restaurant_enterprise'],
    ];

    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_industries')) {
            $this->command?->error('package_industries table missing — run migrations first.');

            return;
        }

        $industryKeys = DB::table('industries')->where('status', 'active')->pluck('slug')->all();

        $rows = [];

        foreach ($this->mappings as $industry => $packageSlugs) {
            if (! in_array($industry, $industryKeys, true)) {
                $this->command?->warn("Skipping unknown industry: {$industry}");

                continue;
            }

            $position = 0;

            foreach ($packageSlugs as $slug) {
                $packageId = DB::table('subscription_packages')
                    ->whereRaw('LOWER(slug) = ?', [$slug])
                    ->value('id');

                if (! $packageId) {
                    $this->command?->warn("Skipping missing package: {$slug}");

                    continue;
                }

                $rows[] = [
                    'package_id' => $packageId,
                    'industry_key' => $industry,
                    'is_active' => true,
                    'sort_order' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $position++;
            }
        }

        if ($rows !== []) {
            // upsert on uq_package_industry: only is_active/updated_at are
            // refreshed on conflict, so pre-existing sort_order values (e.g.
            // PackageSeeder's vertical tiers, admin-UI rows) are never churned.
            DB::table('package_industries')->upsert(
                $rows,
                ['package_id', 'industry_key'],
                ['is_active', 'updated_at']
            );
        }

        $this->command?->info('Package-Industry mappings seeded: '.count($rows).' row(s).');
    }
}
