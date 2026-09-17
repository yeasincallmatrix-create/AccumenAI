<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Industry;
use App\Models\Institute;
use App\Models\SubIndustry;
use App\Services\InstituteTaxonomyBackfill;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InstituteTaxonomyBackfillTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\IndustryTaxonomySeeder']);
        Cache::flush();
    }

    protected function bangladeshId(): int
    {
        $id = Country::where('name', 'Bangladesh')->value('id');
        $this->assertNotNull($id);

        return (int) $id;
    }

    protected function makeInstitute(array $overrides): Institute
    {
        return Institute::create(array_merge([
            'name' => 'Backfill-' . uniqid(),
            'slug' => 'backfill-' . uniqid(),
            'country' => 'Bangladesh',
            'country_id' => $this->bangladeshId(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_matching_legacy_values_are_resolved(): void
    {
        $edu = $this->makeInstitute(['industry' => 'education', 'sub_industry' => 'school']);
        $hospital = $this->makeInstitute(['industry' => 'healthcare', 'sub_industry' => 'hospital']);
        $countryless = $this->makeInstitute(['industry' => 'education', 'sub_industry' => 'school', 'country_id' => null, 'country' => '']);

        $report = InstituteTaxonomyBackfill::run();

        $this->assertNull($report['skipped']);
        $this->assertSame('education', $edu->fresh()->industry);
        $this->assertSame(Industry::where('slug', 'education')->value('id'), $edu->fresh()->industry_id);
        // The institute is Bangladeshi, so the country-specific row wins
        // over the global one (same priority rule as production data).
        $this->assertSame(
            SubIndustry::where('slug', 'school')->where('country_id', $this->bangladeshId())->value('id'),
            $edu->fresh()->sub_industry_id
        );

        // Country-specific row preferred for the Bangladeshi hospital.
        $expectedSub = SubIndustry::where('slug', 'hospital')
            ->where('country_id', $this->bangladeshId())
            ->value('id');
        $this->assertNotNull($expectedSub);
        $this->assertSame($expectedSub, $hospital->fresh()->sub_industry_id);

        // Country-less institutes resolve to the global row.
        $this->assertSame(
            SubIndustry::where('slug', 'school')->whereNull('country_id')->value('id'),
            $countryless->fresh()->sub_industry_id
        );

        $this->assertSame([], $report['unmatched_industries']);
        $this->assertSame([], $report['unmatched_subs']);
    }

    public function test_legacy_alias_transport_resolves_to_transportation(): void
    {
        $inst = $this->makeInstitute(['industry' => 'transport', 'sub_industry' => '']);

        InstituteTaxonomyBackfill::run();

        $this->assertSame('transport', $inst->fresh()->industry); // legacy string preserved
        $this->assertSame(
            Industry::where('slug', 'transportation')->value('id'),
            $inst->fresh()->industry_id
        );
    }

    public function test_unmatched_values_left_null_and_reported(): void
    {
        $inst = $this->makeInstitute(['industry' => 'mystery_industry', 'sub_industry' => 'mystery_sub']);

        $report = InstituteTaxonomyBackfill::run();

        $this->assertNull($inst->fresh()->industry_id);
        $this->assertNull($inst->fresh()->sub_industry_id);
        $this->assertContains('mystery_industry', $report['unmatched_industries']);
        // Sub cannot be verified without a resolved parent industry.
        $this->assertNotEmpty($report['unmatched_subs']);
    }

    public function test_repeated_execution_is_idempotent(): void
    {
        $inst = $this->makeInstitute(['industry' => 'education', 'sub_industry' => 'college']);

        $first = InstituteTaxonomyBackfill::run();
        $this->assertSame(1, $first['industry_filled'] >= 1 ? 1 : 0);

        $industryId = $inst->fresh()->industry_id;
        $subId = $inst->fresh()->sub_industry_id;
        $this->assertNotNull($industryId);
        $this->assertNotNull($subId);

        $second = InstituteTaxonomyBackfill::run();

        $this->assertSame(0, $second['industry_filled']);
        $this->assertSame(0, $second['sub_filled']);
        $this->assertSame($industryId, $inst->fresh()->industry_id);
        $this->assertSame($subId, $inst->fresh()->sub_industry_id);
    }

    public function test_valid_existing_fk_is_preserved(): void
    {
        $healthcareId = Industry::where('slug', 'healthcare')->value('id');
        $inst = $this->makeInstitute([
            'industry' => 'education', // legacy string disagrees…
            'sub_industry' => '',
            'industry_id' => $healthcareId, // …but the valid FK must win.
        ]);

        InstituteTaxonomyBackfill::run();

        $this->assertSame($healthcareId, $inst->fresh()->industry_id);
        $this->assertSame('education', $inst->fresh()->industry);
    }

    public function test_parent_industry_consistency_for_all_resolved_rows(): void
    {
        $this->makeInstitute(['industry' => 'training_center', 'sub_industry' => 'professional_training_center']);
        $this->makeInstitute(['industry' => 'healthcare', 'sub_industry' => 'diagnostic_center']);

        InstituteTaxonomyBackfill::run();

        $mismatches = Institute::whereNotNull('sub_industry_id')
            ->get()
            ->filter(fn (Institute $i) => $i->subIndustry && (int) $i->subIndustry->industry_id !== (int) $i->industry_id);

        $this->assertTrue($mismatches->isEmpty(), 'Parent mismatches: ' . $mismatches->pluck('id')->implode(','));
    }
}
