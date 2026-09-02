<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Institute;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\PartyService;
use App\Services\FixedAsset\FixedAssetAccountingService;
use App\Services\FixedAsset\FixedAssetReconciliationService;
use App\Services\FixedAsset\FixedAssetService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 17 — Fixed asset accounting integration. Capitalization, depreciation,
 * disposal gain/loss, impairment and revaluation all post through the existing
 * double-entry engine and reconcile to the subledger.
 */
class FixedAssetAccountingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

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
            'name' => 'FA Acc',
            'slug' => str()->slug('FA Acc-'.uniqid()),
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

    private function supplier(Institute $institute, ?Branch $branch): Party
    {
        return app(PartyService::class)->create($institute->id, $branch?->id, [
            'type' => 'supplier',
            'name' => 'Vendor Co',
        ]);
    }

    private function coaId(Institute $institute, string $code, ?int $branchId = null): int
    {
        return (int) app(ChartOfAccountService::class)->accountByCode($institute->id, $code, $branchId)->id;
    }

    private function ledgerBalance(Institute $institute, string $code, ?int $branchId = null): float
    {
        $coaId = $this->coaId($institute, $code, $branchId);

        $rows = JournalEntry::query()
            ->where('institute_id', $institute->id)
            ->where('coa_id', $coaId)
            ->where(fn ($q) => $branchId === null
                ? $q->whereNull('branch_id')
                : $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->get();

        return round($rows->sum(fn ($e) => (float) $e->debit - (float) $e->credit), 4);
    }

    public function test_capitalization_posts_asset_against_payable(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $supplier = $this->supplier($institute, $branch);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Delivery Van',
            'acquisition_cost' => 1000000,
            'residual_value' => 100000,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
        ]);

        $asset = $service->capitalize($asset, $supplier->id);

        $this->assertSame('active', $asset->status);
        $this->assertSame(1000000.0, $this->ledgerBalance($institute, '1300', $branch->id));
        $this->assertSame(-1000000.0, $this->ledgerBalance($institute, '2001', $branch->id));
    }

    public function test_depreciation_posts_and_accumulates(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Computer',
            'acquisition_cost' => 12000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'depreciation_start_date' => '2026-01-01',
        ]);
        $asset = $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $run = $service->runDepreciation($institute->id, $branch->id, '2026-01-01', '2026-01-31');

        $this->assertNotNull($run);
        $asset->refresh();
        $this->assertEqualsWithDelta(1000.0, (float) $asset->accumulated_depreciation, 0.0001);
        $this->assertEqualsWithDelta(11000.0, $asset->netBookValue(), 0.0001);
        $this->assertSame(1000.0, $this->ledgerBalance($institute, '5010', $branch->id));
        $this->assertSame(-1000.0, $this->ledgerBalance($institute, '1301', $branch->id));
    }

    public function test_duplicate_depreciation_for_same_period_is_rejected(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Printer',
            'acquisition_cost' => 6000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'depreciation_start_date' => '2026-02-01',
        ]);
        $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $service->runDepreciation($institute->id, $branch->id, '2026-02-01', '2026-02-28');

        $this->expectException(ValidationException::class);
        $service->runDepreciation($institute->id, $branch->id, '2026-02-01', '2026-02-28');
    }

    public function test_disposal_with_gain_and_loss(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);

        $gainAsset = $service->create($institute->id, $branch->id, [
            'name' => 'Gain Machine',
            'acquisition_cost' => 1000,
            'residual_value' => 0,
            'useful_life_months' => 10,
            'depreciation_method' => 'straight_line',
        ]);
        $gainAsset = $service->capitalize($gainAsset, null, null, ['paid_immediately' => true]);
        $gainAsset->forceFill(['accumulated_depreciation' => 700])->save();

        $disposal = $service->dispose($gainAsset, 'sale', 350, 'sold');

        $this->assertEqualsWithDelta(50.0, (float) $disposal->gain_loss, 0.0001);
        $this->assertSame('sold', $gainAsset->fresh()->status);

        $lossAsset = $service->create($institute->id, $branch->id, [
            'name' => 'Loss Machine',
            'acquisition_cost' => 1000,
            'residual_value' => 0,
            'useful_life_months' => 10,
            'depreciation_method' => 'straight_line',
        ]);
        $lossAsset = $service->capitalize($lossAsset, null, null, ['paid_immediately' => true]);
        $lossAsset->forceFill(['accumulated_depreciation' => 700])->save();

        $loss = $service->dispose($lossAsset, 'sale', 200, 'sold');

        $this->assertEqualsWithDelta(-100.0, (float) $loss->gain_loss, 0.0001);
    }

    public function test_impairment_preserves_cost_and_reduces_nbv(): void
    {
        $institute = $this->institute('healthcare');
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Land Parcel',
            'acquisition_cost' => 500000,
            'residual_value' => 0,
            'useful_life_months' => 120,
            'depreciation_method' => 'straight_line',
        ]);
        $asset = $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $service->impair($asset, 100000, 'market decline');

        $asset->refresh();
        $this->assertSame(500000.0, $asset->cost());
        $this->assertEqualsWithDelta(400000.0, $asset->netBookValue(), 0.0001);
        $this->assertSame(100000.0, $this->ledgerBalance($institute, '5012', $branch->id));
    }

    public function test_revaluation_requires_capability(): void
    {
        $institute = $this->institute('education');
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Building',
            'acquisition_cost' => 1000000,
            'residual_value' => 0,
            'useful_life_months' => 240,
            'depreciation_method' => 'straight_line',
        ]);
        $asset = $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $this->expectException(ValidationException::class);
        $service->revalue($asset, 1200000, 'appreciation');
    }

    public function test_revaluation_posts_surplus_when_enabled(): void
    {
        $institute = $this->institute('real_estate');
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Warehouse',
            'acquisition_cost' => 1000000,
            'residual_value' => 0,
            'useful_life_months' => 240,
            'depreciation_method' => 'straight_line',
        ]);
        $asset = $service->capitalize($asset, null, null, ['paid_immediately' => true]);

        $revaluation = $service->revalue($asset, 1200000, 'appreciation');

        $this->assertEqualsWithDelta(200000.0, (float) $revaluation->difference, 0.0001);
        $this->assertEqualsWithDelta(-200000.0, $this->ledgerBalance($institute, '3100', $branch->id), 0.0001);
    }

    public function test_reconciliation_matches_subledger_to_gl(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);

        $service = app(FixedAssetService::class);
        $asset = $service->create($institute->id, $branch->id, [
            'name' => 'Generator',
            'acquisition_cost' => 24000,
            'residual_value' => 0,
            'useful_life_months' => 24,
            'depreciation_method' => 'straight_line',
            'depreciation_start_date' => '2026-01-01',
        ]);
        $service->capitalize($asset, null, null, ['paid_immediately' => true]);
        $service->runDepreciation($institute->id, $branch->id, '2026-01-01', '2026-01-31');

        $report = app(FixedAssetReconciliationService::class)->reconcile($institute->id, $branch->id);

        $this->assertSame(1000.0, $report['subledger_total']);
        $this->assertSame(1000.0, $report['gl_total']);
        $this->assertSame(0.0, $report['variance']);
        $this->assertSame([], $report['asset_drifts']);
    }
}
