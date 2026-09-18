<?php

namespace Database\Seeders;

use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class PackageFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $medicalFeatures = \App\Models\FeatureRegistry::where('module_key', 'medical')
            ->pluck('feature_key')
            ->toArray();

        if (empty($medicalFeatures)) {
            if ($this->command) {
                $this->command->warn('No medical features found in feature_registry. Run FeatureRegistrySeeder first.');
            }
            return;
        }

        $packageSlugs = ['free', 'basic', 'advanced', 'premium'];

        $created = 0;
        $updated = 0;

        foreach ($packageSlugs as $slug) {
            $pkg = SubscriptionPackage::where('slug', $slug)->first();
            if (! $pkg) {
                continue;
            }

            $hasMedical = \App\Models\PackageModule::where('package_id', $pkg->id)
                ->where('module_key', 'medical')
                ->where('enabled', true)
                ->exists();

            if (! $hasMedical) {
                continue;
            }

            foreach ($medicalFeatures as $featureKey) {
                $row = PackageFeature::updateOrCreate(
                    ['package_id' => $pkg->id, 'feature_key' => $featureKey],
                    ['enabled' => true]
                );
                $row->wasRecentlyCreated ? $created++ : $updated++;
            }
        }

        if ($this->command) {
            $this->command->info("Package features: {$created} created, {$updated} updated.");
        }
    }
}
