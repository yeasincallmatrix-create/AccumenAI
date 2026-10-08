<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose a Package — AccumenAI</title>
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
            <div class="col-lg-10">
                <div class="auth-card mb-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2 fw-bold text-primary" style="font-size:20px"><i class="bi bi-shield-lock-fill"></i> AccumenAI</div>
                    @include('auth.partials.register-progress', ['step' => 5])

                    <h1 class="auth-title h4 mb-1 text-center">Choose your package</h1>
                    <p class="auth-subtitle mb-4 text-center text-muted small">
                        Select a plan for <strong>{{ $institute->name ?? 'your organization' }}</strong>
                        @if($country) · prices for {{ $country }} @endif
                        — your dashboard stays locked until you pick one.
                    </p>

                    @if(session('status'))
                        <div class="alert alert-success py-2">{{ session('status') }}</div>
                    @endif
                    @if(session('warning'))
                        <div class="alert alert-warning py-2">{{ session('warning') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if($isStaff)
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle"></i> Your organization has no package yet. Pick one below to unlock the workspace.
                        </div>
                    @endif

                    @if(count($cards) === 0)
                        <div class="alert alert-warning py-2 small">No packages are available right now. Please contact support.</div>
                    @else
                        <form method="POST" action="{{ route('register.package.submit') }}" id="package-form">
                            @csrf
                            <div class="row g-3">
                                @foreach($cards as $card)
                                    @php
                                        $pkg = $card['package'];
                                        $currencyCode = $prices[$pkg->id]['currency'] ?? $currency;
                                        $monthly = (float) ($card['monthly'] ?? 0);
                                        $yearly = (float) ($card['yearly'] ?? 0);
                                        $isFree = $monthly <= 0 && $yearly <= 0;
                                        $popular = (int) ($card['tier'] ?? 0) === 3;
                                        $isSelected = (int) ($currentPackageId ?? 0) === (int) $pkg->id;
                                    @endphp
                                    <div class="col-md-6 col-lg-3">
                                        <label class="package-option h-100 d-flex flex-column {{ $popular ? 'border-primary shadow-sm' : '' }}">
                                            <input type="radio" name="package_id" value="{{ $pkg->id }}"
                                                   class="d-none package-input" {{ $isSelected ? 'checked' : '' }}>
                                            <div class="card h-100 border-{{ $popular ? 'primary' : 'light' }}">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h5 class="card-title mb-0">{{ $pkg->name }}</h5>
                                                        @if($popular)
                                                            <span class="badge text-bg-primary">Popular</span>
                                                        @elseif($isSelected)
                                                            <span class="badge text-bg-success">Current</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-muted small text-uppercase">{{ $pkg->slug }}</div>

                                                    <div class="my-3">
                                                        <span class="fs-3 fw-bold">
                                                            {{ \App\Support\CurrencyFormatter::format($isFree ? 0.0 : $monthly, $currencyCode) }}
                                                        </span>
                                                        <span class="text-muted">/month</span>
                                                        <div class="small text-muted">
                                                            {{ \App\Support\CurrencyFormatter::format($yearly, $currencyCode) }} /year
                                                        </div>
                                                        @if(!empty($card['discount_active']) && (float) ($card['discount_percent'] ?? 0) > 0)
                                                            <span class="badge text-bg-success">{{ (float) $card['discount_percent'] }}% off</span>
                                                        @endif
                                                    </div>

                                                    <ul class="list-unstyled small text-muted mb-3">
                                                        <li class="mb-1"><i class="bi bi-check2-circle text-success"></i> {{ (int) ($card['module_count'] ?? 0) }} modules</li>
                                                        <li class="mb-1"><i class="bi bi-check2-circle text-success"></i> {{ (int) ($card['feature_count'] ?? 0) }} features</li>
                                                        @if(!empty($pkg->max_students))
                                                            <li class="mb-1"><i class="bi bi-people text-primary"></i> Up to {{ $pkg->max_students }} students</li>
                                                        @endif
                                                        @if(!empty($pkg->max_teachers))
                                                            <li class="mb-1"><i class="bi bi-person-workspace text-primary"></i> Up to {{ $pkg->max_teachers }} teachers</li>
                                                        @endif
                                                        @if(!empty($card['trial_days']) && (int) $card['trial_days'] > 0)
                                                            <li class="mb-1"><i class="bi bi-gift text-info"></i> {{ (int) $card['trial_days'] }}-day free trial</li>
                                                        @endif
                                                    </ul>

                                                    <span class="mt-auto btn btn-outline-primary select-btn">Select {{ $pkg->name }}</span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-4" id="continue-btn" disabled>
                                <i class="bi bi-arrow-right-circle"></i> Continue to dashboard
                            </button>
                            <div class="form-text text-center mt-2">
                                Paid packages are billed separately — you can complete payment after registration.
                            </div>
                        </form>
                    @endif

                    <p class="text-center mt-3 mb-0 small">
                        <a href="{{ route('logout.get') }}"><i class="bi bi-box-arrow-right"></i> Sign out and finish later</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('package-form');
    if (!form) return;
    var btn = document.getElementById('continue-btn');
    var inputs = form.querySelectorAll('.package-input');

    function sync() {
        var checked = form.querySelector('.package-input:checked');
        btn.disabled = !checked;
        inputs.forEach(function (input) {
            var card = input.closest('.card');
            var label = input.closest('label');
            if (!card || !label) return;
            card.classList.toggle('border-primary', input.checked);
            card.classList.toggle('border-light', !input.checked);
            var action = label.querySelector('.select-btn');
            if (action) {
                action.classList.toggle('btn-primary', input.checked);
                action.classList.toggle('btn-outline-primary', !input.checked);
                action.innerHTML = input.checked
                    ? '<i class="bi bi-check2"></i> Selected'
                    : 'Select ' + (card.querySelector('.card-title') ? card.querySelector('.card-title').textContent.trim() : 'plan');
            }
        });
    }

    inputs.forEach(function (input) {
        input.addEventListener('change', sync);
    });
    sync();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
