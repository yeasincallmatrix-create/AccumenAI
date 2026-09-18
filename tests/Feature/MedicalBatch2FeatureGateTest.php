<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\Membership;
use App\Models\PackageFeature;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MedicalBatch2FeatureGateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('feature_registry or package_features table does not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }
    }

    private function makeInstitute(string $packageSlug): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($packageSlug)])->firstOrFail();
        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Batch2 Test '.uniqid(),
            'slug' => 'batch2-'.uniqid(),
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
        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $inst->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);
        return $user;
    }

    private function loginAs(User $user, Institute $inst): void
    {
        $this->actingAs($user, 'web');
        Workspace::set($inst->id);
    }

    // ── Billing ────────────────────────────────────────────

    public function test_billing_menu_normal_when_enabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertSee('/medical/billing/invoices');
        $response->assertDontSee('menu-item-locked');
    }

    public function test_billing_menu_locked_when_disabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        PackageFeature::where('feature_key', 'medical.billing')
            ->where('package_id', $inst->package_id)
            ->delete();

        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertSee('menu-item-locked');
        $response->assertSee('medical.billing');
    }

    public function test_billing_route_blocked_when_feature_disabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        PackageFeature::where('feature_key', 'medical.billing')
            ->where('package_id', $inst->package_id)
            ->delete();

        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.billing.invoices.index'));
        $response->assertStatus(403);
    }

    // ── Emergency ──────────────────────────────────────────

    public function test_emergency_menu_normal_when_enabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertSee('/medical/emergency/visits');
        $response->assertDontSee('menu-item-locked');
    }

    public function test_emergency_menu_locked_when_disabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        PackageFeature::where('feature_key', 'medical.emergency')
            ->where('package_id', $inst->package_id)
            ->delete();

        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertSee('menu-item-locked');
        $response->assertSee('medical.emergency');
    }

    public function test_emergency_route_blocked_when_feature_disabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        PackageFeature::where('feature_key', 'medical.emergency')
            ->where('package_id', $inst->package_id)
            ->delete();

        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.emergency.index'));
        $response->assertStatus(403);
    }

    // ── Ambulance ──────────────────────────────────────────

    public function test_ambulance_menu_normal_when_enabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertSee('/medical/ambulance/vehicles');
        $response->assertDontSee('menu-item-locked');
    }

    public function test_ambulance_menu_locked_when_disabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        PackageFeature::where('feature_key', 'medical.ambulance')
            ->where('package_id', $inst->package_id)
            ->delete();

        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.dashboard'));
        $response->assertOk();
        $response->assertSee('menu-item-locked');
        $response->assertSee('medical.ambulance');
    }

    public function test_ambulance_route_blocked_when_feature_disabled(): void
    {
        $inst = $this->makeInstitute('advanced');
        PackageFeature::where('feature_key', 'medical.ambulance')
            ->where('package_id', $inst->package_id)
            ->delete();

        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $response = $this->get(route('medical.ambulance.vehicles.index'));
        $response->assertStatus(403);
    }

    // ── Regression ──────────────────────────────────────────

    public function test_batch_1_children_still_work(): void
    {
        $inst = $this->makeInstitute('advanced');
        $user = $this->makeUser($inst);
        $this->loginAs($user, $inst);

        $this->get(route('medical.pharmacy.medicines.index'))->assertOk();
    }
}
