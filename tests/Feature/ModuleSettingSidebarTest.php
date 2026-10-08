<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Settings → Modules toggles must be honoured by the left sidebar.
 *
 * Regression coverage for: a tenant-disabled module staying visible in
 * the institute navbar even though the menu layer never consulted
 * institute_module_overrides.
 */
class ModuleSettingSidebarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function makeInstitute(): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['advanced'])->firstOrFail();

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'ModuleSetting Test '.uniqid(),
            'slug' => 'modulesetting-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $instId,
            'package_id' => $pkg->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        return Institute::withoutGlobalScopes()->find($instId);
    }

    private function makeUser(Institute $inst): User
    {
        $user = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $inst->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        return $user;
    }

    private function loginAs(User $user, Institute $inst): void
    {
        $this->actingAs($user, 'web');
        Workspace::set($inst->id);
    }

    public function test_disabled_medical_sub_module_group_is_hidden_from_sidebar(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $service = app(ModuleAccessService::class);
        $service->enableModule($inst, 'medical.bloodbank', $user->id, 'sidebar visibility test');

        $enabled = $this->get(route('medical.dashboard'));
        $enabled->assertOk();
        $enabled->assertSee('medicalSub_medical_bloodbank');
        $enabled->assertSee('/medical/blood-bank/donors');

        $service->disableModule($inst, 'medical.bloodbank', $user->id, 'sidebar visibility test');

        $disabled = $this->get(route('medical.dashboard'));
        $disabled->assertOk();
        $disabled->assertDontSee('medicalSub_medical_bloodbank');
        $disabled->assertDontSee('/medical/blood-bank/donors');
    }

    public function test_disabling_one_sub_module_keeps_siblings_visible(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $service = app(ModuleAccessService::class);
        $service->enableModule($inst, 'medical.bloodbank', $user->id, 'sidebar visibility test');
        $service->enableModule($inst, 'medical.radiology', $user->id, 'sidebar visibility test');
        $service->disableModule($inst, 'medical.bloodbank', $user->id, 'sidebar visibility test');

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertDontSee('medicalSub_medical_bloodbank');
        $response->assertSee('medicalSub_medical_radiology');
    }

    public function test_module_toggle_state_is_reflected_in_feature_access(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $service = app(ModuleAccessService::class);

        $service->enableModule($inst, 'medical.bloodbank', $user->id, 'feature parity test');
        $this->assertTrue($service->isEnabled($inst, 'medical.bloodbank'));
        $this->assertTrue($service->isFeatureEnabled($inst, 'medical.bloodbank'));

        $service->disableModule($inst, 'medical.bloodbank', $user->id, 'feature parity test');
        $this->assertFalse($service->isEnabled($inst, 'medical.bloodbank'));
        $this->assertFalse($service->isFeatureEnabled($inst, 'medical.bloodbank'));
    }

    public function test_disabled_accounting_hides_accounting_nav_group(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $before = $this->get(route('medical.dashboard'));
        $before->assertOk();
        $before->assertSee('accountingNavGroup');
        $before->assertSee('financeNavGroup');

        app(ModuleAccessService::class)->disableModule($inst, 'accounting', $user->id, 'sidebar visibility test');

        $after = $this->get(route('medical.dashboard'));
        $after->assertOk();
        $after->assertDontSee('accountingNavGroup');
        $after->assertSee('financeNavGroup');
    }

    public function test_disabled_reports_module_hides_medical_reports_link(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $before = $this->get(route('medical.dashboard'));
        $before->assertOk();
        $before->assertSee(route('medical.reports.daily'), false);

        app(ModuleAccessService::class)->disableModule($inst, 'reports', $user->id, 'sidebar visibility test');

        $after = $this->get(route('medical.dashboard'));
        $after->assertOk();
        $after->assertDontSee(route('medical.reports.daily'), false);
    }

    public function test_disabled_purchase_module_hides_purchase_nav_group(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $before = $this->get(route('medical.dashboard'));
        $before->assertOk();
        $before->assertSee('>Purchase<', false);

        app(ModuleAccessService::class)->disableModule($inst, 'purchase', $user->id, 'sidebar visibility test');

        $after = $this->get(route('medical.dashboard'));
        $after->assertOk();
        $after->assertDontSee('>Purchase<', false);
    }
}
