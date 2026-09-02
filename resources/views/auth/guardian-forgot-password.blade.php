<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ mawa_e('guardian.forgot_password_title') }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
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
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-person-heart"></i> AccumenAI</div>
                    <h1 class="auth-title h3 mb-2">{{ mawa_e('guardian.forgot_password_title') }}</h1>
                    <p class="auth-subtitle mb-4">{{ mawa_e('auth.forgot_password_hint') }}</p>

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

                    <form method="POST" action="{{ route('guardian.password.email') }}">
                        @csrf

                        <div class="mb-3 text-start">
                            <label class="form-label" for="email">{{ mawa_e('auth.email') }}</label>
                            <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                        </div>

                        <button class="btn btn-primary auth-btn w-100" type="submit">
                            <i class="bi bi-envelope-arrow-up"></i> {{ mawa_e('auth.send_reset_link') }}
                        </button>
                    </form>

                    <p class="auth-switch mt-4">
                        <a href="{{ route('guardian.login') }}"><i class="bi bi-arrow-left"></i> {{ mawa_e('auth.back_to_login') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/flash.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/flash.js')) }}"></script>
</body>
</html>