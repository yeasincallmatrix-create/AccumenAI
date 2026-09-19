<?php

namespace Tests\Feature\LabIntegration;

use App\Services\LabIntegration\UnitConverter;
use Tests\TestCase;

class UnitConverterTest extends TestCase
{
    public function test_no_conversion_when_units_match(): void
    {
        $result = UnitConverter::convert(7.2, '10^3/uL', '10^3/uL', 2.5);

        $this->assertSame(7.2, $result['value']);
        $this->assertFalse($result['converted']);
    }

    public function test_converts_with_factor(): void
    {
        $result = UnitConverter::convert(100, 'mg/dL', 'g/L', 0.01);

        $this->assertEqualsWithDelta(1.0, $result['value'], 0.0001);
        $this->assertSame('g/L', $result['unit']);
        $this->assertTrue($result['converted']);
    }

    public function test_returns_warning_when_no_factor(): void
    {
        $result = UnitConverter::convert(7.2, 'K/uL', '10^3/uL');

        $this->assertFalse($result['converted']);
        $this->assertArrayHasKey('warning', $result);
        $this->assertSame(7.2, $result['value']);
    }

    public function test_non_numeric_value_returns_original(): void
    {
        $result = UnitConverter::convert('Positive', 'mg/dL', 'g/L', 0.01);

        $this->assertSame('Positive', $result['value']);
        $this->assertFalse($result['converted']);
        $this->assertSame('non-numeric value', $result['warning']);
    }

    public function test_canonical_unit_lookup(): void
    {
        $this->assertSame('10^3/uL', UnitConverter::canonicalUnit('WBC'));
        $this->assertSame('g/dL', UnitConverter::canonicalUnit('HGB'));
        $this->assertNull(UnitConverter::canonicalUnit('NOPE_UNKNOWN'));
    }
}
