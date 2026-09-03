<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
@php
    $portal = request()->routeIs('admin.login') ? 'admin' : 'institute';
    $loginTitle = $portal === 'admin' ? mawa_lang('auth.admin_login_title') : mawa_lang('auth.institute_login_title');
@endphp

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
                    <h1 class="auth-title h3 mb-2">{{ $loginTitle }}</h1>
                    <p class="auth-subtitle mb-4">{{ $hint }}</p>

                    @if (session('status'))
                        <div class="alert alert-success py-2" data-auto-dismiss><i class="bi bi-check-circle-fill me-1"></i>{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger py-2" data-auto-dismiss>
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ $action }}">
                        @csrf

                        <div class="mb-3 text-start">
                            <label class="form-label" for="email">{{ mawa_e('auth.email') }}</label>
                            <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label" for="password">{{ mawa_e('auth.password') }}</label>
                            <input id="password" type="password" class="form-control" name="password" required autocomplete="current-password">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check mb-0">
                                <input id="remember" type="checkbox" name="remember" class="form-check-input">
                                <label class="form-check-label" for="remember">{{ mawa_e('auth.remember_me') }}</label>
                            </div>
                            @if ($errors->any() && $portal !== 'admin')
                                <a class="small text-decoration-none" href="{{ route('password.request') }}"><i class="bi bi-key me-1"></i>{{ mawa_e('auth.forgot_password_link') }}</a>
                            @endif
                        </div>

                        <button class="btn btn-primary auth-btn w-100" type="submit">
                            <i class="bi bi-box-arrow-in-right"></i> {{ mawa_e('auth.sign_in') }}
                        </button>
                    </form>

                    <p class="auth-switch mt-4">
                        <a href="{{ route('login') }}"><i class="bi bi-person-badge"></i> {{ mawa_e('auth.institute_portal') }}</a>
                        <span class="mx-1">/</span>
                        <a href="{{ route('admin.login') }}"><i class="bi bi-shield-lock"></i> {{ mawa_e('auth.admin_portal') }}</a>
                    </p>

                    <hr class="my-4">

                    <p class="auth-switch mb-0 text-center">
                        {{ mawa_e('auth.new_institute_account') }} <a href="{{ route('institute.register') }}">{{ mawa_e('auth.register_here') }}</a>
                    </p>
                    <p class="auth-switch mb-0 text-center mt-1">
                        {{ mawa_e('auth.new_owner_account') }} <a href="{{ route('owner.register') }}">{{ mawa_e('auth.owner_register_here') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
<script src="{{ asset('js/password-toggle.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/password-toggle.js')) }}"></script>
</body>
</html>
