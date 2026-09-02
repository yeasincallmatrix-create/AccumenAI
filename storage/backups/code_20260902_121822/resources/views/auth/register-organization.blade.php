<!DOCTYPE html>
<html lang="{{ mawa_current_lang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization Information — AccumenAI</title>
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
                    @include('auth.partials.register-progress', ['step' => 3])
                    <h1 class="auth-title h4 mb-1 text-center">Organization / Business Information</h1>
                    <p class="auth-subtitle mb-4 text-center text-muted small">Verified: {{ $email }}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2">
                            @foreach ($errors->all() as $error)
                                <div class="small">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.organization.submit') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="organization_name">Organization / Business Name <span class="text-danger">*</span></label>
                            <input id="organization_name" type="text" class="form-control" name="organization_name" value="{{ old('organization_name', $selection['organization_name'] ?? '') }}" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">Contact First Name <span class="text-danger">*</span></label>
                                <input id="first_name" type="text" class="form-control" name="first_name" value="{{ old('first_name', $selection['first_name'] ?? '') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Contact Last Name <span class="text-danger">*</span></label>
                                <input id="last_name" type="text" class="form-control" name="last_name" value="{{ old('last_name', $selection['last_name'] ?? '') }}" required>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                            <input id="phone" type="text" class="form-control" name="phone" value="{{ old('phone', $selection['phone'] ?? '') }}" required>
                            <div class="form-text">Include country code or local format; will be normalized.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="country">{{ mawa_lang('workspace.country') }} <span class="text-danger">*</span></label>
                            <select id="country" name="country" class="form-select" required>
                                <option value="">{{ mawa_lang('workspace.select_country') }}</option>
                                @foreach ($countries as $code => $label)
                                    <option value="{{ $code }}" @selected(old('country', $selection['country'] ?? '') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="industry">{{ mawa_lang('workspace.industry') }} <span class="text-danger">*</span></label>
                            <select id="industry" name="industry" class="form-select" disabled required>
                                <option value="">{{ mawa_lang('workspace.select_industry') }}</option>
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="sub-industry-field">
                            <label class="form-label" for="sub_industry">{{ mawa_lang('workspace.sub_industry') }} <span class="text-danger">*</span></label>
                            <select id="sub_industry" name="sub_industry" class="form-select" disabled>
                                <option value="">{{ mawa_lang('workspace.select_sub_industry') }}</option>
                            </select>
                        </div>

                        <button class="btn btn-primary w-100 mt-4" type="submit" id="continue-btn" disabled>
                            <i class="bi bi-arrow-right"></i> {{ mawa_lang('workspace.continue_btn') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    'use strict';
    var data = { rules: @json($rules), industries: @json($industries) };
    var industryPlaceholder = @json((string) mawa_lang('workspace.select_industry'));
    var subPlaceholder = @json((string) mawa_lang('workspace.select_sub_industry'));
    var countryEl = document.getElementById('country');
    var industryEl = document.getElementById('industry');
    var subEl = document.getElementById('sub_industry');
    var subField = document.getElementById('sub-industry-field');
    var continueBtn = document.getElementById('continue-btn');
    var preselected = { industry: @json(old('industry', $selection['industry'] ?? null)), sub: @json(old('sub_industry', $selection['sub_industry'] ?? null)) };
    function industriesFor(country) {
        var scoped = data.rules[country];
        var slugs = scoped && Object.keys(scoped).length ? Object.keys(scoped) : Object.keys(data.industries);
        return slugs.map(function (slug) { return { slug: slug, label: data.industries[slug] || slug }; });
    }
    function subsFor(country, industry) {
        var scoped = data.rules[country];
        if (!scoped || !scoped[industry]) return [];
        return Object.keys(scoped[industry]).map(function (slug) { return { slug: slug, label: scoped[industry][slug] }; });
    }
    function fillSelect(select, options, placeholder, value) {
        select.innerHTML = '';
        var ph = document.createElement('option'); ph.value = ''; ph.textContent = placeholder; select.appendChild(ph);
        options.forEach(function (opt) { var o=document.createElement('option'); o.value=opt.slug; o.textContent=opt.label; if(opt.slug===value) o.selected=true; select.appendChild(o); });
    }
    function render() {
        var country = countryEl.value;
        var industry = industryEl.value || (country ? preselected.industry : '');
        fillSelect(industryEl, industriesFor(country), industryPlaceholder, industry);
        industryEl.disabled = !country;
        industry = industryEl.value;
        var subs = subsFor(country, industry);
        fillSelect(subEl, subs, subPlaceholder, (country && industry) ? (subEl.value || preselected.sub) : '');
        subEl.disabled = !country || !industry;
        subEl.required = subs.length > 0;
        if (subs.length > 0) subField.classList.remove('d-none'); else subField.classList.add('d-none');
        var orgName = document.getElementById('organization_name').value.trim();
        var fn = document.getElementById('first_name').value.trim();
        var ln = document.getElementById('last_name').value.trim();
        var phone = document.getElementById('phone').value.trim();
        continueBtn.disabled = !country || !industry || (subs.length>0 && !subEl.value) || !orgName || !fn || !ln || !phone;
    }
    countryEl.addEventListener('change', function () { preselected.industry=''; preselected.sub=''; industryEl.value=''; subEl.value=''; render(); });
    industryEl.addEventListener('change', function () { preselected.industry=industryEl.value; preselected.sub=''; subEl.value=''; render(); });
    subEl.addEventListener('change', function () { preselected.sub=subEl.value; render(); });
    ['organization_name','first_name','last_name','phone'].forEach(function(id){ document.getElementById(id).addEventListener('input', render); });
    render();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
