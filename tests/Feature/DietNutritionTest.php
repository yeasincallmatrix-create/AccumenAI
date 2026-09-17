<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\DietPlan;
use App\Models\Medical\DietTemplate;
use App\Models\Medical\MealSchedule;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Services\Medical\DietService;
use App\Services\Medical\NumberSequenceService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class DietNutritionTest extends TestCase
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
            'name' => 'Diet Test Hospital',
            'slug' => 'diet-test-' . uniqid(),
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

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.diet');

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Diet',
            'last_name' => 'Patient',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
            'phone' => '01712345678',
            'is_patient' => true,
            'mr_number' => app(NumberSequenceService::class)->next(NumberSequence::TYPE_MR, $this->institute->id),
        ]);
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $this->patient->id,
            'plan_name' => 'Diabetic Plan A',
            'diet_type' => 'diabetic',
            'restrictions' => 'No sugar, peanut allergy',
            'daily_calories' => 1800,
            'protein_grams' => 70,
            'carbs_grams' => 200,
            'fat_grams' => 50,
            'start_date' => today()->toDateString(),
            'days_planned' => 3,
        ], $overrides);
    }

    public function test_creates_diet_plan(): void
    {
        $response = $this->get(route('medical.diet.plans.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.diet.plans.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.diet.plans.store'), $this->planPayload());
        $response->assertRedirect();

        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($plan);
        $this->assertStringStartsWith('DIET-', $plan->plan_number);
        $this->assertEquals('diabetic', $plan->diet_type);
        $this->assertEquals('active', $plan->status);

        $response = $this->get(route('medical.diet.plans.show', $plan));
        $response->assertStatus(200);

        $response = $this->get(route('medical.diet.plans.edit', $plan));
        $response->assertStatus(200);
    }

    public function test_plan_number_unique_per_institute(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload());
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['plan_name' => 'Plan B']));

        $numbers = DietPlan::where('institute_id', $this->institute->id)->pluck('plan_number');
        $this->assertCount(2, $numbers);
        $this->assertCount(2, $numbers->unique());
    }

    public function test_generates_meal_schedules(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 2]));

        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($plan);

        // 2 days x 6 meals
        $this->assertEquals(12, $plan->mealSchedules()->count());
        $this->assertEquals(6, count(MealSchedule::MEAL_TYPES));
    }

    public function test_meal_generation_is_idempotent(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 2]));
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();

        $again = app(DietService::class)->generateMealSchedules($plan);
        $this->assertEquals(0, $again);
        $this->assertEquals(12, $plan->mealSchedules()->count());

        $response = $this->post(route('medical.diet.plans.generate-meals', $plan));
        $response->assertRedirect();
        $this->assertEquals(12, $plan->refresh()->mealSchedules()->count());
    }

    public function test_marks_meal_prepared(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 1]));
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $meal = $plan->mealSchedules()->first();

        $response = $this->post(route('medical.diet.meals.prepare', $meal));
        $response->assertRedirect();

        $meal->refresh();
        $this->assertEquals('prepared', $meal->status);
        $this->assertNotNull($meal->prepared_at);
    }

    public function test_marks_meal_served(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 1]));
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $meal = $plan->mealSchedules()->first();

        $response = $this->post(route('medical.diet.meals.serve', $meal));
        $response->assertRedirect();

        $meal->refresh();
        $this->assertEquals('served', $meal->status);
        $this->assertNotNull($meal->served_at);
    }

    public function test_marks_meal_refused(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 1]));
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $meal = $plan->mealSchedules()->first();

        $response = $this->post(route('medical.diet.meals.refuse', $meal), ['notes' => 'Patient nauseous']);
        $response->assertRedirect();

        $meal->refresh();
        $this->assertEquals('refused', $meal->status);
        $this->assertEquals('Patient nauseous', $meal->notes);
    }

    public function test_adherence_calculation(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 1]));
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();

        $service = app(DietService::class);
        $this->assertEquals(0, $service->adherencePercent($plan));

        $meals = $plan->mealSchedules()->limit(3)->get();
        foreach ($meals as $meal) {
            $meal->markServed($this->owner->id);
        }

        $this->assertEquals(50, $service->adherencePercent($plan->refresh()));
    }

    public function test_kitchen_today_shows_correct_orders(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload(['days_planned' => 1]));
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $plan->mealSchedules()->first()->markServed($this->owner->id);

        $response = $this->get(route('medical.diet.kitchen.today'));
        $response->assertStatus(200);
        // 6 meals today, 1 served → 5 pending shown
        $orders = app(DietService::class)->getTodayKitchenOrders($this->institute->id);
        $this->assertCount(5, $orders);
    }

    public function test_diet_template_crud(): void
    {
        $response = $this->get(route('medical.diet.templates.index'));
        $response->assertStatus(200);

        $response = $this->get(route('medical.diet.templates.create'));
        $response->assertStatus(200);

        $response = $this->post(route('medical.diet.templates.store'), [
            'name' => 'Standard Diabetic',
            'diet_type' => 'diabetic',
            'description' => 'Default diabetic template',
            'meal_items' => "Breakfast: Oats\nLunch: Rice + dal",
            'total_calories' => 1800,
        ]);
        $response->assertRedirect();

        $template = DietTemplate::where('name', 'Standard Diabetic')->first();
        $this->assertNotNull($template);
        $this->assertEquals(['Breakfast: Oats', 'Lunch: Rice + dal'], $template->meal_items);

        $response = $this->get(route('medical.diet.templates.show', $template));
        $response->assertStatus(200);

        $response = $this->get(route('medical.diet.templates.edit', $template));
        $response->assertStatus(200);

        $response = $this->put(route('medical.diet.templates.update', $template), [
            'name' => 'Standard Diabetic v2',
            'diet_type' => 'diabetic',
            'is_active' => true,
        ]);
        $response->assertRedirect();
        $this->assertEquals('Standard Diabetic v2', $template->refresh()->name);

        $response = $this->delete(route('medical.diet.templates.destroy', $template));
        $response->assertRedirect();
        $this->assertSoftDeleted('diet_templates', ['id' => $template->id]);
    }

    public function test_discontinue_plan(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload());
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();

        $response = $this->post(route('medical.diet.plans.discontinue', $plan), [
            'discontinue_reason' => 'Patient discharged',
        ]);
        $response->assertRedirect();

        $plan->refresh();
        $this->assertEquals('discontinued', $plan->status);
        $this->assertEquals('Patient discharged', $plan->discontinue_reason);
    }

    public function test_tenant_isolation(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload());
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($plan);

        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'diet-other-' . uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $this->assertEquals(1, DietPlan::forInstitute($this->institute->id)->count());
        $this->assertEquals(0, DietPlan::forInstitute($other->id)->count());

        // Cross-institute show → 403
        $otherPlan = DietPlan::create([
            'institute_id' => $other->id,
            'plan_number' => 'DIET-OTHER-1',
            'patient_id' => $this->patient->id,
            'prescribed_by' => $this->owner->id,
            'plan_name' => 'Other plan',
            'diet_type' => 'regular',
            'start_date' => today(),
            'status' => 'active',
        ]);

        $this->get(route('medical.diet.plans.show', $otherPlan))->assertStatus(403);
    }

    public function test_audit_log_records_actions(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload());
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $this->assertNotNull($plan);

        $this->assertTrue(ClinicalAuditLog::where('auditable_type', DietPlan::class)
            ->where('auditable_id', $plan->id)
            ->where('action', 'created')
            ->exists());

        $meal = $plan->mealSchedules()->first();
        $this->post(route('medical.diet.meals.serve', $meal));

        $this->assertTrue(ClinicalAuditLog::where('auditable_type', MealSchedule::class)
            ->where('auditable_id', $meal->id)
            ->where('action', 'served')
            ->exists());
    }

    public function test_permission_enforcement(): void
    {
        $this->post(route('medical.diet.plans.store'), $this->planPayload());
        $plan = DietPlan::where('patient_id', $this->patient->id)->first();
        $meal = $plan->mealSchedules()->first();

        // Limited role with view-only access (mirrors PhysiotherapyTest pattern)
        $viewPermission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical.diet.view'],
            ['name' => 'View Diet & Nutrition', 'module' => 'medical.diet']
        );
        $limitedRole = Role::create([
            'name' => 'Diet View Only',
            'slug' => 'diet-view-only-' . uniqid(),
            'status' => 'active',
        ]);
        $limitedRole->permissions()->attach($viewPermission->id);

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $limitedRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $this->get(route('medical.diet.plans.index'))->assertStatus(200);
        $this->post(route('medical.diet.meals.serve', $meal))->assertStatus(403);
    }

    public function test_dashboard_loads(): void
    {
        $this->get(route('medical.diet.dashboard'))->assertStatus(200)->assertSee('Diet');
        $this->get(route('medical.diet.kitchen.today'))->assertStatus(200);
    }
}
