<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ mawa_e('auth.owner_register_title') }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="auth-card mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    @include('auth.partials.register-progress', ['step' => 1])
                    <h1 class="auth-title h4 mb-1 text-center">{{ mawa_lang('auth.owner_account_details_title') ?? 'Create Account' }}</h1>
                    <p class="auth-subtitle mb-4 text-center text-muted small">Step 1 — Account Credentials</p>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.account.submit') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="email">{{ mawa_e('auth.email') }} <span class="text-danger">*</span></label>
                            <input id="email" type="email" class="form-control" name="email" value="{{ old('email', $email ?? '') }}" required autofocus autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">{{ mawa_e('auth.password') }} <span class="text-danger">*</span></label>
                            <input id="password" type="password" class="form-control" name="password" required autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">{{ mawa_e('auth.confirm_password') }} <span class="text-danger">*</span></label>
                            <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                        </div>
                        <x-password-policy field="password" confirm-field="password_confirmation" />

                        <button class="btn btn-primary auth-btn w-100 mt-4" type="submit">
                            <i class="bi bi-arrow-right"></i> {{ mawa_lang('workspace.continue_btn') ?? 'Continue' }}
                        </button>
                    </form>
                    <p class="auth-switch mt-4 text-center">
                        {{ mawa_e('auth.already_have_account') }} <a href="{{ route('login') }}">{{ mawa_e('auth.sign_in') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/flash.js') }}"></script>
<script src="{{ asset('js/password-toggle.js') }}"></script>
<script src="{{ asset('js/password-policy.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
