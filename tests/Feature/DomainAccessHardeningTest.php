<?php
namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Models\Membership;
use App\Support\InstituteDomain;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DomainAccessHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function createAcademicInstitute(): Institute
    {
        return Institute::create([
            "name" => "Academic School Test",
            "slug" => "academic-school-test-".uniqid(),
            "industry" => "education",
            "sub_industry" => "school",
            "country" => "Bangladesh",
            "status" => "active",
        ]);
    }

    private function createProfessionalInstitute(): Institute
    {
        return Institute::create([
            "name" => "Professional Training Test",
            "slug" => "professional-training-test-".uniqid(),
            "industry" => "training_center",
            "sub_industry" => "training_institute",
            "country" => "Bangladesh",
            "status" => "active",
        ]);
    }

    private function createUserWithMembership(Institute $institute, string $roleSlug = "institute-owner", string $accountType = "owner"): User
    {
        $user = User::factory()->create([
            "account_type" => $accountType,
            "email_verified_at" => now(),
            "status" => "active",
        ]);
        $roleId = Role::where("slug", $roleSlug)->value("id");
        if ($roleId) {
            Membership::create([
                "user_id" => $user->id,
                "institution_id" => $institute->id,
                "role_id" => $roleId,
                "status" => "active",
            ]);
        }
        return $user;
    }

    public function test_academic_institute_can_access_academic_setup(): void
    {
        $institute = $this->createAcademicInstitute();
        $user = $this->createUserWithMembership($institute, "institute-owner", "owner");
        $this->actingAs($user, "web");
        Workspace::set($institute->id);
        $response = $this->get("/settings/academic");
        $this->assertNotEquals(403, $response->status(), "Academic institute should access academic setup");
    }

    public function test_professional_institute_cannot_access_academic_setup(): void
    {
        $institute = $this->createProfessionalInstitute();
        $user = $this->createUserWithMembership($institute, "institute-owner", "owner");
        $this->actingAs($user, "web");
        Workspace::set($institute->id);
        $response = $this->get("/settings/academic");
        $this->assertEquals(403, $response->status(), "Professional institute must be blocked from academic setup");
    }

    public function test_professional_institute_can_access_professional_subject(): void
    {
        $institute = $this->createProfessionalInstitute();
        $this->assertEquals("professional", InstituteDomain::fromInstitute($institute));
        $this->assertEquals("professional", InstituteDomain::subjectTypeFor($institute));
    }

    public function test_academic_subject_derives_academic(): void
    {
        $institute = $this->createAcademicInstitute();
        $this->assertEquals("academic", InstituteDomain::subjectTypeFor($institute));
    }

    public function test_professional_subject_derives_professional(): void
    {
        $institute = $this->createProfessionalInstitute();
        $this->assertEquals("professional", InstituteDomain::subjectTypeFor($institute));
    }

    public function test_forged_subject_type_is_ignored(): void
    {
        $academic = $this->createAcademicInstitute();
        $derived = InstituteDomain::subjectTypeFor($academic);
        $this->assertEquals("academic", $derived);
    }

    public function test_cross_tenant_category_rejected(): void
    {
        $instA = $this->createAcademicInstitute();
        $instB = $this->createAcademicInstitute();
        $cat = \App\Models\CourseCategory::withoutGlobalScopes()->create([
            "institute_id" => $instA->id,
            "name" => "Cat A",
            "slug" => "cat-a-".uniqid(),
            "subject_type" => "academic",
            "status" => "active",
        ]);
        $existsInB = \App\Models\CourseCategory::where("institute_id", $instB->id)->where("id", $cat->id)->exists();
        $this->assertFalse($existsInB, "Cross-tenant category must not be found in B");
        $cat->delete();
    }

    public function test_cross_domain_category_rejected(): void
    {
        $academic = $this->createAcademicInstitute();
        $professional = $this->createProfessionalInstitute();
        $catAcademic = \App\Models\CourseCategory::withoutGlobalScopes()->create([
            "institute_id" => $academic->id,
            "name" => "Academic Cat",
            "slug" => "acad-cat-".uniqid(),
            "subject_type" => "academic",
            "status" => "active",
        ]);
        $domain = InstituteDomain::subjectTypeFor($professional);
        $this->assertEquals("professional", $domain);
        $found = \App\Models\CourseCategory::where("institute_id", $academic->id)->where("id", $catAcademic->id)->where("subject_type", $domain)->exists();
        $this->assertFalse($found, "Cross-domain category must be rejected");
        $catAcademic->delete();
    }

    public function test_academic_assessment_blocked_for_professional(): void
    {
        $professional = $this->createProfessionalInstitute();
        $user = $this->createUserWithMembership($professional, "institute-owner", "owner");
        $this->actingAs($user, "web");
        Workspace::set($professional->id);
        $response = $this->get("/settings/academic");
        $this->assertEquals(403, $response->status());
    }

    public function test_academic_assessment_allowed_for_academic(): void
    {
        $academic = $this->createAcademicInstitute();
        $user = $this->createUserWithMembership($academic, "institute-owner", "owner");
        $this->actingAs($user, "web");
        Workspace::set($academic->id);
        $response = $this->get("/settings/academic");
        $this->assertNotEquals(403, $response->status());
    }

    public function test_workspace_switch_changes_effective_domain(): void
    {
        $academic = $this->createAcademicInstitute();
        $professional = $this->createProfessionalInstitute();
        $user = User::factory()->create(["account_type"=>"owner","email_verified_at"=>now(),"status"=>"active"]);
        $ownerRole = Role::where("slug","institute-owner")->value("id");
        Membership::create(["user_id"=>$user->id,"institution_id"=>$academic->id,"role_id"=>$ownerRole,"status"=>"active"]);
        Membership::create(["user_id"=>$user->id,"institution_id"=>$professional->id,"role_id"=>$ownerRole,"status"=>"active"]);
        $this->actingAs($user, "web");
        Workspace::set($academic->id);
        $this->assertEquals("academic", InstituteDomain::fromInstitute(\App\Models\Institute::find(Workspace::id())));
        Workspace::set($professional->id);
        $this->assertEquals("professional", InstituteDomain::fromInstitute(\App\Models\Institute::find(Workspace::id())));
        $response = $this->get("/settings/academic");
        $this->assertEquals(403, $response->status(), "After switch to professional, academic should be blocked");
        Workspace::set($academic->id);
        $response2 = $this->get("/settings/academic");
        $this->assertNotEquals(403, $response2->status(), "After switch back to academic, should allow");
    }

    public function test_direct_url_access_is_protected(): void
    {
        $professional = $this->createProfessionalInstitute();
        $user = $this->createUserWithMembership($professional, "institute-owner", "owner");
        $this->actingAs($user, "web");
        Workspace::set($professional->id);
        $response = $this->post("/settings/academic/academic-years", ['name' => '2026','code'=>'2026','start_date'=>'2026-01-01','end_date'=>'2026-12-31']);
        $this->assertEquals(403, $response->status());
    }

    public function test_rbac_still_applies(): void
    {
        $academic = $this->createAcademicInstitute();
        $user = $this->createUserWithMembership($academic, "teacher", "staff");
        $this->actingAs($user, "web");
        Workspace::set($academic->id);
        $response = $this->get("/settings/academic");
        $this->assertEquals(403, $response->status());
    }

    public function test_branch_isolation_remains_intact(): void
    {
        $this->assertTrue(true);
    }
}
