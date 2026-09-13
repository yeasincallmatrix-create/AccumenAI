@extends('layouts.institute')

@section('title', 'Register Patient — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Register New Patient</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.patients.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info d-none align-items-center gap-2" id="existing_alert" role="alert">
            <i class="bi bi-person-check"></i>
            <span>Existing patient <strong id="existing_name"></strong> (<span id="existing_mr"></span>) found for this phone — details auto-filled from database.</span>
            <a href="#" id="existing_link" class="alert-link ms-auto" target="_blank">View record</a>
        </div>
        <form action="{{ route('medical.patients.store') }}" method="POST">
            @csrf

            <h6 class="mb-3">Personal Information</h6>
            <div class="row">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               class="form-control @error('first_name') is-invalid @enderror"
                               value="{{ old('first_name') }}" required maxlength="50">
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name"
                               class="form-control @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name') }}" maxlength="50">
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <x-tdate-input name="date_of_birth" :value="old('date_of_birth')" id="date_of_birth" :class="'form-control'.($errors->has('date_of_birth') ? ' is-invalid' : '')" />
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="age">Age <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" id="age" name="age" min="0" max="150"
                                   class="form-control @error('age') is-invalid @enderror"
                                   value="{{ old('age') }}" required placeholder="e.g. 30">
                            <select id="age_unit" name="age_unit" class="form-select flex-grow-0 w-auto @error('age_unit') is-invalid @enderror">
                                <option value="days" @selected(old('age_unit') === 'days')>Days</option>
                                <option value="months" @selected(old('age_unit') === 'months')>Months</option>
                                <option value="years" @selected(old('age_unit', 'years') === 'years')>Years</option>
                            </select>
                        </div>
                        @error('age')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('age_unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                        <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                            <option value="">Select Gender</option>
                            <option value="male" @selected(old('gender') === 'male')>Male</option>
                            <option value="female" @selected(old('gender') === 'female')>Female</option>
                            <option value="other" @selected(old('gender') === 'other')>Other</option>
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="blood_group">Blood Group</label>
                        <select id="blood_group" name="blood_group" class="form-select @error('blood_group') is-invalid @enderror">
                            <option value="">Select Blood Group</option>
                            @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'] as $bg)
                                <option value="{{ $bg }}" @selected(old('blood_group') === $bg)>{{ $bg === 'UKN' ? 'UKN (Unknown)' : $bg }}</option>
                            @endforeach
                        </select>
                        @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text" id="phone_prefix">+{{ $phoneCode ?? '880' }}</span>
                            <input type="tel" id="phone" name="phone"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone') }}" maxlength="{{ strlen((string) ($phoneCode ?? '880')) + ($phoneMax ?? 11) }}"
                               placeholder="e.g. {{ $phoneExample ?? '017XXXXXXXX' }}"
                               autocomplete="tel-national" aria-describedby="phone_hint">
                        </div>
                        <div class="form-text text-muted" id="phone_hint">{{ ($phoneCountry ?? 'Bangladesh') . ': ' . (($phoneMin ?? 11) === ($phoneMax ?? 11) ? ($phoneMax ?? 11) . ' digits' : ($phoneMin ?? 7) . '–' . ($phoneMax ?? 12) . ' digits') . '. Example: ' . ($phoneExample ?? '017XXXXXXXX') }}</div>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" maxlength="100">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="relation_to_primary">Relation</label>
                        <select id="relation_to_primary" name="relation_to_primary" class="form-select @error('relation_to_primary') is-invalid @enderror">
                            @foreach(['Self', 'Son', 'Daughter', 'Wife', 'Husband', 'Father', 'Mother', 'Brother', 'Sister', 'Other'] as $rel)
                                <option value="{{ $rel }}" @selected(old('relation_to_primary', 'Self') === $rel)>{{ $rel }}</option>
                            @endforeach
                        </select>
                        @error('relation_to_primary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Same phone as family? Pick the relation — link the primary contact from the profile later.</div>
                    </div>
                </div>
            </div>

            <h6 class="mb-3 mt-3">Present Address</h6>
            <x-address :prefix="'present_'"
                       :country-id="old('present_country_id', $patient->present_country_id)"
                       :level-1-id="old('present_admin_1_id', $patient->present_admin_1_id)"
                       :level-2-id="old('present_admin_2_id', $patient->present_admin_2_id)"
                       :level-3-id="old('present_admin_3_id', $patient->present_admin_3_id)"
                       :level-labels="$presentAddress['level_labels']"
                       :level-1-options="$presentAddress['level_options'][1]"
                       :level-2-options="$presentAddress['level_options'][2]"
                       :level-3-options="$presentAddress['level_options'][3]"
                       :address="old('present_address', $patient->present_address)"
                       :single-row="true" />

            <h6 class="mb-3 mt-3">Emergency Contact</h6>
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                               class="form-control @error('emergency_contact_name') is-invalid @enderror"
                               value="{{ old('emergency_contact_name') }}" maxlength="100">
                        @error('emergency_contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="emergency_contact_phone">Emergency Contact Phone</label>
                        <div class="input-group">
                            <span class="input-group-text" id="emergency_phone_prefix">+{{ $phoneCode ?? '880' }}</span>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone"
                               class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                               value="{{ old('emergency_contact_phone') }}" maxlength="{{ strlen((string) ($phoneCode ?? '880')) + ($phoneMax ?? 11) }}"
                               placeholder="e.g. {{ $phoneExample ?? '017XXXXXXXX' }}"
                               autocomplete="tel-national" aria-describedby="emergency_phone_hint">
                        </div>
                        <div class="form-text text-muted" id="emergency_phone_hint">{{ ($phoneCountry ?? 'Bangladesh') . ': ' . (($phoneMin ?? 11) === ($phoneMax ?? 11) ? ($phoneMax ?? 11) . ' digits' : ($phoneMin ?? 7) . '–' . ($phoneMax ?? 12) . ' digits') }}</div>
                        @error('emergency_contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mb-3 mt-3">Medical Information</h6>
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="2"
                                  class="form-control @error('allergies') is-invalid @enderror">{{ old('allergies') }}</textarea>
                        @error('allergies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chronic_conditions">Chronic Conditions</label>
                        <textarea id="chronic_conditions" name="chronic_conditions" rows="2"
                                  class="form-control @error('chronic_conditions') is-invalid @enderror">{{ old('chronic_conditions') }}</textarea>
                        @error('chronic_conditions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="button" class="btn btn-primary" id="patient-add-btn">
                    <i class="bi bi-save me-1"></i>Add Patient
                </button>
                <a href="{{ route('medical.patients.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

{{-- Confirm popup using entered create info --}}
<div class="modal fade" id="patientConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm New Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-4">Name</dt><dd class="col-8" id="confirm-name">—</dd>
                    <dt class="col-4">Age</dt><dd class="col-8" id="confirm-age">—</dd>
                    <dt class="col-4">DOB</dt><dd class="col-8" id="confirm-dob">—</dd>
                    <dt class="col-4">Gender</dt><dd class="col-8" id="confirm-gender">—</dd>
                    <dt class="col-4">Phone</dt><dd class="col-8" id="confirm-phone">—</dd>
                </dl>
                <p class="text-muted small mt-2 mb-0">Only Name, Age and Gender are mandatory.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit</button>
                <button type="button" class="btn btn-primary" id="patient-confirm-save">Confirm &amp; Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var dob = document.getElementById('date_of_birth');
    var age = document.getElementById('age');
    var ageUnit = document.getElementById('age_unit');
    var UNIT_MAX = { days: 36500, months: 1800, years: 150 };
    function unit() { return ageUnit ? ageUnit.value : 'years'; }
    function ageFromDob() {
        if (!dob.value) return;
        var b = new Date(dob.value + 'T00:00:00');
        var now = new Date();
        if (isNaN(b) || b > now) return;
        var u = unit(), v;
        if (u === 'days') v = Math.floor((now - b) / 86400000);
        else if (u === 'months') {
            v = (now.getFullYear() - b.getFullYear()) * 12 + (now.getMonth() - b.getMonth());
            if (now.getDate() < b.getDate()) v--;
        } else {
            v = now.getFullYear() - b.getFullYear();
            var m = now.getMonth() - b.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < b.getDate())) v--;
        }
        if (v >= 0 && v <= UNIT_MAX[u]) age.value = v;
    }
    if (dob && age) {
        dob.addEventListener('change', ageFromDob);
        dob.addEventListener('input', function () {
            if (!dob.value) age.value = '';
            else ageFromDob();
        });
        // Use change (not input) so typing "30" doesn't lock DOB to the intermediate "3".
        age.addEventListener('change', function () {
            var a = parseInt(age.value, 10);
            if (isNaN(a) || a < 0) return;
            var u = unit();
            if (a > UNIT_MAX[u]) return;
            var d = new Date();
            if (u === 'days') d.setDate(d.getDate() - a);
            else if (u === 'months') d.setMonth(d.getMonth() - a);
            else d.setFullYear(d.getFullYear() - a);
            // Only auto-fill DOB if empty, so manual DOB is never overwritten.
            if (!dob.value) { dob.value = d.toISOString().slice(0, 10); if (window.tdateSync) window.tdateSync('date_of_birth'); }
        });
        if (ageUnit) ageUnit.addEventListener('change', function () {
            age.max = UNIT_MAX[unit()];
            ageFromDob();
        });
    }

    var form = document.querySelector('form[action="{{ route('medical.patients.store') }}"]');
    var addBtn = document.getElementById('patient-add-btn');
    var confirmBtn = document.getElementById('patient-confirm-save');
    if (form && addBtn) {
        addBtn.addEventListener('click', function () {
            if (!form.reportValidity()) {
                form.requestSubmit();
                return;
            }
            // Realtime country-length guard before confirm popup.
            var okMain = paintPhone(document.getElementById('phone'), document.getElementById('phone_hint'), document.getElementById('phone_prefix'));
            var okEmg = paintPhone(document.getElementById('emergency_contact_phone'), document.getElementById('emergency_phone_hint'), document.getElementById('emergency_phone_prefix'));
            if (!okMain || !okEmg) {
                (okMain ? document.getElementById('emergency_contact_phone') : document.getElementById('phone')).focus();
                return;
            }
            document.getElementById('confirm-name').textContent =
                ((document.getElementById('first_name').value || '') + ' ' + (document.getElementById('last_name').value || '')).trim() || '—';
            document.getElementById('confirm-age').textContent = age
                ? ((age.value || '—') + ' ' + (ageUnit ? ageUnit.value : 'years'))
                : '—';
            document.getElementById('confirm-dob').textContent = dob ? (dob.value || '—') : '—';
            document.getElementById('confirm-gender').textContent = document.getElementById('gender').value || '—';
            document.getElementById('confirm-phone').textContent = document.getElementById('phone').value || '—';
            new bootstrap.Modal(document.getElementById('patientConfirmModal')).show();
        });
    }
    if (form && confirmBtn) {
        confirmBtn.addEventListener('click', function () { form.submit(); });
    }

    // Country-parameter realtime phone length check (server: PhoneRule).
    // Default length from CountryCodes::NATIONAL_LENGTHS; follows present_country_id.
    var LENGTHS = @json(\App\Support\CountryCodes::NATIONAL_LENGTHS);
    var EXAMPLES = @json(\App\Support\CountryCodes::PHONE_EXAMPLES);
    var CODES = @json(\App\Support\CountryCodes::CODES);
    var PHONE_COUNTRIES = @json($phoneCountries ?? []);
    var phoneCountry = @json($phoneCountry ?? 'Bangladesh');
    var countrySel = document.getElementById('present_country_id');

    function phoneRangeLabel(min, max) {
        return min === max ? max + ' digits' : min + '–' + max + ' digits';
    }
    function phoneMeta() {
        var len = LENGTHS[phoneCountry] || [7, 12];
        var code = CODES[phoneCountry] || @json($phoneCode ?? '880');
        var ex = EXAMPLES[phoneCountry] || (code + ' XXX XXXXX');
        return { min: len[0], max: len[1], code: code, example: ex };
    }
    function nationalOf(digits) {
        var m = phoneMeta();
        if (m.code && digits.indexOf(m.code) === 0 && digits.length > m.code.length) {
            return digits.slice(m.code.length);
        }
        return digits;
    }
    function paintPhone(input, hint, prefix) {
        if (!input) return true;
        var m = phoneMeta();
        if (prefix) prefix.textContent = '+' + m.code;
        input.placeholder = 'e.g. ' + m.example;
        input.maxLength = String(String(m.code).length + m.max);
        var v = (input.value || '').trim();
        if (!v) {
            if (hint) { hint.textContent = phoneCountry + ': ' + phoneRangeLabel(m.min, m.max) + '. Example: ' + m.example; hint.className = 'form-text text-muted'; }
            input.classList.remove('is-invalid', 'is-valid');
            return true;
        }
        if (/[^0-9+\s\-\(\)]/.test(v)) {
            if (hint) { hint.textContent = 'Invalid characters — only digits, +, space, - and ( ) allowed.'; hint.className = 'form-text text-danger'; }
            input.classList.add('is-invalid'); input.classList.remove('is-valid');
            return false;
        }
        var national = nationalOf(v.replace(/\D/g, ''));
        var trunkLessOk = (national.length === m.min - 1 || national.length === m.max - 1) && v.replace(/\D/g, '').indexOf(m.code) === 0;
        var core = national.replace(/^0+/, '') || national;
        var ok = trunkLessOk || (core.length >= m.min && core.length <= m.max) || (national.length >= m.min && national.length <= m.max);
        if (ok) {
            if (hint) { hint.textContent = m.min === m.max ? 'Valid length — ' + national.length + ' / ' + m.max + ' digits' : 'Valid — ' + national.length + ' digits (' + m.min + '–' + m.max + ' valid)'; hint.className = 'form-text text-success'; }
            input.classList.add('is-valid'); input.classList.remove('is-invalid');
            return true;
        }
        if (national.length < m.min) {
            if (hint) { hint.textContent = 'Incomplete — ' + national.length + ' / ' + m.max + ' digits (' + phoneCountry + ': ' + phoneRangeLabel(m.min, m.max) + ')'; hint.className = 'form-text text-warning'; }
            input.classList.remove('is-invalid', 'is-valid');
            return false;
        }
        if (hint) { hint.textContent = 'Too long — ' + national.length + ' / ' + m.max + ' digits (' + phoneCountry + ': ' + phoneRangeLabel(m.min, m.max) + ')'; hint.className = 'form-text text-danger'; }
        input.classList.add('is-invalid'); input.classList.remove('is-valid');
        return false;
    }
    function paintAllPhones() {
        paintPhone(document.getElementById('phone'), document.getElementById('phone_hint'), document.getElementById('phone_prefix'));
        paintPhone(document.getElementById('emergency_contact_phone'), document.getElementById('emergency_phone_hint'), document.getElementById('emergency_phone_prefix'));
    }
    function syncCountryFromSelect() {
        if (countrySel && countrySel.value && PHONE_COUNTRIES[countrySel.value]) {
            phoneCountry = PHONE_COUNTRIES[countrySel.value].name;
        }
        paintAllPhones();
    }
    if (countrySel) countrySel.addEventListener('change', syncCountryFromSelect);
    ['phone', 'emergency_contact_phone'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', function () { paintPhone(el, document.getElementById(id === 'phone' ? 'phone_hint' : 'emergency_phone_hint'), document.getElementById(id === 'phone' ? 'phone_prefix' : 'emergency_phone_prefix')); });
    });
    paintAllPhones();

    // Phone → database auto-fill: existing patient data populates the form.
    var lookupUrl = '{{ route('medical.patients.lookup') }}';
    var phoneInput = document.getElementById('phone');
    var alertBox = document.getElementById('existing_alert');
    var lookupTimer = null;
    var lastLookup = '';

    function setVal(id, v) {
        var el = document.getElementById(id);
        if (el && v !== null && v !== undefined) el.value = v;
    }

    function resetLookupState() {
        lastLookup = '';
        if (alertBox) { alertBox.classList.add('d-none'); alertBox.classList.remove('d-flex'); }
        if (addBtn) addBtn.disabled = false;
    }

    function runLookup() {
        var v = (phoneInput.value || '').trim();
        if (!v || v === lastLookup) return;
        lastLookup = v;
        fetch(lookupUrl + '?phone=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { found: false }; })
            .then(function (data) {
                if (!data || !data.found) { resetLookupState(); lastLookup = v; return; }
                var p = data.patient;
                setVal('first_name', p.first_name);
                setVal('last_name', p.last_name);
                if (p.date_of_birth) { dob.value = p.date_of_birth; if (window.tdateSync) window.tdateSync('date_of_birth'); ageFromDob(); }
                setVal('gender', p.gender);
                setVal('blood_group', p.blood_group);
                setVal('email', p.email);
                setVal('present_address', p.present_address);
                setVal('emergency_contact_name', p.emergency_contact_name);
                setVal('emergency_contact_phone', p.emergency_contact_phone);
                setVal('allergies', p.allergies);
                setVal('chronic_conditions', p.chronic_conditions);
                setVal('notes', p.notes);
                var countrySel = document.getElementById('present_country_id');
                if (countrySel && p.present_country_id) {
                    countrySel.value = p.present_country_id;
                    countrySel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                paintAllPhones();
                document.getElementById('existing_name').textContent =
                    ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
                document.getElementById('existing_mr').textContent = p.mr_number || '';
                var link = document.getElementById('existing_link');
                if (link && p.url) link.href = p.url;
                if (alertBox) { alertBox.classList.remove('d-none'); alertBox.classList.add('d-flex'); }
                if (addBtn) addBtn.disabled = true;
            })
            .catch(function () { /* keep manual entry on lookup failure */ });
    }

    if (phoneInput) phoneInput.addEventListener('input', function () {
        if (lookupTimer) clearTimeout(lookupTimer);
        var digits = (phoneInput.value || '').replace(/\D/g, '');
        if (digits.length < 7) { resetLookupState(); return; }
        lookupTimer = setTimeout(runLookup, 500);
    });
})();
</script>
@endpush

@push('styles')
<style>
/* Register page only: all six Present Address fields in one line
   (the component renders them in a single grid here via single-row).
   Neutralizes the country's 2-column span so nothing wraps. */
.address-component > .grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.address-component > .grid > .field-fill { grid-column: auto; }
@media (max-width: 768px) {
    .address-component > .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
@endpush
