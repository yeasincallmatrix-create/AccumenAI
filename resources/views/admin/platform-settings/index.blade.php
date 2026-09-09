@extends('layouts.standalone')
@php $backUrl = route('admin.platform-settings.index'); @endphp
@section('title', 'Platform Configuration Center — Accumen AI')
@section('page_title', 'Platform Configuration Center')

@section('content')
<div class="standalone-heading d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-gear-wide-connected"></i> Platform Configuration Center</h4>
        <p>Super Admin centralized settings for all platform services. Secrets are encrypted & masked.</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

@if(session('status'))
<div class="alert alert-success"><i class="bi bi-check-circle"></i> {{ session('status') }}</div>
@endif

<div class="settings-layout">
    <div class="settings-nav" style="min-width:220px">
        <div class="settings-nav-title">Platform Settings</div>

        <div class="settings-nav-group-label">General</div>
        <button class="settings-nav-item settings-tab-btn active" type="button" data-target="pane-general"><i class="bi bi-globe"></i> <span>General</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-branding"><i class="bi bi-brush"></i> <span>Branding</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-maintenance"><i class="bi bi-tools"></i> <span>Maintenance</span></button>

        <div class="settings-nav-group-label">Communication</div>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-email"><i class="bi bi-envelope"></i> <span>Email / SMTP</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-sms"><i class="bi bi-chat-dots"></i> <span>SMS Provider</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-otp"><i class="bi bi-key"></i> <span>OTP &amp; Verification</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-notifications"><i class="bi bi-bell"></i> <span>Notifications</span></button>

        <div class="settings-nav-group-label">Security</div>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-security"><i class="bi bi-shield-lock"></i> <span>Two-Factor / 2FA</span></button>

        <div class="settings-nav-group-label">Infrastructure</div>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-queue"><i class="bi bi-stack"></i> <span>Queue</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-storage"><i class="bi bi-hdd"></i> <span>Storage</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-maps"><i class="bi bi-geo-alt"></i> <span>Maps &amp; Geo</span></button>

        <div class="settings-nav-group-label">Integrations</div>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-payment"><i class="bi bi-credit-card"></i> <span>Payment Gateways</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-ai"><i class="bi bi-robot"></i> <span>AI</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-api"><i class="bi bi-code-slash"></i> <span>API &amp; Webhooks</span></button>

        <div class="settings-nav-group-label">Medical</div>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-dgda"><i class="bi bi-capsule"></i> <span>DGDA Drug Codes</span></button>

        <div class="settings-nav-group-label">Admin</div>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-account"><i class="bi bi-person-gear"></i> <span>Account</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-staff"><i class="bi bi-person-plus-fill"></i> <span>Staff Requests</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-appearance"><i class="bi bi-palette"></i> <span>Appearance</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-mail"><i class="bi bi-envelope-paper"></i> <span>Mail &amp; Payment</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-ai"><i class="bi bi-robot"></i> <span>AI</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-security"><i class="bi bi-shield-lock"></i> <span>Security</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-admin-geo"><i class="bi bi-globe2"></i> <span>Geo Settings</span></button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-industry"><i class="bi bi-diagram-2-fill"></i> <span>Industry &amp; Themes</span></button>
    </div>
    <div class="settings-content">
        <div class="admin-card settings-options-card">

            {{-- GENERAL --}}
            <div class="settings-pane active" id="pane-general">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-globe"></i> General / Platform</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.general') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Application Name</label><input name="app_name" class="form-control" value="{{ $appName }}" required></div>
                    <div class="col-md-3"><label class="form-label">Short Name</label><input name="app_short_name" class="form-control" value="{{ $appShortName }}"></div>
                    <div class="col-md-3"><label class="form-label">Timezone</label><input name="app_timezone" class="form-control" value="{{ $timezone }}"></div>
                    <div class="col-md-6"><label class="form-label">Application URL</label><input name="app_url" type="url" class="form-control" value="{{ $appUrl }}" required></div>
                    <div class="col-md-2"><label class="form-label">Country</label><input name="app_country" class="form-control" value="{{ $country }}"></div>
                    <div class="col-md-2"><label class="form-label">Currency</label><input name="app_currency" class="form-control" value="{{ $currency }}"></div>
                    <div class="col-md-2"><label class="form-label">Language</label><select name="app_language" class="form-select"><option value="en" {{ $language==='en'?'selected':'' }}>en</option><option value="bn" {{ $language==='bn'?'selected':'' }}>bn</option><option value="auto" {{ $language==='auto'?'selected':'' }}>auto</option></select></div>
                    <div class="col-md-3"><label class="form-label">Date Format</label><input name="app_date_format" class="form-control" value="{{ $dateFormat }}"></div>
                    <div class="col-md-3"><label class="form-label">Time Format</label><input name="app_time_format" class="form-control" value="{{ $timeFormat }}"></div>
                    <div class="col-md-2"><label class="form-label">Pagination</label><input name="app_pagination" type="number" class="form-control" value="{{ $pagination }}"></div>
                    <div class="col-md-4"><label class="form-label">Contact Email</label><input name="app_contact_email" type="email" class="form-control" value="{{ $contactEmail }}"></div>
                    <div class="col-md-4"><label class="form-label">Support Phone</label><input name="app_support_phone" class="form-control" value="{{ $supportPhone }}"></div>
                    <div class="col-md-4"><label class="form-label">Support URL</label><input name="app_support_url" type="url" class="form-control" value="{{ $supportUrl }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save General</button>
                </form>
            </div>

            {{-- EMAIL --}}
            <div class="settings-pane" id="pane-email">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-envelope"></i> Email / SMTP — <small class="text-muted">{{ $smtpConfigured ? 'Configured' : 'NOT CONFIGURED' }}</small></div></div>
                <form method="POST" action="{{ route('admin.platform-settings.email') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><label class="form-label">SMTP Host</label><input name="smtp_host" class="form-control" value="{{ $smtpHost }}" placeholder="smtp.gmail.com"></div>
                    <div class="col-md-2"><label class="form-label">Port</label><input name="smtp_port" type="number" class="form-control" value="{{ $smtpPort }}"></div>
                    <div class="col-md-2"><label class="form-label">Encryption</label><select name="smtp_encryption" class="form-select"><option value="none" {{ $smtpEncryption==='none'?'selected':'' }}>None</option><option value="tls" {{ $smtpEncryption==='tls'?'selected':'' }}>TLS</option><option value="ssl" {{ $smtpEncryption==='ssl'?'selected':'' }}>SSL</option></select></div>
                    <div class="col-md-4"><label class="form-label">Username</label><input name="smtp_username" class="form-control" value="{{ $smtpUsername }}"></div>
                    <div class="col-md-4"><label class="form-label">Password</label><input name="smtp_password" type="password" class="form-control" placeholder="{{ $smtpPasswordMasked }}"></div>
                    <div class="col-md-4"><label class="form-label">From Address</label><input name="smtp_from_address" type="email" class="form-control" value="{{ $smtpFromAddress }}" required></div>
                    <div class="col-md-4"><label class="form-label">From Name</label><input name="smtp_from_name" class="form-control" value="{{ $smtpFromName }}"></div>
                    <div class="col-md-4"><label class="form-label">Reply-to Name</label><input name="smtp_reply_to_name" class="form-control" value="{{ $smtpReplyName }}"></div>
                    <div class="col-md-4"><label class="form-label">Reply-to Email</label><input name="smtp_reply_to_email" type="email" class="form-control" value="{{ $smtpReplyEmail }}"></div>
                    <div class="col-md-2"><label class="form-label">Enabled</label><select name="smtp_enabled" class="form-select"><option value="1" {{ $smtpEnabled==='1'?'selected':'' }}>Yes</option><option value="0" {{ $smtpEnabled==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Queue</label><select name="smtp_queue" class="form-select"><option value="1" {{ $smtpQueue==='1'?'selected':'' }}>Yes</option><option value="0" {{ $smtpQueue==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Retry</label><input name="smtp_retry_count" type="number" class="form-control" value="{{ $smtpRetry }}"></div>
                    <div class="col-md-2"><label class="form-label">Timeout (s)</label><input name="smtp_timeout" type="number" class="form-control" value="{{ $smtpTimeout }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save Email</button>
                </form>
                <hr>
                <form method="POST" action="{{ route('admin.platform-settings.email.test') }}">@csrf
                <div class="row g-3"><div class="col-md-6"><label class="form-label">Test Email</label><input name="test_email" type="email" class="form-control" placeholder="you@example.com" required></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-send"></i> Send Test Email</button></div></div>
                @error('smtp_test')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </form>
            </div>

            {{-- SMS --}}
            <div class="settings-pane" id="pane-sms">
                {{-- SECTION 10 — Provider Status Card --}}
                <div class="card mb-3" style="border-left:4px solid {{ $sms['enabled']==='1' ? '#198754' : '#6c757d' }}">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-broadcast"></i> SMS Provider Status
                            <span class="badge bg-{{ $sms['enabled']==='1' ? 'success' : 'secondary' }} ms-2">SMS {{ $sms['status_label'] }}</span>
                            <span class="badge bg-{{ $sms['provider_status']['provider_raw']==='http' ? 'primary' : 'secondary' }} ms-1">{{ $sms['provider_status']['provider'] }}</span>
                        </h6>
                        <div class="row small">
                            <div class="col-md-3"><strong>SMS Service:</strong> <span class="{{ $sms['enabled']==='1'?'text-success':'text-muted' }}">{{ $sms['status_label'] }}</span><br><small class="text-muted">Enable SMS for phone verification, SMS 2FA and SMS recovery. When disabled, no external SMS is sent.</small></div>
                            <div class="col-md-3"><strong>Provider:</strong> {{ $sms['provider_status']['provider'] }}<br><small class="text-muted">Active engine: {{ $sms['provider_status']['provider_raw']==='http' ? 'HttpSmsProvider' : 'LogSmsProvider' }} via SmsProviderContract</small></div>
                            <div class="col-md-3"><strong>Configuration:</strong> {{ $sms['provider_status']['config_state'] }}<br><small class="text-muted">API URL: {{ $sms['is_configured'] ? 'Complete' : 'Missing' }} | HTTPS: {{ $sms['provider_status']['is_https'] ? 'Yes' : ($sms['provider_status']['has_url']?'Warning — HTTP':'Not configured') }}</small></div>
                            <div class="col-md-3"><strong>Connection:</strong> {{ $sms['provider_status']['connection'] }}<br><strong>Delivery:</strong> {{ $sms['provider_status']['delivery'] }}<br><small class="text-muted">Connection via Test Provider Connection. Delivery via Send Test SMS.</small></div>
                        </div>
                        @if($sms['enabled']!=='1')
                        <div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-exclamation-triangle"></i> SMS is <strong>DISABLED</strong> — OTP, 2FA and password recovery will not attempt external delivery. Credentials are kept encrypted for re-enable.</div>
                        @endif
                    </div>
                </div>

                {{-- SECTION 1 — MASTER CONTROL already in status card + enabled select below --}}
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-chat-dots"></i> SMS Provider Configuration — Platform-Global (Super Admin only)</div><small class="text-muted">Credentials are encrypted and never displayed after saving. One global provider serves the platform.</small></div>

                <form method="POST" action="{{ route('admin.platform-settings.sms') }}">@csrf
                <h6 class="mt-3"><i class="bi bi-toggle-on"></i> Section 1 — SMS Master Control</h6>
                <p class="small text-muted">Enable SMS services for phone verification, SMS 2FA and SMS recovery. When disabled, the system falls back to Log provider — no external SMS is sent and no credits are consumed.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">SMS Service Status *</label>
                        <select name="sms_enabled" class="form-select"><option value="1" {{ $sms['enabled']==='1'?'selected':'' }}>Enabled</option><option value="0" {{ $sms['enabled']==='0'?'selected':'' }}>Disabled</option></select>
                        <small class="text-muted">MASTER SWITCH — disables all SMS delivery when OFF.</small>
                    </div>
                    <div class="col-md-9 d-flex align-items-end">
                        <div class="alert {{ $sms['enabled']==='1'?'alert-success':'alert-secondary' }} small mb-0 w-100">SMS Service: <strong>{{ $sms['status_label'] }}</strong> — {{ $sms['enabled']==='1' ? 'External SMS will be sent via the active provider.' : 'External SMS is suppressed (Log provider).' }}</div>
                    </div>
                </div>

                <h6><i class="bi bi-hdd-network"></i> Section 2 — Active Provider</h6>
                <p class="small text-muted">Select the service used to deliver SMS messages. Based on existing provider registry: <code>log</code> (development, no HTTP) and <code>http</code> (generic API). Never hard-codes a second engine.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Active SMS Provider *</label><select name="sms_provider" class="form-select"><option value="log" {{ $sms['provider']==='log'?'selected':'' }}>Log — Development / Fallback (no HTTP)</option><option value="http" {{ $sms['provider']==='http'?'selected':'' }}>HTTP — Generic API Gateway</option></select><small class="text-muted">Provider: {{ $sms['provider_status']['provider'] }}</small></div>
                    <div class="col-md-3"><label class="form-label">Type (legacy)</label><select name="sms_type" class="form-select"><option value="log" {{ $sms['type']==='log'?'selected':'' }}>Log</option><option value="http" {{ $sms['type']==='http'?'selected':'' }}>HTTP</option></select><small class="text-muted">Kept for backwards compatibility.</small></div>
                    <div class="col-md-6"><div class="alert alert-info small mb-0"><i class="bi bi-info-circle"></i> Reuses <code>SmsProviderContract</code> + <code>LogSmsProvider</code>/<code>HttpSmsProvider</code> + <code>SmsConfig::activeProvider()</code>. Tenant isolation preserved — this setting is platform-global.</div></div>
                </div>

                <h6><i class="bi bi-globe"></i> Section 3 — HTTP Provider Configuration</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">API URL * (when HTTP)</label><input name="sms_api_url" type="url" class="form-control" value="{{ $sms['api_url'] }}" placeholder="https://sms-provider.example/api/send"><small class="text-muted">HTTPS required for production. Valid URL is validated on save and before enabling.</small><div id="sms-https-warning" class="small text-danger d-none"><i class="bi bi-exclamation-triangle"></i> HTTPS is required — plain HTTP will be rejected when enabling SMS.</div></div>
                    <div class="col-md-2"><label class="form-label">HTTP Method</label><select name="sms_http_method" class="form-select"><option value="POST" {{ $sms['http_method']==='POST'?'selected':'' }}>POST</option><option value="GET" {{ $sms['http_method']==='GET'?'selected':'' }}>GET</option></select><small class="text-muted">Only POST/GET supported by HttpSmsProvider.</small></div>
                    <div class="col-md-4 d-flex align-items-end"><small class="text-muted">Do not call provider during save. Validation only.</small></div>
                </div>

                <h6><i class="bi bi-key"></i> Section 4 — Authentication Configuration</h6>
                <p class="small text-muted">Credentials are encrypted via <code>Setting::$encrypted</code> + <code>Crypt::encryptString</code> and never displayed after saving (<code>••••••••</code> / <code>Configured</code>). Leave blank to keep existing value.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">API Key</label><input name="sms_api_key" type="password" class="form-control" placeholder="{{ $sms['api_key_masked'] }}"><small class="text-muted">Required for HTTP when enabled.</small></div>
                    <div class="col-md-3"><label class="form-label">API Secret</label><input name="sms_api_secret" type="password" class="form-control" placeholder="{{ $sms['api_secret_masked'] }}"><small class="text-muted">Stored encrypted; sent as <code>api_secret</code> if gateway needs it.</small></div>
                    <div class="col-md-3"><label class="form-label">Username</label><input name="sms_username" class="form-control" value="{{ $sms['username'] ?? '' }}"><small class="text-muted">Only if your provider uses basic auth.</small></div>
                    <div class="col-md-3"><label class="form-label">Password</label><input name="sms_password" type="password" class="form-control" placeholder="{{ $sms['password_masked'] }}"><small class="text-muted">Encrypted; leave blank to keep.</small></div>
                    <div class="col-md-3"><label class="form-label">Auth Type</label><select name="sms_auth_type" class="form-select"><option value="none" {{ $sms['auth_type']==='none'?'selected':'' }}>None</option><option value="basic" {{ $sms['auth_type']==='basic'?'selected':'' }}>Basic</option><option value="bearer" {{ $sms['auth_type']==='bearer'?'selected':'' }}>Bearer</option><option value="apikey" {{ $sms['auth_type']==='apikey'?'selected':'' }}>ApiKey</option></select><small class="text-muted">Stored but only <code>none</code>/<code>apikey</code> currently consumed (generic HTTP).</small></div>
                </div>

                <h6><i class="bi bi-person-badge"></i> Section 5 — Sender Configuration</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Sender ID</label><input name="sms_sender_id" class="form-control" value="{{ $sms['sender_id'] }}" placeholder="AccumenAI"><small class="text-muted">Must be approved by your SMS provider where applicable.</small></div>
                    <div class="col-md-3"><label class="form-label">Sender Name</label><input name="sms_sender_name" class="form-control" value="{{ $sms['sender_name'] }}" placeholder="Accumen AI"><small class="text-muted">Optional display name.</small></div>
                </div>

                <h6><i class="bi bi-input-cursor"></i> Section 6 — Request Field Mapping</h6>
                <p class="small text-muted">Current HttpSmsProvider uses generic structure <code>to</code>/<code>message</code>/<code>api_key</code>/<code>from</code> via <code>config/notifications.php sms.http.fields</code>. Defaults are kept unless your provider docs specify different names.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Recipient Field</label><input name="sms_phone_param" class="form-control" value="{{ $sms['phone_param'] }}" placeholder="to"><small class="text-muted">Default: <code>to</code> (config fields)</small></div>
                    <div class="col-md-3"><label class="form-label">Message Field</label><input name="sms_message_param" class="form-control" value="{{ $sms['message_param'] }}" placeholder="message"><small class="text-muted">Default: <code>message</code></small></div>
                    <div class="col-md-3"><label class="form-label">API Key Field</label><input class="form-control" value="api_key" disabled><small class="text-muted">Fixed as <code>api_key</code> in engine.</small></div>
                    <div class="col-md-3"><label class="form-label">Sender Field</label><input class="form-control" value="from" disabled><small class="text-muted">Fixed as <code>from</code>.</small></div>
                </div>

                <h6><i class="bi bi-check2-square"></i> Section 7 — Response Validation</h6>
                <p class="small text-muted">Current HttpSmsProvider considers <code>HTTP 2xx</code> as sent. Provider-specific <em>Success Condition</em> is stored but not yet evaluated — documented for future provider contract without changing engine.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Success Condition (stored, not yet enforced)</label><input name="sms_success_condition" class="form-control" value="{{ $sms['success_condition'] }}" placeholder="status=success"><small class="text-muted">Example: <code>status=success</code> — kept for docs, engine still uses HTTP 2xx.</small></div>
                    <div class="col-md-6 d-flex align-items-end"><small class="text-muted">If your provider returns <code>{"status":"failed"}</code> with 200, contact dev to wire <code>response_message_id_path</code>.</small></div>
                </div>

                @error('sms_enabled')<div class="alert alert-danger small">{{ $message }}</div>@enderror
                @error('sms_api_url')<div class="alert alert-danger small">{{ $message }}</div>@enderror
                @error('sms_api_key')<div class="alert alert-danger small">{{ $message }}</div>@enderror
                <button class="btn btn-primary" type="submit">Save SMS Settings</button>
                <small class="text-muted ms-2">Validates, encrypts, preserves blank credentials, audits, never calls provider on save.</small>
                </form>

                <hr>
                <h6><i class="bi bi-activity"></i> Section 8 — Provider Connection Test</h6>
                <p class="small text-muted">Checks that the API URL is reachable via HTTPS without sending an SMS or consuming credits. Safe to run anytime.</p>
                <form method="POST" action="{{ route('admin.platform-settings.sms.test-connection') }}">@csrf
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-wifi"></i> Test Provider Connection</button>
                    <small class="text-muted ms-2">Does not send OTP. 5s timeout, TLS verified.</small>
                </form>
                @error('sms_test')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                @if(session('status') && str_contains(session('status'),'connection'))<div class="text-success small mt-2">{{ session('status') }}</div>@endif

                <hr>
                <h6><i class="bi bi-send"></i> Section 9 — Send Test SMS</h6>
                <p class="small text-muted">Sends a <strong>real SMS</strong> using the fixed text <code>Accumen AI test SMS. Your SMS provider configuration is working.</code> — does not create an OTP. Requires explicit confirmation and may consume provider credits.</p>
                <form method="POST" action="{{ route('admin.platform-settings.sms.test') }}" id="sms-test-form">@csrf
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Test Mobile Number *</label><input name="test_phone" class="form-control" placeholder="+88017XXXXXXXX" required><small class="text-muted">E.164 format e.g. +88017XXXXXXXX</small></div>
                    <div class="col-md-5"><label class="form-label">Test Message (fixed)</label><input class="form-control" value="Accumen AI test SMS. Your SMS provider configuration is working." disabled><small class="text-muted">Real OTP is never used for this test.</small><input type="hidden" name="test_message" value="Accumen AI test SMS. Your SMS provider configuration is working."></div>
                    <div class="col-md-4"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="confirm_send" value="1" id="confirm_send" required><label class="form-check-label" for="confirm_send">I confirm this will send a real SMS and may consume credits.</label></div><small class="text-muted">Throttled: 3 per 10 minutes per admin/IP.</small></div>
                </div>
                <div class="mt-3"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-send"></i> Send Test SMS</button></div>
                </form>
                @if(session('status') && str_contains(session('status'),'Test SMS'))<div class="text-success small mt-2">{{ session('status') }}</div>@endif
                <script>
                document.getElementById('sms-test-form')?.addEventListener('submit', function(e){
                    if(!document.getElementById('confirm_send').checked){
                        e.preventDefault(); alert('Please confirm that this will send a real SMS.');
                    } else if(!confirm('This action will send a real SMS and may consume provider credits. Continue?')){
                        e.preventDefault();
                    }
                });
                (function(){
                    var urlInput=document.querySelector('input[name=\"sms_api_url\"]');
                    var warn=document.getElementById('sms-https-warning');
                    if(!urlInput||!warn) return;
                    function check(){ warn.classList.toggle('d-none', urlInput.value.startsWith('https://') || urlInput.value===''); }
                    urlInput.addEventListener('input', check); check();
                })();
                </script>
            </div>

            {{-- OTP --}}
            <div class="settings-pane" id="pane-otp">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-key"></i> OTP & Verification</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.otp') }}">@csrf
                <h6>Email OTP</h6><div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Enabled</label><select name="email_otp_enabled" class="form-select"><option value="1" {{ $otp['email_otp.enabled']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $otp['email_otp.enabled']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Length</label><input name="email_otp_length" type="number" class="form-control" value="{{ $otp['email_otp.length'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Expiry (min)</label><input name="email_otp_expiry" type="number" class="form-control" value="{{ $otp['email_otp.expiry'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Max Attempts</label><input name="email_otp_max_attempts" type="number" class="form-control" value="{{ $otp['email_otp.max_attempts'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Cooldown (s)</label><input name="email_otp_resend_cooldown" type="number" class="form-control" value="{{ $otp['email_otp.resend_cooldown'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Max Resend</label><input name="email_otp_max_resend" type="number" class="form-control" value="{{ $otp['email_otp.max_resend'] }}"></div>
                </div>
                <h6>SMS OTP</h6><div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Enabled</label><select name="sms_otp_enabled" class="form-select"><option value="1" {{ $otp['sms_otp.enabled']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $otp['sms_otp.enabled']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Length</label><input name="sms_otp_length" type="number" class="form-control" value="{{ $otp['sms_otp.length'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Expiry (min)</label><input name="sms_otp_expiry" type="number" class="form-control" value="{{ $otp['sms_otp.expiry'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Max Attempts</label><input name="sms_otp_max_attempts" type="number" class="form-control" value="{{ $otp['sms_otp.max_attempts'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Cooldown (s)</label><input name="sms_otp_resend_cooldown" type="number" class="form-control" value="{{ $otp['sms_otp.resend_cooldown'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Max Resend</label><input name="sms_otp_max_resend" type="number" class="form-control" value="{{ $otp['sms_otp.max_resend'] }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save OTP</button>
                </form>
            </div>

            {{-- SECURITY 2FA --}}
            <div class="settings-pane" id="pane-security">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-shield-lock"></i> Security / Two-Factor</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.twofactor') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Allow TOTP</label><select name="allow_totp" class="form-select"><option value="1" {{ $twofa['2fa.allow_totp']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $twofa['2fa.allow_totp']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Allow Email OTP</label><select name="allow_email" class="form-select"><option value="1" {{ $twofa['2fa.allow_email']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $twofa['2fa.allow_email']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Allow SMS OTP</label><select name="allow_sms" class="form-select"><option value="1" {{ $twofa['2fa.allow_sms']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $twofa['2fa.allow_sms']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Preferred</label><select name="preferred" class="form-select"><option value="totp" {{ $twofa['2fa.preferred']==='totp'?'selected':'' }}>Authenticator App</option><option value="email" {{ $twofa['2fa.preferred']==='email'?'selected':'' }}>Email OTP</option><option value="sms" {{ $twofa['2fa.preferred']==='sms'?'selected':'' }}>SMS OTP</option></select></div>
                    <div class="col-md-2"><label class="form-label">User Can Change</label><select name="allow_user_change" class="form-select"><option value="1" {{ $twofa['2fa.allow_user_change']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $twofa['2fa.allow_user_change']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Require Verified Email</label><select name="require_verified_email" class="form-select"><option value="1" {{ $twofa['2fa.require_verified_email']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $twofa['2fa.require_verified_email']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Require Verified Phone</label><select name="require_verified_phone" class="form-select"><option value="1" {{ $twofa['2fa.require_verified_phone']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $twofa['2fa.require_verified_phone']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Max Failed</label><input name="max_failed" type="number" class="form-control" value="{{ $twofa['2fa.max_failed'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Challenge Expiry (min)</label><input name="challenge_expiry" type="number" class="form-control" value="{{ $twofa['2fa.challenge_expiry'] }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save 2FA</button>
                </form>
                <hr>
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-lock"></i> Login Protection</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.login-security') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Max Attempts</label><input name="login_max_attempts" type="number" class="form-control" value="{{ $loginSec['login.max_attempts'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Lockout (min)</label><input name="login_lockout" type="number" class="form-control" value="{{ $loginSec['login.lockout_duration'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Session (min)</label><input name="login_session" type="number" class="form-control" value="{{ $loginSec['login.session_lifetime'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Remember Me</label><select name="login_remember" class="form-select"><option value="1" {{ $loginSec['login.remember_me']==='1'?'selected':'' }}>Yes</option><option value="0" {{ $loginSec['login.remember_me']==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">2FA Challenge (min)</label><input name="login_2fa_lifetime" type="number" class="form-control" value="{{ $loginSec['login.2fa_challenge_lifetime'] }}"></div>
                    <div class="col-md-2"><label class="form-label">Password Min</label><input name="password_min" type="number" class="form-control" value="{{ $loginSec['password.min_length'] }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save Login Security</button>
                </form>
            </div>

            {{-- QUEUE --}}
            <div class="settings-pane" id="pane-queue">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-stack"></i> Queue / Background Jobs</div></div>
                <dl class="row">
                    <dt class="col-sm-4">Queue Driver</dt><dd class="col-sm-8"><code>{{ $queueDriver }}</code></dd>
                    <dt class="col-sm-4">Default Queue</dt><dd class="col-sm-8">{{ config('queue.connections.database.queue','default') }} , {{ config('notifications.delivery.queue','notifications') }}</dd>
                    <dt class="col-sm-4">Pending Jobs</dt><dd class="col-sm-8">{{ $queuePending }}</dd>
                    <dt class="col-sm-4">Failed Jobs</dt><dd class="col-sm-8">{{ $queueFailed }}</dd>
                    <dt class="col-sm-4">Last Job</dt><dd class="col-sm-8">{{ $queueLastJob }}</dd>
                    <dt class="col-sm-4">Last Failed</dt><dd class="col-sm-8">{{ $queueLastFailed }}</dd>
                </dl>
                <div class="alert alert-info small">Worker must run: <code>php artisan queue:work database --queue=default,notifications --tries=3 --timeout=25</code></div>
                <form method="POST" action="{{ route('admin.platform-settings.queue.health') }}">@csrf<button class="btn btn-outline-primary" type="submit">Queue Health Check</button></form>
            </div>

            {{-- NOTIFICATIONS --}}
            <div class="settings-pane" id="pane-notifications">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-bell"></i> Notifications</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.notifications') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Email Enabled</label><select name="notif_email" class="form-select"><option value="1" {{ $notifEmail==='1'?'selected':'' }}>Yes</option><option value="0" {{ $notifEmail==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">SMS Enabled</label><select name="notif_sms" class="form-select"><option value="1" {{ $notifSms==='1'?'selected':'' }}>Yes</option><option value="0" {{ $notifSms==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Queue</label><select name="notif_queue" class="form-select"><option value="1" {{ $notifQueue==='1'?'selected':'' }}>Yes</option><option value="0" {{ $notifQueue==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Retry Count</label><input name="notif_retry" type="number" class="form-control" value="{{ $notifRetry }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save Notifications</button>
                </form>
                <p class="text-muted small mt-3">Engine: NotificationService → MailChannel/SmsChannel → SendNotificationJob (queue: notifications)</p>
            </div>

            {{-- PAYMENT --}}
            <div class="settings-pane" id="pane-payment">
                <div class="alert alert-success small"><i class="bi bi-check-circle"></i> ACTIVE — Runtime now reads <code>BkashConfig::get()</code> (DB <code>payment.*</code> → env fallback). Credentials encrypted; institute gateway still has priority.</div>
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-credit-card"></i> Payment Gateways — <small class="text-muted">ACTIVE</small></div></div>
                <form method="POST" action="{{ route('admin.platform-settings.payment') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Provider</label><input name="payment_provider" class="form-control" value="{{ $payProvider }}" placeholder="bkash / mock"></div>
                    <div class="col-md-2"><label class="form-label">Mode</label><select name="payment_mode" class="form-select"><option value="sandbox" {{ $payMode==='sandbox'?'selected':'' }}>Sandbox</option><option value="live" {{ $payMode==='live'?'selected':'' }}>Live</option></select></div>
                    <div class="col-md-2"><label class="form-label">Currency</label><input name="payment_currency" class="form-control" value="{{ $payCurrency }}"></div>
                    <div class="col-md-2"><label class="form-label">Enabled</label><select name="payment_enabled" class="form-select"><option value="1" {{ $payEnabled==='1'?'selected':'' }}>Yes</option><option value="0" {{ $payEnabled==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-3"><label class="form-label">API Key</label><input name="payment_api_key" type="password" class="form-control" placeholder="{{ $payKeyMasked }}"></div>
                    <div class="col-md-3"><label class="form-label">API Secret</label><input name="payment_api_secret" type="password" class="form-control" placeholder="{{ $paySecretMasked }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save Payment</button>
                </form>
                <p class="text-muted small mt-2">Existing engine: payment_gateways / institute_payment_gateways / online_payment_attempts — credentials encrypted.</p>
            </div>

            {{-- STORAGE --}}
            <div class="settings-pane" id="pane-storage">
                <div class="alert alert-success small"><i class="bi bi-check-circle"></i> ACTIVE — New uploads use <code>StorageConfig::disk()</code> (DB <code>storage.disk</code> → env). Existing files stay on their original disk.</div>
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-hdd"></i> Storage — <small class="text-muted">ACTIVE</small></div></div>
                <form method="POST" action="{{ route('admin.platform-settings.storage') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Disk</label><select name="storage_disk" class="form-select"><option value="local" {{ $storageDisk==='local'?'selected':'' }}>Local (private)</option><option value="public" {{ $storageDisk==='public'?'selected':'' }}>Public</option><option value="s3" {{ $storageDisk==='s3'?'selected':'' }}>S3-compatible</option></select></div>
                    <div class="col-md-3"><label class="form-label">Max Size (KB)</label><input name="storage_max" type="number" class="form-control" value="{{ $storageMaxKb }}"></div>
                    <div class="col-md-2"><label class="form-label">Auto Resize</label><select name="storage_resize" class="form-select"><option value="1" {{ $storageResize==='1'?'selected':'' }}>Yes</option><option value="0" {{ $storageResize==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">WebP</label><select name="storage_webp" class="form-select"><option value="1" {{ $storageWebp==='1'?'selected':'' }}>Yes</option><option value="0" {{ $storageWebp==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Thumbnails</label><select name="storage_thumb" class="form-select"><option value="1" {{ $storageThumb==='1'?'selected':'' }}>Yes</option><option value="0" {{ $storageThumb==='0'?'selected':'' }}>No</option></select></div>
                </div>
                <button class="btn btn-primary" type="submit">Save Storage</button>
                </form>
            </div>

            {{-- MAPS --}}
            <div class="settings-pane" id="pane-maps">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-geo-alt"></i> Maps & Geolocation</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.maps') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Enabled</label><select name="maps_enabled" class="form-select"><option value="1" {{ $mapsEnabled==='1'?'selected':'' }}>Yes</option><option value="0" {{ $mapsEnabled==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Geocoding</label><select name="maps_geocoding" class="form-select"><option value="1" {{ $mapsGeocoding==='1'?'selected':'' }}>Yes</option><option value="0" {{ $mapsGeocoding==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-2"><label class="form-label">Places</label><select name="maps_places" class="form-select"><option value="1" {{ $mapsPlaces==='1'?'selected':'' }}>Yes</option><option value="0" {{ $mapsPlaces==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-3"><label class="form-label">API Key</label><input name="maps_api_key" type="password" class="form-control" placeholder="{{ $mapsKeyMasked }}"></div>
                    <div class="col-md-3"><label class="form-label">Default Country</label><input name="maps_default_country_hidden" class="form-control" value="{{ $mapsCountry }}" disabled><input type="hidden" name="maps_default_country" value="{{ $mapsCountry }}"></div>
                    <div class="col-md-3"><label class="form-label">Default Lat</label><input name="maps_lat" class="form-control" value="{{ $mapsLat }}"></div>
                    <div class="col-md-3"><label class="form-label">Default Lng</label><input name="maps_lng" class="form-control" value="{{ $mapsLng }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save Maps</button>
                </form>
                <p class="text-muted small mt-2">Keys encrypted/masked. No Google Maps integration currently active — uses offline Geo tables (countries/administrative_units).</p>
            </div>

            {{-- AI --}}
            <div class="settings-pane" id="pane-ai">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-robot"></i> AI</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.ai') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">Enabled</label><select name="ai_enabled" class="form-select"><option value="1" {{ $aiEnabled==='1'?'selected':'' }}>Yes</option><option value="0" {{ $aiEnabled==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-3"><label class="form-label">Provider</label><select name="ai_provider" class="form-select"><option value="openai" {{ $aiProvider==='openai'?'selected':'' }}>OpenAI</option><option value="anthropic" {{ $aiProvider==='anthropic'?'selected':'' }}>Anthropic (Claude)</option><option value="gemini" {{ $aiProvider==='gemini'?'selected':'' }}>Google Gemini</option><option value="groq" {{ $aiProvider==='groq'?'selected':'' }}>Groq</option><option value="custom" {{ $aiProvider==='custom'?'selected':'' }}>Custom (OpenAI-compatible)</option></select><small class="text-muted d-block mt-1">API keys stored per provider and encrypted.</small></div>
                    <div class="col-md-3"><label class="form-label">Model</label><input name="ai_model" class="form-control" value="{{ $aiModel }}"></div>
                    <div class="col-md-4"><label class="form-label">Base URL</label><input name="ai_base_url" type="url" class="form-control" value="{{ $aiBaseUrl }}"></div>
                    <div class="col-md-4"><label class="form-label">API Key</label><input name="ai_api_key" type="password" class="form-control" placeholder="{{ $aiKeyMasked }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save AI</button>
                </form>
                <p class="text-muted small mt-2">Reuses AiConfig / AiProvider / AiToolRegistry — API key encrypted at rest.</p>
                @include('admin.settings._ai_api_keys')
            </div>

            {{-- API --}}
            <div class="settings-pane" id="pane-api">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-code-slash"></i> API & Webhooks — Messaging</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.api') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-2"><label class="form-label">API Enabled</label><select name="api_enabled" class="form-select"><option value="1" {{ $apiEnabled==='1'?'selected':'' }}>Yes</option><option value="0" {{ $apiEnabled==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-4"><label class="form-label">Webhook URL</label><input name="webhook_url" type="url" class="form-control" value="{{ $webhookUrl }}"></div>
                    <div class="col-md-3"><label class="form-label">Webhook Secret</label><input name="webhook_secret" type="password" class="form-control" placeholder="{{ $webhookSecretMasked }}"></div>
                    <div class="col-md-2"><label class="form-label">Retry</label><input name="webhook_retry" type="number" class="form-control" value="{{ $webhookRetry }}"></div>
                    <div class="col-md-2"><label class="form-label">Timeout (s)</label><input name="webhook_timeout" type="number" class="form-control" value="{{ $webhookTimeout }}"></div>
                </div>
                <button class="btn btn-primary" type="submit">Save API</button>
                </form>
                <div class="alert alert-secondary small mt-3">WhatsApp: <strong>{{ $whatsappStatus }}</strong> — future provider via SmsProviderContract architecture. Email/SMS are active channels.</div>
            </div>

            {{-- DGDA --}}
            <div class="settings-pane" id="pane-dgda">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-capsule"></i> DGDA Drug Codes</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.dgda') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">DGDA Integration</label>
                        <div class="form-check form-switch mt-1">
                            <input type="hidden" name="dgda_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="dgda_enabled" value="1" id="dgdaToggle" {{ ($dgdaEnabled ?? '0') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="dgdaToggle">{{ ($dgdaEnabled ?? '0') === '1' ? 'Enabled' : 'Disabled' }}</label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Save DGDA</button>
                </form>
                <p class="text-muted small mt-2">When enabled, prescription forms show DGDA codes per medicine, print/PDF tables carry a DGDA column, uncoded items raise a notice on save, and <code>medical:dgda-sync</code> may run. When disabled, all of that is hidden or skipped. Registry codes themselves are never invented — they come from DGDA/OCL sync only.</p>
            </div>

            {{-- ADMIN: Account (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-account">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-person-gear"></i> Account</div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $admin->name ?? 'Yasin Sheikh' }}</dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $admin->email }}</dd>
                    <dt class="col-sm-4">Role</dt><dd class="col-sm-8">{{ $roleLabel }}</dd>
                </dl>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-key"></i> {{ mawa_e('settings_page.change_password') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.password') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                            @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Update Password</button>
                </form>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-translate"></i> Language</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.language') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="language">Language</label>
                            <select id="language" name="language" class="form-select">
                                <option value="en" {{ $preferredLanguage === 'en' ? 'selected' : '' }}>English</option>
                                <option value="bn" {{ $preferredLanguage === 'bn' ? 'selected' : '' }}>বাংলা</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-translate"></i> Save Language</button>
                </form>
            </div>

            {{-- ADMIN: Staff Requests (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-staff">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-person-plus-fill"></i> Staff Registration Requests ({{ $pendingStaff->count() }})</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Institute</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registered</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingStaff as $staff)
                                <tr>
                                    <td class="fw-semibold">{{ $staff->name }}</td>
                                    <td>{{ $staff->institute->name ?? '—' }}</td>
                                    <td>{{ $staff->email }}</td>
                                    <td>{{ $staff->phone }}</td>
                                    <td><x-tdate :value="$staff->created_at" fallback="d M Y H:i" :datetime="true" /></td>
                                    <td class="text-end">
                                        <form class="d-inline" method="POST" action="{{ route('admin.settings.staff-action', $staff) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check-lg"></i> Approve</button>
                                        </form>
                                        <form class="d-inline" method="POST" action="{{ route('admin.settings.staff-action', $staff) }}"
                                              onsubmit="return confirm('Reject registration for {{ $staff->name }}?');">
                                            @csrf
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-lg"></i> Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No pending staff registrations.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ADMIN: Appearance (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-appearance">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-palette"></i> {{ mawa_e('settings_page.theme_heading') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.appearance.update') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label">Theme</label>
                            <div class="row g-3">
                                @foreach ($themes as $item)
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <label class="theme-option {{ $activeTheme?->id === $item->id ? 'selected' : '' }}" data-theme-option>
                                            <input type="radio" name="theme_id" value="{{ $item->id }}" {{ $activeTheme?->id === $item->id ? 'checked' : '' }}>
                                            <div class="theme-swatch">
                                                <div class="swatch-primary" style="background:{{ $item->primary_color }}"></div>
                                                <div class="swatch-secondary" style="background:{{ $item->secondary_color }}"></div>
                                            </div>
                                            <span class="theme-name">{{ $item->name }}</span>
                                            @if ($activeTheme?->id === $item->id)
                                                <i class="bi bi-check-circle-fill theme-check"></i>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('theme_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sidebar_color">Navigation Drawer Color</label>
                            <input type="color" id="sidebar_color" name="sidebar_color" class="form-control form-control-color"
                                   value="{{ $sidebarColor ?? '#FFFFFF' }}">
                            @error('sidebar_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tall Navigation</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" id="tall_navigation" name="tall_navigation" value="1" @checked($tallNavigation ?? false)>
                                <label class="form-check-label small text-muted" for="tall_navigation">
                                    Topbar stretches full width, sidebar sits below it
                                </label>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                </form>
            </div>

            {{-- ADMIN: Mail & Payment (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-mail">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-envelope"></i> SMTP</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail-payment.update') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="{{ $smtpHost }}" placeholder="smtp.gmail.com">
                            @error('smtp_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_port">SMTP Port</label>
                            <input type="text" id="smtp_port" name="smtp_port" class="form-control" value="{{ $smtpPort }}" placeholder="587">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_encryption">Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption" class="form-select">
                                <option value="none" {{ $smtpEncryption === 'none' ? 'selected' : '' }}>None</option>
                                <option value="tls" {{ $smtpEncryption === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ $smtpEncryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_username">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="{{ $smtpUsername }}" placeholder="you@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-control" value="" placeholder="{{ $smtpPasswordMasked ?? '••••••••' }}">
                            <div class="form-text small text-muted">{{ ($smtpConfigured ?? false) ? 'Leave blank to keep existing password' : 'Not configured' }}</div>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
                </form>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-credit-card"></i> {{ mawa_e('settings_page.payment_heading') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail-payment.update') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="payment_gateway">Payment Gateway</label>
                            <input type="text" id="payment_gateway" name="payment_gateway" class="form-control" value="{{ $paymentGateway }}" placeholder="bKash / Nagad / Stripe">
                            @error('payment_gateway')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
                </form>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-send"></i> {{ mawa_e('settings_page.test_email_heading') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.settings.mail-payment.test') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label" for="test_email">Recipient</label>
                            <input type="email" id="test_email" name="test_email" class="form-control" value="{{ $admin->email }}" placeholder="you@example.com">
                            @error('smtp_test')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send"></i> Save & Test</button>
                </form>
            </div>

            {{-- ADMIN: AI (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-ai">
                @include('admin.settings._ai', ['aiEmbedded' => true])
            </div>

            {{-- ADMIN: Security (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-security">
                @include('security._panel')
            </div>

            {{-- ADMIN: Geo Settings (merged from admin/settings) --}}
            <div class="settings-pane" id="pane-admin-geo">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-globe2"></i> Geo Settings</div>
                </div>
                <p class="text-muted">Manage the geography data used across the platform — countries, administrative levels/units and geography package imports.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <a class="text-decoration-none" href="{{ route('admin.geo.index') }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fs-3"><i class="bi bi-globe"></i></span>
                                    <div>
                                        <div class="fw-semibold fs-6">Locations</div>
                                        <div class="text-muted small">Manage countries, levels and the world's modern administrative units.</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-primary small"><i class="bi bi-arrow-right"></i> Open Locations</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a class="text-decoration-none" href="{{ route('admin.geo.imports') }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fs-3"><i class="bi bi-cloud-arrow-up"></i></span>
                                    <div>
                                        <div class="fw-semibold fs-6">Import Geography Package</div>
                                        <div class="text-muted small">Import a geography package for countries and administrative units.</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-primary small"><i class="bi bi-arrow-right"></i> Open Import</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            {{-- INDUSTRY & THEMES (merged from abolished admin/industry-settings) --}}
            <div class="settings-pane" id="pane-industry">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-diagram-2-fill"></i> {{ $selectedLabel }} Settings</div>
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <div class="dropdown country-filter">
                            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by country">
                                <i class="bi bi-globe2"></i>
                                <span class="country-filter-label">
                                    @if (isset($country) && $country)
                                        <img src="{{ mawa_country_flag($country) }}" class="country-flag me-1" alt="" width="18" height="13">
                                        {{ config('countries')[$country] ?? $country }}
                                    @else
                                        {{ mawa_e('dashboard.all_countries') }}
                                    @endif
                                </span>
                                <i class="bi bi-chevron-down small"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item {{ ($country ?? null) === null ? 'active' : '' }}"
                                       href="{{ indSettingsLink(['industry' => $selectedKey, 'sub_industry' => $subIndustry ?? null, 'country' => null]) }}">
                                        <i class="bi bi-globe me-2"></i>{{ mawa_e('dashboard.all_countries') }}
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @foreach (config('countries') as $value => $label)
                                    <li>
                                        <a class="dropdown-item {{ ($country ?? null) === $value ? 'active' : '' }}"
                                           href="{{ indSettingsLink(['industry' => $selectedKey, 'sub_industry' => $subIndustry ?? null, 'country' => $value]) }}">
                                            <img src="{{ mawa_country_flag($value) }}" class="country-flag me-1" alt="" width="18" height="13">
                                            {{ $label }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @if ($selectedKey !== 'all' && count($subIndustries) > 0)
                            <div class="dropdown sub-industry-filter">
                            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by sub industry">
                                <i class="bi bi-diagram-2-fill"></i>
                                <span class="sub-industry-filter-label">
                                    {{ ($subIndustry ?? null) ? ($subIndustries[$subIndustry] ?? $subIndustry) : mawa_e('dashboard.all_sub_industries') }}
                                </span>
                                <i class="bi bi-chevron-down small"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item {{ ($subIndustry ?? null) === null ? 'active' : '' }}"
                                       href="{{ indSettingsLink(['industry' => $selectedKey, 'country' => $country ?? null, 'sub_industry' => null]) }}">
                                        <i class="bi bi-collection me-2"></i>{{ mawa_e('dashboard.all_sub_industries') }}
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @foreach ($subIndustries as $subValue => $subLabel)
                                    <li>
                                        <a class="dropdown-item {{ ($subIndustry ?? null) === $subValue ? 'active' : '' }}"
                                           href="{{ indSettingsLink(['industry' => $selectedKey, 'country' => $country ?? null, 'sub_industry' => $subValue]) }}">
                                            <i class="bi bi-diagram-2 me-2"></i>{{ $subLabel }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small mb-0" for="industrySelect">Industry:</label>
                            <select id="industrySelect" class="form-select form-select-sm" style="max-width: 320px;" aria-label="Select an industry">
                                <option value="{{ indSettingsLink(['country' => $country ?? null, 'sub_industry' => $subIndustry ?? null]) }}" {{ $selectedKey === 'all' ? 'selected' : '' }}>All Industries</option>
                                @foreach ($industries as $key => $label)
                                    <option value="{{ indSettingsLink(['industry' => $key, 'country' => $country ?? null, 'sub_industry' => $subIndustry ?? null]) }}" {{ $selectedKey === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-toolbar mt-3">
                    <div class="toolbar-info"><i class="bi bi-info-circle-fill"></i> How it works</div>
                </div>
                <p class="text-muted mb-0">Select an industry from the top-right to view and manage settings specific to that industry. Settings here apply platform-wide for institutes of the chosen industry. Choose "All Industries" to set a fallback used when no industry-specific theme exists, and to manage the platform themes themselves.</p>

                <div class="table-toolbar mt-4">
                    <div class="toolbar-info"><i class="bi bi-palette-fill"></i> Manage Themes</div>
                </div>
                <p class="text-muted">Edit the platform themes used across all industries. The theme marked as default is the fallback used by institutes before any industry default applies.</p>

                <div class="table-toolbar mt-4">
                    <div class="toolbar-info"><i class="bi bi-palette"></i> Default Color Theme</div>
                </div>
                <p class="text-muted">Choose the default theme applied to institutes of <strong>{{ $selectedLabel }}</strong> when they have not set their own theme.</p>
                <form method="POST" action="{{ route('admin.industry-settings.theme') }}">
                    @csrf
                    <input type="hidden" name="industry_key" value="{{ $selectedKey }}">
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label">Color Theme</label>
                            <div class="row g-3">
                                @foreach ($themes as $item)
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <label class="theme-option {{ $setting?->theme_slug === $item->slug ? 'selected' : '' }}" data-theme-option>
                                            <input type="radio" name="theme_slug" value="{{ $item->slug }}" {{ $setting?->theme_slug === $item->slug ? 'checked' : '' }}>
                                            <div class="theme-swatch">
                                                <div class="swatch-primary" style="background:{{ $item->primary_color }}"></div>
                                                <div class="swatch-secondary" style="background:{{ $item->secondary_color }}"></div>
                                            </div>
                                            <span class="theme-name">{{ $item->name }}</span>
                                            @if ($setting?->theme_slug === $item->slug)
                                                <i class="bi bi-check-circle-fill theme-check"></i>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('theme_slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @error('industry_key')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Default Theme</button>
                    @if (session('status'))
                        <span class="text-success small ms-2">{{ session('status') }}</span>
                    @endif
                </form>

                <hr class="my-4">

                @foreach ($allThemes as $item)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="d-inline-block rounded-circle" style="width:18px;height:18px;background:{{ $item->primary_color }};border:1px solid rgba(0,0,0,.15)"></span>
                            <span class="fw-semibold">{{ $item->name }}</span>
                            @if ($item->is_default)
                                <span class="badge bg-success">Default</span>
                            @endif
                            @if ($item->is_dark)
                                <span class="badge bg-dark">Dark</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.themes.update', $item) }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="name_{{ $item->id }}">Theme Name</label>
                                    <input type="text" id="name_{{ $item->id }}" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="primary_{{ $item->id }}">Primary</label>
                                    <input type="color" id="primary_{{ $item->id }}" name="primary_color" class="form-control form-control-color" value="{{ old('primary_color', $item->primary_color) }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="secondary_{{ $item->id }}">Secondary</label>
                                    <input type="color" id="secondary_{{ $item->id }}" name="secondary_color" class="form-control form-control-color" value="{{ old('secondary_color', $item->secondary_color) }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="status_{{ $item->id }}">Status</label>
                                    <select id="status_{{ $item->id }}" name="status" class="form-select">
                                        <option value="active" {{ old('status', $item->status) === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $item->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex flex-column justify-content-end gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_dark" value="1" id="dark_{{ $item->id }}" {{ $item->is_dark ? 'checked' : '' }}>
                                        <label class="form-check-label" for="dark_{{ $item->id }}">Dark Mode</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="default_{{ $item->id }}" {{ $item->is_default ? 'checked' : '' }}>
                                        <label class="form-check-label" for="default_{{ $item->id }}">Mark as default</label>
                                    </div>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    @error('name')<span class="text-danger small me-2">{{ $message }}</span>@enderror
                                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>

            {{-- BRANDING --}}
            <div class="settings-pane" id="pane-branding">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-brush"></i> Branding — <small class="text-muted">ACTIVE</small></div></div>
                <form method="POST" action="{{ route('admin.platform-settings.branding') }}" enctype="multipart/form-data">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Platform Name</label><input name="brand_name" class="form-control" value="{{ $brandName }}" required></div>
                    <div class="col-md-6"><label class="form-label">Footer Text</label><input name="brand_footer" class="form-control" value="{{ $brandFooter }}"></div>
                    <div class="col-md-3"><label class="form-label">Primary Color</label><input name="brand_primary" type="color" class="form-control form-control-color" value="{{ $brandPrimary ?: '#0d6efd' }}"><small class="text-muted">{{ $brandPrimary ?: 'NOT SET' }}</small></div>
                    <div class="col-md-3"><label class="form-label">Secondary Color</label><input name="brand_secondary" type="color" class="form-control form-control-color" value="{{ $brandSecondary ?: '#6c757d' }}"><small class="text-muted">{{ $brandSecondary ?: 'NOT SET' }}</small></div>
                    <div class="col-md-3"><label class="form-label">Logo (png/jpg/webp, max 2MB)</label><input name="brand_logo" type="file" class="form-control" accept=".png,.jpg,.jpeg,.webp,.gif">@if(filled($brandLogo))<small class="text-muted">Current: {{ $brandLogo }}</small>@endif</div>
                    <div class="col-md-3"><label class="form-label">Favicon (png/ico, max 512KB)</label><input name="brand_favicon" type="file" class="form-control" accept=".png,.ico,.jpg,.jpeg,.webp">@if(filled($brandFavicon))<small class="text-muted">Current: {{ $brandFavicon }}</small>@endif</div>
                </div>
                <button class="btn btn-primary" type="submit">Save Branding</button>
                </form>
                <p class="text-muted small mt-2">Branding is ACTIVE via AppServiceProvider (app.name + brand.*). Logo/favicon stored on public disk under branding/.</p>
            </div>

            {{-- MAINTENANCE --}}
            <div class="settings-pane" id="pane-maintenance">
                <div class="table-toolbar"><div class="toolbar-info"><i class="bi bi-tools"></i> Maintenance</div></div>
                <form method="POST" action="{{ route('admin.platform-settings.maintenance') }}">@csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Maintenance Mode</label><select name="maint_enabled" class="form-select"><option value="1" {{ $maintEnabled==='1'?'selected':'' }}>Enabled</option><option value="0" {{ $maintEnabled==='0'?'selected':'' }}>Disabled</option></select></div>
                    <div class="col-md-3"><label class="form-label">Allow Super Admin</label><select name="maint_allow_admin" class="form-select"><option value="1" {{ $maintAllowAdmin==='1'?'selected':'' }}>Yes</option><option value="0" {{ $maintAllowAdmin==='0'?'selected':'' }}>No</option></select></div>
                    <div class="col-md-6"><label class="form-label">Maintenance Message</label><input name="maint_message" class="form-control" value="{{ $maintMessage }}" placeholder="Scheduled maintenance..."></div>
                </div>
                <button class="btn btn-warning" type="submit">Save Maintenance</button>
                </form>
                <div class="alert alert-warning small mt-3">Super Admin is never locked out when "Allow Super Admin" is enabled.</div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
 var tabs=document.querySelectorAll('.settings-tab-btn');
 var panes=document.querySelectorAll('.settings-pane');
 function act(id){ tabs.forEach(b=>{b.classList.toggle('active',b.dataset.target===id)}); panes.forEach(p=>{p.classList.toggle('active',p.id===id)}); if(history.replaceState) history.replaceState(null,'','#'+id); }
 tabs.forEach(b=>b.addEventListener('click',()=>act(b.dataset.target)));
 var h=location.hash; if(h){ var id=h.slice(1); if(document.getElementById(id)) act(id); }
})();
</script>
<script>
// Merged industry pane: filter navigation guard + theme option picker.
(function () {
    var sel = document.getElementById('industrySelect');
    if (sel) sel.addEventListener('change', function () {
        var v = this && this.value ? String(this.value) : '';
        if (!v || v.indexOf('[object') !== -1 || v.indexOf('%5Bobject') !== -1) { return; }
        window.location.href = v;
    });
    document.querySelectorAll('[data-theme-option]').forEach(function (label) {
        var input = label.querySelector('input');
        label.addEventListener('click', function () {
            document.querySelectorAll('[data-theme-option]').forEach(function (other) {
                other.classList.remove('selected');
                var check = other.querySelector('.theme-check');
                if (check) check.remove();
            });
            label.classList.add('selected');
            var check = label.querySelector('.theme-check');
            if (!check) {
                check = document.createElement('i');
                check.className = 'bi bi-check-circle-fill theme-check';
                label.appendChild(check);
            }
            if (input) input.checked = true;
        });
    });
})();
</script>
@endpush
