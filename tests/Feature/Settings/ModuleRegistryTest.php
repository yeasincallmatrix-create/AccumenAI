<?php

namespace Tests\Feature\Settings;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_vat_module_registered(): void
    {
        $this->assertDatabaseHas('module_registry', [
            'key' => 'vat',
            'type' => 'core',
            'status' => 'active',
        ]);
    }

    public function test_tds_module_registered(): void
    {
        $this->assertDatabaseHas('module_registry', [
            'key' => 'tds',
            'type' => 'core',
            'status' => 'active',
        ]);
    }

    public function test_vat_in_basic_package(): void
    {
        $basic = DB::table('subscription_packages')->where('slug', 'basic')->first();
        if (! $basic) {
            $this->markTestSkipped('basic package not seeded');
        }

        $this->assertDatabaseHas('package_modules', [
            'package_id' => $basic->id,
            'module_key' => 'vat',
            'enabled' => true,
        ]);
    }

    public function test_tds_in_basic_package(): void
    {
        $basic = DB::table('subscription_packages')->where('slug', 'basic')->first();
        if (! $basic) {
            $this->markTestSkipped('basic package not seeded');
        }

        $this->assertDatabaseHas('package_modules', [
            'package_id' => $basic->id,
            'module_key' => 'tds',
            'enabled' => true,
        ]);
    }

    public function test_vat_in_advanced_package(): void
    {
        $advanced = DB::table('subscription_packages')->where('slug', 'advanced')->first();
        if (! $advanced) {
            $this->markTestSkipped('advanced package not seeded');
        }

        $this->assertDatabaseHas('package_modules', [
            'package_id' => $advanced->id,
            'module_key' => 'vat',
            'enabled' => true,
        ]);
    }

    public function test_tds_in_advanced_package(): void
    {
        $advanced = DB::table('subscription_packages')->where('slug', 'advanced')->first();
        if (! $advanced) {
            $this->markTestSkipped('advanced package not seeded');
        }

        $this->assertDatabaseHas('package_modules', [
            'package_id' => $advanced->id,
            'module_key' => 'tds',
            'enabled' => true,
        ]);
    }

    public function test_vat_in_premium_package(): void
    {
        $premium = DB::table('subscription_packages')->where('slug', 'premium')->first();
        if (! $premium) {
            $this->markTestSkipped('premium package not seeded');
        }

        $this->assertDatabaseHas('package_modules', [
            'package_id' => $premium->id,
            'module_key' => 'vat',
            'enabled' => true,
        ]);
    }

    public function test_tds_in_premium_package(): void
    {
        $premium = DB::table('subscription_packages')->where('slug', 'premium')->first();
        if (! $premium) {
            $this->markTestSkipped('premium package not seeded');
        }

        $this->assertDatabaseHas('package_modules', [
            'package_id' => $premium->id,
            'module_key' => 'tds',
            'enabled' => true,
        ]);
    }

    public function test_vat_not_in_free_package(): void
    {
        $free = DB::table('subscription_packages')->where('slug', 'free')->first();
        if (! $free) {
            $this->markTestSkipped('free package not seeded');
        }

        $this->assertDatabaseMissing('package_modules', [
            'package_id' => $free->id,
            'module_key' => 'vat',
        ]);
    }

    public function test_tds_not_in_free_package(): void
    {
        $free = DB::table('subscription_packages')->where('slug', 'free')->first();
        if (! $free) {
            $this->markTestSkipped('free package not seeded');
        }

        $this->assertDatabaseMissing('package_modules', [
            'package_id' => $free->id,
            'module_key' => 'tds',
        ]);
    }
}
