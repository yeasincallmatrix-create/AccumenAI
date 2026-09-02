<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicSelectionGroup;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicMarksService;
use App\Services\StudentAcademicPlacementService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A4 — Academic Placement Integrity Hardening
 * Covers placement uniqueness, tenant/branch isolation, subject resolution,
 * assessment/marks eligibility, lifecycle safety, historical freeze, concurrency, IDOR.
 */
class AcademicPlacementIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ---- helpers
    private function country(string $iso2 = 'BD'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }
    private function system(Country $c): EducationSystem
    {
        return EducationSystem::create(['country_id' => $c->id, 'name' => 'GE-'.uniqid(), 'code' => 'gen-'.uniqid(), 'display_order' => 0, 'status' => true]);
    }
    private function level(EducationSystem $s): AcademicLevel
    {
        return AcademicLevel::create(['country_id' => $s->country_id, 'education_system_id' => $s->id, 'name' => 'Sec-'.uniqid(), 'code' => 'sec-'.uniqid(), 'display_order' => 1, 'status' => true]);
    }
    private function classGrade(AcademicLevel $l, string $name = 'Class 8'): ClassGrade
    {
        return ClassGrade::create(['country_id' => $l->country_id, 'education_system_id' => $l->education_system_id, 'academic_level_id' => $l->id, 'name' => $name.'-'.uniqid(), 'code' => 'c'.uniqid(), 'display_order' => 0, 'status' => true]);
    }
    private function group(ClassGrade $cg): AcademicGroup
    {
        return AcademicGroup::create(['country_id' => $cg->country_id, 'education_system_id' => $cg->education_system_id, 'academic_level_id' => $cg->academic_level_id, 'class_grade_id' => $cg->id, 'name' => 'Science-'.uniqid(), 'code' => 'sci-'.uniqid(), 'display_order' => 0, 'status' => true]);
    }
    private function subject(string $name, string $code): Subject
    {
        return Subject::create(['institute_id' => null, 'subject_type' => 'academic', 'subject_code' => $code.'-'.uniqid(), 'name' => $name, 'slug' => str()->slug($name.'-'.uniqid()), 'short_name' => substr($name,0,8), 'status' => 'active']);
    }
    private function assign(Subject $s, ClassGrade $cg, string $type, int $ord, ?int $sgId=null, ?AcademicGroup $ag=null): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create(['subject_id'=>$s->id,'class_grade_id'=>$cg->id,'academic_group_id'=>$ag?->id,'requirement_type'=>$type,'selection_group_id'=>$sgId,'display_order'=>$ord,'status'=>'active']);
    }
    private function selGroup(ClassGrade $cg, int $min, int $max): AcademicSelectionGroup
    {
        return AcademicSelectionGroup::create(['class_grade_id'=>$cg->id,'name'=>'G-'.uniqid(),'code'=>'g-'.uniqid(),'selection_type'=>'optional','minimum_selection'=>$min,'maximum_selection'=>$max,'display_order'=>1,'status'=>'active']);
    }
    private function institute(Country $c): Institute
    {
        return Institute::create(['name'=>'Inst-'.uniqid(),'slug'=>str()->slug('inst-'.uniqid()),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active','industry'=>'education','sub_industry'=>'school']);
    }
    private function branch(Institute $i): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>'Br-'.uniqid(),'status'=>'active']); }
    private function user(Institute $i, string $role, ?Branch $b=null): InstituteUser {
        return InstituteUser::create(['institute_id'=>$i->id,'branch_id'=>$b?->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'first_name'=>'F','last_name'=>'L','email'=>uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt($this->password),'status'=>'active','email_verified_at'=>now()]);
    }
    private function student(Institute $i, ?Branch $b=null): Student {
        return Student::create(['institute_id'=>$i->id,'branch_id'=>$b?->id,'student_id_number'=>'SID'.mt_rand(100000,999999),'first_name'=>'S','last_name'=>'T','status'=>'active','admission_date'=>now()->toDateString()]);
    }
    private function year(Institute $i, string $code='2026'): AcademicYear {
        return AcademicYear::create(['institute_id'=>$i->id,'name'=>'AY '.$code,'code'=>$code.'-'.uniqid(),'is_current'=>false,'status'=>true]);
    }
    private function curriculumWired(): array
    {
        $c=$this->country(); $s=$this->system($c); $l=$this->level($s); $cg=$this->classGrade($l); $g=$this->group($cg);
        $inst=$this->institute($c); $owner=$this->user($inst,'institute-owner');
        $bangla=$this->subject('Bangla','PLB'); $eng=$this->subject('English','PLE'); $math=$this->subject('Math','PLM');
        $bio=$this->subject('Biology','PLBIO'); $hmath=$this->subject('HMath','PLH');
        $this->assign($bangla,$cg,'mandatory',1); $this->assign($eng,$cg,'mandatory',2); $this->assign($math,$cg,'mandatory',3);
        $sg=$this->selGroup($cg,1,1); $this->assign($bio,$cg,'optional',4,$sg->id); $this->assign($hmath,$cg,'optional',5,$sg->id);
        return compact('c','s','l','cg','g','inst','owner','bangla','eng','math','bio','hmath','sg');
    }

    // ---- Placement
    public function test_create_valid_placement_mandatory_auto_included(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']); $yr->update(['is_current'=>true]);
        TenantContext::set($w['inst']->id);
        $this->actingAs($w['owner'],'institute_user')->post(route('settings.academic.placements.store'),[
            'student_id'=>$stu->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'status'=>'active','subject_ids'=>[$w['bio']->id],
        ])->assertRedirect();
        $this->assertDatabaseHas('student_academic_placements',['student_id'=>$stu->id,'academic_year_id'=>$yr->id]);
        $pl=StudentAcademicPlacement::where('student_id',$stu->id)->first();
        // mandatory are auto-included even if not in subject_ids
        $this->assertTrue($pl->selections()->where('subject_id',$w['bangla']->id)->exists());
        $this->assertTrue($pl->selections()->where('subject_id',$w['bio']->id)->exists());
    }

    public function test_duplicate_placement_blocked(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
    }

    public function test_service_tenant_guard_blocks_cross_institute(): void
    {
        $w=$this->curriculumWired(); $c2=$this->country('US'); $inst2=$this->institute($c2); $yr2=$this->year($inst2); $stu2=$this->student($inst2);
        // try to create placement for inst2 student but passing w['inst'] as institute
        $svc=app(StudentAcademicPlacementService::class);
        try {
            $svc->storePlacement($w['inst'],$stu2,$yr2,$w['cg'],null,[$w['bio']->id]);
            $this->fail('should have thrown');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('student_id', $e->errors());
        }
        // cross year tenant
        $yr=$this->year($w['inst']); $stu=$this->student($w['inst']);
        try {
            $svc->storePlacement($w['inst'],$stu,$yr2,$w['cg'],null,[$w['bio']->id]);
            $this->fail('should have thrown year');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('academic_year_id', $e->errors());
        }
    }

    public function test_closed_year_blocks_placement(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']); $yr->update(['status'=>false]);
        TenantContext::set($w['inst']->id);
        $this->actingAs($w['owner'],'institute_user')->post(route('settings.academic.placements.store'),[
            'student_id'=>$stu->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'status'=>'active','subject_ids'=>[$w['bio']->id],
        ])->assertSessionHasErrors('academic_year_id');
    }

    public function test_invalid_group_rejected(): void
    {
        $w=$this->curriculumWired(); $cg2=$this->classGrade($w['l'],'Class 9'); $rogue=$this->group($cg2);
        $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $this->actingAs($w['owner'],'institute_user')->post(route('settings.academic.placements.store'),[
            'student_id'=>$stu->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'academic_group_id'=>$rogue->id,'status'=>'active','subject_ids'=>[$w['bio']->id],
        ])->assertStatus(422);
    }

    public function test_branch_isolation(): void
    {
        $w=$this->curriculumWired(); $brA=$this->branch($w['inst']); $brB=$this->branch($w['inst']);
        $stuB=$this->student($w['inst'],$brB); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $pl=$svc->storePlacement($w['inst'],$stuB,$yr,$w['cg'],null,[$w['bio']->id]);
        $adminA=$this->user($w['inst'],'branch-manager',$brA);
        BranchContext::set($brA->id);
        $this->actingAs($adminA,'institute_user')->get(route('settings.academic.placements.show',$pl->id))->assertStatus(403);
        BranchContext::clear();
        BranchContext::set($brB->id);
        $adminB=$this->user($w['inst'],'institute-owner',$brB);
        // reload placement to avoid cached null relation
        $freshPl=StudentAcademicPlacement::withoutGlobalScopes()->find($pl->id);
        $this->actingAs($adminB,'institute_user')->get(route('settings.academic.placements.show',$freshPl->id))->assertOk();
    }

    // ---- Subject
    public function test_optional_subject_validation_enforced(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        // selecting 0 optional when min 1 should fail
        $this->actingAs($w['owner'],'institute_user')->post(route('settings.academic.placements.store'),[
            'student_id'=>$stu->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'status'=>'active','subject_ids'=>[],
        ])->assertSessionHasErrors('subjects');
    }

    public function test_deleted_subject_excluded_from_selection(): void
    {
        $w=$this->curriculumWired(); $w['bio']->delete(); // soft delete
        $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        // bio is soft deleted, should not be valid
        $this->actingAs($w['owner'],'institute_user')->post(route('settings.academic.placements.store'),[
            'student_id'=>$stu->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'status'=>'active','subject_ids'=>[$w['bio']->id],
        ])->assertSessionHasErrors('subjects');
    }

    // ---- Assessment eligibility
    public function test_assessment_eligibility_blocks_wrong_group(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        $gSci=$this->group($w['cg']); $gCom=AcademicGroup::create(['country_id'=>$w['cg']->country_id,'education_system_id'=>$w['cg']->education_system_id,'academic_level_id'=>$w['cg']->academic_level_id,'class_grade_id'=>$w['cg']->id,'name'=>'Commerce','code'=>'com-'.uniqid(),'display_order'=>1,'status'=>true]);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $pl=$svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],$gCom,[$w['bio']->id]);
        // assessment for Science
        $assessment=\App\Models\AcademicAssessment::create(['institute_id'=>$w['inst']->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'academic_group_id'=>$gSci->id,'name'=>'Mid','status'=>'draft','display_order'=>1]);
        $marksSvc=app(AcademicMarksService::class);
        $eligible=$marksSvc->eligiblePlacements($assessment);
        $this->assertFalse($eligible->contains('id',$pl->id));
    }

    // ---- Marks eligibility + IDOR
    public function test_marks_save_blocks_ineligible_placement(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $pl=$svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
        // create assessment for different class
        $cg2=$this->classGrade($w['l'],'Class 9');
        $ass=\App\Models\AcademicAssessment::create(['institute_id'=>$w['inst']->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$cg2->id,'name'=>'Other','status'=>'draft','display_order'=>1]);
        // need subject + component
        $comp=Component::first() ?? Component::create(['name'=>'Written','slug'=>'w-'.uniqid(),'status'=>true]);
        $as=\App\Models\AssessmentSubject::create(['assessment_id'=>$ass->id,'subject_id'=>$w['bangla']->id,'pass_rule'=>'total_only','display_order'=>1,'status'=>'active']);
        $asc=\App\Models\AssessmentSubjectComponent::create(['assessment_subject_id'=>$as->id,'component_id'=>$comp->id,'full_mark'=>100,'pass_mark'=>40,'display_order'=>1,'status'=>'active']);
        $marksSvc=app(AcademicMarksService::class);
        // try to save marks for pl which is not eligible (different class)
        try {
            $marksSvc->saveMarks($ass,$as,null,[$pl->id=>['marks'=>[$asc->id=>50]]]);
            $this->fail('should have thrown not eligible');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('not eligible', json_encode($e->errors()));
        }
    }

    // ---- Historical freeze
    public function test_placement_update_blocked_after_marks(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $pl=$svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
        $ass=\App\Models\AcademicAssessment::create(['institute_id'=>$w['inst']->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'name'=>'Test','status'=>'draft','display_order'=>1]);
        $comp=Component::first() ?? Component::create(['name'=>'W','slug'=>'w-'.uniqid(),'status'=>true]);
        $as=\App\Models\AssessmentSubject::create(['assessment_id'=>$ass->id,'subject_id'=>$w['bangla']->id,'pass_rule'=>'total_only','display_order'=>1,'status'=>'active']);
        $asc=\App\Models\AssessmentSubjectComponent::create(['assessment_subject_id'=>$as->id,'component_id'=>$comp->id,'full_mark'=>100,'pass_mark'=>40,'display_order'=>1,'status'=>'active']);
        $marksSvc=app(AcademicMarksService::class);
        $marksSvc->saveMarks($ass,$as,$w['owner']->id,[$pl->id=>['marks'=>[$asc->id=>80]]]);
        // now try to update placement class
        $cg2=$this->classGrade($w['l'],'Class 9');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->updatePlacement($w['inst'],$pl,$yr,$cg2,null,[$w['bio']->id]);
    }

    public function test_completed_placement_excluded_from_eligibility(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $pl=$svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
        $pl->update(['status'=>'completed']);
        $ass=\App\Models\AcademicAssessment::create(['institute_id'=>$w['inst']->id,'academic_year_id'=>$yr->id,'class_grade_id'=>$w['cg']->id,'name'=>'A','status'=>'draft','display_order'=>1]);
        $eligible=app(AcademicMarksService::class)->eligiblePlacements($ass);
        $this->assertFalse($eligible->contains('id',$pl->id));
    }

    // ---- Concurrency duplicate race (simulate via DB unique)
    public function test_concurrent_duplicate_handled_via_db_unique(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        $svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
        // second attempt should be clean ValidationException not raw DB error
        try {
            $svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
            $this->fail('expected duplicate');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('academic_year_id',$e->errors());
        }
    }

    // ---- Curriculum not applicable
    public function test_academic_placement_does_not_require_curriculum(): void
    {
        $w=$this->curriculumWired(); $stu=$this->student($w['inst']); $yr=$this->year($w['inst']);
        TenantContext::set($w['inst']->id);
        $svc=app(StudentAcademicPlacementService::class);
        // no course_curricula row at all, placement should succeed
        $pl=$svc->storePlacement($w['inst'],$stu,$yr,$w['cg'],null,[$w['bio']->id]);
        $this->assertDatabaseHas('student_academic_placements',['id'=>$pl->id]);
        $this->assertNotNull($pl->classGrade);
        // ensure no curriculum_id column exists
        $this->assertFalse(\Schema::hasColumn('student_academic_placements','curriculum_id'));
    }
}
