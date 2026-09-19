<?php

namespace Database\Seeders;

use App\Models\Industry;
use App\Models\SubIndustry;
use Illuminate\Database\Seeder;

/**
 * Ensures the minimum industry/sub_industry rows needed by feature tests exist.
 *
 * Delegates to the production IndustryTaxonomySeeder first. If that fails
 * (e.g. missing Country data in a bare test DB), falls back to inserting
 * the minimal set directly.
 *
 * Idempotent — uses firstOrCreate / where().first().
 */
class IndustryTaxonomyTestSeeder extends Seeder
{
    public function run(): void
    {
        if (Industry::count() > 0 && SubIndustry::count() > 0) {
            $this->command?->info('Industry taxonomy already seeded — skipping.');

            return;
        }

        try {
            $this->call(IndustryTaxonomySeeder::class);
        } catch (\Throwable $e) {
            $this->command?->warn('IndustryTaxonomySeeder failed: ' . $e->getMessage());
            $this->command?->warn('Falling back to minimal test seed.');
            $this->seedMinimal();
        }

        if (Industry::count() === 0) {
            $this->command?->warn('IndustryTaxonomySeeder produced no rows — seeding minimal set.');
            $this->seedMinimal();
        }
    }

    private function seedMinimal(): void
    {
        $industries = [
            'education'        => 'Education',
            'training_center'  => 'Training Center',
            'healthcare'       => 'Healthcare',
        ];

        foreach ($industries as $slug => $name) {
            Industry::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'status' => 'active', 'sort_order' => 0]
            );
        }

        $subIndustries = [
            'education'   => ['school' => 'School'],
            'healthcare'  => [
                'hospital' => 'Hospital',
                'clinic'   => 'Clinic',
            ],
        ];

        foreach ($subIndustries as $industrySlug => $subs) {
            $industry = Industry::where('slug', $industrySlug)->first();
            if (! $industry) {
                continue;
            }

            foreach ($subs as $subSlug => $subName) {
                SubIndustry::firstOrCreate(
                    ['industry_id' => $industry->id, 'slug' => $subSlug, 'country_id' => null],
                    ['name' => $subName, 'status' => 'active', 'sort_order' => 0]
                );
            }
        }

        $this->command?->info('Minimal industry taxonomy seeded: '
            . Industry::count() . ' industries, '
            . SubIndustry::count() . ' sub-industries.');
    }
}
