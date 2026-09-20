<?php

namespace Tests\Unit;

use App\Models\Institute;
use App\Models\TenantCurrencySetting;
use App\Services\CurrencyService;
use App\Services\Sales\SalesSettingsService;
use App\Services\ModuleAccessService;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 9b-3: currency defaults come from config/locale.php.
 * Defaults identical when env is unset; override floats behavior.
 */
class LocaleCurrencyDefaultTest extends TestCase
{
    use DatabaseTransactions;

    private function institute(): Institute
    {
        return Institute::create([
            'name' => 'Locale Cur '.uniqid(),
            'slug' => 'locale-cur-'.uniqid(),
            'status' => 'active',
            'industry' => 'retail',
            'country' => 'Bangladesh',
        ]);
    }

    public function test_currency_map_fallback_default(): void
    {
        $this->assertSame('BDT', app(CurrencyService::class)->detectCurrencyForCountry('XX'));
    }

    public function test_currency_map_fallback_override(): void
    {
        config(['locale.currency.default_code' => 'EUR']);

        $this->assertSame('EUR', app(CurrencyService::class)->detectCurrencyForCountry('XX'));

        config(['locale.currency.default_code' => 'BDT']);
    }

    public function test_tenant_currency_default_and_override(): void
    {
        $inst = $this->institute();

        $defaults = TenantCurrencySetting::defaultsForTenant($inst->id);
        $this->assertSame('BDT', $defaults['base_currency']);

        config(['locale.currency.default_code' => 'EUR']);
        $overridden = TenantCurrencySetting::defaultsForTenant($inst->id);
        config(['locale.currency.default_code' => 'BDT']);

        $this->assertSame('EUR', $overridden['base_currency']);
    }

    public function test_sales_default_currency_and_override(): void
    {
        $inst = $this->institute();

        $this->assertSame('BDT', app(SalesSettingsService::class)->get($inst->id)['default_currency']);

        config(['locale.currency.default_code' => 'EUR']);
        $overridden = app(SalesSettingsService::class)->get($inst->id)['default_currency'];
        config(['locale.currency.default_code' => 'BDT']);

        $this->assertSame('EUR', $overridden);
    }

    public function test_scoped_price_currency_fallback_and_override(): void
    {
        // Fresh package with no scopes: legacy path + config fallback.
        $pkg = SubscriptionPackage::create([
            'slug' => 'localecur-'.uniqid(),
            'name' => 'LocaleCur '.uniqid(),
            'price_monthly' => 100,
            'price_yearly' => 1000,
            'status' => 'active',
            'is_default' => false,
        ]);
        $inst = Institute::withoutEvents(fn () => Institute::create([
            'name' => 'Locale Cur '.uniqid(),
            'slug' => 'locale-cur-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'healthcare',
            'country' => 'Bangladesh',
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
        ]));

        $price = app(ModuleAccessService::class)->resolveScopedPrice($inst);
        $this->assertSame('BDT', $price['currency']);

        config(['locale.currency.default_code' => 'EUR']);
        $overridden = app(ModuleAccessService::class)->resolveScopedPrice($inst);
        config(['locale.currency.default_code' => 'BDT']);

        $this->assertSame('EUR', $overridden['currency']);
    }
}
