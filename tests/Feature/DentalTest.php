<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\ModuleRegistry;
use App\Models\Role;
use App\Models\User;
use App\Models\Medical\Patient;
use App\Models\Medical\Doctor;
use App\Models\Medical\DentalChart;
use App\Models\Medical\DentalProcedure;
use App\Models\Medical\DentalTreatmentPlan;
use App\Models\Medical\DentalProcedureCatalog;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\ClinicalAuditLog;
use App\Services\Medical\DentalService;
use App\Services\Medical\NumberSequenceService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class DentalTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;
    private User $owner;
    private Patient $patient;
    private User $dentist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Dental Test Hospital',
            'slug' => 'dental-test-' . uniqid(),
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

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.dental');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);

        $this->dentist = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_creates_dental_chart_for_patient()
    {
        $chart = DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $this->assertDatabaseHas('dental_charts', [
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
        ]);
        $this->assertEquals(32, $chart->total_teeth);
    }

    public function test_updates_tooth_condition()
    {
        $chart = DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $service = app(DentalService::class);
        $service->updateTooth($chart, '11', 'caries', 'Small lesion');

        $chart->refresh();
        $conditions = $chart->tooth_conditions;
        $this->assertArrayHasKey('11', $conditions);
        $this->assertEquals('caries', $conditions['11']['condition']);
        $this->assertEquals('Small lesion', $conditions['11']['notes']);
    }

    public function test_recalculates_chart_counts()
    {
        $chart = DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $service = app(DentalService::class);
        $service->updateTooth($chart, '11', 'caries');
        $service->updateTooth($chart, '12', 'caries');
        $service->updateTooth($chart, '21', 'filled');
        $service->updateTooth($chart, '31', 'missing');

        $chart->refresh();
        $this->assertEquals(2, $chart->caries_count);
        $this->assertEquals(1, $chart->filled_count);
        $this->assertEquals(1, $chart->missing_count);
        $this->assertEquals(0, $chart->crown_count);
        $this->assertEquals(0, $chart->rct_count);
    }

    public function test_dental_chart_unique_per_patient()
    {
        DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);
    }

    public function test_creates_dental_procedure()
    {
        $chart = DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $seq = app(NumberSequenceService::class);
        $procedureNumber = $seq->next(NumberSequence::TYPE_DENTAL_PROCEDURE, $this->institute->id);

        $procedure = DentalProcedure::create([
            'institute_id' => $this->institute->id,
            'procedure_number' => $procedureNumber,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'procedure_name' => 'Composite Filling',
            'procedure_code' => 'D2391',
            'category' => 'restorative',
            'tooth_number' => '11',
            'performed_at' => now(),
            'fee' => 5000,
        ]);

        $this->assertDatabaseHas('dental_procedures', [
            'procedure_number' => $procedureNumber,
            'patient_id' => $this->patient->id,
        ]);
        $this->assertEquals('restorative', $procedure->category);
    }

    public function test_procedure_number_unique_per_institute()
    {
        $seq = app(NumberSequenceService::class);
        $num = $seq->next(NumberSequence::TYPE_DENTAL_PROCEDURE, $this->institute->id);

        DentalProcedure::create([
            'institute_id' => $this->institute->id,
            'procedure_number' => $num,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'procedure_name' => 'Test Procedure',
            'performed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        DentalProcedure::create([
            'institute_id' => $this->institute->id,
            'procedure_number' => $num,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'procedure_name' => 'Duplicate',
            'performed_at' => now(),
        ]);
    }

    public function test_procedure_links_to_chart()
    {
        $chart = DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $procedure = DentalProcedure::create([
            'institute_id' => $this->institute->id,
            'procedure_number' => 'DP-2026-00001',
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'procedure_name' => 'Root Canal',
            'dental_chart_id' => $chart->id,
            'performed_at' => now(),
        ]);

        $this->assertNotNull($procedure->dentalChart);
        $this->assertEquals($chart->id, $procedure->dental_chart_id);
    }

    public function test_creates_treatment_plan()
    {
        $seq = app(NumberSequenceService::class);
        $planNumber = $seq->next(NumberSequence::TYPE_DENTAL_PLAN, $this->institute->id);

        $steps = [
            ['procedure' => 'Scaling', 'tooth' => null, 'estimated_fee' => 1500, 'status' => 'completed'],
            ['procedure' => 'Filling', 'tooth' => '11', 'estimated_fee' => 3000, 'status' => 'pending'],
        ];

        $plan = DentalTreatmentPlan::create([
            'institute_id' => $this->institute->id,
            'plan_number' => $planNumber,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'chief_complaint' => 'Toothache and sensitivity',
            'diagnosis' => 'Dental caries on tooth 11',
            'planned_steps' => $steps,
            'total_steps' => 2,
            'completed_steps' => 1,
            'start_date' => now(),
            'total_estimated_fee' => 4500,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('dental_treatment_plans', [
            'plan_number' => $planNumber,
            'patient_id' => $this->patient->id,
        ]);
        $this->assertEquals(2, $plan->total_steps);
        $this->assertEquals(1, $plan->completed_steps);
    }

    public function test_completes_plan_step()
    {
        $steps = [
            ['procedure' => 'Scaling', 'status' => 'pending'],
            ['procedure' => 'Filling', 'status' => 'pending'],
        ];

        $plan = DentalTreatmentPlan::create([
            'institute_id' => $this->institute->id,
            'plan_number' => 'DT-2026-00001',
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'chief_complaint' => 'Toothache',
            'planned_steps' => $steps,
            'total_steps' => 2,
            'completed_steps' => 0,
            'start_date' => now(),
            'status' => 'active',
        ]);

        $service = app(DentalService::class);
        $service->completeStep($plan, 0);

        $plan->refresh();
        $this->assertEquals(1, $plan->completed_steps);
        $this->assertEquals('completed', $plan->planned_steps[0]['status']);
        $this->assertEquals('active', $plan->status);
    }

    public function test_plan_auto_completes_when_all_steps_done()
    {
        $steps = [
            ['procedure' => 'Scaling', 'status' => 'completed'],
            ['procedure' => 'Filling', 'status' => 'pending'],
        ];

        $plan = DentalTreatmentPlan::create([
            'institute_id' => $this->institute->id,
            'plan_number' => 'DT-2026-00002',
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'chief_complaint' => 'Toothache',
            'planned_steps' => $steps,
            'total_steps' => 2,
            'completed_steps' => 1,
            'start_date' => now(),
            'status' => 'active',
        ]);

        $service = app(DentalService::class);
        $service->completeStep($plan, 1);

        $plan->refresh();
        $this->assertEquals('completed', $plan->status);
        $this->assertEquals(2, $plan->completed_steps);
    }

    public function test_discontinue_plan()
    {
        $plan = DentalTreatmentPlan::create([
            'institute_id' => $this->institute->id,
            'plan_number' => 'DT-2026-00003',
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'chief_complaint' => 'Toothache',
            'planned_steps' => [],
            'total_steps' => 0,
            'completed_steps' => 0,
            'start_date' => now(),
            'status' => 'active',
        ]);

        $service = app(DentalService::class);
        $service->discontinuePlan($plan, 'Patient declined treatment');

        $plan->refresh();
        $this->assertEquals('discontinued', $plan->status);
        $this->assertEquals('Patient declined treatment', $plan->discontinue_reason);
    }

    public function test_procedure_catalog_crud()
    {
        $catalog = DentalProcedureCatalog::create([
            'institute_id' => $this->institute->id,
            'code' => 'D2391',
            'name' => 'Composite Filling - One Surface',
            'category' => 'restorative',
            'body_site' => 'tooth',
            'default_fee' => 3000,
            'default_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('dental_procedure_catalog', [
            'code' => 'D2391',
            'name' => 'Composite Filling - One Surface',
        ]);

        $catalog->update(['default_fee' => 3500]);
        $this->assertEquals(3500, $catalog->fresh()->default_fee);
    }

    public function test_catalog_scoped_to_institute()
    {
        $rival = Institute::create([
            'name' => 'Rival Hospital',
            'slug' => 'rival-' . uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        DentalProcedureCatalog::create([
            'institute_id' => $this->institute->id,
            'name' => 'My Catalog',
            'category' => 'restorative',
            'is_active' => true,
        ]);

        DentalProcedureCatalog::create([
            'institute_id' => null,
            'name' => 'Global Catalog',
            'category' => 'restorative',
            'is_active' => true,
        ]);

        DentalProcedureCatalog::create([
            'institute_id' => $rival->id,
            'name' => 'Rival Catalog',
            'category' => 'restorative',
            'is_active' => true,
        ]);

        $results = DentalProcedureCatalog::forInstitute($this->institute->id)->get();
        $names = $results->pluck('name')->toArray();

        $this->assertContains('My Catalog', $names);
        $this->assertContains('Global Catalog', $names);
        $this->assertNotContains('Rival Catalog', $names);
    }

    public function test_quadrant_from_tooth_number()
    {
        $this->assertEquals('upper_right', DentalService::quadrantFromTooth('11'));
        $this->assertEquals('upper_right', DentalService::quadrantFromTooth('18'));
        $this->assertEquals('upper_left', DentalService::quadrantFromTooth('21'));
        $this->assertEquals('upper_left', DentalService::quadrantFromTooth('28'));
        $this->assertEquals('lower_left', DentalService::quadrantFromTooth('31'));
        $this->assertEquals('lower_left', DentalService::quadrantFromTooth('38'));
        $this->assertEquals('lower_right', DentalService::quadrantFromTooth('41'));
        $this->assertEquals('lower_right', DentalService::quadrantFromTooth('48'));
    }

    public function test_dashboard_shows_today_procedures()
    {
        DentalProcedure::create([
            'institute_id' => $this->institute->id,
            'procedure_number' => 'DP-2026-99999',
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'procedure_name' => 'Test Procedure',
            'performed_at' => now(),
        ]);

        $response = $this->get(route('medical.dental.dashboard'));
        $response->assertStatus(200);
    }

    public function test_tenant_isolation()
    {
        $rival = Institute::create([
            'name' => 'Rival Hospital',
            'slug' => 'rival-iso-' . uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        DentalChart::create([
            'institute_id' => $rival->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $charts = DentalChart::where('institute_id', $this->institute->id)->count();
        $this->assertEquals(0, $charts);
    }

    public function test_audit_log_records_actions()
    {
        $chart = DentalChart::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'tooth_conditions' => [],
            'total_teeth' => 32,
        ]);

        $service = app(DentalService::class);
        $service->updateTooth($chart, '11', 'caries');

        $auditCount = ClinicalAuditLog::where('auditable_type', DentalChart::class)
            ->where('auditable_id', $chart->id)
            ->where('action', 'tooth_updated')
            ->count();

        $this->assertGreaterThan(0, $auditCount);
    }

    public function test_permission_enforcement()
    {
        $user = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $roleId = Role::where('slug', 'doctor')->value('id');
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');
        Workspace::set($this->institute->id);

        // Doctor does NOT have catalog.manage, should get 403
        $response = $this->get(route('medical.dental.catalog.create'));
        $response->assertStatus(403);
    }
}
