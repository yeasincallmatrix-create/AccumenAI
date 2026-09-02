@php
    $isPlatformAdmin = $securityGuard === 'platform_admin';
    $prefix = $isPlatformAdmin ? 'admin' : 'account';
    $hasSecret = ! empty($securityUser->two_factor_secret);
    $isConfirmed = ! empty($securityUser->two_factor_confirmed_at);
    $verified = $securityUser->hasVerifiedEmail();
    // E18: 2FA method statuses
    $phoneVerified = ! empty($securityUser->phone_verified_at);
    $emailVerified = ! empty($securityUser->email_verified_at);
    $smsEnabled = ! empty($securityUser->sms_2fa_enabled);
    $email2faEnabled = ! empty($securityUser->email_2fa_enabled);
    $preferred = $securityUser->preferred_2fa_method ?? null;
    // Masks
    $maskPhone = $securityUser->phone ? substr($securityUser->phone,0,3).str_repeat('*', max(0, strlen($securityUser->phone)-6)).substr($securityUser->phone,-3) : null;
    if ($securityUser->email) {
        $parts = explode('@', $securityUser->email);
        $local = $parts[0] ?? '';
        $domain = $parts[1] ?? '';
        $maskedEmail = strlen($local) > 2 ? substr($local,0,1).str_repeat('*', strlen($local)-2).substr($local,-1).'@'.$domain : str_repeat('*', strlen($local)).'@'.$domain;
    } else {
        $maskedEmail = null;
    }
@endphp

@if (! $verified)
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <div><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ mawa_e('security.not_verified_warning') }}</div>
        <div class="ms-3 d-flex align-items-center gap-2">
            <span id="resend-email-status" class="small" style="display:none"></span>
            <button type="button" id="resend-email-btn" class="btn btn-sm btn-outline-warning" data-resend-url="{{ route($prefix . '.verification.send') }}">
                {{ mawa_e('security.resend_verification') }}
            </button>
        </div>
    </div>
@endif

{{-- E18: Phone verification prompt --}}
@if (! $phoneVerified && ! empty($securityUser->phone))
    <div class="alert alert-info">
        <i class="bi bi-phone me-1"></i> Your mobile number {{ $maskPhone }} is not verified. Verify to enable SMS two-step verification.
        <span class="small text-muted">We sent a 6-digit verification code to {{ $maskPhone }}. Enter the 6-digit code sent to your mobile.</span>
        <div class="mt-2">
            <form method="POST" action="{{ route('account.phone.verify') }}" class="d-flex gap-2" style="max-width:400px">
                @csrf
                <input type="text" name="otp" inputmode="numeric" class="form-control form-control-sm" placeholder="000000" required maxlength="6">
                <button type="submit" class="btn btn-sm btn-primary">Verify</button>
            </form>
            <form method="POST" action="{{ route('account.phone.verify-send') }}" class="mt-2">
                @csrf
                <div class="input-group input-group-sm" style="max-width:400px">
                    <input type="password" name="password" class="form-control" placeholder="Current password" required>
                    <button type="submit" class="btn btn-outline-secondary">Resend Code</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- ============ Two-factor authentication ============ --}}
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-shield-lock"></i> {{ mawa_e('security.two_factor_heading') }}</div>
        @if ($isConfirmed)
            <form method="POST" action="{{ route($prefix . '.two-factor.disable') }}" class="d-inline"
                  onsubmit="return confirm('{{ mawa_e('security.disable_confirm') }}');">
                @csrf
                <div class="input-group input-group-sm" style="width:auto">
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="{{ mawa_e('security.current_password') }}" required autocomplete="current-password">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-shield-slash"></i> {{ mawa_e('security.two_factor_disable') }}</button>
                </div>
            </form>
        @endif
    </div>

    <div class="p-3 pt-2">

        @if (! $hasSecret)
            <p class="text-body-secondary mb-3">{{ mawa_e('security.two_factor_desc_off') }}</p>
            <form method="POST" action="{{ route($prefix . '.two-factor.enable') }}" style="max-width:420px">
                @csrf
                <label class="form-label" for="enable-password">{{ mawa_e('security.current_password') }}</label>
                <div class="input-group">
                    <input type="password" id="enable-password" name="password" class="form-control" placeholder="{{ mawa_e('security.current_password') }}" required autocomplete="current-password">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-plus"></i> {{ mawa_e('security.two_factor_enable') }}</button>
                </div>
                @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        @endif

        @if ($hasSecret && ! $isConfirmed)
            <div data-two-factor-confirming>
                <p class="text-body-secondary mb-3">{{ mawa_e('security.two_factor_desc_confirm') }}</p>

                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card border" data-qr-container>
                            <div class="card-body text-center py-4">
                                <div id="twoFactorQr" class="mb-3"><span class="spinner-border spinner-border-sm text-primary"></span></div>
                                <div class="text-start small text-body-secondary">
                                    <strong>{{ mawa_e('security.setup_key') }}:</strong>
                                    <code data-setup-key class="d-block text-break mt-1"></code>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <form method="POST" action="{{ route($prefix . '.two-factor.confirm') }}">
                            @csrf
                            <label class="form-label" for="confirm-password">{{ mawa_e('security.current_password') }}</label>
                            <input type="password" id="confirm-password" name="password" class="form-control mb-2" required autocomplete="current-password">
                            <label class="form-label" for="confirm-code">{{ mawa_e('security.confirm_code') }}</label>
                            <input type="text" id="confirm-code" name="code" inputmode="numeric" class="form-control mb-2" required autocomplete="one-time-code">
                            @error('code')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check"></i> {{ mawa_e('security.two_factor_confirm') }}</button>
                        </form>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>{{ mawa_e('security.recovery_codes') }}</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-recovery-list>{{ mawa_e('security.show_recovery_codes') }}</button>
                    </div>
                    <p class="text-body-secondary small mb-2">{{ mawa_e('security.recovery_codes_hint') }}</p>
                    <ol id="recoveryCodesList" class="d-none row row-cols-1 row-cols-md-2 g-1 small"></ol>
                </div>
            </div>
        @endif

        @if ($isConfirmed)
            <p class="text-body-secondary mb-3">
                <span class="badge text-bg-success me-1"><i class="bi bi-check-circle-fill"></i></span>
                {{ mawa_e('security.two_factor_desc_on') }}
            </p>

            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>{{ mawa_e('security.recovery_codes') }}</strong>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-recovery-list>{{ mawa_e('security.show_recovery_codes') }}</button>
                        <form class="d-inline" method="POST" action="{{ route($prefix . '.two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-clockwise"></i> {{ mawa_e('security.regenerate_recovery_codes') }}</button>
                        </form>
                    </div>
                </div>
                <p class="text-body-secondary small mb-2">{{ mawa_e('security.recovery_codes_hint') }}</p>
                <ol id="recoveryCodesList" class="d-none row row-cols-1 row-cols-md-2 g-1 small"></ol>
            </div>
        @endif

    </div>
