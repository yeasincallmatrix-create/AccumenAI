<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Ai\AiContext;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\AiProviderResponse;
use App\Services\Ai\Tools\Education\StudentsTool;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FakeChatProvider implements AiProvider
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

class AiAssistantAjaxTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

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

    protected function enableAi(array $features = ['assistant'], array $limits = []): void
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

    public function test_send_returns_standard_envelope_with_answer(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();
        app()->instance(AiProvider::class, new FakeChatProvider(
            second: new AiProviderResponse('There are 42 students.', [], 15)
        ));

        $owner = $this->makeStaff('institute-owner', 'chat-ok@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'how many students?'])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['answer', 'status', 'tools', 'tool_used'], 'errors'])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.answer', 'There are 42 students.')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.tool_used', false)
            ->assertJsonPath('errors', []);
    }

    public function test_send_runs_tools_and_reports_tool_used(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();
        TenantContext::set($this->academy()->id);
        app()->instance(AiProvider::class, new FakeChatProvider(
            first: new AiProviderResponse('', [
                ['name' => (new StudentsTool)->name(), 'arguments' => '{}', 'id' => 'call_1'],
            ], 10),
            second: new AiProviderResponse('Found 3 students.', [], 20)
        ));

        $owner = $this->makeStaff('institute-owner', 'chat-tool@example.test');

        $response = $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'list students'])
            ->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.tool_used', true)
            ->assertJsonPath('data.tools.0', (new StudentsTool)->name());
    }

    public function test_send_rejects_empty_and_oversized_messages(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();

        $owner = $this->makeStaff('institute-owner', 'chat-validate@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => ''])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['message']]);

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => str_repeat('a', 4001)])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_send_rejects_oversized_history(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();

        $owner = $this->makeStaff('institute-owner', 'chat-history@example.test');

        $history = array_fill(0, 21, ['role' => 'user', 'content' => 'x']);

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hi', 'history' => $history])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['history']]);
    }

    public function test_send_blocked_when_usage_limit_reached(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi(limits: ['daily_limit' => 0]);

        $owner = $this->makeStaff('institute-owner', 'chat-limit@example.test');

        // A zero limit means unlimited, so instead force a reached state by
        // pre-seeding the daily counter.
        \App\Models\AiUsage::query()->updateOrCreate(
            [
                'institute_id' => $this->academy()->id,
                'period_type' => 'daily',
                'period' => now()->format('Y-m-d'),
            ],
            []
        )->increment('requests');

        $this->enableAi(limits: ['daily_limit' => 1]);

        app()->instance(AiProvider::class, new FakeChatProvider(
            second: new AiProviderResponse('should not run', [], 5)
        ));

        $response = $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'anything'])
            ->assertOk();

        $response->assertJsonPath('success', false)
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.answer', '')
            ->assertJsonPath('message', 'Daily AI request limit reached.');
    }

    public function test_send_provider_failure_does_not_leak_internals(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();
        app()->instance(AiProvider::class, new FakeChatProvider(
            error: new \RuntimeException('openai failed with sk-SUPERSECRET123456789 trace: /var/www/app')
        ));

        $owner = $this->makeStaff('institute-owner', 'chat-fail@example.test');

        $response = $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hello'])
            ->assertOk();

        $response->assertJsonPath('success', false)
            ->assertJsonPath('data.status', 'error')
            ->assertJsonPath('data.answer', '');

        $raw = $response->getContent();
        $this->assertStringNotContainsString('SUPERSECRET', $raw);
        $this->assertStringNotContainsString('/var/www', $raw);
        $this->assertStringNotContainsString('openai', strtolower($raw));
        $this->assertStringNotContainsString('sk-', $raw);
    }

    public function test_send_requires_authentication(): void
    {
        $this->postJson('/ai/assistant', ['message' => 'hello'])->assertUnauthorized();
    }

    public function test_send_blocked_when_platform_disabled(): void
    {
        Setting::set('ai.enabled', '0');
        $this->enableAi();

        $owner = $this->makeStaff('institute-owner', 'chat-platform@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hello'])
            ->assertForbidden();
    }

    public function test_send_blocked_when_institute_disabled(): void
    {
        Setting::set('ai.enabled', '1');

        $owner = $this->makeStaff('institute-owner', 'chat-institute@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hello'])
            ->assertForbidden();
    }

    public function test_send_blocked_when_feature_disabled(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi(['analytics']);

        $owner = $this->makeStaff('institute-owner', 'chat-feature@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hello'])
            ->assertForbidden();
    }

    public function test_send_blocked_without_permission(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();

        $teacher = $this->makeStaff('teacher', 'chat-noperm@example.test');

        $this->actingAs($teacher, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hello'])
            ->assertForbidden();
    }

    public function test_send_context_uses_authenticated_institute_never_client_input(): void
    {
        Setting::set('ai.enabled', '1');
        $this->enableAi();

        $other = Institute::create([
            'name' => 'Other Chat Institute',
            'slug' => 'other-chat-'.uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);
        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $other->id,
            'ai_config' => ['enabled' => true, 'features' => ['assistant'], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);

        app()->instance(AiProvider::class, new FakeChatProvider(
            second: new AiProviderResponse('context answer', [], 5)
        ));

        $owner = $this->makeStaff('institute-owner', 'chat-context@example.test');

        $this->actingAs($owner, 'institute_user')
            ->postJson('/ai/assistant', ['message' => 'hi', 'institute_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('success', true);

        // The fake provider captured the system prompt; it must reference the
        // authenticated academy, never the spoofed institute id.
        $provider = app(AiProvider::class);
        $messages = $provider->calls[0] ?? [];
        $system = $messages[0]['content'] ?? '';
        $this->assertStringContainsString($this->academy()->name, $system);
        $this->assertStringNotContainsString($other->name, $system);
    }
}
