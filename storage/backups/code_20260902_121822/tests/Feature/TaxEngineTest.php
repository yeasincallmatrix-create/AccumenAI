<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\JournalEntry;
use App\Models\TaxAuditLog;
use App\Models\TaxJurisdiction;
use App\Models\TaxRate;
use App\Models\TaxRateHistory;
use App\Models\TaxReturnLine;
use App\Models\TaxReturnPeriod;
use App\Models\TaxRule;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Tax\TaxAccountingService;
use App\Services\Tax\TaxCalculationService;
use App\Services\Tax\TaxComplianceService;
use App\Services\Tax\TaxEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaxEngineTest extends TestCase
{
    use DatabaseTransactions;

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $industry = 'education'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => 'Tax Inst',
            'slug' => str()->slug('Tax Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => $industry,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute): Branch
    {
        return Branch::create(['institute_id' => $institute->id, 'name' => 'Main', 'status' => 'active']);
    }

    private function coaId(Institute $institute, string $code, ?int $branchId = null): int
    {
        return (int) app(ChartOfAccountService::class)->accountByCode($institute->id, $code, $branchId)->id;
    }

    private function ledgerBalance(Institute $institute, string $code, ?int $branchId = null): float
    {
        $coaId = $this->coaId($institute, $code, $branchId);

        return round(
            JournalEntry::query()
                ->where('institute_id', $institute->id)
                ->where('coa_id', $coaId)
                ->where(fn ($q) => $branchId === null
                    ? $q->whereNull('branch_id')
                    : $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->get()
                ->sum(fn ($e) => (float) $e->debit - (float) $e->credit),
            4
        );
    }

    public function test_jurisdiction_crud(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);

        $compliance = app(TaxComplianceService::class);
        $jurisdiction = $compliance->createJurisdiction($institute->id, $branch->id, [
            'name' => 'Dhaka Division',
            'code' => 'DHK',
            'country_iso2' => 'BD',
            'is_active' => true,
        ]);

        $this->assertSame('Dhaka Division', $jurisdiction->name);
        $this->assertSame('BD', $jurisdiction->country_iso2);
        $this->assertTrue((bool) $jurisdiction->is_active);
    }

    public function test_rate_crud_and_history(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);

        $compliance = app(TaxComplianceService::class);
        $rate = $compliance->createRate($institute->id, $branch->id, [
            'name' => 'Standard VAT',
            'type' => 'vat',
            'rate_type' => 'percentage',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);

        $this->assertSame(15.0, (float) $rate->rate);

        $rate = $compliance->updateRate($rate, ['rate' => 17.5], null);

        $this->assertSame(17.5, (float) $rate->rate);

        $history = TaxRateHistory::where('tax_rate_id', $rate->id)->first();
        $this->assertNotNull($history);
        $this->assertSame(15.0, (float) $history->old_rate);
        $this->assertSame(17.5, (float) $history->new_rate);
    }

    public function test_engine_resolves_active_rates(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $compliance->createRate($institute->id, null, [
            'name' => 'VAT',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);

        $engine = app(TaxEngine::class);
        $rates = $engine->resolveRates($institute->id, null);

        $this->assertGreaterThanOrEqual(1, $rates->count());
        $this->assertSame('vat', $rates->first()->type);
    }

    public function test_engine_excludes_expired_rates(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $compliance->createRate($institute->id, null, [
            'name' => 'Old VAT',
            'type' => 'vat',
            'rate' => 10.0,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);

        $engine = app(TaxEngine::class);
        $rates = $engine->resolveRates($institute->id, null, null, ['date' => '2026-06-01']);

        $this->assertCount(0, $rates);
    }

    public function test_calculation_percentage_exclusive(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $rate = $compliance->createRate($institute->id, null, [
            'name' => 'VAT 15%',
            'type' => 'vat',
            'rate_type' => 'percentage',
            'rate' => 15.0,
            'is_inclusive' => false,
            'effective_from' => '2026-01-01',
        ]);

        $calc = app(TaxCalculationService::class);
        $result = $calc->calculateLineTax(1000.0, $rate);

        $this->assertEqualsWithDelta(150.0, $result['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, $result['net_amount'], 0.0001);
        $this->assertEqualsWithDelta(1150.0, $result['gross_amount'], 0.0001);
    }

    public function test_calculation_percentage_inclusive(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $rate = $compliance->createRate($institute->id, null, [
            'name' => 'VAT 15% inc',
            'type' => 'vat',
            'rate_type' => 'percentage',
            'rate' => 15.0,
            'is_inclusive' => true,
            'effective_from' => '2026-01-01',
        ]);

        $calc = app(TaxCalculationService::class);
        $result = $calc->calculateLineTax(1150.0, $rate);

        $this->assertEqualsWithDelta(150.0, $result['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, $result['net_amount'], 0.0001);
        $this->assertEqualsWithDelta(1150.0, $result['gross_amount'], 0.0001);
    }

    public function test_calculation_fixed_rate(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $rate = $compliance->createRate($institute->id, null, [
            'name' => 'Excise BDT 50',
            'type' => 'excise',
            'rate_type' => 'fixed',
            'rate' => 50.0,
            'effective_from' => '2026-01-01',
        ]);

        $calc = app(TaxCalculationService::class);
        $result = $calc->calculateLineTax(1000.0, $rate);

        $this->assertEqualsWithDelta(50.0, $result['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, $result['net_amount'], 0.0001);
        $this->assertEqualsWithDelta(1050.0, $result['gross_amount'], 0.0001);
    }

    public function test_compound_tax(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $compliance->createRate($institute->id, null, [
            'name' => 'VAT',
            'type' => 'vat',
            'rate_type' => 'percentage',
            'rate' => 15.0,
            'is_compound' => true,
            'effective_from' => '2026-01-01',
        ]);

        $compliance->createRate($institute->id, null, [
            'name' => 'Excise',
            'type' => 'excise',
            'rate_type' => 'percentage',
            'rate' => 10.0,
            'is_compound' => false,
            'effective_from' => '2026-01-01',
        ]);

        $calc = app(TaxCalculationService::class);
        $engine = app(TaxEngine::class);
        $rates = $engine->resolveRates($institute->id, null);

        $result = $calc->calculateItemTax(1000.0, $rates);

        $this->assertGreaterThan(0, $result['total_tax']);
        $this->assertCount(2, $result['items']);
    }

    public function test_rule_filters_rates(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $vatRate = $compliance->createRate($institute->id, null, [
            'name' => 'Standard VAT',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);

        $whRate = $compliance->createRate($institute->id, null, [
            'name' => 'WHT 10%',
            'type' => 'withholding',
            'rate' => 10.0,
            'effective_from' => '2026-01-01',
        ]);

        TaxRule::create([
            'institute_id' => $institute->id,
            'tax_rate_id' => $whRate->id,
            'item_type' => 'services',
            'product_category' => '*',
        ]);

        $engine = app(TaxEngine::class);

        $servicesRates = $engine->resolveRates($institute->id, null, null, ['item_type' => 'services']);
        $this->assertGreaterThanOrEqual(1, $servicesRates->count());

        $goodsRates = $engine->resolveRates($institute->id, null, null, ['item_type' => 'goods']);
        $whForGoods = $goodsRates->filter(fn ($r) => $r->type === 'withholding');
        $this->assertCount(0, $whForGoods);
    }

    public function test_accounting_posts_tax_journal(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $accounting = app(TaxAccountingService::class);
        $journal = $accounting->salesTaxJournal($institute->id, $branch->id, 150.0, null, '2026-06-01');

        $this->assertNotNull($journal);

        $vatPayableBalance = $this->ledgerBalance($institute, '2100', $branch->id);
        $this->assertEqualsWithDelta(-150.0, $vatPayableBalance, 0.0001);

        $clearingBalance = $this->ledgerBalance($institute, '2102', $branch->id);
        $this->assertEqualsWithDelta(150.0, $clearingBalance, 0.0001);
    }

    public function test_accounting_posts_input_tax(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $accounting = app(TaxAccountingService::class);
        $journal = $accounting->purchaseTaxJournal($institute->id, $branch->id, 200.0, null, '2026-06-01');

        $this->assertNotNull($journal);

        $inputVatBalance = $this->ledgerBalance($institute, '1201', $branch->id);
        $this->assertEqualsWithDelta(200.0, $inputVatBalance, 0.0001);

        $clearingBalance = $this->ledgerBalance($institute, '2102', $branch->id);
        $this->assertEqualsWithDelta(-200.0, $clearingBalance, 0.0001);
    }

    public function test_accounting_posts_withholding_tax(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $accounting = app(TaxAccountingService::class);
        $journal = $accounting->withholdingTaxJournal($institute->id, $branch->id, 75.0, null, '2026-06-01');

        $this->assertNotNull($journal);
    }

    public function test_country_config_returns_bd(): void
    {
        $config = app(TaxComplianceService::class)->countryConfig('BD');

        $this->assertNotNull($config);
        $this->assertSame(15.0, $config['vat_rate']);
        $this->assertArrayHasKey('accounts', $config);
        $this->assertSame('2100', $config['accounts']['output']);
        $this->assertSame('1201', $config['accounts']['input']);
    }

    public function test_return_lifecycle(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);

        $compliance = app(TaxComplianceService::class);
        $return = $compliance->createReturn($institute->id, $branch->id, [
            'name' => 'June 2026 VAT',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'due_date' => '2026-07-15',
            'status' => 'open',
        ]);

        $this->assertSame('open', $return->status);

        $return = $compliance->computeReturn($return, $institute->id, $branch->id);
        $this->assertNotNull($return);

        $return = $compliance->fileReturn($return);
        $this->assertSame('filed', $return->status);
    }

    public function test_cannot_file_closed_return(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);

        $compliance = app(TaxComplianceService::class);
        $return = $compliance->createReturn($institute->id, $branch->id, [
            'name' => 'May 2026 VAT',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'due_date' => '2026-06-15',
            'status' => 'open',
        ]);

        $compliance->fileReturn($return);

        try {
            $compliance->fileReturn($return);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only open returns can be filed', $e->getMessage());
        }
    }

    public function test_audit_logs_created(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);

        $compliance = app(TaxComplianceService::class);
        $rate = $compliance->createRate($institute->id, $branch->id, [
            'name' => 'Audit Rate',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);

        $compliance->updateRate($rate, ['rate' => 16.0]);

        $logs = TaxAuditLog::where('institute_id', $institute->id)->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        $this->assertContains('rate_created', $logs->pluck('event')->all());
        $this->assertContains('rate_updated', $logs->pluck('event')->all());
    }

    public function test_tenant_isolation(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $rateA = $compliance->createRate($instA->id, null, [
            'name' => 'Rate A',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);

        $engine = app(TaxEngine::class);
        $ratesB = $engine->resolveRates($instB->id, null);
        $this->assertCount(0, $ratesB);

        $ratesA = $engine->resolveRates($instA->id, null);
        $this->assertGreaterThanOrEqual(1, $ratesA->count());
    }

    public function test_rates_summary(): void
    {
        $institute = $this->institute();

        $compliance = app(TaxComplianceService::class);
        $compliance->createRate($institute->id, null, [
            'name' => 'VAT',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);
        $compliance->createRate($institute->id, null, [
            'name' => 'WHT',
            'type' => 'withholding',
            'rate' => 10.0,
            'effective_from' => '2026-01-01',
        ]);

        $summary = $compliance->ratesSummary($institute->id, null);
        $this->assertCount(2, $summary);

        $vatOnly = $compliance->ratesSummary($institute->id, null, 'vat');
        $this->assertCount(1, $vatOnly);
    }

    public function test_tax_permissions_granted_by_role(): void
    {
        $permissionIds = \App\Models\Permission::query()->where('module', 'tax')->pluck('id');
        $this->assertCount(5, $permissionIds);

        $owner = \App\Models\Role::query()->where('slug', 'institute-owner')->firstOrFail();
        $receptionist = \App\Models\Role::query()->where('slug', 'receptionist')->firstOrFail();

        $granted = fn (\App\Models\Role $role) => \Illuminate\Support\Facades\DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $permissionIds)
            ->count();

        $this->assertSame(5, $granted($owner));

        $receptionistSlugs = \App\Models\Permission::query()
            ->whereIn('id', \Illuminate\Support\Facades\DB::table('role_permissions')
                ->where('role_id', $receptionist->id)
                ->whereIn('permission_id', $permissionIds)
                ->pluck('permission_id'))
            ->pluck('slug')
            ->all();
        $this->assertSame(['tax.view'], $receptionistSlugs);
    }

    public function test_calculate_items_tax_bulk(): void
    {
        $institute = $this->institute();
        $compliance = app(TaxComplianceService::class);

        $compliance->createRate($institute->id, null, [
            'name' => 'VAT',
            'type' => 'vat',
            'rate_type' => 'percentage',
            'rate' => 15.0,
            'is_inclusive' => false,
            'effective_from' => '2026-01-01',
        ]);

        $calc = app(TaxCalculationService::class);
        $result = $calc->calculateItemsTax($institute->id, null, [
            ['amount' => 1000, 'item_type' => 'goods'],
            ['amount' => 2000, 'item_type' => 'services'],
        ]);

        $this->assertEqualsWithDelta(450.0, $result['total_tax'], 0.0001);
        $this->assertEqualsWithDelta(3000.0, $result['total_net'], 0.0001);
        $this->assertEqualsWithDelta(3450.0, $result['total_gross'], 0.0001);
        $this->assertCount(2, $result['lines']);
    }

    public function test_clear_input_tax_account(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $accounting = app(TaxAccountingService::class);
        $accounting->purchaseTaxJournal($institute->id, $branch->id, 200.0, null, '2026-06-01');

        $before = $this->ledgerBalance($institute, '1201', $branch->id);
        $this->assertEqualsWithDelta(200.0, $before, 0.0001);

        $accounting->clearTaxAccount($institute->id, $branch->id, 200.0, true, '2026-06-15');

        $after = $this->ledgerBalance($institute, '1201', $branch->id);
        $this->assertEqualsWithDelta(0.0, $after, 0.0001);
    }

    public function test_clear_output_tax_account(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $accounting = app(TaxAccountingService::class);
        $accounting->salesTaxJournal($institute->id, $branch->id, 150.0, null, '2026-06-01');

        $before = $this->ledgerBalance($institute, '2100', $branch->id);
        $this->assertEqualsWithDelta(-150.0, $before, 0.0001);

        $accounting->clearTaxAccount($institute->id, $branch->id, 150.0, false, '2026-06-15');

        $after = $this->ledgerBalance($institute, '2100', $branch->id);
        $this->assertEqualsWithDelta(0.0, $after, 0.0001);
    }

    public function test_branch_scoped_rates(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute);
        $branchB = Branch::create(['institute_id' => $institute->id, 'name' => 'Branch B', 'status' => 'active']);

        $compliance = app(TaxComplianceService::class);
        $compliance->createRate($institute->id, $branchA->id, [
            'name' => 'Branch A VAT',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
        ]);

        $engine = app(TaxEngine::class);
        $ratesA = $engine->resolveRates($institute->id, $branchA->id);
        $ratesB = $engine->resolveRates($institute->id, $branchB->id);

        $this->assertGreaterThanOrEqual(1, $ratesA->filter(fn ($r) => $r->name === 'Branch A VAT')->count());
        $this->assertCount(0, $ratesB->filter(fn ($r) => $r->name === 'Branch A VAT'));
    }

    public function test_jurisdiction_scoped_rates(): void
    {
        $institute = $this->institute();
        $compliance = app(TaxComplianceService::class);

        $jurisdiction = $compliance->createJurisdiction($institute->id, null, [
            'name' => 'Dhaka',
            'code' => 'DHK',
            'country_iso2' => 'BD',
            'is_active' => true,
        ]);

        $rate = $compliance->createRate($institute->id, null, [
            'name' => 'Dhaka VAT',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
            'jurisdiction_id' => $jurisdiction->id,
        ]);

        $engine = app(TaxEngine::class);
        $ratesWithJurisdiction = $engine->resolveRates($institute->id, null, $jurisdiction->id);
        $ratesWithoutJurisdiction = $engine->resolveRates($institute->id, null);

        $this->assertGreaterThanOrEqual(1, $ratesWithJurisdiction->filter(fn ($r) => $r->id === $rate->id)->count());
        $this->assertCount(0, $ratesWithoutJurisdiction->filter(fn ($r) => $r->id === $rate->id));
    }

    public function test_rate_at_effective_to_boundary(): void
    {
        $institute = $this->institute();
        $compliance = app(TaxComplianceService::class);

        $compliance->createRate($institute->id, null, [
            'name' => 'Boundary Rate',
            'type' => 'vat',
            'rate' => 15.0,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);

        $engine = app(TaxEngine::class);
        $ratesOnBoundary = $engine->resolveRates($institute->id, null, null, ['date' => '2026-06-30']);
        $ratesAfterBoundary = $engine->resolveRates($institute->id, null, null, ['date' => '2026-07-01']);

        $this->assertGreaterThanOrEqual(1, $ratesOnBoundary->count());
        $this->assertCount(0, $ratesAfterBoundary);
    }

    public function test_inactive_rates_excluded(): void
    {
        $institute = $this->institute();
        $compliance = app(TaxComplianceService::class);

        $compliance->createRate($institute->id, null, [
            'name' => 'Inactive Rate',
            'type' => 'vat',
            'rate' => 25.0,
            'is_active' => false,
            'effective_from' => '2026-01-01',
        ]);

        $engine = app(TaxEngine::class);
        $rates = $engine->resolveRates($institute->id, null);

        $this->assertCount(0, $rates->filter(fn ($r) => $r->name === 'Inactive Rate'));
    }

    public function test_jurisdiction_for_country(): void
    {
        $institute = $this->institute();
        $compliance = app(TaxComplianceService::class);

        $compliance->createJurisdiction($institute->id, null, [
            'name' => 'Bangladesh',
            'code' => 'BGD',
            'country_iso2' => 'BD',
            'is_active' => true,
        ]);

        $engine = app(TaxEngine::class);
        $found = $engine->jurisdictionForCountry($institute->id, null, 'BD');
        $notFound = $engine->jurisdictionForCountry($institute->id, null, 'IN');

        $this->assertNotNull($found);
        $this->assertSame('BD', $found->country_iso2);
        $this->assertNull($notFound);
    }

    public function test_tax_accounting_throws_on_missing_accounts(): void
    {
        $institute = $this->institute();
        $accounting = app(TaxAccountingService::class);

        $this->expectException(\InvalidArgumentException::class);
        $accounting->salesTaxJournal($institute->id, null, 100.0);
    }
}
