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

        // All active packages (legacy free/basic/advanced/premium AND
        // industry starter/growth/enterprise tiers). A feature is enabled
        // for a package when its parent module is enabled for that package
        // (legacy package_modules, backfilled from package_industry_modules
        // + core for industry tiers).
        $packageSlugs = \App\Models\SubscriptionPackage::where('status', 'active')
            ->pluck('slug')
            ->all();

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
