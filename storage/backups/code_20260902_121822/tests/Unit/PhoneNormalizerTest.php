<?php

namespace Tests\Unit;

use App\Support\CountryCodes;
use App\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_bangladesh_length(): void
    {
        $this->assertSame([11, 11], CountryCodes::nationalLengthFor('Bangladesh'));
    }

    public function test_us_length(): void
    {
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('United States'));
    }

    public function test_indonesia_range(): void
    {
        $this->assertSame([10, 12], CountryCodes::nationalLengthFor('Indonesia'));
    }

    public function test_country_fallback(): void
    {
        $this->assertSame([7, 12], CountryCodes::nationalLengthFor('Neverland'));
        $this->assertSame([7, 12], CountryCodes::nationalLengthFor(null));
    }

    public function test_national_normalization_bangladesh(): void
    {
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('01786699448', 'Bangladesh'));
    }

    public function test_international_normalization_bangladesh(): void
    {
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('+8801786699448', 'Bangladesh'));
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('8801786699448', 'Bangladesh'));
    }

    public function test_formatted_number_normalization_bangladesh(): void
    {
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('017-866-99448', 'Bangladesh'));
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('017 866 99448', 'Bangladesh'));
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('(017)86699448', 'Bangladesh'));
        $this->assertSame('+8801786699448', PhoneNormalizer::toE164('+880 178-669-9448', 'Bangladesh'));
    }

    public function test_international_us(): void
    {
        $this->assertSame('+15551234567', PhoneNormalizer::toE164('5551234567', 'United States'));
        $this->assertSame('+15551234567', PhoneNormalizer::toE164('+15551234567', 'United States'));
        $this->assertSame('+15551234567', PhoneNormalizer::toE164('+1 555-123-4567', 'United States'));
    }

    public function test_strip_preserves_plus(): void
    {
        $this->assertSame('+8801786699448', PhoneNormalizer::strip('+880 178-669-9448'));
        $this->assertSame('01786699448', PhoneNormalizer::strip('017-866-99448'));
        $this->assertSame('+15551234567', PhoneNormalizer::strip('+1 (555) 123-4567'));
    }

    public function test_invalid_characters(): void
    {
        $this->assertTrue(PhoneNormalizer::hasInvalidCharacters('01786abc'));
        $this->assertFalse(PhoneNormalizer::hasInvalidCharacters('01786699448'));
        $this->assertFalse(PhoneNormalizer::hasInvalidCharacters('+8801786699448'));
        $this->assertFalse(PhoneNormalizer::hasInvalidCharacters('017-866-99448'));
        $this->assertTrue(PhoneNormalizer::hasInvalidCharacters('01786@99448'));
        $this->assertNull(PhoneNormalizer::toE164('01786abc', 'Bangladesh'));
    }

    public function test_national_part(): void
    {
        // BD national part without code is 10 digits subscriber (without trunk 0)
        $this->assertSame('1786699448', PhoneNormalizer::nationalPart('+8801786699448', 'Bangladesh'));
        $this->assertSame('1786699448', PhoneNormalizer::nationalPart('8801786699448', 'Bangladesh'));
        // For national input keep trunk
        $this->assertSame('01786699448', PhoneNormalizer::nationalPart('01786699448', 'Bangladesh'));
        // US
        $this->assertSame('5551234567', PhoneNormalizer::nationalPart('+15551234567', 'United States'));
        $this->assertSame('5551234567', PhoneNormalizer::nationalPart('5551234567', 'United States'));
    }

    public function test_classify(): void
    {
        $this->assertSame('NATIONAL_FORMAT', PhoneNormalizer::classify('01786699448', 'Bangladesh'));
        $this->assertSame('VALID_NORMALIZED', PhoneNormalizer::classify('+8801786699448', 'Bangladesh'));
        $this->assertSame('FORMATTED', PhoneNormalizer::classify('017-866-99448', 'Bangladesh'));
        $this->assertSame('INTERNATIONAL_FORMAT', PhoneNormalizer::classify('8801786699448', 'Bangladesh'));
        $this->assertSame('INVALID', PhoneNormalizer::classify('01786abc', 'Bangladesh'));
        $this->assertSame('EMPTY', PhoneNormalizer::classify('', 'Bangladesh'));
        $this->assertSame('EMPTY', PhoneNormalizer::classify(null, 'Bangladesh'));
    }

    public function test_legacy_values(): void
    {
        // Legacy national without code should still normalize
        $this->assertSame('+8801700380200', PhoneNormalizer::toE164('01700380200', 'Bangladesh'));
        // Already normalized stays same
        $this->assertSame('+8801700380200', PhoneNormalizer::toE164('+8801700380200', 'Bangladesh'));
        // Invalid legacy stays invalid (null)
        $this->assertNull(PhoneNormalizer::toE164('ff', 'Bangladesh'));
        // Short values still normalize (validation will fail length, not normalizer)
        $this->assertSame('+88012', PhoneNormalizer::toE164('12', 'Bangladesh'));
    }

    public function test_australia_trunk_handling(): void
    {
        // Australia 04XX XXX XXX (10 digits) -> +61 4XX XXX XXX (drop 0)
        $this->assertSame('+61412345678', PhoneNormalizer::toE164('0412345678', 'Australia'));
        $this->assertSame('+61412345678', PhoneNormalizer::toE164('+61412345678', 'Australia'));
    }

    public function test_indonesia_varying_lengths(): void
    {
        // Indonesia 10-12 digits valid
        $this->assertSame('+628123456789', PhoneNormalizer::toE164('08123456789', 'Indonesia'));
        $this->assertSame('+6281234567890', PhoneNormalizer::toE164('081234567890', 'Indonesia'));
    }

    public function test_germany_range(): void
    {
        $this->assertSame([10, 11], CountryCodes::nationalLengthFor('Germany'));
    }

    public function test_uae_and_saudi(): void
    {
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('United Arab Emirates'));
        $this->assertSame([10, 10], CountryCodes::nationalLengthFor('Saudi Arabia'));
    }
}
