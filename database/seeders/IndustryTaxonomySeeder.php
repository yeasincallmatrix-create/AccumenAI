<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Industry;
use App\Models\SubIndustry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds industries and sub_industries tables from config/industry_rules.php.
 * Idempotent — uses updateOrCreate to prevent duplicates.
 */
class IndustryTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $config = config('industry_rules');

        if (empty($config['global']['industries'])) {
            return;
        }

        // Build country name -> id map
        $countryMap = Country::pluck('id', 'name')->toArray();

        // Seed global industries
        $industries = $config['global']['industries'];
        $sortOrder = 0;

        foreach ($industries as $slug => $label) {
            if ($slug === 'transport') {
                continue;
            }

            $industry = Industry::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $label,
                    'slug' => $slug,
                    'status' => 'active',
                    'sort_order' => $sortOrder,
                ]
            );
            $sortOrder++;

            $globalSubs = $config['global']['sub_industries'][$slug] ?? [];
            $subSort = 0;

            foreach ($globalSubs as $subSlug => $subLabel) {
                $this->upsertSubIndustry(
                    $industry->id,
                    null,
                    $subLabel,
                    $subSlug,
                    $subSort
                );
                $subSort++;
            }
        }

        // Seed country-specific sub-industries
        foreach ($config as $countryName => $countryData) {
            if ($countryName === 'global' || $countryName === 'capabilities') {
                continue;
            }

            if (!is_array($countryData)) {
                continue;
            }

            $countryId = $countryMap[$countryName] ?? null;

            if ($countryId === null) {
                continue;
            }

            foreach ($countryData as $industrySlug => $subIndustries) {
                if (!is_array($subIndustries) || $subIndustries === []) {
                    continue;
                }

                $industry = Industry::where('slug', $industrySlug)->first();
                if (!$industry) {
                    continue;
                }

                $subSort = 0;
                foreach ($subIndustries as $subSlug => $subLabel) {
                    $this->upsertSubIndustry(
                        $industry->id,
                        $countryId,
                        $subLabel,
                        $subSlug,
                        $subSort
                    );
                    $subSort++;
                }
            }
        }

        $this->command->info('Industry taxonomy seeded: '
            . Industry::count() . ' industries, '
            . SubIndustry::count() . ' sub-industries.');
    }

    /**
     * Insert or update a sub-industry without touching the generated scope_hash column.
     */
    private function upsertSubIndustry(
        int $industryId,
        ?int $countryId,
        string $name,
        string $slug,
        int $sortOrder
    ): void {
        $existing = SubIndustry::where('industry_id', $industryId)
            ->where('country_id', $countryId)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            $existing->update([
                'name' => $name,
                'sort_order' => $sortOrder,
            ]);
        } else {
            SubIndustry::create([
                'industry_id' => $industryId,
                'country_id' => $countryId,
                'name' => $name,
                'slug' => $slug,
                'status' => 'active',
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
