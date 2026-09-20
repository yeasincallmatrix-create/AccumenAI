<?php

namespace Tests\Unit;

use App\Support\CountryCodes;
use App\Support\PhoneNormalizer;
use Tests\TestCase;

/**
 * Phase 9b-3: phone defaults come from config/locale.php.
 * Defaults identical when env is unset; override floats behavior.
 */
class LocalePhoneDefaultTest extends TestCase
{
    public function test_phone_code_fallback_default(): void
    {
        $this->assertSame('880', CountryCodes::codeFor(null));
        $this->assertSame('880', CountryCodes::codeFor('Neverland'));
        $this->assertSame('880', CountryCodes::codeForIso2('ZZ'));
    }

    public function test_phone_code_override_changes_fallback(): void
    {
        config(['locale.phone.default_country_code' => '1']);

        $this->assertSame('1', CountryCodes::codeFor(null));
        $this->assertSame('1', CountryCodes::codeForIso2('ZZ'));

        config(['locale.phone.default_country_code' => '880']);
    }

    public function test_normalizer_uses_config_fallback(): void
    {
        // Known country path unchanged.
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('01786699448', 'Bangladesh'));

        // Unknown country floats with the config default.
        config(['locale.phone.default_country_code' => '1']);
        $result = PhoneNormalizer::toE164('01786699448', 'Neverland');
        config(['locale.phone.default_country_code' => '880']);

        $this->assertNotNull($result);
        $this->assertTrue(str_starts_with($result, '+1'));
    }
}
