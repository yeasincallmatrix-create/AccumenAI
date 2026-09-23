<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\PackageFeature;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeatureRegistryPilotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_feature_registry_table_exists_with_medical_features(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry table does not exist — run migrations first.');
        }

        $count = FeatureRegistry::where('module_key', 'medical')->count();
        $this->assertGreaterThan(0, $count, 'No medical features seeded in feature_registry.');
    }

    public function test_only_medical_features_seeded(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry table does not exist.');
        }

        $medicalCount = FeatureRegistry::where('module_key', 'medical')->count();
        $this->assertEquals(12, $medicalCount, 'Expected exactly 12 medical features.');

        // Education / training_center features are co-seeded (multi-module
        // registry); only reject unknown module keys.
        $known = ['medical', 'education', 'training_center'];
        $unknown = FeatureRegistry::whereNotIn('module_key', $known)->count();
        $this->assertEquals(0, $unknown, 'Unknown-module features should not exist.');
    }

    public function test_feature_key_is_unique(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry table does not exist.');
        }

        $this->expectException(QueryException::class);

        FeatureRegistry::create([
            'feature_key' => 'medical.pharmacy',
            'module_key' => 'medical',
            'name' => 'Duplicate Pharmacy',
            'sort_order' => 999,
            'status' => 'active',
        ]);
    }

    public function test_package_features_mapping(): void
    {
        if (! Schema::hasTable('package_features') || ! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $packages = [
            'free' => 0,
            'basic' => 0,
            'advanced' => 12,
            'premium' => 12,
        ];

        foreach ($packages as $slug => $expectedCount) {
            $pkg = \App\Models\SubscriptionPackage::where('slug', $slug)->first();
            if (! $pkg) {
                continue;
            }

            $medicalFeatureCount = PackageFeature::where('package_id', $pkg->id)
                ->whereIn('feature_key', FeatureRegistry::where('module_key', 'medical')->pluck('feature_key'))
                ->where('enabled', true)
                ->count();

            $this->assertEquals(
                $expectedCount,
                $medicalFeatureCount,
                "Package '{$slug}' should have {$expectedCount} medical features, got {$medicalFeatureCount}."
            );
        }
    }

    public function test_seeder_idempotency(): void
    {
        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $featureCountBefore = FeatureRegistry::count();
        (new \Database\Seeders\FeatureRegistrySeeder)->run();
        $featureCountAfter = FeatureRegistry::count();
        $this->assertEquals($featureCountBefore, $featureCountAfter, 'FeatureRegistrySeeder is not idempotent.');

        $packageFeatureCountBefore = PackageFeature::count();
        (new \Database\Seeders\PackageFeatureSeeder)->run();
        $packageFeatureCountAfter = PackageFeature::count();
        $this->assertEquals($packageFeatureCountBefore, $packageFeatureCountAfter, 'PackageFeatureSeeder is not idempotent.');
    }
}
