<?php

namespace Tests\Feature;

use App\Models\AiLog;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\AiUsageException;
use App\Services\Ai\AiUsageTracker;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\AiProviderResponse;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Tools\Core\IncomeExpenseTool;
use App\Services\Ai\Tools\Education\AttendanceTool;
use App\Services\Ai\Tools\Education\EnrollmentTool;
use App\Services\Ai\Tools\Education\FeesTool;
use App\Services\Ai\Tools\Education\StudentsTool;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FakeAiProvider implements AiProvider
{
    public array $calls = [];

    public function __construct(
        protected ?AiProviderResponse $first = null,
        protected ?AiProviderResponse $second = null,
        protected ?\Throwable $error = null,
    ) {}

    public function chat(array $messages, array $tools): AiProviderResponse
    {
        $this->calls[] = $messages;

        if ($this->error !== null) {
            throw $this->error;
        }

        if ($this->first !== null && count($this->calls) === 1) {
            return $this->first;
        }

        return $this->second ?? new AiProviderResponse('fallback answer', [], 10);
    }
}

class FakeCoreTool implements AiTool
{
    public function name(): string
    {
        return 'get_invoices_core';
    }

    public function description(): string
    {
        return 'Fake shared industry-neutral tool.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function permission(): ?string
    {
        return 'finance.view';
    }

    public function feature(): string
    {
        return 'assistant';
    }

    public function mode(): string
    {
        return 'read';
    }

    public function handle(array $args, AiContext $context): array
    {
        return ['ok' => true];
    }
}

class AiIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function academy(): Institute
    {
        return Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
    }

