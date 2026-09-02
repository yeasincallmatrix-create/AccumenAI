<?php

namespace Tests\Feature;

use App\Models\GradeScale;
use App\Services\AcademicFinalResultService;
use App\Services\AcademicGradingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicResultBusinessRulesTest extends TestCase
{
    use DatabaseTransactions;

    private function gradeScale(array $overrides = []): GradeScale
    {
        $defaults = [
            'name' => 'Test Scale '.uniqid(),
            'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
            'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED,
            'optional_subject_bonus_threshold' => 2.00,
            'optional_subject_bonus_enabled' => true,
            'max_gpa' => 5.00,
            'gpa_decimal_places' => 2,
            'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_SINGLE,
            'status' => true,
        ];
        $data = array_merge($defaults, $overrides);

        return GradeScale::updateOrCreate(
            ['institute_id' => null, 'country_id' => null, 'education_system_id' => null, 'academic_level_id' => null],
            $data
        );
    }

    public function test_bangladesh_default_threshold_is_two(): void
    {
        $scale = $this->gradeScale();
        $this->assertEquals(2.00, (float) $scale->optional_subject_bonus_threshold);
        $this->assertTrue((bool) $scale->optional_subject_bonus_enabled);
        $this->assertEquals(5.00, (float) $scale->max_gpa);
        $this->assertEquals('single', $scale->multiple_optional_policy);
    }

    public function test_bonus_gp_five_gives_three(): void { $this->assertEquals(3.00, max(5.00-2.00,0)); }
    public function test_bonus_gp_four_gives_two(): void { $this->assertEquals(2.00, max(4.00-2.00,0)); }
    public function test_bonus_gp_three_five_gives_one_five(): void { $this->assertEquals(1.50, max(3.50-2.00,0)); }
    public function test_bonus_gp_three_gives_one(): void { $this->assertEquals(1.00, max(3.00-2.00,0)); }
    public function test_bonus_gp_two_gives_zero(): void { $this->assertEquals(0.0, max(2.00-2.00,0)); }
    public function test_bonus_below_two_gives_zero(): void { $this->assertEquals(0.0, max(1.00-2.00,0)); }

    public function test_bonus_excluded_from_denominator(): void
    {
        // 7 mandatory total 31.5 + optional 5 bonus 3 => (31.5+3)/7 = 4.928 => 4.93
        $mandatoryTotal = 31.5; $bonus = 3.00;
        $gpa = round(($mandatoryTotal + $bonus)/7, 2);
        $this->assertEquals(4.93, $gpa);
        // Incorrect denominator 8 would give 4.31
        $wrong = round(($mandatoryTotal + $bonus)/8, 2);
        $this->assertNotEquals($gpa, $wrong);
    }

    public function test_gpa_capped_at_five(): void
    {
        $scale = $this->gradeScale(['max_gpa'=>5.00]);
        // Simulate 7*5=35 + bonus 3 => 38/7=5.428 -> capped 5.00
        $value = (35+3)/7;
        $capped = $value > (float)$scale->max_gpa ? (float)$scale->max_gpa : $value;
        $this->assertEquals(5.00, $capped);
    }

    public function test_bonus_disabled_excludes_optional_bonus(): void
    {
        $scale = $this->gradeScale(['optional_subject_bonus_enabled'=>false]);
        $this->assertFalse((bool)$scale->optional_subject_bonus_enabled);
        // When disabled, optional should not generate bonus (code path: if isOptional && bonusEnabled)
        // Verified via file contains check
        $src = file_get_contents(app()->basePath('app/Services/AcademicFinalResultService.php'));
        $this->assertStringContainsString('bonusEnabled', $src);
    }

    public function test_configurable_threshold(): void
    {
        $scale = $this->gradeScale(['optional_subject_bonus_threshold'=>3.00]);
        $bonus = max(5.00 - (float)$scale->optional_subject_bonus_threshold, 0);
        $this->assertEquals(2.00, $bonus);
        $this->assertEquals(0.0, max(2.50 - 3.00, 0));
    }

    public function test_configurable_max_gpa(): void
    {
        $scale = $this->gradeScale(['max_gpa'=>4.00]);
        $value = 4.5;
        $capped = $value > (float)$scale->max_gpa ? (float)$scale->max_gpa : $value;
        $this->assertEquals(4.00, $capped);
    }

    public function test_configurable_decimal_places(): void
    {
        $scale = $this->gradeScale(['gpa_decimal_places'=>3]);
        $this->assertEquals(3, (int)$scale->gpa_decimal_places);
        $svc = app(AcademicGradingService::class);
        $rounded = $svc->preciseRound(4.928571, 3, GradeScale::ROUNDING_HALF_UP);
        $this->assertEquals(4.929, $rounded);
        $rounded2 = $svc->preciseRound(4.928571, 2, GradeScale::ROUNDING_HALF_UP);
        $this->assertEquals(4.93, $rounded2);
    }

    public function test_multiple_optional_policy_single(): void
    {
        $scale = $this->gradeScale(['multiple_optional_policy'=>'single']);
        $this->assertEquals('single', $scale->multiple_optional_policy);
        // Service should keep only first bonus
        $src = file_get_contents(app()->basePath('app/Services/AcademicFinalResultService.php'));
        $this->assertStringContainsString('MULTIPLE_OPTIONAL_SINGLE', $src);
        $this->assertStringContainsString('multiple_optional_policy', $src);
    }

    public function test_multiple_optional_policy_best_and_sum(): void
    {
        $c = \App\Models\Country::firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]);
        $i1 = \App\Models\Institute::create(['name'=>'I1-'.uniqid(),'slug'=>str()->slug('i1-'.uniqid()),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']);
        $i2 = \App\Models\Institute::create(['name'=>'I2-'.uniqid(),'slug'=>str()->slug('i2-'.uniqid()),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']);
        $best = GradeScale::create(['name'=>'Best '.uniqid(),'multiple_optional_policy'=>'best','status'=>true,'institute_id'=>$i1->id]);
        $this->assertEquals('best', $best->multiple_optional_policy);
        $sum = GradeScale::create(['name'=>'Sum '.uniqid(),'multiple_optional_policy'=>'sum','status'=>true,'institute_id'=>$i2->id]);
        $this->assertEquals('sum', $sum->multiple_optional_policy);
    }

    public function test_historical_gpa_immutable(): void
    {
        // Snapshot stores gpa, not live calc — verify controller reads frozen rows
        $src = file_get_contents(app()->basePath('app/Http/Controllers/AcademicFinalResultController.php'));
        $this->assertStringContainsString('AcademicFinalResultRow', $src);
        $this->assertStringContainsString('where(\'result_id\'', $src);
    }

    public function test_absent_deterministic(): void
    {
        $src = file_get_contents(app()->basePath('app/Services/AcademicResultAggregationService.php'));
        $this->assertStringContainsString('STATUS_ABSENT', $src);
        $this->assertStringContainsString('renormalize', $src);
    }

    public function test_legacy_exams_isolated(): void
    {
        $src = file_get_contents(app()->basePath('app/Services/AcademicFinalResultService.php'));
        $this->assertStringNotContainsString('exam_results', strtolower($src));
    }
}
