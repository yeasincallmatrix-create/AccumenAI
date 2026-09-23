<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('package_features')) {
            return;
        }

        $medicalFeatures = DB::table('feature_registry')
            ->where('module_key', 'medical')
            ->where('status', 'active')
            ->pluck('feature_key')
            ->all();

        if (empty($medicalFeatures)) {
            return;
        }

        $packageSlugs = ['free', 'basic', 'advanced', 'premium'];

        foreach ($packageSlugs as $slug) {
            $pkg = DB::table('subscription_packages')->where('slug', $slug)->first();
            if (! $pkg) {
                continue;
            }

            $hasMedical = DB::table('package_modules')
                ->where('package_id', $pkg->id)
                ->where('module_key', 'medical')
                ->where('enabled', true)
                ->exists();

            if (! $hasMedical) {
                continue;
            }

            foreach ($medicalFeatures as $featureKey) {
                DB::table('package_features')->updateOrInsert(
                    ['package_id' => $pkg->id, 'feature_key' => $featureKey],
                    ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Data backfill only — intentionally a no-op.
    }
};
