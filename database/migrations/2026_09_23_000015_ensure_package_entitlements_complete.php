<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCorePackageModules();
        $this->ensurePackageFeaturesForAllModules();
    }

    /**
     * Parent/core package_modules rows expected by ModuleRegistrySeeder
     * but missing on this database (silent gaps from partial backfills).
     */
    private function ensureCorePackageModules(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_modules')
            || ! DB::getSchemaBuilder()->hasTable('subscription_packages')) {
            return;
        }

        $expected = [
            'free'     => ['notifications'],
            'basic'    => ['finance', 'reports', 'notifications', 'education', 'vat', 'tds'],
            'advanced' => ['finance', 'accounting', 'reports', 'notifications', 'ai', 'education', 'sales', 'medical', 'training_center', 'vat', 'tds'],
            'premium'  => ['finance', 'accounting', 'inventory', 'hr', 'reports', 'notifications', 'ai', 'education', 'sales', 'purchase', 'medical', 'training_center', 'vat', 'tds'],
        ];

        foreach ($expected as $slug => $moduleKeys) {
            $pkg = DB::table('subscription_packages')->where('slug', $slug)->first();
            if (! $pkg) {
                continue;
            }

            foreach ($moduleKeys as $key) {
                DB::table('package_modules')->updateOrInsert(
                    ['package_id' => $pkg->id, 'module_key' => $key],
                    ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    /**
     * package_features for EVERY feature_registry key whose parent module
     * is enabled on the package (not just medical — education/training too).
     */
    private function ensurePackageFeaturesForAllModules(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_features')
            || ! DB::getSchemaBuilder()->hasTable('feature_registry')
            || ! DB::getSchemaBuilder()->hasTable('package_modules')) {
            return;
        }

        $featuresByModule = DB::table('feature_registry')
            ->where('status', 'active')
            ->get()
            ->groupBy('module_key');

        foreach ($featuresByModule as $moduleKey => $features) {
            $packageIds = DB::table('package_modules')
                ->where('module_key', $moduleKey)
                ->where('enabled', true)
                ->pluck('package_id');

            foreach ($packageIds as $packageId) {
                foreach ($features as $feature) {
                    DB::table('package_features')->updateOrInsert(
                        ['package_id' => $packageId, 'feature_key' => $feature->feature_key],
                        ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Data backfill only — intentionally a no-op.
    }
};
