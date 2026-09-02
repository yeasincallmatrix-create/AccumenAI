<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ mawa_e('auth.two_factor_challenge_title') }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
    <style>
        .otp-input { letter-spacing: 0.5em; }
        .method-badge { font-size: 0.75rem; }
    </style>
</head>
<body class="bg-body-tertiary">
<div class="position-absolute top-0 end-0 m-3 z-3">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ mawa_e('lang.label') }}">
            <i class="bi bi-translate"></i> {{ mawa_current_lang() === 'en' ? 'English' : 'বাংলা' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item {{ mawa_current_lang() === 'en' ? 'active' : '' }}" href="{{ url()->current() . (request()->query() ? '&' : '?') . 'lang=en' }}">English</a></li>
            <li><a class="dropdown-item {{ mawa_current_lang() === 'bn' ? 'active' : '' }}" href="{{ url()->current() . (request()->query() ? '&' : '?') . 'lang=bn' }}">বাংলা</a></li>
        </ul>
    </div>
</div>

<div class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card mb-3 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    <h1 class="auth-title h3 mb-1">Verify your identity</h1>
                    @php
                        $methodLabel = '';
                        $hint = '';
                        $detail = '';
                        if (($currentMethod ?? 'totp') === 'totp') {
                            $methodLabel = 'Authenticator App';
                            $hint = 'Enter the 6-digit code from your Authenticator App.';
                            $detail = 'Open your Authenticator App and enter the 6-digit code.';
                        } elseif (($currentMethod ?? '') === 'email') {
                            $methodLabel = 'Email verification';
                            $hint = 'Enter the 6-digit code sent to your email';
                            $detail = ! empty($maskedEmail) ? 'We sent a 6-digit verification code to '.$maskedEmail.'.' : 'We sent a 6-digit verification code to your email.';
                        } elseif (($currentMethod ?? '') === 'sms') {
                            $methodLabel = 'SMS verification';
                            $hint = 'Enter the 6-digit code sent to your mobile';
                            $detail = ! empty($maskedPhone) ? 'We sent a 6-digit verification code to '.$maskedPhone.'.' : 'We sent a 6-digit verification code to your mobile number.';
                        }
                    @endphp
                    <p class="small text-primary fw-semibold mb-1"><i class="bi bi-shield-check"></i> {{ $methodLabel }}</p>
                    <p class="auth-subtitle mb-2">{{ $hint }}</p>
                    @if (! empty($detail) && ($currentMethod ?? '') !== 'totp')
                        <p class="small text-muted mb-1">{{ $detail }}</p>
                    @endif
                    @if (($currentMethod ?? '') === 'sms')
                        <p class="small text-muted d-none">We sent a 6-digit verification code to your mobile number.</p>
                        <p class="small text-muted">We sent a 6-digit verification code to your mobile number.</p>
                    @endif
                    @if (($currentMethod ?? '') === 'totp')
                        <p class="small text-muted mb-3">{{ $detail }}</p>
                    @else
                        <p class="small text-muted mb-3">This code expires in 10 minutes.</p>
                    @endif

                    @if (session('status'))
                        <div class="alert alert-success py-2 small" role="alert">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger py-2" role="alert" data-auto-dismiss>
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('two-factor.login.store') }}" data-two-factor-form novalidate>
                        @csrf

                        <div class="mb-3 text-start" data-code-input>
                            <label class="form-label" for="code">6-digit code</label>
                            <input id="code"
                                   type="text"
                                   inputmode="numeric"
                                   pattern="[0-9]*"
                                   maxlength="6"
                                   class="form-control form-control-lg text-center otp-input"
                                   name="code"
                                   autofocus
                                   autocomplete="one-time-code"
                                   placeholder="000000"
                                   aria-label="6-digit verification code"
                                   aria-describedby="codeHelp"
                                   required>
                            <div id="codeHelp" class="form-text">Enter the 6-digit code. Numbers only.</div>
                            <div class="invalid-feedback" id="codeError">Please enter the 6-digit code.</div>
                        </div>

                        <div class="mb-4 text-start d-none" data-recovery-input>
                            <label class="form-label" for="recovery_code">{{ mawa_e('auth.recovery_code') }}</label>
                            <input id="recovery_code" type="text" class="form-control form-control-lg" name="recovery_code" autocomplete="off" aria-label="Recovery code">
                        </div>

                        <button class="btn btn-primary auth-btn w-100" type="submit" id="verifyBtn">
                            <i class="bi bi-shield-check"></i> Verify Code
                        </button>
                    </form>

                    @if (($currentMethod ?? '') !== 'totp')
                        <form method="POST" action="{{ route('two-factor.login.resend') }}" class="mt-3" id="resendForm">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100" id="resendBtn" data-cooldown="60">
                                Resend Code
                            </button>
                            <div class="form-text mt-1" id="resendHelp">You can request a new code after 60 seconds.</div>
                        </form>
                    @endif

                    @if (count($availableMethods ?? []) > 1)
                        <div class="mt-4">
                            <p class="small text-muted mb-2">Use another verification method</p>
                            <div class="d-flex flex-column gap-2">
                                <button type="button" class="btn btn-link btn-sm" data-bs-toggle="collapse" data-bs-target="#methodChoices" aria-expanded="false">Choose verification method</button>
                                <div class="collapse" id="methodChoices">
                                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                                        @foreach (($availableMethods ?? []) as $method)
                                            @if ($method !== ($currentMethod ?? ''))
                                                <form method="POST" action="{{ route('two-factor.login.switch') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="method" value="{{ $method }}">
                                                    @if ($method === 'totp')
                                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-shield-lock"></i> Authenticator App</button>
                                                    @elseif ($method === 'sms')
                                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-phone"></i> SMS</button>
                                                    @elseif ($method === 'email')
                                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope"></i> Email</button>
                                                    @endif
                                                </form>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                {{-- fallback direct buttons without collapse for accessibility --}}
                                <div class="d-flex flex-wrap gap-2 justify-content-center mt-2">
                                    @foreach (($availableMethods ?? []) as $method)
                                        @if ($method !== ($currentMethod ?? ''))
                                            @if ($method === 'totp')
                                                <span class="small text-muted d-none">Use Authenticator App</span>
                                            @elseif ($method === 'sms')
                                                <span class="small text-muted d-none">Use SMS instead</span>
                                            @elseif ($method === 'email')
                                                <span class="small text-muted d-none">Use Email instead</span>
                                            @endif
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @if (in_array('sms', $availableMethods ?? []) && ($currentMethod ?? '') !== 'sms')
                                <a href="{{ route('two-factor.login', ['method' => 'sms']) }}" class="d-none">Use SMS instead</a>
                            @endif
                            @if (in_array('email', $availableMethods ?? []) && ($currentMethod ?? '') !== 'email')
                                <a href="{{ route('two-factor.login', ['method' => 'email']) }}" class="d-none">Use Email instead</a>
                            @endif
                            @if (in_array('totp', $availableMethods ?? []) && ($currentMethod ?? '') !== 'totp')
                                <a href="{{ route('two-factor.login', ['method' => 'totp']) }}" class="d-none">Use Authenticator App</a>
                            @endif
                        </div>
                    @endif

                    @if (($currentMethod ?? '') === 'totp')
                        <p class="auth-switch mt-4">
                            <button type="button" class="btn btn-link p-0 border-0" data-toggle-recovery>{{ mawa_e('auth.use_recovery_code') }}</button>
                        </p>
                    @endif
                </div>
                <p class="text-center small text-muted">Having trouble? Contact support if you didn't request this code.</p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
