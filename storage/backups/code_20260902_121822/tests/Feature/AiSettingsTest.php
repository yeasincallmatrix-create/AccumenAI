<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiUsageException;
use App\Services\Ai\AiUsageTracker;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Contracts\AiProviderResponse;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FakeAiConnectionProvider implements AiProvider
{
    public function __construct(protected ?\Throwable $error = null) {}

    public function chat(array $messages, array $tools): AiProviderResponse
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return new AiProviderResponse('OK', [], 4);
    }
}

class AiSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    protected function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'ai-settings-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function academy(): Institute
    {
        return Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
    }

    protected function owner(string $email): InstituteUser
    {
        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->academy()->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_super_admin_can_view_ai_settings(): void
    {
        $this->actingAs($this->admin(), 'platform_admin');

        $this->get(route('admin.settings.ai'))
            ->assertOk()
            ->assertSee('AI Provider / API Configuration')
            ->assertSee('API Base URL')
            ->assertSee('Maximum Output Tokens')
            ->assertSee('Request Timeout')
            ->assertSee('Global AI Instructions')
            ->assertSee('Response Language')
            ->assertSee('Daily AI Request Limit')
            ->assertSee('AI Features')
            ->assertSee('Test API Connection')
            ->assertSee('Save AI Settings');
    }

    public function test_api_key_never_appears_in_page_response(): void
    {
        Setting::set('ai.api_key', 'sk-SUPERSECRETTESTKEY123456789');

        $this->actingAs($this->admin(), 'platform_admin');

        $this->get(route('admin.settings.ai'))
            ->assertOk()
            ->assertSee('API Key: Configured')
            ->assertDontSee('sk-SUPERSECRETTESTKEY123456789');
    }

    public function test_institute_owner_cannot_access_ai_settings_url(): void
    {
        $this->actingAs($this->owner('ai-settings-owner@example.test'), 'institute_user');

        $this->get(route('admin.settings.ai'))
            ->assertStatus(302);
    }

    public function test_institute_owner_cannot_call_update_endpoint_directly(): void
    {
        $this->actingAs($this->owner('ai-settings-owner2@example.test'), 'institute_user');

        $this->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ])->assertStatus(302);
    }

    public function test_institute_owner_cannot_call_test_endpoint_directly(): void
    {
        $this->actingAs($this->owner('ai-settings-owner3@example.test'), 'institute_user');

        $this->post(route('admin.settings.ai.test'))->assertStatus(302);
    }

    public function test_save_provider_and_parameters(): void
    {
        $this->actingAs($this->admin(), 'platform_admin');

        $this->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com/v1',
            'max_tokens' => 1200,
            'temperature' => 0.5,
            'timeout' => 45,
            'global_instructions' => 'Always be concise.',
            'response_language' => 'auto',
            'daily_limit' => 1000,
            'monthly_limit' => 20000,
            'log_prompts' => false,
        ])->assertRedirect(route('admin.settings.ai'));

        $this->assertSame('1', Setting::get('ai.enabled'));
        $this->assertSame('openai', Setting::get('ai.provider'));
        $this->assertSame('gpt-4o', Setting::get('ai.model'));
        $this->assertSame('1200', Setting::get('ai.max_tokens'));
        $this->assertSame('0.5', Setting::get('ai.temperature'));
        $this->assertSame('45', Setting::get('ai.timeout'));
        $this->assertSame('auto', Setting::get('ai.response_language'));
        $this->assertSame('1000', Setting::get('ai.daily_limit'));
        $this->assertSame('20000', Setting::get('ai.monthly_limit'));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'ai',
            'action' => 'AI settings updated',
            'user_type' => 'platform_admin',
        ]);
    }

    public function test_replace_api_key_is_encrypted_at_rest_and_never_in_db_plaintext(): void
    {
        $this->actingAs($this->admin(), 'platform_admin');

        $this->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-REPLACEKEY1234567890',
            'base_url' => '',
            'max_tokens' => 900,
            'temperature' => 0.2,
            'timeout' => 60,
            'response_language' => 'auto',
            'daily_limit' => 0,
            'monthly_limit' => 0,
        ])->assertRedirect(route('admin.settings.ai'));

        $stored = DB::table('settings')->where('key', 'ai.api_key')->value('value');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('sk-REPLACEKEY1234567890', $stored);
        $this->assertSame('sk-REPLACEKEY1234567890', Setting::get('ai.api_key'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AI API key replaced',
            'module' => 'ai',
        ]);
        $log = AuditLog::query()->where('action', 'AI API key replaced')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('sk-REPLACEKEY1234567890', (string) $log->new_values);
        $this->assertStringContainsString('"api_key_replaced":true', (string) $log->new_values);
    }

    public function test_existing_api_key_preserved_when_blank(): void
    {
        Setting::set('ai.api_key', 'sk-ORIGINALKEY9876543210');
        $this->actingAs($this->admin(), 'platform_admin');

        $this->post(route('admin.settings.ai.update'), [
            'ai_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => '',
            'base_url' => '',
            'max_tokens' => 900,
            'temperature' => 0.2,
            'timeout' => 60,
            'response_language' => 'auto',
            'daily_limit' => 0,
            'monthly_limit' => 0,
        ])->assertRedirect(route('admin.settings.ai'));

        $this->assertSame('sk-ORIGINALKEY9876543210', Setting::get('ai.api_key'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'AI API key replaced']);
    }

    public function test_connection_test_success(): void
    {
        app()->instance(AiProvider::class, new FakeAiConnectionProvider);
        $this->actingAs($this->admin(), 'platform_admin');

        $this->get(route('admin.settings.ai'))->assertOk();

        $this->post(route('admin.settings.ai.test'))
            ->assertRedirect()
            ->assertSessionHas('status', 'AI API connection successful.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AI connection test performed',
            'module' => 'ai',
        ]);
        $log = AuditLog::query()->where('action', 'AI connection test performed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('"result":"success"', (string) $log->new_values);
    }

    public function test_connection_test_failure_is_safe(): void
    {
        app()->instance(AiProvider::class, new FakeAiConnectionProvider(
            error: new \RuntimeException('401 invalid api key sk-FAILKEY1234567890')
        ));
        $this->actingAs($this->admin(), 'platform_admin');

        $this->get(route('admin.settings.ai'))->assertOk();

        $response = $this->post(route('admin.settings.ai.test'))
            ->assertRedirect()
            ->assertSessionHasErrors('ai_test');

        $messages = $response->getSession()->get('errors')->getBag('default')->get('ai_test');
        $this->assertSame('Unable to connect to AI provider. Please verify the API configuration.', $messages[0]);
        $this->assertStringNotContainsString('sk-FAILKEY1234567890', $messages[0]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AI connection test performed',
            'module' => 'ai',
        ]);
        $log = AuditLog::query()->where('action', 'AI connection test performed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('sk-FAILKEY1234567890', (string) $log->new_values);
    }

    public function test_platform_daily_limit_caps_unlimited_institute(): void
    {
        Setting::set('ai.daily_limit', '1');
        InstituteSetting::withoutGlobalScopes()->updateOrCreate(
            ['institute_id' => $this->academy()->id],
            [
                'ai_config' => [
                    'enabled' => true,
                    'features' => ['assistant'],
                    'daily_limit' => 0,
                    'monthly_limit' => 0,
                ],
            ]
        );

        $owner = $this->owner('ai-settings-limit@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());
        $tracker = app(AiUsageTracker::class);

        $tracker->enforceLimits($ctx);
        $tracker->record($ctx, 5);

        $this->expectException(AiUsageException::class);
        $tracker->enforceLimits($ctx);
    }

    public function test_platform_limit_uses_stricter_of_both(): void
    {
        Setting::set('ai.daily_limit', '5');
        InstituteSetting::withoutGlobalScopes()->updateOrCreate(
            ['institute_id' => $this->academy()->id],
            [
                'ai_config' => [
                    'enabled' => true,
                    'features' => ['assistant'],
                    'daily_limit' => 2,
                    'monthly_limit' => 0,
                ],
            ]
        );

        $owner = $this->owner('ai-settings-strict@example.test');
        $ctx = AiContext::resolve($owner, $this->academy());
        $tracker = app(AiUsageTracker::class);

        $tracker->enforceLimits($ctx);
        $tracker->record($ctx, 5);
        $tracker->enforceLimits($ctx);
        $tracker->record($ctx, 5);

        $this->expectException(AiUsageException::class);
        $tracker->enforceLimits($ctx);
    }
}