</div>

{{-- ============ E18/E19.1: Two-Factor Authentication - Optional SMS / Email / Authenticator App ============ --}}
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-shield-check"></i> Two-Factor Authentication</div>
        <span class="small text-muted">Add an extra layer of security to your account.</span>
    </div>
    <div class="p-3 pt-2">
        <p class="text-body-secondary small mb-3">Add an extra layer of security to your account. Choose the method you understand.</p>

        {{-- Authenticator App --}}
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1"><i class="bi bi-shield-lock"></i> Authenticator App</h6>
                    <p class="small text-muted mb-1">Use an authenticator app to generate security codes.</p>
                    @if ($isConfirmed)
                        <span class="badge text-bg-primary">Enabled</span>
                        @if ($preferred === 'totp') <span class="badge text-bg-dark">Preferred</span> @endif
                    @elseif ($hasSecret)
                        <span class="badge text-bg-warning">Not confirmed</span>
                    @else
                        <span class="badge text-bg-secondary">Disabled</span>
                    @endif
                </div>
                <div>
                    @if (! $hasSecret)
                        <span class="small text-muted">Set up in the section above</span>
                    @elseif (! $isConfirmed)
                        <span class="small text-muted">Finish setup above</span>
                    @else
                        <span class="small text-success"><i class="bi bi-check-circle"></i> Active</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- SMS OTP --}}
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1"><i class="bi bi-phone"></i> SMS OTP</h6>
                    <p class="small text-muted mb-1">Receive a one-time verification code on your verified mobile number.</p>
                    @if (! empty($securityUser->phone))
                        <small class="text-muted">{{ $maskPhone }}</small>
                        @if ($phoneVerified)
                            <span class="badge text-bg-success ms-1">Verified</span>
                        @else
                            <span class="badge text-bg-warning ms-1">Not verified</span>
                        @endif
                    @else
                        <small class="text-danger">No mobile number on file</small>
                    @endif
                    @if ($smsEnabled)
                        <span class="badge text-bg-primary ms-1">Enabled</span>
                        @if ($preferred === 'sms') <span class="badge text-bg-dark">Preferred</span> @endif
                    @elseif ($phoneVerified)
                        <span class="badge text-bg-secondary ms-1">Available</span>
                    @endif
                    @if (! $phoneVerified && ! empty($securityUser->phone))
                        <div class="small text-warning mt-1">Please verify your mobile number before enabling SMS OTP.</div>
                    @endif
                </div>
                <div>
                    @if ($smsEnabled)
                        <form method="POST" action="{{ route($prefix . '.two-factor.sms.disable') }}" class="d-inline" onsubmit="return confirm('Disable SMS two-step verification?');">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="password" name="password" class="form-control" placeholder="Current password" required>
                                <button type="submit" class="btn btn-outline-danger">Disable</button>
                            </div>
                        </form>
                    @else
                        <form method="POST" action="{{ route($prefix . '.two-factor.sms.enable') }}" class="d-inline">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="password" name="password" class="form-control" placeholder="Current password" required>
                                <button type="submit" class="btn btn-primary" @if(! $phoneVerified) disabled @endif>Enable</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Email OTP --}}
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1"><i class="bi bi-envelope"></i> Email OTP</h6>
                    <p class="small text-muted mb-1">Receive a one-time verification code by email when you sign in.</p>
                    @if (! empty($securityUser->email))
                        <small class="text-muted">{{ $maskedEmail }}</small>
                        @if ($emailVerified)
                            <span class="badge text-bg-success ms-1">Verified</span>
                        @else
                            <span class="badge text-bg-warning ms-1">Not verified</span>
                        @endif
                    @else
                        <small class="text-danger">No email on file</small>
                    @endif
                    @if ($email2faEnabled)
                        <span class="badge text-bg-primary ms-1">Enabled</span>
                        @if ($preferred === 'email') <span class="badge text-bg-dark">Preferred</span> @endif
                    @elseif ($emailVerified)
                        <span class="badge text-bg-secondary ms-1">Available</span>
                    @endif
                    @if (! $emailVerified && ! empty($securityUser->email))
                        <div class="small text-warning mt-1">Please verify your email address before enabling Email OTP.</div>
                    @endif
                </div>
                <div>
                    @if ($email2faEnabled)
                        <form method="POST" action="{{ route($prefix . '.two-factor.email.disable') }}" class="d-inline" onsubmit="return confirm('Disable Email two-step verification?');">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="password" name="password" class="form-control" placeholder="Current password" required>
                                <button type="submit" class="btn btn-outline-danger">Disable</button>
                            </div>
                        </form>
                    @else
                        <form method="POST" action="{{ route($prefix . '.two-factor.email.enable') }}" class="d-inline">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="password" name="password" class="form-control" placeholder="Current password" required>
                                <button type="submit" class="btn btn-primary" @if(! $emailVerified) disabled @endif>Enable</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if ($smsEnabled || $email2faEnabled || $isConfirmed)
            <div class="mt-3">
                <label class="form-label small">Preferred two-step method</label>
                <form method="POST" action="{{ route($prefix . '.two-factor.preferred') }}" class="d-flex gap-2">
                    @csrf
                    <select name="method" class="form-select form-select-sm" style="max-width:200px">
                        @if ($isConfirmed) <option value="totp" @if($preferred==='totp') selected @endif>Authenticator App</option> @endif
                        @if ($smsEnabled) <option value="sms" @if($preferred==='sms') selected @endif>SMS OTP</option> @endif
                        @if ($email2faEnabled) <option value="email" @if($preferred==='email') selected @endif>Email OTP</option> @endif
                    </select>
                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Current password" required style="max-width:200px">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                </form>
            </div>
        @endif
    </div>
