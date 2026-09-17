<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Unavailable — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
    <link rel="icon" href="{{ platform_logo_url() }}">
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
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px">
                        @include('partials.platform-logo', ['height' => 36]) AccumenAI
                    </div>

                    <div class="my-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 mb-3" style="width:80px;height:80px;">
                            <i class="bi bi-person-lock text-warning" style="font-size:36px;"></i>
                        </div>
                    </div>

                    <h1 class="auth-title h3 mb-2">Registration Unavailable</h1>
                    <p class="auth-subtitle mb-4">
                        Staff self-registration is currently disabled. Please contact your <strong>organization administrator</strong> to create an account for you.
                    </p>

                    <div class="alert alert-light border mb-4 text-start">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                            <div>
                                <p class="mb-1 fw-semibold">How to get access:</p>
                                <ol class="mb-0 ps-3 small text-muted">
                                    <li>Reach out to your institute administrator</li>
                                    <li>Request them to create a staff account for you</li>
                                    <li>Once approved, you will receive login credentials</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('login') }}" class="btn btn-primary auth-btn w-100">
                        <i class="bi bi-box-arrow-in-right"></i> {{ mawa_e('auth.sign_in') }}
                    </a>

                    <p class="auth-switch mt-4 mb-0">
                        <a href="{{ route('login') }}"><i class="bi bi-person-badge me-1"></i> {{ mawa_e('auth.institute_portal') }}</a>
                        <span class="mx-1">/</span>
                        <a href="{{ route('admin.login') }}"><i class="bi bi-shield-lock me-1"></i> {{ mawa_e('auth.admin_portal') }}</a>
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
