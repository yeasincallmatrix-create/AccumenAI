<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 9b-4: Class D helpers are deprecated but behavior-identical.
 * No helper is removed; signatures and return types are frozen.
 * (Deprecation is docblock-only — no E_USER_DEPRECATED is triggered,
 * so views calling these helpers stay noise-free.)
 */
class LegacyHelpersDeprecationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mawa_is_bangladesh_still_works_but_deprecated(): void
    {
        $this->assertTrue(mawa_is_bangladesh('Bangladesh'));
        $this->assertFalse(mawa_is_bangladesh('India'));
        // No tenant in test context: null resolves to non-Bangladesh.
        $this->assertFalse(mawa_is_bangladesh(null));
    }

    public function test_mawa_currency_symbol_accepts_name_and_iso2(): void
    {
        $taka = "\xE0\xA7\xB3"; // ৳
        $rupee = "\xE2\x82\xB9"; // ₹

        $this->assertSame($taka, mawa_currency_symbol('Bangladesh'));
        $this->assertSame($taka, mawa_currency_symbol('BD'));
        $this->assertSame($rupee, mawa_currency_symbol('India'));
        $this->assertSame($taka, mawa_currency_symbol('Neverland'));
    }

    public function test_mawa_date_format_uses_config_default(): void
    {
        $this->assertSame('dmy', config('locale.date.default_format_key'));
        $this->assertSame('d/m/Y', mawa_date_format());
        // Settings path wins while the column exists: output is
        // country-independent (legacy branch only runs without it).
        $this->assertSame(mawa_date_format(), mawa_date_format('Bangladesh'));
        $this->assertSame(mawa_date_format(), mawa_date_format('India'));
    }

    public function test_mawa_datetime_format_uses_config_default(): void
    {
        $this->assertSame('d/m/Y h:i A', mawa_datetime_format());
        $this->assertSame(mawa_datetime_format(), mawa_datetime_format('India'));
    }

    public function test_mawa_date_format_key_unchanged(): void
    {
        // The surviving pattern: Settings-driven, untouched by 9b-4.
        $this->assertSame('dmy', mawa_date_format_key());
    }

    public function test_mawa_tenant_country_still_returns_name_or_null(): void
    {
        $country = mawa_tenant_country();

        $this->assertTrue($country === null || is_string($country));
    }
}
