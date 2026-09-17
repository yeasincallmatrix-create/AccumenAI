<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Medical\PhysiotherapyExercise;
use App\Models\Medical\PhysiotherapyPlan;
use App\Models\Medical\PhysiotherapySession;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PhysiotherapyTest extends TestCase
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
            'name' => 'Physio Test Hospital',
            'slug' => 'physio-test-' . uniqid(),
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

        app(\App\Services\ModuleAccessService::class)
            ->enableModule($this->institute, 'medical.physiotherapy');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => 'MR-2026-00001',
        ]);
    }

    private function createPlan(array $overrides = []): PhysiotherapyPlan
    {
        return PhysiotherapyPlan::create(array_merge([
            'institute_id' => $this->institute->id,
            'plan_number' => 'PP-2026-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->owner->id,
            'chief_complaint' => 'Test complaint',
            'modality' => 'exercise',
            'sessions_planned' => 5,
            'sessions_completed' => 0,
            'frequency' => 'weekly',
            'start_date' => now(),
            'status' => 'active',
            'fee_per_session' => 0,
            'total_fee' => 0,
            'payment_status' => 'pending',
        ], $overrides));
    }

    private function createSession(PhysiotherapyPlan $plan, array $overrides = []): PhysiotherapySession
    {
        return PhysiotherapySession::create(array_merge([
            'institute_id' => $this->institute->id,
            'physiotherapy_plan_id' => $plan->id,
            'session_number' => 'PS-2026-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'session_order' => 1,
            'therapist_id' => $this->owner->id,
            'session_date' => now(),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'fee' => 0,
            'payment_status' => 'pending',
        ], $overrides));
    }

    public function test_creates_physiotherapy_plan(): void
    {
        $response = $this->post(route('medical.physiotherapy.plans.store'), [
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->owner->id,
            'chief_complaint' => 'Lower back pain',
            'modality' => 'exercise',
            'sessions_planned' => 10,
            'frequency' => 'weekly',
            'start_date' => now()->format('Y-m-d'),
            'fee_per_session' => 500,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('physiotherapy_plans', [
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->owner->id,
            'modality' => 'exercise',
            'status' => 'active',
        ]);
    }

    public function test_plan_number_unique_per_institute(): void
    {
        $this->post(route('medical.physiotherapy.plans.store'), [
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->owner->id,
            'chief_complaint' => 'Knee pain',
            'modality' => 'manual',
            'sessions_planned' => 5,
            'frequency' => 'daily',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $this->post(route('medical.physiotherapy.plans.store'), [
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->owner->id,
            'chief_complaint' => 'Shoulder stiffness',
            'modality' => 'electrotherapy',
            'sessions_planned' => 8,
            'frequency' => 'alternate_day',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $plans = PhysiotherapyPlan::where('institute_id', $this->institute->id)->get();
        $this->assertCount(2, $plans);
        $this->assertNotEquals(
            $plans[0]->plan_number,
            $plans[1]->plan_number,
            'Plan numbers must be unique per institute'
        );
        $this->assertMatchesRegularExpression('/^PP-\d{4}-\d{5}$/', $plans[0]->plan_number);
        $this->assertMatchesRegularExpression('/^PP-\d{4}-\d{5}$/', $plans[1]->plan_number);
    }

    public function test_schedules_first_session(): void
    {
        $plan = $this->createPlan();

        $response = $this->post(route('medical.physiotherapy.sessions.store', $plan), [
            'therapist_id' => $this->owner->id,
            'session_date' => now()->format('Y-m-d'),
            'duration_minutes' => 30,
            'fee' => 500,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('physiotherapy_sessions', [
            'physiotherapy_plan_id' => $plan->id,
            'institute_id' => $this->institute->id,
            'session_order' => 1,
            'status' => 'scheduled',
        ]);
    }

    public function test_attending_session_updates_plan_progress(): void
    {
        $plan = $this->createPlan(['sessions_planned' => 5]);
        $session = $this->createSession($plan);

        app(\App\Services\Medical\PhysiotherapyService::class)
            ->markAttended($session, ['treatment_given' => 'Stretching exercises']);

        $plan->refresh();
        $this->assertEquals(1, $plan->sessions_completed);
    }

    public function test_pain_reduction_calculated(): void
    {
        $plan = $this->createPlan(['sessions_planned' => 3]);
        $session = $this->createSession($plan);

        app(\App\Services\Medical\PhysiotherapyService::class)
            ->markAttended($session, [
                'pain_score_before' => 8,
                'pain_score_after' => 5,
            ]);

        $reduction = app(\App\Services\Medical\PhysiotherapyService::class)
            ->averagePainReduction($plan);

        $this->assertEquals(3.0, $reduction);
    }

    public function test_plan_auto_completes_when_sessions_reached(): void
    {
        $plan = $this->createPlan(['sessions_planned' => 1]);
        $session = $this->createSession($plan);

        app(\App\Services\Medical\PhysiotherapyService::class)
            ->markAttended($session, []);

        $plan->refresh();
        $this->assertEquals('completed', $plan->status);
    }

    public function test_discontinue_plan_with_reason(): void
    {
        $plan = $this->createPlan([
            'sessions_planned' => 10,
            'sessions_completed' => 2,
            'fee_per_session' => 500,
            'total_fee' => 5000,
            'payment_status' => 'partial',
        ]);

        app(\App\Services\Medical\PhysiotherapyService::class)
            ->discontinuePlan($plan, 'Patient relocated to another city');

        $plan->refresh();
        $this->assertEquals('discontinued', $plan->status);
        $this->assertEquals('Patient relocated to another city', $plan->discontinue_reason);
    }

    public function test_exercise_library_crud(): void
    {
        $response = $this->post(route('medical.physiotherapy.exercises.store'), [
            'name' => 'Hamstring Stretch',
            'category' => 'stretching',
            'body_area' => 'lower_back',
            'difficulty' => 'easy',
            'default_reps' => 10,
            'default_sets' => 3,
            'default_hold_seconds' => 15,
            'instructions' => 'Hold each stretch for 15 seconds',
        ]);

        $response->assertRedirect();
        $exercise = PhysiotherapyExercise::where('name', 'Hamstring Stretch')->first();
        $this->assertNotNull($exercise);
        $this->assertEquals($this->institute->id, $exercise->institute_id);

        $response = $this->get(route('medical.physiotherapy.exercises.show', $exercise));
        $response->assertStatus(200);

        $response = $this->put(route('medical.physiotherapy.exercises.update', $exercise), [
            'name' => 'Hamstring Stretch Advanced',
            'category' => 'stretching',
            'body_area' => 'lower_back',
            'difficulty' => 'medium',
            'default_reps' => 15,
            'default_sets' => 4,
            'default_hold_seconds' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('physiotherapy_exercises', [
            'id' => $exercise->id,
            'name' => 'Hamstring Stretch Advanced',
            'difficulty' => 'medium',
        ]);

        $response = $this->delete(route('medical.physiotherapy.exercises.destroy', $exercise));
        $response->assertRedirect();
        $this->assertSoftDeleted('physiotherapy_exercises', ['id' => $exercise->id]);
    }

    public function test_dashboard_shows_today_sessions(): void
    {
        $response = $this->get(route('medical.physiotherapy.dashboard'));
        $response->assertStatus(200);
    }

    public function test_tenant_isolation(): void
    {
        $plan = $this->createPlan();

        $otherInstitute = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-physio-' . uniqid(),
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

        $otherRoleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $otherOwner->id,
            'institution_id' => $otherInstitute->id,
            'role_id' => $otherRoleId,
            'status' => 'active',
        ]);

        $this->actingAs($otherOwner, 'web');
        Workspace::set($otherInstitute->id);

        app(\App\Services\ModuleAccessService::class)
            ->enableModule($otherInstitute, 'medical.physiotherapy');

        $response = $this->get(route('medical.physiotherapy.plans.show', $plan));
        $response->assertStatus(403);
    }

    public function test_audit_log_records_actions(): void
    {
        $this->post(route('medical.physiotherapy.plans.store'), [
            'patient_id' => $this->patient->id,
            'therapist_id' => $this->owner->id,
            'chief_complaint' => 'Audit test complaint',
            'modality' => 'exercise',
            'sessions_planned' => 5,
            'frequency' => 'weekly',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $plan = PhysiotherapyPlan::where('institute_id', $this->institute->id)->latest()->first();

        $this->assertDatabaseHas('clinical_audit_logs', [
            'auditable_type' => 'App\Models\Medical\PhysiotherapyPlan',
            'auditable_id' => $plan->id,
            'action' => 'created',
        ]);
    }

    public function test_permission_enforcement(): void
    {
        $viewPermission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical.physiotherapy.view'],
            ['name' => 'View Physiotherapy', 'module' => 'medical.physiotherapy']
        );

        $limitedRole = Role::create([
            'name' => 'Physio View Only',
            'slug' => 'physio-view-only-' . uniqid(),
            'status' => 'active',
        ]);
        $limitedRole->permissions()->attach($viewPermission->id);

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $limitedRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        app(\App\Services\ModuleAccessService::class)
            ->enableModule($this->institute, 'medical.physiotherapy');

        $response = $this->get(route('medical.physiotherapy.dashboard'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.physiotherapy.plans.store'), [
            'patient_id' => $this->patient->id,
            'therapist_id' => $staff->id,
            'modality' => 'exercise',
            'sessions_planned' => 5,
            'frequency' => 'weekly',
            'start_date' => now()->format('Y-m-d'),
        ]);
        $response->assertStatus(403);
    }
}
