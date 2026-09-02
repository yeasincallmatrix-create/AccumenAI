<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\InstituteSetting;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Services\Ai\AiToolRegistry;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiAssistantCompleteTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(?Country $c = null): Institute
    {
        $c ??= $this->country();
        return Institute::create([
            'name' => 'AI Inst '.uniqid(),
            'slug' => 'ai-'.uniqid(),
            'country' => $c->name,
            'country_id' => $c->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $i, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $role, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $i->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'branch_id' => $branchId,
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function enableAi(Institute $inst): void
    {
        Setting::set('ai.enabled', '1');

        InstituteSetting::updateOrCreate(
            ['institute_id' => $inst->id],
            ['ai_config' => ['enabled' => true, 'features' => ['assistant']]]
        );
    }

    public function test_ai_assistant_page_renders(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        $this->enableAi($inst);
        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('ai.assistant'))
            ->assertOk();
    }

    public function test_ai_tools_registered(): void
    {
        $registry = app(AiToolRegistry::class);
        $tools = $registry->all();

        $this->assertNotEmpty($tools, 'AiToolRegistry should have at least one tool registered');

        $toolNames = array_keys($tools);
        $this->assertContains('get_financial_summary', $toolNames);
        $this->assertContains('get_crm_summary', $toolNames);
        $this->assertContains('get_income_expense', $toolNames);
        $this->assertCount(19, $tools, 'Expected 19 AI tools registered');
    }

    public function test_ai_service_resolves_context(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst, 'institute-owner');
        TenantContext::set($inst->id);

        $context = AiContext::resolve($owner, $inst);

        $this->assertInstanceOf(AiContext::class, $context);
        $this->assertEquals($inst->id, $context->institute?->id);
        $this->assertEquals($owner->id, $context->actor?->id);
        $this->assertTrue($context->hasPermission('*'), 'Owner should have wildcard permission');
    }

    public function test_ai_assistant_requires_auth(): void
    {
        $this->get(route('ai.assistant'))->assertRedirect();
    }

    public function test_ai_assistant_requires_permission(): void
    {
        $inst = $this->institute();
        TenantContext::set($inst->id);

        $studentRole = Role::where('slug', 'teacher')->first();
        if ($studentRole === null) {
            $this->markTestSkipped('Teacher role not found in database.');
        }

        $teacher = InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $studentRole->id,
            'first_name' => 'Teacher',
            'last_name' => 'NoAi',
            'email' => 'noai-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('ai.assistant'))
            ->assertStatus(403);
    }
}
