<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\EmergencyVisit;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmergencyVisitTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Emergency Test Hospital',
            'slug' => 'emergency-test-'.uniqid(),
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

        // Enable emergency module for this institute
        app(\App\Services\ModuleAccessService::class)
            ->enableModule($this->institute, 'medical.emergency');
    }

    public function test_emergency_dashboard_loads(): void
    {
        $response = $this->get(route('medical.emergency.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Triage Board');
    }

    public function test_emergency_index_loads(): void
    {
        $response = $this->get(route('medical.emergency.index'));
        $response->assertStatus(200);
        $response->assertSee('Emergency Visits');
    }

    public function test_create_emergency_visit(): void
    {
        $response = $this->post(route('medical.emergency.store'), [
            'patient_name_temp' => 'Test Patient',
            'patient_age'       => 30,
            'patient_gender'    => 'male',
            'triage_level'      => 'yellow',
            'arrival_mode'      => 'ambulance',
            'chief_complaint'   => 'Chest pain',
            'arrived_at'        => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('emergency_visits', [
            'institute_id' => $this->institute->id,
            'patient_name_temp' => 'Test Patient',
            'triage_level' => 'yellow',
        ]);
    }

    public function test_triage_visit(): void
    {
        $visit = EmergencyVisit::create([
            'institute_id'  => $this->institute->id,
            'visit_number'  => 'ER-2026-00001',
            'arrived_at'    => now(),
            'status'        => 'registered',
        ]);

        $response = $this->post(route('medical.emergency.triage', $visit), [
            'triage_level' => 'orange',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('emergency_visits', [
            'id' => $visit->id,
            'triage_level' => 'orange',
            'status' => 'triaged',
        ]);
    }

    public function test_discharge_visit(): void
    {
        $visit = EmergencyVisit::create([
            'institute_id'  => $this->institute->id,
            'visit_number'  => 'ER-2026-00002',
            'arrived_at'    => now(),
            'status'        => 'attended',
        ]);

        $response = $this->post(route('medical.emergency.discharge', $visit), [
            'disposition'       => 'discharge',
            'treatment_given'   => 'Painkillers',
            'disposition_notes' => 'Stable',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('emergency_visits', [
            'id' => $visit->id,
            'status' => 'discharged',
            'disposition' => 'discharge',
        ]);
    }

    public function test_tenant_isolation(): void
    {
        // Create another institute and owner
        $otherInstitute = Institute::create([
            'name' => 'Other Test Hospital',
            'slug' => 'other-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $otherOwner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $otherOwner->id,
            'institution_id' => $otherInstitute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        app(\App\Services\ModuleAccessService::class)
            ->enableModule($otherInstitute, 'medical.emergency');

        // Create visit in this institute
        EmergencyVisit::create([
            'institute_id' => $this->institute->id,
            'visit_number' => 'ER-2026-00100',
            'arrived_at'   => now(),
            'status'       => 'waiting',
        ]);

        // Other owner should not see it
        $this->actingAs($otherOwner, 'web');
        Workspace::set($otherInstitute->id);

        $response = $this->get(route('medical.emergency.index'));
        $response->assertStatus(200);
        $response->assertDontSee('ER-2026-00100');
    }

    public function test_emergency_permissions_exist(): void
    {
        $permCount = DB::table('permissions')
            ->where('module', 'medical_emergency')
            ->count();

        $this->assertGreaterThanOrEqual(6, $permCount);
    }

    public function test_walk_in_registration(): void
    {
        $response = $this->post(route('medical.emergency.store'), [
            'patient_name_temp' => 'Walk-in Patient',
            'arrival_mode'      => 'walk_in',
            'chief_complaint'   => 'Headache',
            'arrived_at'        => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('emergency_visits', [
            'institute_id'     => $this->institute->id,
            'patient_name_temp' => 'Walk-in Patient',
            'arrival_mode'      => 'walk_in',
        ]);
    }
}
