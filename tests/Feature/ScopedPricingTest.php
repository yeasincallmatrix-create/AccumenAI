<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PackageScope;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Concerns\ResolvesTestIds;

class ScopedPricingTest extends TestCase
{
    use DatabaseTransactions;
    use ResolvesTestIds;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes') || ! Schema::hasTable('subscription_packages')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);
    }

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::firstOrCreate(
            ['slug' => "pricing-{$slug}-" . uniqid()],
            [
                'name' => "Pricing {$slug} " . uniqid(),
                'price_monthly' => 100.00,
                'price_yearly' => 1000.00,
                'status' => 'active',
                'is_default' => false,
            ]
        );
    }

    public function test_scope_price_used_when_set(): void
    {
        $pkg = $this->package('direct');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'price_monthly' => 50.00,
            'price_yearly' => 500.00,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $this->assertEquals(50.0, $scope->effectiveMonthlyPrice());
        $this->assertEquals(500.0, $scope->effectiveYearlyPrice());
        $this->assertEquals('USD', $scope->effectiveCurrency());
    }

    public function test_scope_falls_back_to_parent_price(): void
    {
        $pkg = $this->package('parent');

        $parentScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'price_monthly' => 75.00,
            'price_yearly' => 750.00,
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => $this->educationIndustryId(),
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);

        $this->assertEquals(75.0, $childScope->effectiveMonthlyPrice());
        $this->assertEquals(750.0, $childScope->effectiveYearlyPrice());
        $this->assertEquals('EUR', $childScope->effectiveCurrency());
    }

    public function test_scope_falls_back_to_package_price(): void
    {
        $pkg = $this->package('pkg');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);

        $this->assertEquals(100.0, $scope->effectiveMonthlyPrice());
        $this->assertEquals(1000.0, $scope->effectiveYearlyPrice());
    }

    public function test_currency_inherits_from_parent(): void
    {
        $pkg = $this->package('curr');

        $parentScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'currency' => 'MYR',
            'status' => 'active',
        ]);

        $childScope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'industry_id' => $this->educationIndustryId(),
            'inherit_from_parent' => true,
            'status' => 'active',
        ]);

        $this->assertEquals('MYR', $childScope->effectiveCurrency());
    }

    public function test_resolve_scoped_price_returns_full_array(): void
    {
        $pkg = $this->package('array');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
            'price_monthly' => 200.00,
            'price_yearly' => 2000.00,
            'currency' => 'INR',
            'status' => 'active',
        ]);

        $inst = Institute::create([
            'name' => 'Scoped Price Array Test ' . uniqid(),
            'slug' => 'scoped-price-array-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'country_id' => $this->bdCountryId(),
        ]);

        $price = $this->service->resolveScopedPrice($inst);

        $this->assertIsArray($price);
        $this->assertArrayHasKey('monthly', $price);
        $this->assertArrayHasKey('yearly', $price);
        $this->assertArrayHasKey('currency', $price);
        $this->assertEquals(200.0, $price['monthly']);
        $this->assertEquals(2000.0, $price['yearly']);
        $this->assertEquals('INR', $price['currency']);
    }

    public function test_resolve_scoped_price_handles_null_package(): void
    {
        $inst = Institute::withoutEvents(function () {
            return Institute::create([
                'name' => 'Scoped Price Null Test ' . uniqid(),
                'slug' => 'scoped-price-null-' . uniqid(),
                'status' => 'active',
                'package_id' => null,
            ]);
        });

        $price = $this->service->resolveScopedPrice($inst);

        $this->assertIsArray($price);
        $this->assertEquals(0, $price['monthly']);
        $this->assertEquals(0, $price['yearly']);
        $this->assertEquals('BDT', $price['currency']);
    }

    public function test_effective_price_string_format(): void
    {
        $pkg = $this->package('fmt');
        $scope = PackageScope::create([
            'package_id' => $pkg->id,
            'price_monthly' => 50.00,
            'price_yearly' => 500.00,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $priceStr = $scope->effectivePriceString();

        $this->assertStringContainsString('50.00', $priceStr);
        $this->assertStringContainsString('USD', $priceStr);
        $this->assertStringContainsString('500.00', $priceStr);
    }
}
