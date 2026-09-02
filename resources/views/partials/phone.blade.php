@php
    if (! isset($name)) { $name = 'phone'; }
    $id = $id ?? $name;
    $value = $value ?? '';
    $placeholder = $placeholder ?? null;
    $required = $required ?? false;
    $class = $class ?? '';

    $codes = \App\Support\CountryCodes::all();
    ksort($codes, SORT_STRING);

    $defaultCountry = $country ?? null;
    if (! $defaultCountry) {
        $instUser = \Illuminate\Support\Facades\Auth::guard('institute_user')->user();
        if ($instUser && ! empty($instUser->institute_id)) {
            $defaultCountry = \App\Models\Institute::query()
                ->where('id', $instUser->institute_id)
                ->value('country');
        }
    }
    if (! $defaultCountry) { $defaultCountry = 'Bangladesh'; }

    if ($placeholder === null) {
        $placeholder = \App\Support\CountryCodes::phoneExampleFor($defaultCountry);
    }

    $selectedCode = \App\Support\CountryCodes::codeFor($defaultCountry);
    $number = (string) $value;
    if (str_starts_with($number, '+')) {
        $digits = substr($number, 1);
        $matched = \App\Support\CountryCodes::matchPrefix($digits);
        if ($matched !== null) {
            $selectedCode = $matched;
            $number = substr($digits, strlen($matched));
        }
    }

    // Phase 1 live hint metadata: inc-trunk national lengths, e.g. BD 11, US 10, ID 10-12
    $hintCountry = $defaultCountry;
    // If the stored value already resolved to a different dial code, keep hint in sync with that code's country
    // (best-effort: find first country name matching selectedCode)
    if ($selectedCode !== \App\Support\CountryCodes::codeFor($defaultCountry)) {
        foreach (\App\Support\CountryCodes::CODES as $cname => $ccode) {
            if ($ccode === $selectedCode) { $hintCountry = $cname; break; }
        }
    }
    $hintRange = \App\Support\CountryCodes::nationalLengthFor($hintCountry);
    $hintMin = $hintRange[0];
    $hintMax = $hintRange[1];
    $hintExample = \App\Support\CountryCodes::phoneExampleFor($hintCountry);
    $hintMaxLen = strlen($selectedCode) + $hintMax + 1; // +1 for '+'
@endphp

