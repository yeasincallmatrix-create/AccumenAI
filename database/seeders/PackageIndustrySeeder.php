<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageIndustrySeeder extends Seeder
{
    /**
     * Default package ↔ industry offer matrix.
     *
     * @var array<string, array<int, string>>
     */
    protected array $mappings = [
        'healthcare' => ['free', 'basic', 'advanced', 'premium'],
        'education' => ['free', 'basic', 'advanced'],
        'training_center' => ['free', 'basic'],
        'retail' => ['free', 'basic', 'advanced'],
        'manufacturing' => ['free', 'basic', 'advanced'],
        'real_estate' => ['free', 'basic'],
    ];

    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_industries')) {
            $this->command?->error('package_industries table missing — run migrations first.');

            return;
        }

        $industryKeys = DB::table('industries')->where('status', 'active')->pluck('slug')->all();

        $inserted = 0;

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

                DB::table('package_industries')->updateOrInsert(
                    ['package_id' => $packageId, 'industry_key' => $industry],
                    [
                        'is_active' => true,
                        'sort_order' => $position,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $position++;
                $inserted++;
            }
        }

        $this->command?->info("Package-Industry mappings seeded: {$inserted} row(s).");
    }
}
