<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\TerminologyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 — TerminologyService resolution priority:
 * tenant override > country > global > fallback.
 */
class TerminologyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TerminologyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TerminologyService::class);
    }

    private function makeInstitute(?string $country): Institute
    {
        $inst = new Institute();
        $inst->id = null;
        $inst->country_code = $country;

        return $inst;
    }

    public function test_global_term_is_returned_for_foreign_country(): void
    {
        $us = $this->makeInstitute('US');

        $global = DB::table('module_terminology')
            ->whereNull('country_code')
            ->where('term_key', 'tax.vat')
            ->value('term_value');

        $this->assertSame($global, $this->service->get('tax.vat', $us, 'VAT'));
    }

    public function test_country_term_beats_global(): void
    {
        $bd = $this->makeInstitute('BD');

        $bdValue = DB::table('module_terminology')
            ->where('country_code', 'BD')
            ->where('term_key', 'medical.opd')
            ->value('term_value');
        $globalValue = DB::table('module_terminology')
            ->whereNull('country_code')
            ->where('term_key', 'medical.opd')
            ->value('term_value');

        $this->assertNotNull($bdValue, 'BD medical.opd row must exist');
        $this->assertNotSame($globalValue, $bdValue, 'BD wording must differ from global');
        $this->assertSame($bdValue, $this->service->get('medical.opd', $bd, 'OPD'));
    }

    public function test_tenant_override_beats_country_and_global(): void
    {
        $inst = $this->savedInstitute('BD');
        $this->service->setOverride($inst, 'medical.opd', 'Consultation Room');

        $inst->refresh();
        $this->assertSame('Consultation Room', $this->service->get('medical.opd', $inst, 'OPD'));
    }

    public function test_default_fallback_for_unknown_key(): void
    {
        $inst = $this->makeInstitute('BD');

        $this->assertSame('Fallback', $this->service->get('unknown.term', $inst, 'Fallback'));
    }

    public function test_term_key_returned_when_no_default_given(): void
    {
        $inst = $this->makeInstitute('BD');

        $this->assertSame('unknown.term', $this->service->get('unknown.term', $inst));
    }

    public function test_remove_override_restores_upstream_wording(): void
    {
        $inst = $this->savedInstitute('BD');
        $upstream = $this->service->get('common.customer', $inst, 'Customer');

        $this->service->setOverride($inst, 'common.customer', 'Client');
        $inst->refresh();
        $this->assertSame('Client', $this->service->get('common.customer', $inst, 'Customer'));

        $this->service->removeOverride($inst, 'common.customer');
        $inst->refresh();
        $this->assertSame($upstream, $this->service->get('common.customer', $inst, 'Customer'));
    }

    public function test_get_all_for_country_bd_returns_three_terms(): void
    {
        $terms = $this->service->getAllForCountry('BD');

        $this->assertCount(3, $terms, 'Phase 3 seeded 3 BD terms (vat, opd, ipd)');
        $this->assertArrayHasKey('tax.vat', $terms);
        $this->assertArrayHasKey('medical.opd', $terms);
        $this->assertArrayHasKey('medical.ipd', $terms);
    }

    public function test_override_persists_as_json_string(): void
    {
        $inst = $this->savedInstitute('BD');
        $this->service->setOverride($inst, 'common.invoice', 'Bill');

        $inst->refresh();
        $this->assertIsString($inst->terminology_overrides);

        $decoded = json_decode($inst->terminology_overrides, true);
        $this->assertSame('Bill', $decoded['common.invoice'] ?? null);
    }

    private function savedInstitute(string $country): Institute
    {
        $suffix = substr(uniqid(), -8);
        $pkgId = DB::table('subscription_packages')->where('slug', 'basic')->value('id');

        $inst = Institute::withoutEvents(function () use ($suffix, $pkgId) {
            $inst = Institute::create([
                'name' => "Term Test {$suffix}",
                'slug' => "term-test-{$suffix}",
                'status' => 'active',
                'package_id' => $pkgId,
            ]);
            $inst->country_code = 'BD';
            $inst->save();

            return $inst;
        });

        return $inst;
    }
}