<div class="input-group phone-country-group">
    <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2 phone-country-btn"
            data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
        <img src="{{ mawa_country_flag($defaultCountry) }}" class="country-flag phone-country-flag" alt="" width="18" height="13">
        <span class="phone-country-code">{{ $selectedCode }}</span>
        <i class="bi bi-chevron-down small"></i>
    </button>

    <select class="phone-country-select d-none" aria-hidden="true">
        @foreach ($codes as $cname => $ccode)
            @php $r = \App\Support\CountryCodes::nationalLengthFor($cname); $ex = \App\Support\CountryCodes::phoneExampleFor($cname); @endphp
            <option value="{{ $ccode }}" data-flag="{{ mawa_country_flag($cname) }}" data-country="{{ $cname }}" data-min="{{ $r[0] }}" data-max="{{ $r[1] }}" data-example="{{ $ex }}" @selected($selectedCode === $ccode)>{{ $ccode }} - {{ $cname }}</option>
        @endforeach
    </select>

    <div class="dropdown-menu dropdown-menu-start phone-country-menu">
        <div class="phone-country-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="phone-country-search" placeholder="Search country..." autocomplete="off">
        </div>
        <div class="phone-country-list">
            @foreach ($codes as $cname => $ccode)
                @php $r = \App\Support\CountryCodes::nationalLengthFor($cname); $ex = \App\Support\CountryCodes::phoneExampleFor($cname); @endphp
                <a href="#" class="dropdown-item phone-country-item" data-code="{{ $ccode }}" data-flag="{{ mawa_country_flag($cname) }}" data-country="{{ $cname }}" data-min="{{ $r[0] }}" data-max="{{ $r[1] }}" data-example="{{ $ex }}">
                    <img src="{{ mawa_country_flag($cname) }}" class="country-flag" alt="" width="18" height="13">
                    <span class="ms-2">{{ $cname }}</span>
                    <span class="ms-auto text-muted small">{{ $ccode }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <input id="{{ $id }}" type="tel" name="{{ $name }}" class="form-control phone-number-input {{ $class }}"
           value="{{ $number }}" placeholder="{{ $hintExample }}" maxlength="{{ $hintMaxLen }}"
           autocomplete="tel-national" @if($required) required @endif
           data-country="{{ $hintCountry }}" data-code="{{ $selectedCode }}" data-min="{{ $hintMin }}" data-max="{{ $hintMax }}" data-example="{{ $hintExample }}">
</div>
<small class="phone-hint d-block mt-1 small text-muted" id="{{ $id }}-hint" aria-live="polite"></small>

<script>
if (! window.__monetixPhoneJs) {
    window.__monetixPhoneJs = true;

    window.monetixPhoneSync = function (select) {
        var group = select.closest('.phone-country-group');
        if (! group) { return; }
        var opt = select.options[select.selectedIndex];
        if (! opt) { return; }
        var code = opt.value;
        var flag = group.querySelector('.phone-country-flag');
        if (flag && opt.getAttribute('data-flag')) { flag.src = opt.getAttribute('data-flag'); }
        var codeEl = group.querySelector('.phone-country-code');
        if (codeEl) { codeEl.textContent = code; }
    };

    window.monetixUpdatePhoneHint = function (input) {
        if (! input || ! input.classList.contains('phone-number-input')) { return; }
        var group = input.closest('.phone-country-group');
        var hint = group ? group.nextElementSibling : null;
        // hint is the <small> immediately after the input-group; fallback: id-hint
        if (! hint || ! hint.classList.contains('phone-hint')) {
            hint = document.getElementById(input.id + '-hint');
        }
        if (! hint) { return; }
        var raw = String(input.value);
        var min = parseInt(input.getAttribute('data-min') || '7', 10);
        var max = parseInt(input.getAttribute('data-max') || '12', 10);
        var example = input.getAttribute('data-example') || '';
        var maxLen = parseInt(input.getAttribute('maxlength') || '0', 10);

        // Invalid chars: anything except digits, +, space, -, (), leading +
        var invalid = /[^0-9+\s\-\(\)]/.test(raw);
        // Count digits only (national inc trunk 0 as typed, e.g. BD 01... =11)
        var digits = raw.replace(/\D/g, '');
        var len = digits.length;

        var rangeLabel = (min === max) ? (String(max) + ' digits') : (String(min) + '\u2013' + String(max) + ' digits');

        if (raw.trim() === '') {
            hint.textContent = 'Example: ' + example + ' \u2014 ' + rangeLabel;
            hint.className = 'phone-hint d-block mt-1 small text-muted';
            input.classList.remove('is-invalid','is-valid');
            input.removeAttribute('aria-invalid');
            return;
        }
        if (invalid) {
            hint.textContent = 'Invalid characters';
            hint.className = 'phone-hint d-block mt-1 small text-danger';
            input.classList.add('is-invalid'); input.classList.remove('is-valid');
            input.setAttribute('aria-invalid','true');
            return;
        }
        if (len < min) {
            hint.textContent = 'Incomplete \u2014 ' + len + ' / ' + (min === max ? String(max) : rangeLabel.replace(' digits','')) + ' digits';
            hint.className = 'phone-hint d-block mt-1 small text-warning';
            input.classList.remove('is-valid'); input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            return;
        }
        if (len > max) {
            hint.textContent = 'Too long \u2014 ' + len + ' / ' + String(max) + ' digits';
            hint.className = 'phone-hint d-block mt-1 small text-danger';
            input.classList.add('is-invalid'); input.classList.remove('is-valid');
            input.setAttribute('aria-invalid','true');
            return;
        }
        // Within range: valid
        if (min === max) {
            hint.textContent = 'Valid length \u2014 ' + len + ' / ' + String(max) + ' digits';
        } else {
            hint.textContent = 'Valid \u2014 ' + len + ' digits (' + String(min) + '\u2013' + String(max) + ' valid)';
        }
        hint.className = 'phone-hint d-block mt-1 small text-success';
        input.classList.add('is-valid'); input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
    };

    document.addEventListener('click', function (e) {
        var item = e.target.closest('.phone-country-item');
        if (! item) { return; }
        e.preventDefault();
        var group = item.closest('.phone-country-group');
        var code = item.getAttribute('data-code');
        var country = item.getAttribute('data-country') || '';
        var cmin = item.getAttribute('data-min') || '7';
        var cmax = item.getAttribute('data-max') || '12';
        var cex = item.getAttribute('data-example') || '';
        var select = group.querySelector('.phone-country-select');
        Array.prototype.forEach.call(select.options, function (o) { o.selected = (o.value === code && o.getAttribute('data-country') === country) || (o.value === code && !country); });
        // Prefer exact country match
        var matchedOpt = null;
        Array.prototype.forEach.call(select.options, function (o) { if (o.getAttribute('data-country')===country && o.value===code) matchedOpt=o; });
        if (matchedOpt) { Array.prototype.forEach.call(select.options, function (o){ o.selected=false; }); matchedOpt.selected=true; }
        var btn = group.querySelector('.phone-country-btn');
        btn.querySelector('.phone-country-flag').src = item.getAttribute('data-flag');
        btn.querySelector('.phone-country-code').textContent = code;
        var input = group.querySelector('.phone-number-input');
        if (input) {
            input.setAttribute('data-country', country);
            input.setAttribute('data-code', code);
            input.setAttribute('data-min', cmin);
            input.setAttribute('data-max', cmax);
            input.setAttribute('data-example', cex);
            input.placeholder = cex;
            input.maxLength = String(parseInt(code.replace(/\D/g,'').length,10) + parseInt(cmax,10) + 1);
            input.focus();
            window.monetixUpdatePhoneHint(input);
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('phone-country-search')) {
            var menu = e.target.closest('.phone-country-menu');
            var q = e.target.value.trim().toLowerCase();
            menu.querySelectorAll('.phone-country-item').forEach(function (item) {
                item.style.display = item.textContent.trim().toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
            return;
        }
        if (e.target.classList.contains('phone-number-input')) {
            window.monetixUpdatePhoneHint(e.target);
        }
    });

    // Initialize hints on load (all phone inputs)
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.phone-number-input').forEach(function (el) { window.monetixUpdatePhoneHint(el); });
    });
    // Also init immediately for already-parsed DOM (in case DOMContentLoaded already fired)
    (function(){ document.querySelectorAll('.phone-number-input').forEach(function (el){ window.monetixUpdatePhoneHint(el); }); })();

    document.addEventListener('submit', function (e) {
        var groups = e.target.querySelectorAll('.phone-country-group');
        for (var i = 0; i < groups.length; i++) {
            var select = groups[i].querySelector('.phone-country-select');
            var input = groups[i].querySelector('.phone-number-input, input[type="tel"]');
            if (! select || ! input) { continue; }
            var code = String(select.value).replace(/\D/g, '');
            var v = String(input.value).replace(/\+/g, '').replace(/\s/g, '').replace(/-/g, '').replace(/[\(\)]/g, '').trim();
            if (v === '') { continue; }
            if (! v.startsWith(code)) { v = code + v; }
            input.value = '+' + v;
        }
    }, true);
}
</script>
