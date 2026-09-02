<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\FixedAsset;
use App\Models\Institute;
use App\Services\Accounting\AccountingSetupService;
use App\Services\FixedAsset\Depreciation\DepreciationMethodResolver;
use App\Services\FixedAsset\DepreciationEngine;
use App\Services\FixedAsset\FixedAssetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 17 — Depreciation methods, residual protection and schedule
 * reproducibility. Exercises every strategy through the shared engine so
 * posting and reporting stay consistent.
 */
class FixedAssetDepreciationTest extends TestCase
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
            'name' => 'Dep Inst',
            'slug' => str()->slug('Dep Inst-'.uniqid()),
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

    private function asset(Institute $institute, ?Branch $branch, array $data): FixedAsset
    {
        return app(FixedAssetService::class)->create($institute->id, $branch?->id, $data);
    }

    private function engine(): DepreciationEngine
    {
        return app(DepreciationEngine::class);
    }

    public function test_straight_line_schedule_is_reproducible(): void
    {
        $institute = $this->institute();
        app(AccountingSetupService::class)->setupForInstitute($institute->id);

        $asset = $this->asset($institute, null, [
            'name' => 'Laptop',
            'acquisition_cost' => 1200,
            'residual_value' => 200,
            'useful_life_months' => 10,
            'depreciation_method' => 'straight_line',
        ]);

        $schedule = $this->engine()->schedule($asset);

        $this->assertCount(10, $schedule);
        foreach ($schedule as $row) {
            $this->assertSame(100.0, $row['depreciation']);
        }
        $this->assertSame(200.0, $schedule[9]['closing_nbv']);
        $this->assertSame(1000.0, $schedule[9]['accumulated_depreciation']);
    }

    public function test_double_declining_balance_is_accelerated(): void
    {
        $institute = $this->institute();
        $asset = $this->asset($institute, null, [
            'name' => 'Machine',
            'acquisition_cost' => 1000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'double_declining_balance',
        ]);

        $first = $this->engine()->periodAmount($asset, 0, 1);

        $this->assertEqualsWithDelta(round(1000 * (2 / 12), 4), $first, 0.0001);
    }

    public function test_declining_balance_never_breaches_residual_value(): void
    {
        $institute = $this->institute();
        $asset = $this->asset($institute, null, [
            'name' => 'Vehicle',
            'acquisition_cost' => 1000,
            'residual_value' => 100,
            'useful_life_months' => 24,
            'depreciation_method' => 'declining_balance',
            'depreciation_rate' => 0.30,
        ]);

        $schedule = $this->engine()->schedule($asset);

        foreach ($schedule as $row) {
            $this->assertGreaterThanOrEqual(100.0 - 0.0001, $row['closing_nbv']);
            $this->assertLessThanOrEqual(900.0 + 0.0001, $row['accumulated_depreciation']);
        }
    }

    public function test_sum_of_years_digits_first_period_is_largest(): void
    {
        $institute = $this->institute();
        $asset = $this->asset($institute, null, [
            'name' => 'Equipment',
            'acquisition_cost' => 1200,
            'residual_value' => 200,
            'useful_life_months' => 5,
            'depreciation_method' => 'sum_of_years_digits',
        ]);

        $schedule = $this->engine()->schedule($asset);

        $this->assertCount(5, $schedule);
        $this->assertEqualsWithDelta(round(1000 * (5 / 15), 4), $schedule[0]['depreciation'], 0.0001);
        $this->assertEqualsWithDelta(round(1000 * (1 / 15), 4), $schedule[4]['depreciation'], 0.0001);
        $this->assertSame(200.0, $schedule[4]['closing_nbv']);
    }

    public function test_units_of_production_depends_on_activity(): void
    {
        $institute = $this->institute();
        $asset = $this->asset($institute, null, [
            'name' => 'Press',
            'acquisition_cost' => 1000,
            'residual_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'units_of_production',
            'unit_of_measure' => 'units',
            'total_units' => 1000,
        ]);

        $schedule = $this->engine()->schedule($asset, [100, 200, 300]);

        $this->assertCount(3, $schedule);
        $this->assertSame(100.0, $schedule[0]['depreciation']);
        $this->assertSame(200.0, $schedule[1]['depreciation']);
        $this->assertSame(300.0, $schedule[2]['depreciation']);
        $this->assertSame(600.0, $schedule[2]['accumulated_depreciation']);
    }

    public function test_straight_line_clamps_final_period_rounding(): void
    {
        $institute = $this->institute();
        $asset = $this->asset($institute, null, [
            'name' => 'Furniture',
            'acquisition_cost' => 1000,
            'residual_value' => 0,
            'useful_life_months' => 3,
            'depreciation_method' => 'straight_line',
        ]);

        $schedule = $this->engine()->schedule($asset);

        $this->assertEqualsWithDelta(1000.0, $schedule[2]['accumulated_depreciation'], 0.0001);
    }

    public function test_resolver_rejects_unknown_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(DepreciationMethodResolver::class)->resolve('teleportation');
    }
}
