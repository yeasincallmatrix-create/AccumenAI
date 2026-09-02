<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BusinessProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function makeInstitute(string $name, string $industry, string $subIndustry): Institute
    {
        $c = $this->country();
        return Institute::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'country' => $c->name,
            'country_id' => $c->id,
            'industry' => $industry,
            'sub_industry' => $subIndustry,
            'status' => 'active',
            'verified' => true,
            'phone' => '017'.mt_rand(10000000, 99999999),
            'email' => uniqid().'@test.test',
            'address' => 'Test Address',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'upazila' => 'Dhanmondi',
            'postal_code' => '1209',
        ]);
    }

    private function createInstituteUser(Institute $inst, string $role = 'institute-owner'): InstituteUser
    {
        $u = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid().'@test.test',
            'phone' => '017'.mt_rand(10000000, 99999999),
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        // Force verified state
        $u->forceFill(['email_verified_at' => now()])->save();
        return $u->fresh();
    }

    private function createOwner(string $email): User
    {
        $u = (new UserAccountService)->registerOwner([
            'name' => 'Owner',
            'first_name' => 'Owner',
            'last_name' => 'Test',
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
        ]);
        $u->forceFill(['email_verified_at' => now()])->save();
        return $u->fresh();
    }

    private function assign(User $user, Institute $inst, string $roleSlug = 'institute-owner', array $attrs = []): \App\Models\Membership
    {
        $roleId = Role::where('slug', $roleSlug)->firstOrFail()->id;
        return (new MembershipService)->assign($user, $inst->id, $roleId, $attrs);
    }

    private function asWeb(User $user, int $workspaceId)
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    // 1. authenticated user can open active business profile
    public function test_authenticated_user_can_open_active_business_profile(): void
    {
        $inst = $this->makeInstitute('TBN Dance Academy', 'training_center', 'dance_academy');
        $user = $this->createInstituteUser($inst);
        $this->actingAs($user, 'institute_user')
            ->get(route('business.profile'))
            ->assertOk()
            ->assertSee('TBN Dance Academy')
            ->assertSee('Business Profile');
    }

    // 2. profile resolves current Workspace
    public function test_profile_resolves_current_workspace(): void
    {
        $inst = $this->makeInstitute('Active Biz', 'training_center', 'dance_academy');
        $owner = $this->createOwner('ws-resolve-'.uniqid().'@test.test');
        $this->assign($owner, $inst);
        $this->asWeb($owner, (int) $inst->id)
            ->get(route('business.profile'))
            ->assertOk()
            ->assertSee($inst->name);
    }

    // 3. profile never trusts institute_id from request
    public function test_profile_never_trusts_institute_id_from_request(): void
    {
        $instA = $this->makeInstitute('Business A', 'training_center', 'dance_academy');
        $instB = $this->makeInstitute('Business B', 'education', 'school');
        $user = $this->createInstituteUser($instA);
        // Try to smuggle institute_id via query / post
        $this->actingAs($user, 'institute_user')
            ->get(route('business.profile', ['institute_id' => $instB->id]))
            ->assertOk()
            ->assertSee($instA->name)
            ->assertDontSee($instB->name);
    }

    // 4. cross-business profile access is blocked
    public function test_cross_business_profile_access_is_blocked(): void
    {
        $instA = $this->makeInstitute('Business A Cross', 'training_center', 'dance_academy');
        $instB = $this->makeInstitute('Business B Cross', 'retail', 'general_store');
        $owner = $this->createOwner('cross-'.uniqid().'@test.test');
        // Owner belongs only to A, try to view B via forged workspace session
        $this->assign($owner, $instA);
        // With forged workspace B — SetTenantContext should clear / controller should not leak B
        $this->asWeb($owner, (int) $instB->id)
            ->get(route('business.profile'))
            ->assertOk()
            ->assertSee($instA->name)
            ->assertDontSee($instB->name);
    }

    // 5. multi-business user sees active business only — main profile header must show active only (sidebar switcher legitimately lists both, so we check the profile heading, not global dontSee)
    public function test_multi_business_user_sees_active_business_only(): void
    {
        $instA = $this->makeInstitute('Multi A '.uniqid(), 'training_center', 'dance_academy');
        $instB = $this->makeInstitute('Multi B '.uniqid(), 'education', 'school');
        $owner = $this->createOwner('multi-'.uniqid().'@test.test');
        $this->assign($owner, $instA);
        $this->assign($owner, $instB);

        $respA = $this->asWeb($owner, (int) $instA->id)->get(route('business.profile'));
        $respA->assertOk()->assertSee($instA->name);
        // Heading must be Active A (first h1). Sidebars legitimately list B, so we verify profile's Business Information block shows A
        $respA->assertSee('<dt class="col-sm-5">Business Name</dt><dd class="col-sm-7">'.$instA->name.'</dd>', false);

        $respB = $this->asWeb($owner, (int) $instB->id)->get(route('business.profile'));
        $respB->assertOk()->assertSee($instB->name);
        $respB->assertSee('<dt class="col-sm-5">Business Name</dt><dd class="col-sm-7">'.$instB->name.'</dd>', false);
    }

    // 6. switching workspace changes displayed business
    public function test_switching_workspace_changes_displayed_business(): void
    {
        $instA = $this->makeInstitute('Switch A', 'training_center', 'dance_academy');
        $instB = $this->makeInstitute('Switch B', 'manufacturing', 'garments');
        $owner = $this->createOwner('switch-'.uniqid().'@test.test');
        $this->assign($owner, $instA);
        $this->assign($owner, $instB);

        // Initial workspace A
        $this->asWeb($owner, (int) $instA->id)->get(route('business.profile'))->assertSee($instA->name);

        // Switch via endpoint then profile should show B
        $this->asWeb($owner, (int) $instA->id)->post(route('workspace.switch', $instB->id))->assertRedirect(route('dashboard'));
        // After switch, session should be B — simulate next request with B session
        $this->asWeb($owner, (int) $instB->id)->get(route('business.profile'))->assertSee($instB->name);
    }

    // 7. academic business shows academic-oriented sections
    public function test_academic_business_shows_academic_sections(): void
    {
        $inst = $this->makeInstitute('Academic School', 'education', 'school');
        $user = $this->createInstituteUser($inst);
        $this->actingAs($user, 'institute_user')
            ->get(route('business.profile'))
            ->assertOk()
            ->assertSee('Academic Overview')
            ->assertSee('Academic institution')
            ->assertDontSee('Training Overview');
    }

    // 8. professional business shows professional-oriented sections
    public function test_professional_business_shows_professional_sections(): void
    {
        $inst = $this->makeInstitute('TBN Dance Academy Prof', 'training_center', 'dance_academy');
        $user = $this->createInstituteUser($inst);
        $this->actingAs($user, 'institute_user')
            ->get(route('business.profile'))
            ->assertOk()
            ->assertSee('Training Overview')
            ->assertSee('Professional domain')
            ->assertDontSee('Academic Overview');
    }

    // 9. retail/manufacturing/service/transportation/restaurant render without academic UI
    public function test_other_industries_render_without_academic_ui(): void
    {
        $cases = [
            ['Retail Shop '.uniqid(), 'retail', 'general_store'],
            ['Mfg Co '.uniqid(), 'manufacturing', 'garments'],
            ['Svc Co '.uniqid(), 'service', null],
            ['Trans Co '.uniqid(), 'transportation', null],
            ['Resto '.uniqid(), 'restaurant', null],
        ];
        foreach ($cases as [$fullName, $ind, $sub]) {
            TenantContext::clear(); BranchContext::clear(); Workspace::clear();
            $inst = $this->makeInstitute($fullName, $ind, $sub ?? '');
            if ($sub === null) {
                \DB::table('institutes')->where('id', $inst->id)->update(['sub_industry' => null]);
                $inst->refresh();
            }
            $user = $this->createInstituteUser($inst);
            $this->actingAs($user, 'institute_user')
                ->get(route('business.profile'))
                ->assertOk()
                ->assertSee($fullName)
                ->assertDontSee('Academic Overview')
                ->assertDontSee('Training Overview');
        }
    }

    // 10. tenant isolation
    public function test_tenant_isolation(): void
    {
        $instA = $this->makeInstitute('Tenant A', 'training_center', 'dance_academy');
        $instB = $this->makeInstitute('Tenant B', 'training_center', 'dance_academy');
        Branch::create(['institute_id' => $instB->id, 'name' => 'Secret Branch B', 'status' => 'active']);
        Branch::create(['institute_id' => $instA->id, 'name' => 'Visible Branch A', 'status' => 'active']);
        $userA = $this->createInstituteUser($instA);
        $this->actingAs($userA, 'institute_user')
            ->get(route('business.profile'))
            ->assertSee('Visible Branch A')
            ->assertDontSee('Secret Branch B');
    }

    // 11. branch isolation where applicable — branch_scoped user still sees correct branches of active institute
    public function test_branch_isolation(): void
    {
        $inst = $this->makeInstitute('Branch Isol Inst', 'training_center', 'dance_academy');
        $other = $this->makeInstitute('Branch Other', 'training_center', 'dance_academy');
        Branch::create(['institute_id' => $inst->id, 'name' => 'Inst Branch 1', 'status' => 'active']);
        Branch::create(['institute_id' => $other->id, 'name' => 'Other Branch X', 'status' => 'active']);
        $user = $this->createInstituteUser($inst);
        $this->actingAs($user, 'institute_user')
            ->get(route('business.profile'))
            ->assertSee('Inst Branch 1')
            ->assertDontSee('Other Branch X');
    }

    // 12. unauthorized user blocked
    public function test_unauthorized_user_blocked(): void
    {
        $this->get(route('business.profile'))->assertRedirect();
        // Platform admin or guest without institute context -> 403 or redirect to login
    }

    // 13. sensitive subscription/config values never rendered
    public function test_sensitive_values_never_rendered(): void
    {
        $inst = $this->makeInstitute('Sensitive Biz '.uniqid(), 'training_center', 'dance_academy');
        $pkg = SubscriptionPackage::first();
        if ($pkg === null) {
            $pkg = SubscriptionPackage::create(['name' => 'TestPkg', 'slug' => 'testpkg-'.uniqid(), 'status' => 'active']);
        }
        \DB::table('institutes')->where('id', $inst->id)->update(['package_id' => $pkg->id]);
        $inst->refresh();
        $uniquePriceSecret = 'SECRET_PRICE_'.uniqid();
        $uniqueRef = 'SECRET_REF_'.uniqid();
        InstituteSubscription::create([
            'institute_id' => $inst->id,
            'package_id' => $pkg->id,
            'billing_cycle' => 'monthly',
            'price_paid' => 12345,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'payment_reference' => $uniqueRef,
            'status' => 'active',
        ]);
        // Store a decryptable sensitive value in institute_settings to prove it is not leaked
        \DB::table('institute_settings')->updateOrInsert(['institute_id' => $inst->id], [
            'smtp_password_enc' => $uniquePriceSecret,
            'timezone' => 'Asia/Dhaka', 'language' => 'en', 'theme' => 'default', 'primary_color' => '#0d6efd', 'secondary_color' => '#6c757d',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = $this->createInstituteUser($inst);
        $resp = $this->actingAs($user, 'institute_user')->get(route('business.profile'));
        $resp->assertOk();
        $resp->assertDontSee($uniqueRef);
        $resp->assertDontSee($uniquePriceSecret);
        $resp->assertDontSee('smtp_password_enc');
        $resp->assertDontSee('sms_api_key_enc');
        $resp->assertDontSee('payment_config_enc');
    }

    // 14. nonexistent/missing workspace handled safely
    public function test_missing_workspace_handled_safely(): void
    {
        $owner = $this->createOwner('missing-ws-'.uniqid().'@test.test');
        // No membership at all — middleware will try to resolve fallback (single membership) but none exists → 403 or redirect to workspace picker/verify
        $resp = $this->asWeb($owner, 999999)->get(route('business.profile'));
        $this->assertTrue(in_array($resp->status(), [302, 403], true), 'Expected 302 or 403 for missing workspace, got '.$resp->status());
    }

    // 15. topbar business name links to business.profile
    public function test_topbar_business_name_links_to_business_profile(): void
    {
        $inst = $this->makeInstitute('Topbar Biz', 'training_center', 'dance_academy');
        $user = $this->createInstituteUser($inst);
        $resp = $this->actingAs($user, 'institute_user')->get(route('business.profile'));
        $resp->assertOk();
        // The profile page itself contains a breadcrumb/dashboard link; topbar via layout is harder to isolate,
        // but we assert the route exists and the dashboard contains the profile link when rendered via institute layout
        $dashboard = $this->actingAs($user, 'institute_user')->get(route('dashboard'));
        $dashboard->assertOk();
        // Dashboard renders layouts.institute which now points brand to business.profile
        $dashboard->assertSee(route('business.profile'), false);
    }

    // Additional: ensure institute_id param via query is ignored (IDOR)
    public function test_idor_via_query_param_is_ignored(): void
    {
        $instA = $this->makeInstitute('IDOR A', 'training_center', 'dance_academy');
        $instB = $this->makeInstitute('IDOR B', 'retail', 'general_store');
        $owner = $this->createOwner('idor-'.uniqid().'@test.test');
        $this->assign($owner, $instA);
        // Owner of A tries ?institute_id=B
        $this->asWeb($owner, (int) $instA->id)->get(route('business.profile').'?institute_id='.$instB->id.'&id='.$instB->id)
            ->assertOk()->assertSee($instA->name)->assertDontSee($instB->name);
    }
}
