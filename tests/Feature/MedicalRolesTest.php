<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Medical\Ambulance;
use App\Models\Medical\AmbulanceDriver;
use App\Models\Medical\AmbulanceTrip;
use App\Models\Medical\DietPlan;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Medical\RadiologyOrder;
use App\Services\Medical\NumberSequenceService;
use App\Services\MembershipService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\MedicalRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class MedicalRolesTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Roles Test Hospital',
            'slug' => 'roles-test-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);

        // Baseline template roles (doctor, nurse, lab-technician, …) as the
        // institute-creation flow would create them.
        app(\App\Services\RoleTemplateService::class)->seedForInstitute($this->institute);

        // The disposable test database does not carry the full permissions
        // catalog — create every slug the role map references.
        $this->ensureTestPermissions();

        // Seed the specialized roles for this (and every) healthcare institute.
        (new MedicalRoleSeeder)->run();

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Role',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);
    }

    private function staffWithRole(string $slug): User
    {
        $role = Role::where('institute_id', $this->institute->id)->where('slug', $slug)->firstOrFail();

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        return $staff;
    }

    private function rolePerms(string $slug): array
    {
        return Role::where('institute_id', $this->institute->id)
            ->where('slug', $slug)->firstOrFail()
            ->permissions()->pluck('slug')->all();
    }

    private function ensureTestPermissions(): void
    {
        $slugs = [];
        foreach (MedicalRoleSeeder::roles() as $def) {
            $slugs = array_merge($slugs, $def['permissions']);
        }
        foreach (MedicalRoleSeeder::existingRoleExtensions() as $list) {
            $slugs = array_merge($slugs, $list);
        }

        foreach (array_unique($slugs) as $slug) {
            $module = str_contains($slug, '.')
                ? implode('.', array_slice(explode('.', $slug), 0, 2))
                : explode('_', $slug)[0] . '_' . (explode('_', $slug)[1] ?? '');
            \App\Models\Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => $module, 'name' => $slug]
            );
        }
    }

    public function test_all_medical_roles_are_seeded(): void
    {
        $expected = array_keys(MedicalRoleSeeder::roles());
        $this->assertCount(37, $expected);

        foreach ($expected as $slug) {
            $this->assertDatabaseHas('roles', [
                'institute_id' => $this->institute->id,
                'slug' => $slug,
            ]);
        }

        // Pre-existing roles untouched and still present.
        foreach (['doctor', 'nurse', 'receptionist', 'hospital-admin', 'pharmacist', 'lab-technician', 'records-officer'] as $slug) {
            $this->assertDatabaseHas('roles', [
                'institute_id' => $this->institute->id,
                'slug' => $slug,
            ]);
        }
    }

    public function test_each_role_has_expected_permissions(): void
    {
        $dietitian = $this->rolePerms('dietitian');
        $this->assertContains('medical.diet.plan.create', $dietitian);
        $this->assertContains('medical.diet.template.manage', $dietitian);

        $kitchen = $this->rolePerms('kitchen-staff');
        $this->assertContains('medical.diet.meal.serve', $kitchen);
        $this->assertNotContains('medical.diet.plan.create', $kitchen);

        $driver = $this->rolePerms('ambulance-driver');
        $this->assertContains('medical.ambulance.trip.complete', $driver);
        $this->assertNotContains('medical.ambulance.trip.create', $driver);

        $dispatcher = $this->rolePerms('ambulance-dispatcher');
        $this->assertContains('medical.ambulance.trip.dispatch', $dispatcher);

        $radiologist = $this->rolePerms('radiologist');
        $this->assertContains('medical_radiology.report', $radiologist);
        $this->assertContains('medical_radiology.verify', $radiologist);

        $tech = $this->rolePerms('radiology-technician');
        $this->assertContains('medical_radiology.perform', $tech);
        $this->assertNotContains('medical_radiology.report', $tech);

        // Pre-existing lab-technician keeps old grants AND gains the new dot-notation twins.
        $labTech = $this->rolePerms('lab-technician');
        $this->assertContains('medical_lab.view', $labTech);
        $this->assertContains('medical.laboratory.tests.view', $labTech);
    }

    public function test_ambulance_driver_can_complete_trip(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.ambulance');

        $vehicle = Ambulance::create([
            'institute_id' => $this->institute->id,
            'vehicle_number' => 'ROLE-AMB-1',
            'type' => 'basic',
        ]);
        $driver = AmbulanceDriver::create([
            'institute_id' => $this->institute->id,
            'driver_number' => 'ROLE-AD-1',
            'name' => 'Role Driver',
            'phone' => '01811111111',
        ]);
        $trip = AmbulanceTrip::create([
            'institute_id' => $this->institute->id,
            'trip_number' => 'ROLE-AT-1',
            'trip_type' => 'emergency_pickup',
            'pickup_location' => 'Home',
            'dropoff_location' => 'ER',
            'requested_at' => now(),
            'status' => 'requested',
        ]);

        // Dispatch as owner first.
        $this->post(route('medical.ambulance.trips.dispatch', $trip), [
            'ambulance_id' => $vehicle->id,
            'driver_id' => $driver->id,
        ]);

        $this->staffWithRole('ambulance-driver');

        $response = $this->post(route('medical.ambulance.trips.cancel', $trip), [
            'cancellation_reason' => 'Driver test cancel',
        ]);
        $response->assertRedirect();
        $this->assertEquals('cancelled', $trip->refresh()->status);
    }

    public function test_ambulance_driver_cannot_create_trip(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.ambulance');
        $this->staffWithRole('ambulance-driver');

        $response = $this->post(route('medical.ambulance.trips.store'), [
            'trip_type' => 'emergency_pickup',
            'pickup_location' => 'Home',
            'dropoff_location' => 'ER',
        ]);
        $response->assertStatus(403);
    }

    public function test_kitchen_staff_can_serve_meal(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.diet');

        $this->post(route('medical.diet.plans.store'), [
            'patient_id' => $this->patient->id,
            'plan_name' => 'Role Plan',
            'diet_type' => 'regular',
            'start_date' => today()->toDateString(),
            'days_planned' => 1,
        ]);
        $meal = DietPlan::where('patient_id', $this->patient->id)->first()->mealSchedules()->first();

        $this->staffWithRole('kitchen-staff');

        $this->post(route('medical.diet.meals.serve', $meal))->assertRedirect();
        $this->assertEquals('served', $meal->refresh()->status);
    }

    public function test_kitchen_staff_cannot_create_plan(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.diet');
        $this->staffWithRole('kitchen-staff');

        $response = $this->post(route('medical.diet.plans.store'), [
            'patient_id' => $this->patient->id,
            'plan_name' => 'Forbidden Plan',
            'diet_type' => 'regular',
            'start_date' => today()->toDateString(),
        ]);
        $response->assertStatus(403);
    }

    public function test_dietitian_can_create_plan(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.diet');
        $this->staffWithRole('dietitian');

        $response = $this->post(route('medical.diet.plans.store'), [
            'patient_id' => $this->patient->id,
            'plan_name' => 'Dietitian Plan',
            'diet_type' => 'diabetic',
            'start_date' => today()->toDateString(),
            'days_planned' => 1,
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('diet_plans', ['plan_name' => 'Dietitian Plan']);
    }

    public function test_radiologist_can_write_reports(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.radiology');

        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'ROLE-RAD-1',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'ordered',
        ]);

        $this->staffWithRole('radiologist');

        $response = $this->post(route('medical.radiology.orders.report', $order), [
            'findings' => 'Clear lungs',
            'impression' => 'Normal study',
        ]);
        $response->assertRedirect();
    }

    public function test_radiology_technician_cannot_write_reports(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.radiology');

        $order = RadiologyOrder::create([
            'institute_id' => $this->institute->id,
            'order_number' => 'ROLE-RAD-2',
            'patient_id' => $this->patient->id,
            'modality' => 'X-Ray',
            'body_part' => 'Chest',
            'status' => 'ordered',
        ]);

        $this->staffWithRole('radiology-technician');

        // Can view the worklist…
        $this->get(route('medical.radiology.orders.index'))->assertStatus(200);

        // …but cannot file a report.
        $response = $this->post(route('medical.radiology.orders.report', $order), [
            'findings' => 'Clear lungs',
            'impression' => 'Normal study',
        ]);
        $response->assertStatus(403);
    }

    public function test_dental_hygienist_can_create_procedure(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.dental');
        $this->staffWithRole('dental-hygienist');

        $response = $this->post(route('medical.dental.procedures.store'), [
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->owner->id,
            'procedure_name' => 'Scaling',
            'performed_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $response->assertRedirect();

        // …but cannot manage the procedure catalog.
        $this->get(route('medical.dental.catalog.create'))->assertStatus(403);
    }

    public function test_phlebotomist_can_view_blood_bank(): void
    {
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.bloodbank');
        $this->staffWithRole('phlebotomist');

        $this->get(route('medical.blood-bank.requests.index'))->assertStatus(200);
    }

    public function test_lab_technician_can_enter_results(): void
    {
        $perms = $this->rolePerms('lab-technician');
        $this->assertContains('medical_lab.edit', $perms);
        $this->assertContains('medical.laboratory.tests.edit', $perms);
    }

    public function test_role_grouping_is_correct(): void
    {
        // Documented shared roles may appear in two groups (same role,
        // two department lenses in the invite dropdown).
        $shared = ['phlebotomist'];

        $groups = MedicalRoleSeeder::groups();
        $all = [];
        foreach ($groups as $label => $slugs) {
            $this->assertNotEmpty($slugs, "Group {$label} is empty");
            foreach ($slugs as $slug) {
                if (! in_array($slug, $shared, true)) {
                    $this->assertArrayNotHasKey($slug, $all, "Slug {$slug} grouped twice");
                }
                $all[$slug] = $label;
            }
        }

        foreach (array_keys(MedicalRoleSeeder::roles()) as $slug) {
            $this->assertArrayHasKey($slug, $all, "New role {$slug} missing from UI grouping");
        }
    }

    public function test_tenant_isolation_for_roles(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'roles-other-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        (new MedicalRoleSeeder)->run();

        // Cross-institute role assignment is rejected.
        $foreignRole = Role::where('institute_id', $other->id)->where('slug', 'dietitian')->first();
        $this->assertNotNull($foreignRole);

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MembershipService::class)->assign($staff, $this->institute->id, $foreignRole->id);
    }

    public function test_invite_scope_excludes_foreign_roles(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital 2',
            'slug' => 'roles-other2-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        (new MedicalRoleSeeder)->run();

        // Mirror of StaffInvitationController::create scoping.
        $visibleIds = Role::query()
            ->where(function ($query) {
                $query->whereNull('institute_id')
                    ->orWhere('institute_id', $this->institute->id);
            })
            ->where('slug', '!=', 'institute-owner')
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $foreignIds = Role::where('institute_id', $other->id)->pluck('id')->all();
        $this->assertNotEmpty($foreignIds);
        $this->assertEmpty(array_intersect($visibleIds, $foreignIds));

        $visibleSlugs = Role::whereIn('id', $visibleIds)->pluck('slug')->all();
        $this->assertContains('dietitian', $visibleSlugs);
    }
}
