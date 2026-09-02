<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>{{ mawa_lang('workspace.create_title') }} — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
    @include('layouts.partials.theme_colors', ['themePrimary' => $themePrimary, 'themeSecondary' => $themeSecondary])
</head>
<body class="bg-body-tertiary">
<div class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="auth-card mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    <div class="text-center mb-2">
                        <span class="badge text-bg-primary">{{ mawa_lang('workspace.step2_of_2') }}</span>
                    </div>
                    <h1 class="auth-title h3 mb-1 text-center">{{ mawa_lang('workspace.create_title') }}</h1>
                    <p class="auth-subtitle mb-4 text-center">{{ mawa_lang('workspace.create_hint') }}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('workspace.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="name">{{ mawa_lang('workspace.name') }} <span class="text-danger">*</span></label>
                            <input id="name" type="text" class="form-control" name="name" value="{{ old('name') }}" required autofocus>
                        </div>

                        <div class="card border mb-4">
                            <div class="card-body py-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col">
                                        <div class="small text-muted mb-1 d-flex flex-wrap gap-3">
                                            <span><span class="fw-semibold text-body">{{ mawa_lang('workspace.country') }}:</span> {{ $countryLabel }}</span>
                                            <span><span class="fw-semibold text-body">{{ mawa_lang('workspace.industry') }}:</span> {{ $industryLabel }}</span>
                                            @if ($subIndustryLabel)
                                                <span><span class="fw-semibold text-body">{{ mawa_lang('workspace.sub_industry') }}:</span> {{ $subIndustryLabel }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <a href="{{ route('workspace.onboarding') }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-arrow-repeat"></i> {{ mawa_lang('workspace.change_selection') }}
                                        </a>
                                    </div>
                                </div>
                                @if(isset($defaultTemplate) && $defaultTemplate)
                                    <hr class="my-2">
                                    <div class="small">
                                        <div class="fw-semibold"><i class="bi bi-diagram-3"></i> Default Structure: {{ $defaultTemplate->name }}</div>
                                        <div class="text-muted">{{ $defaultLevels->pluck('label')->implode(' → ') }}</div>
                                        <div class="text-muted small mt-1"><i class="bi bi-info-circle"></i> Automatically assigned based on your industry. You can customize after creation.</div>
                                        <div class="mt-2 d-flex gap-2">
                                            <span class="badge text-bg-success"><i class="bi bi-check-lg"></i> Use Default</span>
                                            <span class="badge text-bg-light border">Customize after creation</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label" for="phone">{{ mawa_lang('workspace.phone') }}</label>
                                @include('partials.phone', ['name' => 'phone', 'id' => 'workspace_phone', 'value' => old('phone'), 'country' => $selection['country'] ?? null])
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">{{ mawa_lang('workspace.email') }}</label>
                                <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="address">{{ mawa_lang('workspace.address') }}</label>
                            @if ($geoAddress)
                                <div class="address-box">
                                    <x-address :locked="true"
                                               :country-id="old('country_id', $geoAddress['country_id'])"
                                               :level-1-id="old('admin_1_id')"
                                               :level-2-id="old('admin_2_id')"
                                               :level-3-id="old('admin_3_id')"
                                               :level-labels="$geoAddress['level_labels']"
                                               :level-1-options="$geoAddress['level1_options']"
                                               :address-label="$geoAddress['address_label']"
                                               :zip-first="$geoAddress['zip_first']"
                                               :address="old('address')"
                                               :postal-code="old('zip_code')" />
                                    <div class="hint mt-2">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        {{ mawa_lang('geo.street_hint') }}
                                    </div>
                                </div>
                            @else
                                <textarea id="address" name="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                            @endif
                        </div>

                        <button class="btn btn-primary auth-btn w-100 mt-4" type="submit">
                            <i class="bi bi-building-add"></i> {{ mawa_lang('workspace.create_btn') }}
                        </button>
                    </form>

                    <p class="auth-switch mt-4 text-center">
                        <a href="{{ route('workspace.picker') }}"><i class="bi bi-arrow-left"></i> {{ mawa_lang('workspace.back_picker') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/ajax.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/ajax.js')) }}"></script>
<script src="{{ asset('js/geo-select.js') }}?v={{ \Illuminate\Support\Facades\File::lastModified(public_path('js/geo-select.js')) }}"></script>
</body>
</html>