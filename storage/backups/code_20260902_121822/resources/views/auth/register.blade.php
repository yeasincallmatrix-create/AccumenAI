<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ mawa_e('auth.register_title') }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('css/base.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('css/components.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('css/pages.css')) }}" rel="stylesheet">
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
            <div class="col-md-7 col-lg-6">
                <div class="auth-card mb-3">
                    <div class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                        <h1 class="auth-title h3 mb-2">{{ mawa_e('auth.register_title') }}</h1>
                        <p class="auth-subtitle mb-4">{{ mawa_e('auth.register_subtitle') }}</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2" data-auto-dismiss>
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('institute.register.submit') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">{{ mawa_e('students.first_name') }}</label>
                                <input id="first_name" type="text" class="form-control" name="first_name" value="{{ old('first_name') }}" required autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">{{ mawa_e('students.last_name') }}</label>
                                <input id="last_name" type="text" class="form-control" name="last_name" value="{{ old('last_name') }}" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="institute_id">{{ mawa_e('auth.institute') }}</label>
                            <select id="institute_id" name="institute_id" class="form-select" required>
                                <option value="">{{ mawa_e('auth.select_institute') }}</option>
                                @foreach ($institutes as $institute)
                                    <option value="{{ $institute->id }}" @selected(old('institute_id') == $institute->id)>{{ $institute->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label" for="email">{{ mawa_e('auth.email') }}</label>
                                <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required autocomplete="email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">{{ mawa_e('auth.phone') }}</label>
                                @include('partials.phone', ['name' => 'phone', 'id' => 'phone', 'value' => old('phone'), 'required' => true])
                            </div>
                        </div>

                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label" for="password">{{ mawa_e('auth.password') }}</label>
                                <input id="password" type="password" class="form-control" name="password" required autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">{{ mawa_e('auth.confirm_password') }}</label>
                                <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                            </div>
                        </div>

                        <button class="btn btn-primary auth-btn w-100 mt-4" type="submit">
                            <i class="bi bi-person-plus-fill"></i> {{ mawa_e('auth.create_account') }}
                        </button>
                    </form>

                    <p class="auth-switch mt-4">
                        {{ mawa_e('auth.already_have_account') }} <a href="{{ route('login') }}">{{ mawa_e('auth.sign_in') }}</a>
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