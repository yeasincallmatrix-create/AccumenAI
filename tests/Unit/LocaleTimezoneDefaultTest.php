<?php

namespace Tests\Unit;

use App\Services\Platform\PlatformSettingsService;
use Tests\TestCase;

/**
 * Phase 9b-3: timezone/country platform defaults come from
 * config/locale.php. Defaults identical when env is unset.
 */
class LocaleTimezoneDefaultTest extends TestCase
{
    public function test_platform_defaults_match_legacy_values(): void
    {
        $defaults = PlatformSettingsService::generalDefaults();

        $this->assertSame('Asia/Dhaka', $defaults['app.timezone']);
        $this->assertSame('BD', $defaults['app.country']);
        $this->assertSame('BDT', $defaults['app.currency']);
    }

    public function test_platform_defaults_float_with_config(): void
    {
        config([
            'locale.date.timezone' => 'UTC',
            'locale.country.default_iso2' => 'US',
            'locale.currency.default_code' => 'USD',
        ]);

        $defaults = PlatformSettingsService::generalDefaults();

        config([
            'locale.date.timezone' => 'Asia/Dhaka',
            'locale.country.default_iso2' => 'BD',
            'locale.currency.default_code' => 'BDT',
        ]);

        $this->assertSame('UTC', $defaults['app.timezone']);
        $this->assertSame('US', $defaults['app.country']);
        $this->assertSame('USD', $defaults['app.currency']);
    }

    public function test_locale_config_defaults_loaded(): void
    {
        $this->assertSame('Asia/Dhaka', config('locale.date.timezone'));
        $this->assertSame('BD', config('locale.country.default_iso2'));
        $this->assertSame('Bangladesh', config('locale.country.default_name'));
        $this->assertSame('dmy', config('locale.date.default_format_key'));
    }
}
