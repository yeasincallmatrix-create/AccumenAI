<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomePricingCardsTest extends TestCase
{
    public function test_home_shows_industry_pricing_cards_without_free_plan(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Simple, transparent pricing', false)
            ->assertSee('Medical Starter', false)
            ->assertSee('Medical Enterprise', false)
            ->assertSee('৳', false)
            ->assertDontSee('FREE</h3>', false);
    }

    public function test_bangladesh_visitor_sees_taka_prices(): void
    {
        $page = $this->get('/', ['CF-IPCountry' => 'BD']);

        $page->assertOk()
            ->assertSessionHas('visitor_country', 'BD')
            ->assertSee($this->priceLabel('BD', 'medical_starter'), false)
            ->assertDontSee($this->priceLabel('US', 'medical_starter'), false);
    }

    public function test_foreign_visitor_sees_global_us_dollar_prices(): void
    {
        $page = $this->get('/', ['CF-IPCountry' => 'US']);

        $page->assertOk()
            ->assertSessionHas('visitor_country', 'US')
            ->assertSee($this->priceLabel('US', 'medical_starter'), false)
            ->assertDontSee($this->priceLabel('BD', 'medical_starter'), false);
    }

    public function test_unknown_header_country_falls_back_to_bd(): void
    {
        $this->get('/', ['CF-IPCountry' => 'XX'])
            ->assertOk()
            ->assertSessionHas('visitor_country', 'BD');
    }

    public function test_global_usd_prices_are_realistic(): void
    {
        $rows = DB::table('package_country_prices')
            ->where('country_code', 'US')
            ->get(['package_id', 'price_monthly', 'price_yearly']);

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertLessThanOrEqual(
                199,
                (float) $row->price_monthly,
                "US monthly price for package {$row->package_id} is not realistic."
            );
            $this->assertLessThanOrEqual(
                1990,
                (float) $row->price_yearly,
                "US yearly price for package {$row->package_id} is not realistic."
            );
        }
    }

    /**
     * Price label exactly as the landing page renders it (৳1,500 / $29).
     */
    private function priceLabel(string $country, string $slug): string
    {
        $packageId = DB::table('subscription_packages')->where('slug', $slug)->value('id');
        $price = (float) DB::table('package_country_prices')
            ->where('package_id', $packageId)
            ->where('country_code', $country)
            ->value('price_monthly');

        $symbol = $country === 'BD' ? '৳' : '$';

        return $symbol.number_format($price, 0);
    }
}
