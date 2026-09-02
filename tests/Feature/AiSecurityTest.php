<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Journal;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\AiProviderResponse;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Tools\Core\GetCrmSummaryTool;
use App\Services\Ai\Tools\Core\GetFinancialSummaryTool;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Step 33 — AI Core security: the AI layer must never be an authorization
 * bypass, must never leak tenant/branch/provider data to the model, must never
 * execute write actions and must isolate every tenant and branch.
 */
class AiSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    protected function freshInstitute(string $industry = 'education'): Institute
    {
        $institute = Institute::create([
            'name' => 'Secure Fixture '.mt_rand(1000, 9999),
            'slug' => 'secure-'.mt_rand(1000, 9999),
            'industry' => $industry,
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

    protected function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function userFor(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function contextFor(InstituteUser $user, Institute $institute): AiContext
    {
        return AiContext::resolve($user, $institute);
    }

    public function test_ai_is_not_an_authorization_bypass_for_finance_or_crm(): void
    {
        Setting::set('ai.enabled', '1');
        $institute = $this->freshInstitute();
        TenantContext::set($institute->id);

        $teacher = $this->userFor($institute, 'teacher', 'sec-teacher');
        $ctx = $this->contextFor($teacher, $institute);

        app()->instance(AiProvider::class, new class implements AiProvider
        {
            public array $calls = [];

            public function chat(array $messages, array $tools): AiProviderResponse
            {
                $this->calls[] = $messages;

                if (count($this->calls) === 1) {
                    return new AiProviderResponse('', [
                        ['name' => 'get_financial_summary', 'arguments' => '{"section":"overview"}', 'id' => 'call_fin'],
                        ['name' => 'get_crm_summary', 'arguments' => '{}', 'id' => 'call_crm'],
                    ], 10);
                }

                return new AiProviderResponse('I cannot access that.', [], 5);
            }
        });

        $provider = app(AiProvider::class);
        $result = app(AiService::class)->ask('What is our profit and who are our leads?', $ctx);

        $this->assertSame('ok', $result['status']);
        $this->assertSame([], $result['tools'], 'unauthorized tools must never be reported as executed');
        $this->assertSame('I cannot access that.', $result['content']);

        $last = end($provider->calls);
        $this->assertNotNull($last);
        $blob = json_encode($last, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Tool is not available for this user.', $blob);
    }

    public function test_finance_tool_handle_without_tenant_context_is_rejected(): void
    {
        $institute = $this->freshInstitute();
        TenantContext::clear();

        $owner = $this->userFor($institute, 'institute-owner', 'sec-guard');
        $ctx = $this->contextFor($owner, $institute);

        $this->expectException(HttpException::class);
        app(GetFinancialSummaryTool::class)->handle([], $ctx);
    }

    public function test_crm_tool_handle_without_tenant_context_is_rejected(): void
    {
        $institute = $this->freshInstitute();
        TenantContext::clear();

        $owner = $this->userFor($institute, 'institute-owner', 'sec-guard-crm');
        $ctx = $this->contextFor($owner, $institute);

        $this->expectException(HttpException::class);
        app(GetCrmSummaryTool::class)->handle([], $ctx);
    }

    public function test_provider_never_receives_tenant_ids_branch_ids_or_credentials(): void
    {
        Setting::set('ai.enabled', '1');
        Setting::set('ai.api_key', 'sk-TESTSECRETKEY1234567890');
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'HQ');
        TenantContext::set($institute->id);

        $manager = $this->userFor($institute, 'branch-manager', 'sec-context', $branch);
        $ctx = $this->contextFor($manager, $institute);

        app()->instance(AiProvider::class, new class implements AiProvider
        {
            public array $messages = [];

            public function chat(array $messages, array $tools): AiProviderResponse
            {
                $this->messages = $messages;

                return new AiProviderResponse('Hello.', [], 5);
            }
        });

        $provider = app(AiProvider::class);
        app(AiService::class)->ask('hello', $ctx);

        $blob = json_encode($provider->messages, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('sk-TESTSECRETKEY1234567890', $blob, 'credentials must never reach the model');
        $this->assertStringNotContainsString((string) $institute->id, $blob, 'raw tenant id must never reach the model');
        $this->assertStringNotContainsString((string) $branch->id, $blob, 'raw branch id must never reach the model');
        $this->assertStringContainsString($institute->name, $blob, 'the tenant name is the only tenant signal');
    }

    public function test_tool_failure_never_mutates_business_data(): void
    {
        Setting::set('ai.enabled', '1');
        $institute = $this->freshInstitute();
        TenantContext::set($institute->id);
        $owner = $this->userFor($institute, 'institute-owner', 'sec-failure');
        $ctx = $this->contextFor($owner, $institute);

        $before = Journal::query()->where('institute_id', $institute->id)->count();

        app()->instance(AiProvider::class, new class implements AiProvider
        {
            public array $calls = [];

            public function chat(array $messages, array $tools): AiProviderResponse
            {
                $this->calls[] = $messages;

                if (count($this->calls) === 1) {
                    return new AiProviderResponse('', [
                        ['name' => 'get_financial_summary', 'arguments' => '{"from":"not-a-valid-date"}', 'id' => 'call_bad'],
                    ], 10);
                }

                return new AiProviderResponse('Something went wrong, try again.', [], 5);
            }
        });

        $provider = app(AiProvider::class);
        $result = app(AiService::class)->ask('show finances', $ctx);

        $this->assertSame('ok', $result['status'], 'tool failure must not abort the whole turn');
        $this->assertSame([], $result['tools'], 'a failed tool call is not reported as executed');
        $this->assertSame('Something went wrong, try again.', $result['content']);

        $after = Journal::query()->where('institute_id', $institute->id)->count();
        $this->assertSame($before, $after, 'AI tool failure must never create/change ledger data');

        $last = end($provider->calls);
        $this->assertNotNull($last);
        $this->assertStringContainsString('The tool could not complete the request.', json_encode($last, JSON_UNESCAPED_UNICODE));
    }

    public function test_write_mode_tools_are_never_executed(): void
    {
        Setting::set('ai.enabled', '1');

        $probe = new class implements AiTool
        {
            public bool $executed = false;

            public function name(): string
            {
                return 'write_probe_delete';
            }

            public function description(): string
            {
                return 'A destructive probe that must never run.';
            }

            public function parameters(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function permission(): ?string
            {
                return null;
            }

            public function feature(): string
            {
                return 'assistant';
            }

            public function mode(): string
            {
                return 'write';
            }

            public function handle(array $args, AiContext $context): array
            {
                $this->executed = true;

                return ['done' => true];
            }
        };

        app()->instance(get_class($probe), $probe);
        config(['ai-tools.write_probe' => [get_class($probe)]]);

        $institute = $this->freshInstitute('write_probe');
        TenantContext::set($institute->id);
        $owner = $this->userFor($institute, 'institute-owner', 'sec-write');
        $ctx = $this->contextFor($owner, $institute);

        app()->instance(AiProvider::class, new class implements AiProvider
        {
            public array $calls = [];

            public function chat(array $messages, array $tools): AiProviderResponse
            {
                $this->calls[] = $messages;

                if (count($this->calls) === 1) {
                    return new AiProviderResponse('', [
                        ['name' => 'write_probe_delete', 'arguments' => '{}', 'id' => 'call_write'],
                    ], 10);
                }

                return new AiProviderResponse('Done.', [], 5);
            }
        });

        $provider = app(AiProvider::class);
        $result = app(AiService::class)->ask('please delete everything', $ctx);

        $this->assertSame('ok', $result['status']);
        $this->assertSame([], $result['tools'], 'write tools must never be reported as executed');
        $this->assertFalse($probe->executed, 'a write-mode tool handle must never be called');

        $last = end($provider->calls);
        $this->assertNotNull($last);
        $this->assertStringContainsString(
            'Write actions require explicit confirmation and are disabled.',
            json_encode($last, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_education_tools_are_not_exposed_outside_education(): void
    {
        $registry = app(AiToolRegistry::class);

        foreach (['healthcare', 'real_estate', 'restaurant', 'transportation'] as $industry) {
            $ctx = new AiContext(
                actor: null,
                institute: null,
                industry: $industry,
                aiEnabled: true,
                enabledFeatures: ['assistant'],
                permissions: ['*'],
            );

            $available = $registry->available($ctx);
            $this->assertArrayNotHasKey('get_students', $available, $industry.' must not expose education tools');
        }
    }

    public function test_disabled_ai_or_disabled_assistant_blocks_every_tool(): void
    {
        Setting::set('ai.enabled', '0');
        $institute = $this->freshInstitute();
        TenantContext::set($institute->id);
        $owner = $this->userFor($institute, 'institute-owner', 'sec-disabled');
        $ctx = $this->contextFor($owner, $institute);

        $result = app(AiService::class)->ask('finance?', $ctx);
        $this->assertSame('blocked', $result['status']);

        Setting::set('ai.enabled', '1');
        $institute->settings()->update(['ai_config' => ['enabled' => true, 'features' => ['analytics']]]);
        $ctx = $this->contextFor($owner, Institute::find($institute->id));

        $result = app(AiService::class)->ask('finance?', $ctx);
        $this->assertSame('blocked', $result['status']);
    }
}