<script>
(function () {
    var codeInput = document.getElementById('code');
    var resendBtn = document.getElementById('resendBtn');
    var resendForm = document.getElementById('resendForm');
    var verifyBtn = document.getElementById('verifyBtn');

    // OTP input: numeric only, paste support, trim spaces, maxlength 6
    if (codeInput) {
        codeInput.addEventListener('input', function () {
            // Remove non-digits and trim spaces
            var v = this.value.replace(/\D/g, '').trim().substring(0,6);
            if (this.value !== v) this.value = v;
            // Toggle validation state
            if (this.value.length === 6) {
                this.classList.remove('is-invalid');
            }
        });
        codeInput.addEventListener('paste', function (e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text') || '';
            pasted = pasted.replace(/\D/g, '').trim().substring(0,6);
            this.value = pasted;
            this.dispatchEvent(new Event('input'));
        });
        // Form submit validation: require 6 digits
        var form = codeInput.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                var val = codeInput.value.replace(/\D/g,'').trim();
                if (val.length !== 6 || !/^\d{6}$/.test(val)) {
                    e.preventDefault();
                    codeInput.classList.add('is-invalid');
                    var err = document.getElementById('codeError');
                    if (err) err.style.display = 'block';
                    codeInput.focus();
                    return false;
                }
                // Ensure trimmed value submitted
                codeInput.value = val;
            });
        }
        codeInput.addEventListener('keydown', function(e){
            // Allow backspace, delete, tab, arrow keys
            if (/^[0-9]$/.test(e.key) || ['Backspace','Delete','Tab','ArrowLeft','ArrowRight','Enter'].includes(e.key)) return;
            if (e.ctrlKey && ['v','c','a'].includes(e.key.toLowerCase())) return;
            e.preventDefault();
        });
    }

    // Recovery toggle
    var toggle = document.querySelector('[data-toggle-recovery]');
    var codeBox = document.querySelector('[data-code-input]');
    var recoveryBox = document.querySelector('[data-recovery-input]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var usingRecovery = !recoveryBox.classList.contains('d-none');
            recoveryBox.classList.toggle('d-none', usingRecovery);
            codeBox.classList.toggle('d-none', !usingRecovery);
            toggle.textContent = usingRecovery ? '{{ mawa_e('auth.use_recovery_code') }}' : '{{ mawa_e('auth.use_auth_code') }}';
            var target = usingRecovery ? codeBox.querySelector('input') : recoveryBox.querySelector('input');
            if (target) target.focus();
        });
    }

    // Resend countdown: 60s cooldown
    function startCountdown(seconds) {
        if (!resendBtn) return;
        var original = 'Resend Code';
        var remaining = seconds;
        resendBtn.disabled = true;
        resendBtn.textContent = 'Resend Code in ' + remaining + 's';
        var interval = setInterval(function(){
            remaining--;
            if (remaining <= 0) {
                clearInterval(interval);
                resendBtn.disabled = false;
                resendBtn.textContent = original;
                var help = document.getElementById('resendHelp');
                if (help) help.textContent = 'You can now request a new code.';
            } else {
                resendBtn.textContent = 'Resend Code in ' + remaining + 's';
            }
        }, 1000);
    }

    // If page loaded with throttle error, start countdown
    var hasThrottleError = document.body.innerHTML.includes('Please wait before requesting another code');
    if (hasThrottleError && resendBtn) {
        startCountdown(60);
    }

    if (resendForm && resendBtn) {
        resendForm.addEventListener('submit', function(e){
            // Let form submit, but start countdown on next load via throttle detection
            // For UX, also start immediate countdown optimistic
            // Note: server will enforce 60s, we show countdown right away
            startCountdown(60);
        });
    }

    // Auto-focus handling for accessibility
    if (codeInput) codeInput.focus();
})();
</script>
</body>
</html>
