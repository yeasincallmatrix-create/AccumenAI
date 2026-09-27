<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageCountryPriceAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const SAARC = ['BD', 'IN', 'PK', 'LK', 'NP', 'BT', 'MV'];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_country_prices')) {
            $this->markTestSkipped('package_country_prices table does not exist.');
        }
    }

    public function test_index_renders_country_selector(): void
    {
        $this->loginAsAdmin();

        $page = $this->get(route('admin.package-industries.index'));

        $page->assertOk();
        $page->assertSee('Country');
        $page->assertSee('Industry default (BDT)');

        foreach (self::SAARC as $iso) {
            $page->assertSee($iso, false);
        }
    }

    public function test_index_country_mode_shows_currency_and_save_button(): void
    {
        $this->loginAsAdmin();

        $page = $this->get(route('admin.package-industries.index', ['industry' => 'retail', 'country' => 'IN']));

        $page->assertOk();
        $page->assertSee('INR');
        $page->assertSee('Save INR prices');
        $page->assertSee('Price / month');
    }

    public function test_index_ignores_unknown_country(): void
    {
        $this->loginAsAdmin();

        $page = $this->get(route('admin.package-industries.index', ['country' => 'XX']));

        $page->assertOk();
        $page->assertSee('Industry default (BDT)');
        $page->assertDontSee('id="saveCountryPrices"', false);
    }

    public function test_save_endpoint_upserts_country_price(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::orderBy('id')->firstOrFail();

        DB::table('package_country_prices')
            ->where('package_id', $package->id)
            ->where('country_code', 'IN')
            ->delete();

        $response = $this->postJson(route('admin.package-industries.country-prices.save'), [
            'country' => 'IN',
            'prices' => [
                ['package_id' => $package->id, 'monthly' => 999.55, 'yearly' => 9999.99],
            ],
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'country' => 'IN',
            'currency' => 'INR',
        ]);

        $row = DB::table('package_country_prices')
            ->where('package_id', $package->id)
            ->where('country_code', 'IN')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('INR', $row->currency_code);
        $this->assertEquals(999.55, (float) $row->price_monthly);
        $this->assertEquals(9999.99, (float) $row->price_yearly);
        $this->assertEquals(1, (int) $row->is_active);
    }

    public function test_save_endpoint_rejects_unknown_country(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::orderBy('id')->firstOrFail();

        $this->postJson(route('admin.package-industries.country-prices.save'), [
            'country' => 'XX',
            'prices' => [['package_id' => $package->id, 'monthly' => 1, 'yearly' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('country');
    }

    public function test_country_save_does_not_touch_industry_override(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->first()
            ?: SubscriptionPackage::orderBy('id')->firstOrFail();

        DB::table('package_industries')->where('industry_key', 'retail')->delete();
        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'retail',
            'is_active' => true,
            'sort_order' => 0,
            'price_monthly' => 4321.00,
            'price_yearly' => 43210.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('admin.package-industries.country-prices.save'), [
            'country' => 'NP',
            'prices' => [['package_id' => $package->id, 'monthly' => 10, 'yearly' => 100]],
        ])->assertOk();

        $override = DB::table('package_industries')
            ->where('package_id', $package->id)
            ->where('industry_key', 'retail')
            ->first();

        $this->assertNotNull($override);
        $this->assertEquals(4321.00, (float) $override->price_monthly);
        $this->assertEquals(43210.00, (float) $override->price_yearly);

        $countryRow = DB::table('package_country_prices')
            ->where('package_id', $package->id)
            ->where('country_code', 'NP')
            ->first();

        $this->assertNotNull($countryRow);
        $this->assertEquals(10.00, (float) $countryRow->price_monthly);
    }

    public function test_country_mode_keeps_industry_override_fields_in_form(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::orderBy('id')->firstOrFail();

        DB::table('package_industries')->where('industry_key', 'retail')->delete();
        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'retail',
            'is_active' => true,
            'sort_order' => 0,
            'price_monthly' => 5555.00,
            'price_yearly' => 55550.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $page = $this->get(route('admin.package-industries.index', [
            'industry' => 'retail',
            'country' => 'LK',
        ]));

        $page->assertOk();
        // The industry override still rides along in the big PUT form.
        $page->assertSee('name="price_monthly['.$package->id.']"', false);
        $page->assertSee('value="5555.00"', false);
        // Country price inputs are AJAX-only, so they must NOT share that name.
        $page->assertSee('data-field="monthly"', false);
    }

    public function test_save_endpoint_requires_admin(): void
    {
        $package = SubscriptionPackage::orderBy('id')->firstOrFail();

        $response = $this->postJson(route('admin.package-industries.country-prices.save'), [
            'country' => 'BD',
            'prices' => [['package_id' => $package->id, 'monthly' => 1, 'yearly' => 1]],
        ]);

        $this->assertContains(
            $response->status(),
            [401, 403],
            'guest must not reach the country price endpoint'
        );
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Country',
            'last_name' => 'Pricing',
            'email' => 'country-pricing-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }
}