    protected function makeStaff(string $roleSlug, string $email): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->academy()->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function enableAiForAcademy(array $features = ['assistant'], array $limits = []): void
    {
        InstituteSetting::withoutGlobalScopes()->updateOrCreate(
            ['institute_id' => $this->academy()->id],
            [
                'ai_config' => array_merge([
                    'enabled' => true,
                    'features' => $features,
                    'daily_limit' => 0,
                    'monthly_limit' => 0,
                ], $limits),
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    public function test_ai_page_denied_when_platform_switch_off(): void
    {
        Setting::set('ai.enabled', '0');
        $this->enableAiForAcademy();

        $owner = $this->makeStaff('institute-owner', 'ai-platform-off@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get('/ai/assistant')
            ->assertForbidden();
    }

    public function test_ai_page_denied_when_institute_toggle_off(): void
    {
        Setting::set('ai.enabled', '1');
        $owner = $this->makeStaff('institute-owner', 'ai-institute-off@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get('/ai/assistant')
            ->assertForbidden();
    }

    public function test_ai_page_allows_when_enabled_and_permitted(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAiForAcademy();
        $owner = $this->makeStaff('institute-owner', 'ai-allowed@example.test');

        $this->actingAs($owner, 'institute_user')
            ->get('/ai/assistant')
            ->assertOk()
            ->assertSee('AI Assistant');
    }

    public function test_ai_page_denied_without_ai_permission(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAiForAcademy();
        $teacher = $this->makeStaff('teacher', 'ai-no-perm@example.test');

        $this->actingAs($teacher, 'institute_user')
            ->get('/ai/assistant')
            ->assertForbidden();
    }

    public function test_registry_filters_tools_by_permission(): void
    {
        $this->enableAiForAcademy();
        $registry = app(AiToolRegistry::class);

        $owner = $this->makeStaff('institute-owner', 'ai-owner-tools@example.test');
        $ownerCtx = AiContext::resolve($owner, $this->academy());
        $available = $registry->available($ownerCtx);
        $this->assertArrayHasKey((new StudentsTool)->name(), $available);
        $this->assertArrayHasKey((new FeesTool)->name(), $available);
        $this->assertArrayHasKey((new IncomeExpenseTool)->name(), $available);
        $this->assertArrayHasKey('get_financial_summary', $available);
        $this->assertArrayHasKey('get_crm_summary', $available);
        $this->assertCount(11, $available);

        $teacher = $this->makeStaff('teacher', 'ai-teacher-tools@example.test');
        $teacherCtx = AiContext::resolve($teacher, $this->academy());
        $available = $registry->available($teacherCtx);
        $this->assertArrayHasKey((new StudentsTool)->name(), $available);
        $this->assertArrayNotHasKey((new FeesTool)->name(), $available);
    }

    public function test_registry_hides_tools_when_feature_disabled(): void
    {
        $this->enableAiForAcademy(['analytics']);
        $registry = app(AiToolRegistry::class);

        $owner = $this->makeStaff('institute-owner', 'ai-feature-off@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $this->assertSame([], $registry->available($ctx));
    }

    public function test_students_tool_returns_tenant_data(): void
    {
        $this->enableAiForAcademy();
        TenantContext::set($this->academy()->id);

        Student::create([
            'institute_id' => $this->academy()->id,
            'full_name' => 'AI Tool Test Student',
            'student_id_number' => 'AIT'.mt_rand(1000, 9999),
            'status' => 'active',
            'admission_date' => now(),
        ]);

        $owner = $this->makeStaff('institute-owner', 'ai-tool-data@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $result = (new StudentsTool)->handle([], $ctx);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $this->assertNotEmpty($result['rows']);
    }

    public function test_usage_limit_blocks_after_limit_reached(): void
    {
        $this->enableAiForAcademy(limits: ['daily_limit' => 1]);
        $tracker = app(AiUsageTracker::class);

        $owner = $this->makeStaff('institute-owner', 'ai-limit@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $tracker->enforceLimits($ctx);
        $tracker->record($ctx, 5);

        $this->expectException(AiUsageException::class);
        $tracker->enforceLimits($ctx);
    }

    public function test_service_returns_error_status_and_logs_on_provider_failure(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAiForAcademy();
        Log::spy();
        app()->instance(AiProvider::class, new FakeAiProvider(
            error: new \RuntimeException('provider down with sk-SUPERSECRET123456789')
        ));

        $owner = $this->makeStaff('institute-owner', 'ai-failure@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $result = app(AiService::class)->ask('hello', $ctx);

        $this->assertSame('error', $result['status']);
        $this->assertSame('The AI service is temporarily unavailable. Please try again.', $result['error']);
        $this->assertStringNotContainsString('SUPERSECRET', $result['error']);
        $this->assertDatabaseCount('ai_logs', 1);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message, $context) => $message === 'AI assistant failure'
                && str_contains($context['error'], 'provider down'))
            ->once();
    }

    public function test_service_executes_read_tool_in_loop(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAiForAcademy();
        app()->instance(AiProvider::class, new FakeAiProvider(
            first: new AiProviderResponse('', [
                ['name' => 'get_students', 'arguments' => '{}', 'id' => 'call_1'],
            ], 10),
            second: new AiProviderResponse('There are 3 students in total.', [], 20)
        ));

        $owner = $this->makeStaff('institute-owner', 'ai-loop@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());
        TenantContext::set($this->academy()->id);

        $result = app(AiService::class)->ask('How many students do we have?', $ctx);

        $this->assertSame('ok', $result['status']);
        $this->assertContains('get_students', $result['tools']);
        $this->assertSame('There are 3 students in total.', $result['content']);
    }

    public function test_send_endpoint_returns_json_answer(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAiForAcademy();
        app()->instance(AiProvider::class, new FakeAiProvider(
            second: new AiProviderResponse('Summary of your institute.', [], 12)
        ));

        $owner = $this->makeStaff('institute-owner', 'ai-endpoint@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'summarise my academy'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'Summary of your institute.')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.tool_used', false)
            ->assertJsonStructure(['message', 'data', 'errors']);
    }

    public function test_service_blocks_when_platform_off(): void
    {
        Setting::set('ai.enabled', '0');
        $this->enableAiForAcademy();

        $owner = $this->makeStaff('institute-owner', 'ai-svc-platform-off@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $result = app(AiService::class)->ask('hello', $ctx);

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('disabled on this platform', $result['error']);
        $this->assertDatabaseHas('ai_logs', ['status' => 'blocked']);
    }

    public function test_service_blocks_when_institute_toggle_off(): void
    {
        Setting::set('ai.enabled', '1');

        $owner = $this->makeStaff('institute-owner', 'ai-svc-inst-off@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $result = app(AiService::class)->ask('hello', $ctx);

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('disabled for this institute', $result['error']);
    }

    public function test_service_blocks_when_feature_disabled(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAiForAcademy(['analytics']);

        $owner = $this->makeStaff('institute-owner', 'ai-svc-feature-off@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        $result = app(AiService::class)->ask('hello', $ctx);

        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('feature is disabled', $result['error']);
    }

    public function test_registry_isolates_by_industry(): void
    {
        $health = new AiContext(
            actor: null,
            institute: null,
            industry: 'healthcare',
            aiEnabled: true,
            enabledFeatures: ['assistant'],
            permissions: ['*'],
        );

        $available = app(AiToolRegistry::class)->available($health);

        $this->assertCount(3, $available);
        $this->assertArrayHasKey('get_income_expense', $available);
        $this->assertArrayHasKey('get_financial_summary', $available);
        $this->assertArrayHasKey('get_crm_summary', $available);
        $this->assertArrayNotHasKey('get_students', $available);
    }

    public function test_registry_offers_core_tools_to_every_industry(): void
    {
        config(['ai-tools.core' => [FakeCoreTool::class]]);

        try {
            $health = new AiContext(
                actor: null,
                institute: null,
                industry: 'healthcare',
                aiEnabled: true,
                enabledFeatures: ['assistant'],
                permissions: ['*'],
            );

            $available = app(AiToolRegistry::class)->available($health);

            $this->assertArrayHasKey('get_invoices_core', $available);
            $this->assertArrayNotHasKey('get_students', $available);
        } finally {
            config(['ai-tools.core' => []]);
        }
    }

    public function test_logs_redact_credentials(): void
    {
        Setting::set('ai.enabled', '1');
        Setting::set('ai.log_prompts', '1');
        $this->enableAiForAcademy();
        app()->instance(AiProvider::class, new FakeAiProvider(
            second: new AiProviderResponse('Fine.', [], 5)
        ));

        $owner = $this->makeStaff('institute-owner', 'ai-redact@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());

        app(AiService::class)->ask('My secret is sk-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123', $ctx);

        $log = AiLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('sk-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123', (string) $log->prompt);
        $this->assertStringContainsString('sk-***', (string) $log->prompt);
    }

    public function test_enrollment_tool_aggregates_by_course(): void
    {
        $institute = $this->freshInstitute();
        TenantContext::set($institute->id);

        $owner = $this->instituteOwner($institute, 'ai-enroll-group@example.test');
        $ctx = AiContext::resolve($owner, $institute);

        $this->makeCourseFixtures($institute, 2, 1);

        $result = (new EnrollmentTool)->handle(['group_by' => 'course'], $ctx);

        $this->assertSame(3, $result['total_enrollments']);
        $this->assertSame(2, $result['by_course']['Course A']);
        $this->assertSame(1, $result['by_course']['Course B']);
    }

    public function test_fees_tool_filters_unpaid_and_overdue(): void
    {
        $institute = $this->freshInstitute();
        TenantContext::set($institute->id);

        $student = Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'SID'.mt_rand(10000, 99999),
            'first_name' => 'Fee',
            'last_name' => 'Student',
            'admission_date' => now(),
        ]);

        Invoice::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-'.mt_rand(1000, 9999),
            'total_amount' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'due_date' => Carbon::yesterday(),
            'status' => 'unpaid',
        ]);
        Invoice::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-'.mt_rand(1000, 9999),
            'total_amount' => 200,
            'paid_amount' => 200,
            'due_amount' => 0,
            'status' => 'paid',
        ]);

        $owner = $this->instituteOwner($institute, 'ai-fees@example.test');
        $ctx = AiContext::resolve($owner, $institute);

        $unpaid = (new FeesTool)->handle(['payment_status' => 'unpaid'], $ctx);
        $this->assertSame(1, $unpaid['total_invoices']);
        $this->assertSame(100.0, $unpaid['total_due']);

        $overdue = (new FeesTool)->handle(['payment_status' => 'overdue'], $ctx);
        $this->assertSame(1, $overdue['total_invoices']);
    }

    public function test_attendance_tool_counts_today(): void
    {
        $institute = $this->freshInstitute();
        TenantContext::set($institute->id);

        $batch = Batch::create([
            'institute_id' => $institute->id,
            'course_id' => Course::create(['course_code' => 'C'.mt_rand(1000, 9999), 'name' => 'Att Course'])->id,
            'name' => 'Att Batch',
            'batch_code' => 'AT'.mt_rand(1000, 9999),
            'start_date' => now(),
        ]);
        $studentA = Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'SID'.mt_rand(10000, 99999),
            'first_name' => 'Att',
            'last_name' => 'Student A',
            'admission_date' => now(),
        ]);
        $studentB = Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'SID'.mt_rand(10000, 99999),
            'first_name' => 'Att',
            'last_name' => 'Student B',
            'admission_date' => now(),
        ]);

        foreach ([
            ['present', Carbon::today(), $studentA],
            ['absent', Carbon::today(), $studentB],
            ['present', Carbon::yesterday(), $studentA],
        ] as [$status, $date, $student]) {
            Attendance::create([
                'institute_id' => $institute->id,
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'class_date' => $date,
                'status' => $status,
            ]);
        }

        $owner = $this->instituteOwner($institute, 'ai-att@example.test');
        $ctx = AiContext::resolve($owner, $institute);

        $result = (new AttendanceTool)->handle([
            'from' => Carbon::today()->toDateString(),
            'to' => Carbon::today()->toDateString(),
        ], $ctx);

        $this->assertSame(2, $result['total_records']);
        $this->assertSame(1, $result['present']);
        $this->assertSame(1, $result['absent']);
    }

    public function test_students_tool_isolates_tenants(): void
    {
        $this->enableAiForAcademy();
        $academy = $this->academy();
        TenantContext::set($academy->id);

        $owner = $this->makeStaff('institute-owner', 'ai-tenant@example.test');
        $ctx = AiContext::resolve($owner, $academy);

        $before = (new StudentsTool)->handle([], $ctx)['total'];

        $other = Institute::create([
            'name' => 'Other Academy '.mt_rand(1000, 9999),
            'slug' => 'other-'.mt_rand(1000, 9999),
            'industry' => 'education',
            'status' => 'active',
        ]);

        Student::create([
            'institute_id' => $academy->id,
            'student_id_number' => 'ACE'.mt_rand(1000, 9999),
            'first_name' => 'Tenant A',
            'last_name' => 'Student',
            'admission_date' => now(),
        ]);
        Student::create([
            'institute_id' => $other->id,
            'student_id_number' => 'OTH'.mt_rand(1000, 9999),
            'first_name' => 'Tenant B',
            'last_name' => 'Student',
            'admission_date' => now(),
        ]);

        $result = (new StudentsTool)->handle([], $ctx);
        $this->assertSame($before + 1, $result['total']);
        $this->assertStringNotContainsString('Tenant B', json_encode($result['rows']));

        $spoofed = (new StudentsTool)->handle(['institute_id' => $other->id], $ctx);
        $this->assertSame($before + 1, $spoofed['total']);
    }

    protected function freshInstitute(): Institute
    {
        $institute = Institute::create([
            'name' => 'Fixture Academy '.mt_rand(1000, 9999),
            'slug' => 'fixture-'.mt_rand(1000, 9999),
            'industry' => 'education',
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'ai_config' => [
                'enabled' => true,
                'features' => ['assistant'],
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ],
        ]);

        return $institute;
    }

    protected function instituteOwner(Institute $institute, string $email): InstituteUser
    {
        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function makeCourseFixtures(Institute $institute, int $courseACount, int $courseBCount): void
    {
        $courseA = Course::create(['course_code' => 'CA'.mt_rand(1000, 9999), 'name' => 'Course A']);
        $courseB = Course::create(['course_code' => 'CB'.mt_rand(1000, 9999), 'name' => 'Course B']);

        foreach ([
            [$courseA, $courseACount],
            [$courseB, $courseBCount],
        ] as [$course, $count]) {
            $batch = Batch::create([
                'institute_id' => $institute->id,
                'course_id' => $course->id,
                'name' => 'Batch '.$course->name,
                'batch_code' => 'BT'.mt_rand(1000, 9999),
                'start_date' => now(),
            ]);

            for ($i = 0; $i < $count; $i++) {
                $student = Student::create([
                    'institute_id' => $institute->id,
                    'student_id_number' => 'SID'.mt_rand(10000, 99999),
                    'first_name' => 'Student',
                    'last_name' => (string) mt_rand(1, 999),
                    'admission_date' => now(),
                ]);

                StudentEnrollment::create([
                    'institute_id' => $institute->id,
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'batch_id' => $batch->id,
                    'roll_number' => 'RN'.mt_rand(1000, 9999),
                    'enrollment_date' => now(),
                ]);
            }
        }
    }
}
