{{-- Quick Add Patient popup — reuses medical/patients/create info. Only Name + Age + Gender mandatory.
     Phone is validated against the institute Country parameter (server: PhoneRule, client: hint below). --}}
@php
    $qDefaultCountry = collect($countries ?? [])->firstWhere('id', (int) ($defaultCountryId ?? 0));
    $qDefaultCountryName = $qDefaultCountry->name ?? 'Bangladesh';
    $qDefaultPhoneCode = $qDefaultCountry->phone_code ?? '880';
    $qLen = \App\Support\CountryCodes::nationalLengthFor($qDefaultCountryName);
    $qMin = $qLen[0];
    $qMax = $qLen[1];
    $qExample = \App\Support\CountryCodes::phoneExampleFor($qDefaultCountryName);
    $qRangeLabel = $qMin === $qMax ? $qMax . ' digits' : $qMin . '–' . $qMax . ' digits';
    // Input holds national part after the +880 prefix, but allow pasting full
    // international (code + national) so maxlength = code + max.
    $qInputMaxlength = strlen((string) $qDefaultPhoneCode) + $qMax;
@endphp
<div class="modal fade" id="quickAddPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('medical.patients.store') }}" method="POST" id="quick-add-patient-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_phone">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="q_phone_prefix">+{{ $qDefaultPhoneCode }}</span>
                                    <input type="tel" id="q_phone" name="phone" class="form-control"
                                        maxlength="{{ $qInputMaxlength }}"
                                        placeholder="e.g. {{ $qExample }}"
                                        data-min="{{ $qMin }}" data-max="{{ $qMax }}"
                                        data-example="{{ $qExample }}" data-code="{{ $qDefaultPhoneCode }}"
                                        data-country="{{ $qDefaultCountryName }}"
                                        autocomplete="tel-national">
                                </div>
                                <div class="invalid-feedback" id="q_phone_error" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_mr_number">Patient ID</label>
                                <input type="text" id="q_mr_number" class="form-control" value="{{ clinical_no($previewMr ?? '') }}" readonly>
                                <div class="form-text">Auto preview — confirmed on save.</div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_first_name">First Name <span class="text-danger">*</span></label>
                                <input type="text" id="q_first_name" name="first_name" class="form-control" required maxlength="50">
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_last_name">Last Name</label>
                                <input type="text" id="q_last_name" name="last_name" class="form-control" maxlength="50">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <span class="form-label d-block">Gender <span class="text-danger">*</span></span>
                                <div class="d-flex gap-3 pt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="q_gender_male" name="gender" value="male" required>
                                        <label class="form-check-label" for="q_gender_male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="q_gender_female" name="gender" value="female" required>
                                        <label class="form-check-label" for="q_gender_female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="q_gender_other" name="gender" value="other" required>
                                        <label class="form-check-label" for="q_gender_other">Other</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_date_of_birth">Date of Birth</label>
                                <x-tdate-input name="date_of_birth" value="" id="q_date_of_birth" class="form-control" data-tdate-max="{{ date('Y-m-d') }}" />
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_age">Age <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" id="q_age" name="age" min="0" max="150"
                                           class="form-control" required placeholder="e.g. 30" aria-label="Age">
                                    <select id="q_age_unit" name="age_unit" class="form-select" style="max-width:6.5rem;flex:0 0 auto;" aria-label="Age unit">
                                        <option value="days">Days</option>
                                        <option value="months">Months</option>
                                        <option value="years" selected>Years</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md" style="flex-grow:.5;">
                            <div class="mb-3">
                                <label class="form-label" for="q_blood_group">Blood Group</label>
                                <select id="q_blood_group" name="blood_group" class="form-select">
                                    <option value="">Select</option>
                                    @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'] as $bg)
                                        <option value="{{ $bg }}">{{ $bg === 'UKN' ? 'UKN (Unknown)' : $bg }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div class="mb-3">
                                <label class="form-label" for="q_relation">Relation</label>
                                <select id="q_relation" name="relation_to_primary" class="form-select">
                                    @foreach(['Self', 'Son', 'Daughter', 'Wife', 'Husband', 'Father', 'Mother', 'Brother', 'Sister', 'Other'] as $rel)
                                        <option value="{{ $rel }}" @selected($rel === 'Self')>{{ $rel }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Same phone? Choose the relation, or use “Add Family Member” above.</div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="present_country_id" value="{{ $defaultCountryId }}">
                    <input type="hidden" id="q_primary_contact_id" name="primary_contact_id" value="">
                    <p class="text-muted small mb-2">Only Name, Age and Gender are mandatory.</p>
                    <div class="alert alert-info d-none mb-0" id="q_existing_alert" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-check"></i>
                            <span>Existing patient <strong id="q_existing_name"></strong> (<span id="q_existing_mr"></span>) found — details auto-filled.</span>
                            <a href="#" id="q_existing_link" class="alert-link ms-auto" target="_blank">View</a>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="q_family_btn" style="display:none;">
                            <i class="bi bi-people me-1"></i>Add Family Member to This Phone
                        </button>
                    </div>
                    <div id="q_family_wrap" class="mt-2" style="display:none;">
                        <div class="small fw-semibold text-muted mb-1">Relatives on this phone number (son, daughter, others) — click a row to select</div>
                        <div id="q_matches_list" class="list-group list-group-flush border rounded"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('medical.patients.create') }}" class="btn btn-link me-auto">Full registration form</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="q_submit_btn">
                        <i class="bi bi-save me-1"></i>Add Patient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var dob = document.getElementById('q_date_of_birth');
    var age = document.getElementById('q_age');
    var ageUnit = document.getElementById('q_age_unit');
    var UNIT_MAX = { days: 36500, months: 1800, years: 150 };
    function qUnit() { return ageUnit ? ageUnit.value : 'years'; }
    function qAgeFromDob() {
        if (!dob.value) return;
        var b = new Date(dob.value + 'T00:00:00');
        var now = new Date();
        if (isNaN(b) || b > now) return;
        var u = qUnit(), v;
        if (u === 'days') {
            v = Math.floor((now - b) / 86400000);
        } else if (u === 'months') {
            v = (now.getFullYear() - b.getFullYear()) * 12 + (now.getMonth() - b.getMonth());
            if (now.getDate() < b.getDate()) v--;
        } else {
            v = now.getFullYear() - b.getFullYear();
            var m = now.getMonth() - b.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < b.getDate())) v--;
        }
        if (v >= 0 && v <= UNIT_MAX[u]) age.value = v;
    }
    function qDobFromAge() {
        var a = parseInt(age.value, 10);
        if (isNaN(a) || a < 0) return;
        var u = qUnit();
        if (a > UNIT_MAX[u]) return;
        var d = new Date();
        if (u === 'days') d.setDate(d.getDate() - a);
        else if (u === 'months') d.setMonth(d.getMonth() - a);
        else d.setFullYear(d.getFullYear() - a);
        if (!dob.value) { dob.value = d.toISOString().slice(0, 10); if (window.tdateSync) window.tdateSync('q_date_of_birth'); }
    }
    // DOB can never be in the future: a typed advance date snaps back to
    // today (string compare works — hidden value is always ISO Y-m-d).
    function qClampDob() {
        if (!dob || !dob.value) return;
        var t = new Date();
        var todayIso = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
        if (dob.value > todayIso) {
            dob.value = todayIso;
            if (window.tdateSync) window.tdateSync('q_date_of_birth');
            qAgeFromDob();
        }
    }
    if (dob && age) {
        dob.addEventListener('change', function () { qClampDob(); qAgeFromDob(); });
        dob.addEventListener('input', function () {
            if (!dob.value) age.value = '';
            else qAgeFromDob();
        });
        // Use change (not input) so typing "30" doesn't lock DOB to the intermediate "3".
        age.addEventListener('change', qDobFromAge);
        if (ageUnit) ageUnit.addEventListener('change', function () {
            age.max = UNIT_MAX[qUnit()];
            age.placeholder = qUnit() === 'years' ? 'e.g. 30' : (qUnit() === 'months' ? 'e.g. 6' : 'e.g. 15');
            qAgeFromDob();
        });
    }

    // Institute-country phone hint + REALTIME length check (server: PhoneRule).
    // Default length comes from CountryCodes::NATIONAL_LENGTHS for the institute country.
    var phoneInput = document.getElementById('q_phone');
    var hint = document.getElementById('q_phone_hint');
    var err = document.getElementById('q_phone_error');
    var form = document.getElementById('quick-add-patient-form');
    var submitBtnQ = document.getElementById('q_submit_btn');

    var LENGTHS = @json(\App\Support\CountryCodes::NATIONAL_LENGTHS);
    var EXAMPLES = @json(\App\Support\CountryCodes::PHONE_EXAMPLES);
    var CODES = @json(\App\Support\CountryCodes::CODES);
    var COUNTRY = @json($qDefaultCountryName);

    function qRangeLabel(min, max) {
        return min === max ? max + ' digits' : min + '–' + max + ' digits';
    }

    function qNationalDigits(rawDigits) {
        var len = LENGTHS[COUNTRY] || [7, 12];
        var code = CODES[COUNTRY];
        var national = rawDigits;
        if (code && rawDigits.indexOf(code) === 0 && rawDigits.length > code.length) {
            national = rawDigits.slice(code.length);
        }
        return { national: national, len: len, code: code };
    }

    function refreshHint() {
        var ex = EXAMPLES[COUNTRY] || ((CODES[COUNTRY] || '') + ' XXX XXXXX');
        var len = LENGTHS[COUNTRY];
        var range = len ? qRangeLabel(len[0], len[1]) : '7–12 digits';
        if (hint) {
            hint.textContent = COUNTRY + ': ' + range + '. Example: ' + ex;
            hint.className = 'form-text text-muted';
        }
        if (phoneInput && !phoneInput.value) phoneInput.placeholder = 'e.g. ' + ex;
    }

    // Realtime check on every keystroke: counter + green/red state + submit guard.
    function updatePhoneLive() {
        if (!phoneInput) return true;
        var v = (phoneInput.value || '').trim();
        var info = qNationalDigits(v.replace(/\D/g, ''));
        var min = info.len[0], max = info.len[1];
        var ex = EXAMPLES[COUNTRY] || '';
        var range = qRangeLabel(min, max);

        // Empty = optional, neutral hint.
        if (!v) {
            refreshHint();
            phoneInput.classList.remove('is-invalid', 'is-valid');
            if (err) err.style.display = 'none';
            if (submitBtnQ) submitBtnQ.disabled = false;
            return true;
        }
        // Invalid characters.
        if (/[^0-9+\s\-\(\)]/.test(v)) {
            if (hint) { hint.textContent = 'Invalid characters — only digits, +, space, - and ( ) allowed.'; hint.className = 'form-text text-danger'; }
            phoneInput.classList.add('is-invalid'); phoneInput.classList.remove('is-valid');
            if (err) { err.textContent = 'Phone contains invalid characters.'; err.style.display = 'block'; }
            if (submitBtnQ) submitBtnQ.disabled = true;
            return false;
        }
        var digits = v.replace(/\D/g, '');
        var national = info.national;
        // Trunk-less international paste (e.g. +880178... stored without 0) is one digit shorter — accept live.
        var trunkLessOk = (national.length === min - 1 || national.length === max - 1) && digits.indexOf(info.code || '___') === 0;
        var core = national.replace(/^0+/, '') || national;
        var ok = trunkLessOk
            || (core.length >= min && core.length <= max)
            || (national.length >= min && national.length <= max);

        if (national.length < min && !ok) {
            if (hint) { hint.textContent = 'Incomplete — ' + national.length + ' / ' + max + ' digits (' + COUNTRY + ': ' + range + ')'; hint.className = 'form-text text-warning'; }
            phoneInput.classList.remove('is-invalid', 'is-valid');
            if (err) err.style.display = 'none';
            if (submitBtnQ) submitBtnQ.disabled = false; // allow typing, block only on submit
            return false;
        }
        if (national.length > max && !ok) {
            if (hint) { hint.textContent = 'Too long — ' + national.length + ' / ' + max + ' digits (' + COUNTRY + ': ' + range + ')'; hint.className = 'form-text text-danger'; }
            phoneInput.classList.add('is-invalid'); phoneInput.classList.remove('is-valid');
            if (err) { err.textContent = 'Phone must be ' + range + ' (national) for ' + COUNTRY + (ex ? '. Example: ' + ex : '') + '.'; err.style.display = 'block'; }
            if (submitBtnQ) submitBtnQ.disabled = true;
            return false;
        }
        if (ok) {
            if (hint) {
                hint.textContent = min === max
                    ? 'Valid length — ' + national.length + ' / ' + max + ' digits'
                    : 'Valid — ' + national.length + ' digits (' + min + '–' + max + ' valid)';
                hint.className = 'form-text text-success';
            }
            phoneInput.classList.add('is-valid'); phoneInput.classList.remove('is-invalid');
            if (err) err.style.display = 'none';
            if (submitBtnQ) submitBtnQ.disabled = false;
            return true;
        }
        if (hint) { hint.textContent = COUNTRY + ': ' + range + '. Example: ' + ex; hint.className = 'form-text text-muted'; }
        phoneInput.classList.remove('is-invalid', 'is-valid');
        if (submitBtnQ) submitBtnQ.disabled = false;
        return false;
    }

    function validPhone() {
        var v = (phoneInput.value || '').trim();
        if (!v) return true; // optional
        if (/[^0-9+\s\-\(\)]/.test(v)) return false;
        var digits = v.replace(/\D/g, '');
        var len = LENGTHS[COUNTRY] || [7, 12];
        var code = CODES[COUNTRY];
        var national = digits;
        if (code && digits.indexOf(code) === 0 && digits.length > code.length) {
            national = digits.slice(code.length);
            // allow trunk-less international (one digit shorter)
            if (national.length === len[0] - 1 || national.length === len[1] - 1) return true;
        }
        // strip leading trunk zero for comparison like the server does
        var core = national.replace(/^0+/, '') || national;
        if (core.length >= len[0] && core.length <= len[1]) return true;
        return national.length >= len[0] && national.length <= len[1];
    }

    refreshHint();

    if (form) form.addEventListener('submit', function (e) {
        updatePhoneLive();
        if (!validPhone()) {
            e.preventDefault();
            var len = LENGTHS[COUNTRY] || [7, 12];
            var ex = EXAMPLES[COUNTRY] || '';
            var range = len[0] === len[1] ? len[1] + ' digits' : len[0] + '–' + len[1] + ' digits';
            if (err) {
                err.textContent = 'Phone must be ' + range + ' (national) for ' + COUNTRY + (ex ? '. Example: ' + ex : '') + '.';
                err.style.display = 'block';
            }
            phoneInput.classList.add('is-invalid');
            phoneInput.focus();
        }
    });
    if (phoneInput) phoneInput.addEventListener('input', function () {
        updatePhoneLive();
        scheduleLookup();
    });

    // Phone → Patient ID link: if the phone exists, auto-fill the rest.
    var lookupUrl = @json(route('medical.patients.lookup'));
    var mrInput = document.getElementById('q_mr_number');
    var previewMr = mrInput ? mrInput.value : '';
    var alertBox = document.getElementById('q_existing_alert');
    var submitBtn = document.getElementById('q_submit_btn');
    var lookupTimer = null;
    var lastLookup = '';
    var familyMode = false;
    var familyBtn = document.getElementById('q_family_btn');
    var matchesBox = document.getElementById('q_matches_list');
    var relationSel = document.getElementById('q_relation');
    var primaryInput = document.getElementById('q_primary_contact_id');

    function escHtml(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Family mode: same phone, new person. Never blocks submit — the
    // server warns softly on true duplicates (same name + DOB).
    function setFamilyMode(primaryId, primaryLabel) {
        familyMode = true;
        if (primaryInput) primaryInput.value = primaryId || '';
        ['q_first_name', 'q_last_name'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        if (dob) { dob.value = ''; if (window.tdateSync) window.tdateSync('q_date_of_birth'); }
        if (age) age.value = '';
        setGender(null);
        if (relationSel && relationSel.value === 'Self') relationSel.value = 'Other';
        if (submitBtn) submitBtn.disabled = false;
        var txt = document.getElementById('q_existing_name');
        if (txt && primaryLabel) txt.textContent = primaryLabel + ' — registering a family member below (details cleared).';
    }
    if (familyBtn) familyBtn.addEventListener('click', function () {
        setFamilyMode(familyBtn.getAttribute('data-primary-id') || '', familyBtn.getAttribute('data-primary-label') || '');
    });

    function setGender(v) {
        ['male', 'female', 'other'].forEach(function (g) {
            var r = document.getElementById('q_gender_' + g);
            if (r) r.checked = (g === v);
        });
    }

    function resetLookupState() {
        lastLookup = '';
        familyMode = false;
        if (primaryInput) primaryInput.value = '';
        if (relationSel) relationSel.value = 'Self';
        if (familyBtn) { familyBtn.style.display = 'none'; familyBtn.removeAttribute('data-primary-id'); }
        if (matchesBox) matchesBox.innerHTML = '';
        var familyWrapReset = document.getElementById('q_family_wrap');
        if (familyWrapReset) familyWrapReset.style.display = 'none';
        if (mrInput) mrInput.value = previewMr;
        if (alertBox) { alertBox.classList.add('d-none'); alertBox.classList.remove('d-flex'); }
        // No existing patient — restore button state from realtime phone check
        // (keeps "Too long" disabled, re-enables otherwise).
        if (typeof updatePhoneLive === 'function') { updatePhoneLive(); }
        else if (submitBtn) submitBtn.disabled = false;
    }

    function scheduleLookup() {
        if (lookupTimer) clearTimeout(lookupTimer);
        var digits = (phoneInput.value || '').replace(/\D/g, '');
        if (digits.length < 7) { resetLookupState(); return; }
        lookupTimer = setTimeout(runLookup, 500);
    }

    function runLookup() {
        var v = (phoneInput.value || '').trim();
        if (!v || v === lastLookup) return;
        lastLookup = v;
        fetch(lookupUrl + '?phone=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { found: false }; })
            .then(function (data) {
                if (!data || !data.found) { resetLookupState(); lastLookup = v; return; }
                var list = data.patients || (data.patient ? [data.patient] : []);
                if (matchesBox) {
                    matchesBox.innerHTML = list.map(function (m) {
                        var nm = escHtml(m.label || (((m.first_name || '') + ' ' + (m.last_name || '')).trim()));
                        var rel = escHtml(m.relation || 'Self');
                        var editUrl = m.url ? m.url + '/edit' : '';
                        return '<div class="list-group-item py-1 px-2 d-flex justify-content-between align-items-center gap-2" data-pick-id="' + m.id + '" data-pick-label="' + nm + '" role="button" style="cursor:pointer;" title="Select this patient">' +
                            '<span>' + nm + ' <span class="badge bg-secondary">' + rel + '</span></span>' +
                            '<span class="d-flex align-items-center gap-2">' +
                            '<span class="text-muted small text-nowrap">' + escHtml(m.mr_number || '') + (m.phone ? ' · ' + escHtml(m.phone) : '') + '</span>' +
                            (editUrl ? '<a href="' + editUrl + '" target="_blank" data-edit class="btn btn-sm btn-outline-secondary py-0 px-1" title="Edit patient"><i class="bi bi-pencil"></i></a>' : '') +
                            '</span></div>';
                    }).join('');
                }
                var familyWrap = document.getElementById('q_family_wrap');
                if (familyWrap) familyWrap.style.display = list.length ? '' : 'none';
                var primary = list.find(function (m) { return !m.is_dependent; }) || list[0] || data.patient || null;
                if (familyBtn) {
                    familyBtn.style.display = '';
                    familyBtn.setAttribute('data-primary-id', primary ? primary.id : '');
                    familyBtn.setAttribute('data-primary-label', primary ? (primary.label || '') : '');
                }
                // Family mode: keep what the user typed, just refresh the list.
                if (familyMode) { lastLookup = v; return; }
                var p = list[0] || data.patient;
                if (!p) { resetLookupState(); lastLookup = v; return; }
                document.getElementById('q_first_name').value = p.first_name || '';
                document.getElementById('q_last_name').value = p.last_name || '';
                if (p.date_of_birth) { dob.value = p.date_of_birth; if (window.tdateSync) window.tdateSync('q_date_of_birth'); qAgeFromDob(); }
                setGender(p.gender);
                var bg = document.getElementById('q_blood_group');
                if (bg && p.blood_group) bg.value = p.blood_group;
                if (mrInput) mrInput.value = p.mr_number || '';
                document.getElementById('q_existing_name').textContent = ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
                document.getElementById('q_existing_mr').textContent = p.mr_number || '';
                var link = document.getElementById('q_existing_link');
                if (link && p.url) link.href = p.url;
                if (alertBox) { alertBox.classList.remove('d-none'); }
                // Never lock submit on a shared phone — true duplicates get
                // a soft server-side warning (same name + date of birth).
                if (submitBtn) submitBtn.disabled = false;
            })
            .catch(function () { /* keep manual entry on lookup failure */ });
    }
    // Relatives list: click a row to place that patient into the host
    // page (prescription picker or booking dropdown); the pencil opens
    // the patient edit page in a new tab without losing modal state.
    function selectRelativePatient(id, label) {
        var sel = document.getElementById('patient_id') || document.getElementById('bk_patient_id');
        if (!sel || !id) return;
        var opt = sel.querySelector('option[value="' + id + '"]');
        if (!opt) {
            opt = document.createElement('option');
            opt.value = id;
            opt.textContent = label || ('Patient #' + id);
            sel.appendChild(opt);
        }
        sel.value = String(id);
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        var m = document.getElementById('quickAddPatientModal');
        if (m && window.bootstrap) { var inst = window.bootstrap.Modal.getInstance(m); if (inst) inst.hide(); }
    }
    if (matchesBox && !matchesBox.dataset.pickBound) {
        matchesBox.dataset.pickBound = '1';
        matchesBox.addEventListener('click', function (e) {
            if (e.target.closest('a[data-edit]')) return;
            var row = e.target.closest('[data-pick-id]');
            if (row) selectRelativePatient(row.getAttribute('data-pick-id'), row.getAttribute('data-pick-label'));
        });
    }
})();
</script>
@endpush
