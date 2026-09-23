<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class PackageSubModuleMappingSeeder extends Seeder
{
    /**
     * Map sales/purchase sub-modules onto packages that include the parent module.
     * Parent entitlements (from ModuleRegistrySeeder):
     *   sales → advanced + premium; purchase → premium only.
     */
    public function run(): void
    {
        $parentMap = [
            'sales'    => ['advanced', 'premium'],
            'purchase' => ['premium'],
        ];

        foreach ($parentMap as $parentKey => $packageSlugs) {
            $subKeys = ModuleRegistry::where('parent_key', $parentKey)->pluck('key')->all();

            foreach ($packageSlugs as $slug) {
                $pkg = SubscriptionPackage::where('slug', $slug)->first();
                if (! $pkg) {
                    continue;
                }

                PackageModule::updateOrCreate(
                    ['package_id' => $pkg->id, 'module_key' => $parentKey],
                    ['enabled' => true]
                );

                foreach ($subKeys as $subKey) {
                    PackageModule::updateOrCreate(
                        ['package_id' => $pkg->id, 'module_key' => $subKey],
                        ['enabled' => true]
                    );
                }
            }
        }

        if ($this->command) {
            $this->command->info('Sales/purchase sub-module package mapping applied.');
        }
    }
}
