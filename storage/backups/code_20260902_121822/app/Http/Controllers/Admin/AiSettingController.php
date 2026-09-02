<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Ai\Contracts\AiProvider;
use App\Support\AiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AiSettingController extends Controller
{
    /**
     * Providers the current provider architecture actually supports. Adding a
     * new provider here (plus its AiConfig definition) is all it takes to make
     * it selectable — AiService never changes.
     */
    private const AVAILABLE_PROVIDERS = [
        'openai' => 'OpenAI',
        'custom' => 'Custom Provider',
    ];

    /**
     * AI features that are actually implemented. Unimplemented features are not
     * exposed as functional toggles.
     */
    private const IMPLEMENTED_FEATURES = [
        'assistant' => 'AI Assistant',
    ];

    public function index(Request $request): View
    {
        return view('admin.settings.ai', [
            'admin' => $request->user(),
            'aiEnabled' => AiConfig::enabled(),
            'provider' => AiConfig::provider(),
            'model' => AiConfig::model(),
            'hasApiKey' => filled(AiConfig::apiKey()),
            'baseUrl' => AiConfig::baseUrl(),
            'globalInstructions' => AiConfig::globalInstructions(),
            'maxTokens' => AiConfig::maxTokens(),
            'temperature' => AiConfig::temperature(),
            'timeout' => AiConfig::timeout(),
            'responseLanguage' => AiConfig::responseLanguage(),
            'dailyLimit' => AiConfig::dailyLimit(),
            'monthlyLimit' => AiConfig::monthlyLimit(),
            'features' => AiConfig::features(),
            'storePrompts' => AiConfig::storePrompts(),
            'availableProviders' => self::AVAILABLE_PROVIDERS,
            'implementedFeatures' => self::IMPLEMENTED_FEATURES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_enabled' => ['boolean'],
            'provider' => ['required', 'string', Rule::in(array_keys(self::AVAILABLE_PROVIDERS))],
            'model' => ['required', 'string', 'max:150'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
            'max_tokens' => ['required', 'integer', 'between:100,8192'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'timeout' => ['required', 'integer', 'between:5,300'],
            'global_instructions' => ['nullable', 'string', 'max:4000'],
            'response_language' => ['required', Rule::in(['auto', 'en', 'bn'])],
            'daily_limit' => ['required', 'integer', 'between:0,1000000'],
            'monthly_limit' => ['required', 'integer', 'between:0,1000000'],
            'log_prompts' => ['boolean'],
        ]);

        $apiKeyReplaced = filled($data['api_key'] ?? '');

        Setting::set('ai.enabled', ($data['ai_enabled'] ?? false) ? '1' : '0');
        Setting::set('ai.provider', $data['provider']);
        Setting::set('ai.model', trim($data['model']));
        if ($apiKeyReplaced) {
            Setting::set('ai.api_key', trim($data['api_key']));
        }
        $baseUrl = trim($data['base_url'] ?? '');
        Setting::set('ai.base_url', $baseUrl !== '' ? rtrim($baseUrl, '/') : null);
        Setting::set('ai.max_tokens', (string) $data['max_tokens']);
        Setting::set('ai.temperature', (string) $data['temperature']);
        Setting::set('ai.timeout', (string) $data['timeout']);
        Setting::set('ai.global_instructions', trim($data['global_instructions'] ?? ''));
        Setting::set('ai.response_language', $data['response_language']);
        Setting::set('ai.daily_limit', (string) $data['daily_limit']);
        Setting::set('ai.monthly_limit', (string) $data['monthly_limit']);
        Setting::set('ai.log_prompts', ($data['log_prompts'] ?? false) ? '1' : '0');
        Setting::set('ai.features', json_encode(array_values(self::IMPLEMENTED_FEATURES)));

        $this->audit(
            $request,
            $apiKeyReplaced ? 'AI API key replaced' : 'AI settings updated',
            [
                'provider' => $data['provider'],
                'model' => $data['model'],
                'ai_enabled' => (bool) ($data['ai_enabled'] ?? false),
                'api_key_replaced' => $apiKeyReplaced,
            ]
        );

        if ($request->input('return_to') === 'hub') {
            return redirect(route('admin.settings.index').'#pane-ai')
                ->with('status', 'AI settings saved.');
        }

        return redirect(route('admin.settings.ai'))
            ->with('status', 'AI settings saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        try {
            $response = app(AiProvider::class)->chat([
                ['role' => 'system', 'content' => 'You are a connectivity check. Reply with the single word OK.'],
                ['role' => 'user', 'content' => 'ping'],
            ], []);

            $this->audit($request, 'AI connection test performed', [
                'result' => 'success',
                'received' => filled($response->content),
            ]);

            return back()->with('status', 'AI API connection successful.');
        } catch (Throwable $e) {
            // Never surface the raw provider error: it may echo credentials.
            Log::warning('AI connection test failed ('.get_class($e).')', [
                'user' => $request->user()?->getAuthIdentifier(),
            ]);

            $this->audit($request, 'AI connection test performed', [
                'result' => 'failure',
            ]);

            return back()->withErrors([
                'ai_test' => 'Unable to connect to AI provider. Please verify the API configuration.',
            ]);
        }
    }

    /**
     * Platform-level audit trail for AI configuration changes. Secrets are never
     * written: only safe metadata (action, actor, timestamps).
     */
    private function audit(Request $request, string $action, array $metadata = []): void
    {
        AuditLog::create([
            'institute_id' => null,
            'user_type' => 'platform_admin',
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'ai',
            'new_values' => $metadata !== [] ? json_encode($metadata) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
