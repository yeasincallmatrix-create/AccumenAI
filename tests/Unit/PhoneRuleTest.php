<?php

namespace Tests\Unit;

use App\Rules\PhoneRule;
use PHPUnit\Framework\TestCase;

class PhoneRuleTest extends TestCase
{
    private function assertPasses(?string $country, string $value): void
    {
        $rule = new PhoneRule($country);
        $failed = false;
        $rule->validate('phone', $value, function () use (&$failed) { $failed = true; });
        $this->assertFalse($failed, "Expected passes for country={$country} value={$value}");
    }

    private function assertFails(?string $country, string $value): void
    {
        $rule = new PhoneRule($country);
        $failed = false;
        $msg = '';
        $rule->validate('phone', $value, function ($m) use (&$failed, &$msg) { $failed = true; $msg = $m; });
        $this->assertTrue($failed, "Expected fails for country={$country} value={$value} but passed. Msg: {$msg}");
    }

    public function test_bangladesh_valid(): void
    {
        $this->assertPasses('Bangladesh', '01786699448');
        $this->assertPasses('Bangladesh', '+8801786699448');
        $this->assertPasses('Bangladesh', '8801786699448');
        $this->assertPasses('Bangladesh', '017-866-99448');
    }

    public function test_bangladesh_invalid_too_short(): void
    {
        $this->assertFails('Bangladesh', '0178669944'); // 10 digits national expected 11
    }

    public function test_bangladesh_invalid_too_long(): void
    {
        $this->assertFails('Bangladesh', '017866994481'); // 12 digits
    }

    public function test_bangladesh_invalid_chars(): void
    {
        $this->assertFails('Bangladesh', '01786abc');
    }

    public function test_us_valid(): void
    {
        $this->assertPasses('United States', '5551234567');
        $this->assertPasses('United States', '+15551234567');
        $this->assertPasses('United States', '+1 555-123-4567');
    }

    public function test_us_invalid_short(): void
    {
        $this->assertFails('United States', '555123456'); // 9 digits expected 10
    }

    public function test_indonesia_range_valid(): void
    {
        $this->assertPasses('Indonesia', '0812345678'); // 10
        $this->assertPasses('Indonesia', '08123456789'); // 11
        $this->assertPasses('Indonesia', '081234567890'); // 12
    }

    public function test_indonesia_invalid_short(): void
    {
        $this->assertFails('Indonesia', '081234567'); // 9
    }

    public function test_indonesia_invalid_long(): void
    {
        $this->assertFails('Indonesia', '0812345678901'); // 13
    }

    public function test_fallback_unknown_country(): void
    {
        $this->assertPasses(null, '1234567'); // 7 fallback min
        $this->assertPasses(null, '123456789012'); // 12 fallback max
        $this->assertFails(null, '123456'); // 6 <7
        $this->assertFails(null, '1234567890123'); // 13 >12
    }

    public function test_empty_allowed(): void
    {
        $rule = new PhoneRule('Bangladesh');
        $failed = false;
        $rule->validate('phone', '', function () use (&$failed) { $failed = true; });
        $this->assertFalse($failed);
        $rule->validate('phone', null, function () use (&$failed) { $failed = true; });
        $this->assertFalse($failed);
    }

    public function test_australia_valid(): void
    {
        $this->assertPasses('Australia', '0412345678');
        $this->assertPasses('Australia', '+61412345678');
    }

    public function test_germany_range(): void
    {
        $this->assertPasses('Germany', '0151234567'); // 10
        $this->assertPasses('Germany', '01512345678'); // 11
        $this->assertFails('Germany', '015123456'); // 9
        $this->assertFails('Germany', '015123456789'); // 12
    }
}
