<?php

namespace Tests\Unit;

use App\Support\CountryCodes;
use PHPUnit\Framework\TestCase;

class CountryCodesTest extends TestCase
{
    public function test_known_country_returns_curated_example(): void
    {
        $this->assertSame('017XXXXXXXX', CountryCodes::phoneExampleFor('Bangladesh'));
        $this->assertSame('3XX XXX XXXX', CountryCodes::phoneExampleFor('Italy'));
    }

    public function test_unlisted_country_derives_from_dial_code(): void
    {
        $this->assertSame('355 XXX XXXXX', CountryCodes::phoneExampleFor('Albania'));
    }

    public function test_null_country_defaults_to_bangladesh_example(): void
    {
        $this->assertSame('017XXXXXXXX', CountryCodes::phoneExampleFor(null));
    }

    public function test_examples_match_their_dial_codes(): void
    {
        foreach (CountryCodes::PHONE_EXAMPLES as $country => $example) {
            $this->assertArrayHasKey($country, CountryCodes::CODES, "Missing dial code for {$country}");
        }
    }
}