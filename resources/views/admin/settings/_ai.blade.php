@php $aiEmbedded = $aiEmbedded ?? false; @endphp

@if ($errors->any() && ! $aiEmbedded)
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
    </div>
@endif

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-robot"></i> AI Provider / API Configuration</div>
    </div>

    <form method="POST" action="{{ route('admin.settings.ai.update') }}">
        @csrf
        @if ($aiEmbedded)
            <input type="hidden" name="return_to" value="hub">
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="ai_enabled" name="ai_enabled" value="1" {{ $aiEnabled ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="ai_enabled">AI Enabled</label>
                </div>
                <div class="form-text">Platform-wide switch. When off, AI is disabled for every institute.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="provider">Provider</label>
                <select id="provider" name="provider" class="form-select">
                    @foreach ($availableProviders as $key => $label)
                        <option value="{{ $key }}" {{ $provider === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">Supported: OpenAI, Anthropic (Claude), Google Gemini, Groq, Custom (OpenAI-compatible). API keys are stored per provider and encrypted.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="base_url">API Base URL</label>
                <input type="url" id="base_url" name="base_url" class="form-control" value="{{ $baseUrl }}" placeholder="https://api.openai.com/v1">
                @error('base_url')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="model">Model</label>
                <input type="text" id="model" name="model" class="form-control" value="{{ $model }}" placeholder="gpt-4o-mini">
                @error('model')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="api_key">API Key</label>
                <div class="mb-2">
                    @if ($hasApiKey)
                        <span class="badge text-bg-success"><i class="bi bi-check-circle-fill"></i> API Key: Configured for {{ $availableProviders[$provider] ?? $provider }}</span>
                        <span class="form-text ms-1">The stored key is kept secret per provider. Leave blank to keep the current provider's key.</span>
                    @else
                        <span class="badge text-bg-secondary">API Key: Not configured for {{ $availableProviders[$provider] ?? $provider }}</span>
                        <span class="form-text ms-1">Stored per provider and encrypted at rest.</span>
                    @endif
                </div>
                <input type="password" id="api_key" name="api_key" class="form-control" autocomplete="new-password"
                       placeholder="{{ $hasApiKey ? 'Leave blank to keep the current provider key — or type a new key to replace it' : 'sk-… / anthropic-… / gsk_…' }}">
                @error('api_key')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="max_tokens">Maximum Output Tokens</label>
                <input type="number" id="max_tokens" name="max_tokens" class="form-control" value="{{ $maxTokens }}" min="100" max="8192">
                @error('max_tokens')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="temperature">Temperature</label>
                <input type="number" id="temperature" name="temperature" class="form-control" value="{{ $temperature }}" min="0" max="2" step="0.1">
                @error('temperature')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="timeout">Request Timeout (seconds)</label>
                <input type="number" id="timeout" name="timeout" class="form-control" value="{{ $timeout }}" min="5" max="300">
                @error('timeout')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="global_instructions">Global AI Instructions</label>
                <textarea id="global_instructions" name="global_instructions" class="form-control" rows="4"
                          placeholder="Optional platform-level guidance for the assistant across all institutes.">{{ $globalInstructions }}</textarea>
                @error('global_instructions')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label d-block">Response Language</label>
                <div class="d-flex flex-wrap gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="response_language" id="lang_auto" value="auto" {{ $responseLanguage === 'auto' ? 'checked' : '' }}>
                        <label class="form-check-label" for="lang_auto">Auto Detect</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="response_language" id="lang_en" value="en" {{ $responseLanguage === 'en' ? 'checked' : '' }}>
                        <label class="form-check-label" for="lang_en">English</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="response_language" id="lang_bn" value="bn" {{ $responseLanguage === 'bn' ? 'checked' : '' }}>
                        <label class="form-check-label" for="lang_bn">বাংলা</label>
                    </div>
                </div>
                @error('response_language')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="daily_limit">Daily AI Request Limit (platform cap, 0 = unlimited)</label>
                <input type="number" id="daily_limit" name="daily_limit" class="form-control" value="{{ $dailyLimit }}" min="0" max="1000000">
                @error('daily_limit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="monthly_limit">Monthly AI Request Limit (platform cap, 0 = unlimited)</label>
                <input type="number" id="monthly_limit" name="monthly_limit" class="form-control" value="{{ $monthlyLimit }}" min="0" max="1000000">
                @error('monthly_limit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label d-block">AI Features</label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach ($implementedFeatures as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="feature_{{ $key }}" checked disabled>
                            <label class="form-check-label" for="feature_{{ $key }}">{{ $label }}</label>
                            <span class="badge text-bg-success ms-1">Active</span>
                        </div>
                    @endforeach
                    @foreach (['analytics' => 'AI Analytics', 'reports' => 'AI Reports', 'content' => 'AI Content', 'automation' => 'AI Automation'] as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="feature_{{ $key }}" disabled>
                            <label class="form-check-label text-muted" for="feature_{{ $key }}">{{ $label }}</label>
                            <span class="badge text-bg-secondary ms-1">Not implemented</span>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">Only implemented features are enabled. Unimplemented features are shown for visibility only and are not functional.</div>
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="log_prompts" name="log_prompts" value="1" {{ $storePrompts ? 'checked' : '' }}>
                    <label class="form-check-label" for="log_prompts">Store prompts in AI audit log</label>
                </div>
            </div>
        </div>

        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save AI Settings</button>
    </form>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-plug"></i> Test API Connection</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.ai.test') }}">
        @csrf
        @if ($aiEmbedded)
            <input type="hidden" name="return_to" value="hub">
        @endif
        <p class="form-text">Sends a minimal safe request using the saved configuration. The API key is never shown or logged.</p>
        @error('ai_test')<div class="alert alert-danger py-2"><i class="bi bi-x-circle"></i> {{ $message }}</div>@enderror
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-lightning-charge"></i> Test API Connection</button>
    </form>
</div>

@include('admin.settings._ai_api_keys')
