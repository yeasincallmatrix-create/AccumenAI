<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Institute;
use App\Models\SubscriptionPackage;
use App\Services\Accounting\AccountingSetupService;
use App\Services\CurrencyService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\CountryCodes;
use App\Support\CountryConfigResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9b-5a: multi-country verification matrix (BD + IN).
 *
 * End-to-end pins for the Phase 9b-1–9b-4 stack: resolver chain,
 * config fallbacks, env overrides, BD-only bKash gating, rename
 * resistance, and the B112 null-country contract.
 *
 * US coverage is deferred to 9b-5b (no US country seed row).
 * Uses existing seed rows only — nothing is seeded here.
 */
class MultiCountryVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private function bdId(): int
    {
        return (int) Country::where('iso2', 'BD')->firstOrFail()->id;
    }

    private function inId(): int
    {
        return (int) Country::where('iso2', 'IN')->firstOrFail()->id;
    }

    private function institute(?int $countryId, string $countryName = 'Bangladesh', ?int $packageId = null): Institute
    {
        config(['testing.auto_premium' => false]);

        return Institute::create([
            'name' => 'MultiCntry '.uniqid(),
            'slug' => 'multicntry-'.uniqid(),
            'status' => 'active',
            'package_id' => $packageId,
            'industry' => 'retail',
            'country' => $countryName,
            'country_id' => $countryId,
        ]);
    }

    private function resolver(): CountryConfigResolver
    {
        return app(CountryConfigResolver::class);
    }

    // -----------------------------------------------------------------
    // A) Phone (4)
    // -----------------------------------------------------------------

    public function test_BD_institute_resolves_phone_880(): void
    {
        $inst = $this->institute($this->bdId(), 'Bangladesh');

        $this->assertSame('880', $this->resolver()->resolve($inst, 'phone.default_country_code'));
    }

    public function test_IN_institute_resolves_phone_91(): void
    {
        $inst = $this->institute($this->inId(), 'India');

        $this->assertSame('91', $this->resolver()->resolve($inst, 'phone.default_country_code'));
    }

    public function test_null_country_falls_back_to_880(): void
    {
        $inst = $this->institute(null, '');

        $this->assertSame('880', $this->resolver()->resolve($inst, 'phone.default_country_code'));
    }

    public function test_phone_env_override_changes_default(): void
    {
        config(['locale.phone.default_country_code' => '1']);

        $this->assertSame('1', CountryCodes::codeFor(null));

        $inst = $this->institute(null, '');
        $this->assertSame('1', $this->resolver()->resolve($inst, 'phone.default_country_code'));

        config(['locale.phone.default_country_code' => '880']);
    }

    // -----------------------------------------------------------------
    // B) Currency (4)
    // -----------------------------------------------------------------

    public function test_BD_institute_resolves_BDT(): void
    {
        $inst = $this->institute($this->bdId(), 'Bangladesh');

        $this->assertSame('BDT', $this->resolver()->resolve($inst, 'currency.default_code'));
        $this->assertSame('BDT', app(CurrencyService::class)->detectCurrencyForCountry('BD'));
    }

    public function test_IN_institute_resolves_INR(): void
    {
        $inst = $this->institute($this->inId(), 'India');

        $this->assertSame('INR', $this->resolver()->resolve($inst, 'currency.default_code'));
        $this->assertSame('INR', app(CurrencyService::class)->detectCurrencyForCountry('IN'));
    }

    public function test_null_country_accounting_falls_back_to_USD(): void
    {
        // B112 contract: country-less institutes stay on USD.
        $inst = $this->institute(null, '');

        app(AccountingSetupService::class)->setupForInstitute($inst->id);

        $this->assertSame(
            'USD',
            app(AccountingSetupService::class)->getSetting($inst->id, 'base_currency')
        );
    }

    public function test_currency_env_override_changes_default(): void
    {
        config(['locale.currency.default_code' => 'EUR']);

        $this->assertSame('EUR', app(CurrencyService::class)->detectCurrencyForCountry('XX'));

        $inst = $this->institute(null, '');
        $this->assertSame('EUR', $this->resolver()->resolve($inst, 'currency.default_code'));

        config(['locale.currency.default_code' => 'BDT']);
    }

    // -----------------------------------------------------------------
    // C) Timezone (2)
    // -----------------------------------------------------------------

    public function test_default_timezone_is_Asia_Dhaka(): void
    {
        $this->assertSame('Asia/Dhaka', config('locale.date.timezone'));
        $this->assertSame('Asia/Dhaka', PlatformSettingsService::generalDefaults()['app.timezone']);
    }

    public function test_timezone_env_override(): void
    {
        config(['locale.date.timezone' => 'UTC']);

        $this->assertSame('UTC', config('locale.date.timezone'));
        $this->assertSame('UTC', PlatformSettingsService::generalDefaults()['app.timezone']);

        config(['locale.date.timezone' => 'Asia/Dhaka']);
    }

    // -----------------------------------------------------------------
    // D) Date format (2)
    // -----------------------------------------------------------------

    public function test_default_date_format_dmy(): void
    {
        $this->assertSame('dmy', config('locale.date.default_format_key'));
        $this->assertSame('dmy', mawa_date_format_key());
    }

    public function test_date_format_env_override(): void
    {
        // The runtime helper is Settings-driven; the env key governs
        // the config-layer default it falls back to.
        config(['locale.date.default_format_key' => 'mdy']);

        $this->assertSame('mdy', config('locale.date.default_format_key'));

        config(['locale.date.default_format_key' => 'dmy']);
    }

    // -----------------------------------------------------------------
    // E) bKash gating (3)
    // -----------------------------------------------------------------

    private function checkoutInstitute(string $countryName, ?int $countryId): Institute
    {
        \App\Support\TenantContext::clear();
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug)=?', ['free'])->firstOrFail();

        return Institute::create([
            'name' => 'MultiBkash '.uniqid(),
            'slug' => 'multibkash-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => $countryName,
            'country_id' => $countryId,
        ]);
    }

    private function ownerOf(Institute $inst)
    {
        $role = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();

        return \App\Models\InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'multibkash-'.uniqid().'@test.local',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    public function test_bkash_gating_BD_only(): void
    {
        $inst = $this->checkoutInstitute('Bangladesh', $this->bdId());
        $u = $this->ownerOf($inst);
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug)=?', ['basic'])->firstOrFail();

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertRedirect();
    }

    public function test_bkash_rejected_for_IN(): void
    {
        $inst = $this->checkoutInstitute('India', $this->inId());
        $u = $this->ownerOf($inst);
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug)=?', ['basic'])->firstOrFail();

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertSessionHasErrors('country');
    }

    public function test_bkash_legacy_name_fallback(): void
    {
        // String-only institute (no FK): legacy name path still allows BD.
        $inst = $this->checkoutInstitute('Bangladesh', null);
        $u = $this->ownerOf($inst);
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug)=?', ['basic'])->firstOrFail();

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertRedirect();
    }

    // -----------------------------------------------------------------
    // F) Rename resistance (3)
    // -----------------------------------------------------------------

    public function test_rename_bd_country_name_still_resolves_phone(): void
    {
        DB::table('countries')->where('id', $this->bdId())->update(['name' => 'Bangla (Renamed)']);

        $inst = $this->institute($this->bdId(), 'Bangla (Renamed)');

        $this->assertSame('880', $this->resolver()->resolve($inst, 'phone.default_country_code'));
    }

    public function test_rename_bd_country_name_still_resolves_currency(): void
    {
        DB::table('countries')->where('id', $this->bdId())->update(['name' => 'Bangla (Renamed)']);

        $inst = $this->institute($this->bdId(), 'Bangla (Renamed)');

        $this->assertSame('BDT', $this->resolver()->resolve($inst, 'currency.default_code'));
    }

    public function test_rename_bd_country_name_still_gates_bkash(): void
    {
        DB::table('countries')->where('id', $this->bdId())->update(['name' => 'Bangla (Renamed)']);

        $inst = $this->checkoutInstitute('Bangla (Renamed)', $this->bdId());
        $u = $this->ownerOf($inst);
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug)=?', ['basic'])->firstOrFail();

        $this->actingAs($u, 'institute_user')
            ->post(route('saas.checkout'), ['package_id' => $pkg->id, 'billing_cycle' => 'monthly'])
            ->assertRedirect();
    }

    // -----------------------------------------------------------------
    // G) Resolver (3)
    // -----------------------------------------------------------------

    public function test_country_config_resolver_is_stateless(): void
    {
        $inst = $this->institute($this->bdId(), 'Bangladesh');

        $first = $this->resolver()->resolve($inst, 'currency.default_code');

        $this->assertSame($first, $this->resolver()->resolve($inst, 'currency.default_code'));
        $this->assertSame($first, (new CountryConfigResolver)->resolve($inst, 'currency.default_code'));
    }

    public function test_country_config_resolver_resolves_BD_and_IN(): void
    {
        $bd = $this->institute($this->bdId(), 'Bangladesh');
        $in = $this->institute($this->inId(), 'India');

        $this->assertSame('880', $this->resolver()->resolve($bd, 'phone.default_country_code'));
        $this->assertSame('91', $this->resolver()->resolve($in, 'phone.default_country_code'));
        $this->assertSame('BDT', $this->resolver()->resolve($bd, 'currency.default_code'));
        $this->assertSame('INR', $this->resolver()->resolve($in, 'currency.default_code'));
    }

    public function test_is_country_returns_correct_bool(): void
    {
        $bd = $this->institute($this->bdId(), 'Bangladesh');
        $in = $this->institute($this->inId(), 'India');
        $none = $this->institute(null, '');

        $this->assertTrue($this->resolver()->isCountry($bd, 'BD'));
        $this->assertFalse($this->resolver()->isCountry($bd, 'IN'));
        $this->assertTrue($this->resolver()->isCountry($in, 'IN'));
        $this->assertFalse($this->resolver()->isCountry($in, 'BD'));
        $this->assertFalse($this->resolver()->isCountry($none, 'BD'));
    }
}
