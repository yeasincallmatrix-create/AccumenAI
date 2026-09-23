<?php

namespace Database\Seeders;

use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class PackageFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $featuresByModule = \App\Models\FeatureRegistry::where('status', 'active')
            ->get()
            ->groupBy('module_key');

        if ($featuresByModule->isEmpty()) {
            if ($this->command) {
                $this->command->warn('No active features found in feature_registry. Run FeatureRegistrySeeder first.');
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

            foreach ($featuresByModule as $moduleKey => $features) {
                $hasModule = \App\Models\PackageModule::where('package_id', $pkg->id)
                    ->where('module_key', $moduleKey)
                    ->where('enabled', true)
                    ->exists();

                if (! $hasModule) {
                    continue;
                }

                foreach ($features as $feature) {
                    $row = PackageFeature::updateOrCreate(
                        ['package_id' => $pkg->id, 'feature_key' => $feature->feature_key],
                        ['enabled' => true]
                    );
                    $row->wasRecentlyCreated ? $created++ : $updated++;
                }
            }
        }

        if ($this->command) {
            $this->command->info("Package features: {$created} created, {$updated} updated.");
        }
    }
}