</div>

{{-- ============ Sessions ============ --}}
<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-device-ssd"></i> {{ mawa_e('security.sessions_heading') }}</div>
    </div>

    <div class="p-3 pt-2">
        <p class="text-body-secondary mb-3">{{ mawa_e('security.sessions_hint') }}</p>

        @forelse ($sessions as $session)
            @php
                $current = $session->id === $currentSessionId;
                $agent = \App\Support\SessionAgent::fromUserAgent($session->user_agent);
            @endphp
            <div class="d-flex align-items-start gap-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                <div class="fs-4 text-primary"><i class="bi {{ $current ? 'bi-laptop-fill' : 'bi-device-ssd' }}"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">
                        {{ $agent['platform'] }} — {{ $agent['browser'] }}
                        @if ($current)
                            <span class="badge text-bg-primary ms-1">{{ mawa_e('security.current_device') }}</span>
                        @endif
                    </div>
                    <div class="small text-body-secondary">{{ $session->ip_address }} · {{ mawa_e('security.last_active') }} {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</div>
                </div>
                @if ($current)
                    <form method="POST" action="{{ route('logout') }}" class="ms-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> {{ mawa_e('security.logout_here') }}</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="text-muted text-center py-4">{{ mawa_e('security.sessions_empty') }}</div>
        @endforelse

        @if ($sessions->count() > 1 || $sessions->where('id', '!==', $currentSessionId)->count())
            <form method="POST" action="{{ route($prefix . '.sessions.revoke') }}" style="max-width:420px" class="mt-3">
                @csrf
                <label class="form-label" for="revoke-password">{{ mawa_e('security.log_out_other_hint') }}</label>
                <div class="input-group">
                    <input type="password" id="revoke-password" name="password" class="form-control" placeholder="{{ mawa_e('security.current_password') }}" required autocomplete="current-password">
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i> {{ mawa_e('security.log_out_other') }}</button>
                </div>
                @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        @endif

        @if ($isPlatformAdmin)
            <hr class="my-3">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <div class="fw-semibold text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Force Logout All Users</div>
                    <small class="text-muted">Terminates every session across all users (admins, institute users, everyone). You stay logged in.</small>
                </div>
                <form method="POST" action="{{ route('admin.sessions.flush-all') }}" class="ms-auto" onsubmit="return confirm('This will log out ALL users on ALL devices. Continue?');">
                    @csrf
                    <div class="input-group input-group-sm" style="width:auto">
                        <input type="password" name="password" class="form-control form-control-sm" placeholder="Your password" required autocomplete="current-password">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-power"></i> Logout All</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

<script>
(function () {
    function init() {
        var hasSecret = @json($hasSecret);
        var confirmed = @json($isConfirmed);
        var publicPrefix = @json($prefix);

        var resendBtn = document.getElementById('resend-email-btn');
        var resendStatus = document.getElementById('resend-email-status');
        if (resendBtn && resendStatus) {
            var resendCooldown = 0;
            var resendTimer = null;
            var resendSending = false;

            function setResendCooldown(seconds) {
                resendCooldown = seconds;
                resendBtn.disabled = true;
                resendBtn.textContent = 'Available in ' + resendCooldown + 's';
                resendTimer = setInterval(function () {
                    resendCooldown--;
                    if (resendCooldown <= 0) {
                        clearInterval(resendTimer);
                        resendTimer = null;
                        resendBtn.disabled = false;
                        resendBtn.textContent = '{{ mawa_e("security.resend_verification") }}';
                        resendStatus.style.display = 'none';
                    } else {
                        resendBtn.textContent = 'Available in ' + resendCooldown + 's';
                    }
                }, 1000);
            }

            resendBtn.addEventListener('click', function () {
                if (resendSending || resendBtn.disabled) return;
                resendSending = true;
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending...';
                resendStatus.style.display = 'none';

                fetch(resendBtn.getAttribute('data-resend-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
                .then(function (res) {
                    resendSending = false;
                    if (res.ok && res.data.success) {
                        resendStatus.textContent = 'Verification email sent.';
                        resendStatus.className = 'small text-success';
                        resendStatus.style.display = 'inline';
                        setResendCooldown(60);
                    } else if (res.status === 429) {
                        resendStatus.textContent = 'Too many requests.';
                        resendStatus.className = 'small text-warning';
                        resendStatus.style.display = 'inline';
                        resendBtn.disabled = false;
                        resendBtn.textContent = '{{ mawa_e("security.resend_verification") }}';
                    } else {
                        resendStatus.textContent = 'Failed. Try again.';
                        resendStatus.className = 'small text-danger';
                        resendStatus.style.display = 'inline';
                        resendBtn.disabled = false;
                        resendBtn.textContent = '{{ mawa_e("security.resend_verification") }}';
                    }
                })
                .catch(function () {
                    resendSending = false;
                    resendStatus.textContent = 'Network error.';
                    resendStatus.className = 'small text-danger';
                    resendStatus.style.display = 'inline';
                    resendBtn.disabled = false;
                    resendBtn.textContent = '{{ mawa_e("security.resend_verification") }}';
                });
            });
        }

        function loadData() {
            if (hasSecret && ! confirmed) {
                fetch('{{ route($prefix . '.two-factor.qr-code') }}', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var box = document.getElementById('twoFactorQr');
                        if (box) box.innerHTML = data.svg || '';
                        var setup = document.querySelector('[data-setup-key]');
                        if (setup) setup.textContent = data.setup_key || '';
                    })
                    .catch(function () {});
            }
            if (hasSecret) {
                fetch('{{ route($prefix . '.two-factor.recovery-codes') }}', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var list = document.getElementById('recoveryCodesList');
                        if (! list || ! data.codes) return;
                        list.innerHTML = '';
                        data.codes.forEach(function (code) {
                            var li = document.createElement('li');
                            li.className = 'col font-monospace';
                            li.textContent = code;
                            list.appendChild(li);
                        });
                    })
                    .catch(function () {});
            }
        }
        loadData();

        document.querySelectorAll('[data-toggle-recovery-list]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var list = document.getElementById('recoveryCodesList');
                if (! list) return;
                var hidden = list.classList.toggle('d-none');
                btn.innerHTML = hidden ? '<i class="bi bi-eye"></i> {{ mawa_e('security.show_recovery_codes') }}' : '<i class="bi bi-eye-slash"></i> {{ mawa_e('security.hide_recovery_codes') }}';
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
