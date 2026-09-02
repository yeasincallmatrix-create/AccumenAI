<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Address — AccumenAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/base.css') }}" rel="stylesheet">
    <link href="{{ asset('css/components.css') }}" rel="stylesheet">
    <link href="{{ asset('css/pages.css') }}" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="auth-card mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    @include('auth.partials.register-progress', ['step' => 4])
                    <h1 class="auth-title h4 mb-1 text-center">Local Address</h1>
                    <p class="auth-subtitle mb-4 text-center text-muted small">Standard address for {{ $selection['organization_name'] ?? '' }} ({{ $selection['country'] ?? '' }} — {{ $selection['industry'] ?? '' }})</p>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.address.submit') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="address">Address</label>
                            @if ($geoAddress)
                                <div class="address-box">
                                    <x-address :locked="true"
                                               :country-id="old('country_id', $geoAddress['country_id'])"
                                               :level-1-id="old('admin_1_id', $addressData['admin_1_id'] ?? null)"
                                               :level-2-id="old('admin_2_id', $addressData['admin_2_id'] ?? null)"
                                               :level-3-id="old('admin_3_id', $addressData['admin_3_id'] ?? null)"
                                               :level-labels="$geoAddress['level_labels']"
                                               :level-1-options="$geoAddress['level1_options']"
                                               :address-label="$geoAddress['address_label']"
                                               :zip-first="$geoAddress['zip_first']"
                                               :address="old('address', $addressData['address'] ?? '')"
                                               :postal-code="old('zip_code', $addressData['zip_code'] ?? '')" />
                                    <div class="hint mt-2">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        {{ mawa_lang('geo.street_hint') }}
                                    </div>
                                </div>
                            @else
                                <textarea id="address" name="address" class="form-control" rows="2">{{ old('address', $addressData['address'] ?? '') }}</textarea>
                            @endif
                        </div>

                        <button class="btn btn-primary w-100 mt-4" type="submit">
                            <i class="bi bi-building-add"></i> Create Organization & Continue
                        </button>
                        <div class="form-text text-center mt-2">You can customize after creation. Education industry will continue to onboarding extension.</div>
                    </form>
                    <p class="text-center mt-3"><a href="{{ route('register.organization') }}">Back to Organization</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/ajax.js') }}"></script>
<script src="{{ asset('js/geo-select.js') }}"></script>
</body>
</html>
