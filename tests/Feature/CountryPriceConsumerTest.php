<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Pricing\CountryPriceService;
use App\Support\BranchContext;
use App\Support\CurrencyFormatter;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class CountryPriceConsumerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    private function svc(): CountryPriceService
    {
        return app(CountryPriceService::class);
    }

    private function makeInstitute(string $name, ?string $iso2 = null, ?string $countryName = null): Institute
    {
        $countryId = null;
        if ($iso2 !== null) {
            $countryId = Country::where('iso2', $iso2)->value('id');
        }
        $c = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        return Institute::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'country' => $countryName ?? $c->name,
            'country_id' => $countryId ?? $c->id,
            'industry' => 'retail',
            'sub_industry' => 'grocery',
            'status' => 'active',
            'verified' => true,
            'phone' => '017'.mt_rand(10000000, 99999999),
            'email' => uniqid().'@test.test',
            'address' => 'Test Address',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Dhanmondi',
            'postal_code' => '1209',
        ]);
    }

    private function makeOwner(Institute $inst): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => uniqid().'@test.test',
            'phone' => '017'.mt_rand(10000000, 99999999),
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ])->fresh();
    }

    public function test_resolve_country_returns_session_value_first()
    {
        $this->withSession([CountryPriceService::SESSION_KEY => 'IN']);

        $this->assertEquals('IN', $this->svc()->resolveCountry());
    }

    public function test_resolve_country_falls_back_to_bd_when_no_signal()
    {
        $this->assertEquals('BD', $this->svc()->resolveCountry());
    }

    public function test_set_signup_country_persists_in_session()
    {
        $inst = $this->makeInstitute('Price SET '.uniqid());
        $owner = $this->makeOwner($inst);

        $this->actingAs($owner, 'institute_user')
            ->post(route('saas.country'), ['country' => 'AE'])
            ->assertRedirect();

        $this->assertEquals('AE', session(CountryPriceService::SESSION_KEY));
        $this->assertEquals('AE', $this->svc()->resolveCountry());
    }

    public function test_set_signup_country_rejects_invalid_iso2()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->svc()->setSignupCountry('XX');
    }

    public function test_price_for_returns_localized_when_row_exists()
    {
        $row = DB::table('package_country_prices')
            ->where('country_code', 'IN')
            ->where('is_active', 1)
            ->first();

        $this->assertNotNull($row, 'seed must contain an IN row');

        $price = $this->svc()->priceFor((int) $row->package_id, 'IN');

        $this->assertEquals('INR', $price['currency']);
        $this->assertEquals((float) $row->price_monthly, $price['monthly']);
        $this->assertTrue($price['is_localized']);
        $this->assertEquals('IN', $price['country_code']);
    }

    public function test_price_for_falls_back_to_base_when_no_row()
    {
        $packageId = DB::table('subscription_packages')->where('status', 'active')->value('id');

        $price = $this->svc()->priceFor((int) $packageId, 'KE');

        $this->assertEquals('BDT', $price['currency']);
        $this->assertFalse($price['is_localized']);
        $this->assertEquals('BD', $price['country_code']);
    }

    public function test_price_for_marks_is_localized_correctly()
    {
        $packageId = DB::table('subscription_packages')->where('status', 'active')->value('id');

        $localized = $this->svc()->priceFor((int) $packageId, 'AE');
        $this->assertTrue($localized['is_localized']);
        $this->assertEquals('AE', $localized['country_code']);

        $base = $this->svc()->priceFor((int) $packageId, 'KE');
        $this->assertFalse($base['is_localized']);
    }

    public function test_price_list_for_avoids_n_plus_1()
    {
        $ids = DB::table('subscription_packages')->where('status', 'active')->limit(10)->pluck('id')->all();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $prices = $this->svc()->priceListFor($ids, 'IN');

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(count($ids), $prices);
        $this->assertLessThanOrEqual(2, $queries, "priceListFor must run ≤2 queries, ran {$queries}");
    }

    public function test_available_countries_returns_40_and_sorted()
    {
        $countries = $this->svc()->availableCountries();

        $this->assertCount(40, $countries);
        $this->assertEquals('BD', $countries[0]['country_code']);
        $this->assertEquals('SAARC', $countries[0]['region']);

        $order = ['SAARC' => 0, 'Gulf' => 1, 'SEA' => 2, 'First World' => 3, 'EU' => 4];
        $last = -1;
        foreach ($countries as $c) {
            $this->assertGreaterThanOrEqual($last, $order[$c['region']]);
            $last = $order[$c['region']];
        }
    }

    public function test_currency_formatter_bdt_uses_bangla_symbol()
    {
        $this->assertEquals('৳ 1,234.50', CurrencyFormatter::format(1234.5, 'BDT'));
        $this->assertEquals('₹ 1,234.50', CurrencyFormatter::format(1234.5, 'INR'));
        $this->assertEquals('$ 1,234.50', CurrencyFormatter::format(1234.5, 'USD'));
        $this->assertEquals('XX 1,234.50', CurrencyFormatter::format(1234.5, 'XX'));
    }

    public function test_signup_package_page_shows_localized_price_for_india()
    {
        $inst = $this->makeInstitute('Price IN '.uniqid(), 'IN', 'India');
        $owner = $this->makeOwner($inst);

        $page = $this->actingAs($owner, 'institute_user')
            ->get(route('saas.packages'))
            ->assertOk();

        $page->assertSee('Local price (IN)', false);
        $page->assertSee('₹', false);
    }

    public function test_signup_package_page_falls_back_to_bdt_for_unknown_country()
    {
        $inst = $this->makeInstitute('Price KE '.uniqid());
        $owner = $this->makeOwner($inst);

        $page = $this->actingAs($owner, 'institute_user')
            ->withSession([CountryPriceService::SESSION_KEY => 'KE'])
            ->get(route('saas.packages'))
            ->assertOk();

        // Invalid session country falls back to BD pricing (BDT symbol, no foreign currency).
        $page->assertSee('৳', false);
        $page->assertDontSee('₹', false);
    }
}
