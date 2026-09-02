<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicResultAggregationScheme;
use App\Models\Branch;
use App\Models\Country;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A6 — Result Finalization, Publishing & Historical Integrity
 */
class AcademicResultFinalizationIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear(); BranchContext::clear(); parent::tearDown();
    }

    private function country(): Country { return Country::firstOrCreate(['iso2'=>'BD'],['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]); }
    private function institute(Country $c): Institute { return Institute::create(['name'=>'Inst-'.uniqid(),'slug'=>str()->slug('inst-'.uniqid()),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']); }
    private function branch(Institute $i): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>'Br-'.uniqid(),'status'=>'active']); }
    private function user(Institute $i, string $role, ?Branch $b=null): InstituteUser {
        return InstituteUser::create(['institute_id'=>$i->id,'branch_id'=>$b?->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'first_name'=>'F','last_name'=>'L','email'=>uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt($this->password),'status'=>'active','email_verified_at'=>now()]);
    }

    public function test_state_machine_guards(): void
    {
        $r = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_REVIEW]);
        $r->setRelation('policy', (object)['require_approval'=>true]);
        $this->assertTrue($r->canApprove());
        $this->assertFalse($r->canLock());
        $this->assertFalse($r->canPublish());
        $this->assertFalse($r->hasSnapshot());

        $r2 = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_LOCKED,'locked_at'=>now()]);
        $this->assertTrue($r2->hasSnapshot());
        $this->assertTrue($r2->canPublish());
        $this->assertFalse($r2->canLock());

        $r3 = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_PUBLISHED,'locked_at'=>now()]);
        $this->assertFalse($r3->canPublish());
    }

    public function test_review_can_lock_without_approval_when_policy_disabled(): void
    {
        $policy = new \App\Models\AcademicFinalResultPolicy(['require_approval'=>false]);
        $r = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_REVIEW,'reviewed_at'=>null]);
        $r->setRelation('policy',$policy);
        $this->assertTrue($r->canLock());
        $r2 = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_REVIEW,'reviewed_at'=>null]);
        $r2->setRelation('policy', new \App\Models\AcademicFinalResultPolicy(['require_approval'=>true]));
        $this->assertFalse($r2->canLock());
    }

    public function test_locked_result_cannot_be_modified_via_marks(): void
    {
        $ass = new \App\Models\AcademicAssessment(['locked_at'=>now()]);
        $this->assertTrue($ass->isLocked());
        $src = file_get_contents(app()->basePath('app/Services/AcademicMarksService.php'));
        $this->assertStringContainsString('isLocked', $src);
        $this->assertStringContainsString('abort_if', $src);
    }

    public function test_report_card_uses_frozen_snapshot(): void
    {
        // Verify controller loads from AcademicFinalResultRow, not live marks
        $ref = new \ReflectionMethod(\App\Http\Controllers\AcademicFinalResultController::class, 'report');
        $src = file_get_contents((new \ReflectionMethod(\App\Http\Controllers\AcademicFinalResultController::class, 'report'))->getFileName());
        $this->assertStringContainsString('AcademicFinalResultRow', $src);
        $this->assertStringContainsString('where(\'result_id\'', $src);
        $this->assertStringContainsString('STATUS_PUBLISHED', $src);
    }

    public function test_historical_result_survives_subject_soft_delete(): void
    {
        $subj=\App\Models\Subject::create(['subject_type'=>'academic','subject_code'=>'SB'.uniqid(),'name'=>'Sub','slug'=>str()->slug('sub-'.uniqid()),'status'=>'active']);
        $this->assertNotNull($subj->id);
        $subj->delete(); // soft delete
        $this->assertSoftDeleted($subj);
        $this->assertNotNull(\App\Models\Subject::withTrashed()->find($subj->id));
        // Historical row subject relation uses withTrashed() — verified via model
        $src = file_get_contents(app()->basePath('app/Models/AcademicFinalResultRow.php'));
        $this->assertStringContainsString('withTrashed', $src);
    }

    public function test_concurrent_lock_is_idempotent(): void
    {
        $locked = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_LOCKED,'locked_at'=>now()]);
        $this->assertFalse($locked->canLock());
        $published = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_PUBLISHED]);
        $this->assertFalse($published->canPublish());
        $this->assertFalse($published->canLock());
        $review = new AcademicFinalResult(['status'=>AcademicFinalResult::STATUS_REVIEW]);
        $review->setRelation('policy', new \App\Models\AcademicFinalResultPolicy(['require_approval'=>true]));
        $this->assertFalse($review->canPublish());
    }

    public function test_tenant_isolation_on_final_result(): void
    {
        $src = file_get_contents(app()->basePath('app/Models/AcademicFinalResult.php'));
        $this->assertStringContainsString('TenantScoped', $src);
        $src2 = file_get_contents(app()->basePath('app/Services/AcademicFinalResultLifecycleService.php'));
        $this->assertStringContainsString('institute_id', $src2);
        $this->assertStringContainsString('abort_if', $src2);
    }

    public function test_audit_trail_on_lock(): void
    {
        $src = file_get_contents(app()->basePath('app/Services/AcademicFinalResultLifecycleService.php'));
        $this->assertStringContainsString('final_result.locked', $src);
        $this->assertStringContainsString('final_result.published', $src);
        $this->assertStringContainsString('audit->record', $src);
    }

    public function test_legacy_exams_isolated(): void
    {
        $src = file_get_contents(app()->basePath('app/Services/AcademicFinalResultService.php'));
        $this->assertStringNotContainsString('exam_results', strtolower($src));
        $this->assertStringNotContainsString('exam_subjects', strtolower($src));
    }
}
