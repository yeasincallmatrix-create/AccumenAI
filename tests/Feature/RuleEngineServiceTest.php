<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\RuleEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 — RuleEngineService resolution priority:
 * tenant override > country > global > default.
 * Seeded rules: BD tax.vat.rate, BD medical.pharmacy.expiry_tracking,
 * BD education.fees.fiscal_year, global sales.invoice.auto_post,
 * global common.numbering.format.
 */
class RuleEngineServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RuleEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RuleEngineService::class);
    }

    private function makeInstitute(?string $country): Institute
    {
        $inst = new Institute();
        $inst->id = null;
        $inst->country_code = $country;

        return $inst;
    }

    private function savedInstitute(string $country): Institute
    {
        $suffix = substr(uniqid(), -8);
        $pkgId = DB::table('subscription_packages')->where('slug', 'basic')->value('id');

        $inst = Institute::withoutEvents(function () use ($suffix, $pkgId) {
            $inst = Institute::create([
                'name' => "Rule Test {$suffix}",
                'slug' => "rule-test-{$suffix}",
                'status' => 'active',
                'package_id' => $pkgId,
            ]);
            $inst->country_code = 'BD';
            $inst->save();

            return $inst;
        });

        return $inst;
    }

    public function test_country_rule_beats_global_for_bd(): void
    {
        $bd = $this->makeInstitute('BD');

        $value = $this->service->get('tax.vat', 'rate', $bd, null);

        $this->assertIsArray($value);
        $this->assertEqualsWithDelta(15.0, (float) ($value['rate'] ?? 0), 0.001);
    }

    public function test_global_rule_is_returned_when_no_country_row(): void
    {
        $us = $this->makeInstitute('US');

        $value = $this->service->get('sales.invoice', 'auto_post', $us, null);

        $this->assertIsArray($value);
        $this->assertTrue($value['enabled'] ?? false);
    }

    public function test_country_rule_not_applied_to_other_country(): void
    {
        // tax.vat.rate exists only for BD — US must fall through to default.
        $us = $this->makeInstitute('US');

        $this->assertSame('none', $this->service->get('tax.vat', 'rate', $us, 'none'));
    }

    public function test_default_fallback_for_unknown_rule(): void
    {
        $inst = $this->makeInstitute('BD');

        $this->assertSame('fb', $this->service->get('nope.module', 'nope_key', $inst, 'fb'));
    }

    public function test_null_default_when_rule_missing(): void
    {
        $inst = $this->makeInstitute('BD');

        $this->assertNull($this->service->get('nope.module', 'nope_key', $inst));
    }

    public function test_tenant_override_beats_country_rule(): void
    {
        $inst = $this->savedInstitute('BD');
        $this->service->setOverride($inst, 'tax.vat', 'rate', ['rate' => 20]);

        $inst->refresh();
        $value = $this->service->get('tax.vat', 'rate', $inst, null);

        $this->assertEqualsWithDelta(20.0, (float) ($value['rate'] ?? 0), 0.001);
    }

    public function test_override_uses_dotted_key_format(): void
    {
        $inst = $this->savedInstitute('BD');
        $this->service->setOverride($inst, 'tax.vat', 'rate', ['rate' => 20]);

        $inst->refresh();
        $decoded = is_string($inst->rule_overrides)
            ? json_decode($inst->rule_overrides, true)
            : $inst->rule_overrides;

        $this->assertArrayHasKey('tax.vat.rate', $decoded);
    }

    public function test_multiple_overrides_coexist(): void
    {
        $inst = $this->savedInstitute('BD');
        $this->service->setOverride($inst, 'tax.vat', 'rate', ['rate' => 20]);
        $this->service->setOverride($inst, 'sales.invoice', 'auto_post', ['enabled' => false]);

        $inst->refresh();
        $decoded = json_decode($inst->rule_overrides, true);

        $this->assertCount(2, $decoded);
        $this->assertFalse($decoded['sales.invoice.auto_post']['enabled']);
    }
}
