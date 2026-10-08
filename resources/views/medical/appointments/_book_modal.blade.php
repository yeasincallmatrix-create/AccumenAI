{{-- Book Appointment popup for the appointments index page.
     Appointment fields + an inline new-patient block (Patient ID preview,
     name, gender, DOB, age, blood group, relation) so a walk-in can be
     registered and booked in one submit. The nested Add Patient popup is
     still available via the + button (AJAX quick-store). --}}
<div class="modal fade" id="bookAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <form action="{{ route('medical.appointments.store') }}" method="POST" id="book-appointment-form"
                  data-can-create-patient="{{ ($canCreatePatient ?? false) ? '1' : '0' }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Book Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $bkError)
                                    <li>{{ $bkError }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="alert alert-success d-none align-items-center gap-2" id="bk_patient_added" role="alert">
                        <i class="bi bi-person-check"></i>
                        <span>New patient <strong id="bk_patient_added_name"></strong> added and selected.</span>
                    </div>
                    @php
                        $bkDefaultCountry = collect($countries ?? [])->firstWhere('id', (int) ($defaultCountryId ?? 0));
                        $bkDefaultPhoneCode = $bkDefaultCountry->phone_code ?? '880';
                        $bkCountryName = $bkDefaultCountry->name ?? 'Bangladesh';
                        $bkPhoneExample = \App\Support\CountryCodes::phoneExampleFor($bkCountryName);
                    @endphp
                    <div class="row">
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_phone">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">+{{ $bkDefaultPhoneCode }}</span>
                                    <input type="tel" id="bk_phone" name="phone" class="form-control" autocomplete="off"
                                           maxlength="30"
                                           placeholder="e.g. {{ $bkPhoneExample }}"
                                           value="{{ old('phone') }}"
                                           aria-describedby="bk_phone_hint">
                                </div>
                                <div class="invalid-feedback" id="bk_phone_error" style="display:none;"></div>
                                <div class="form-text" id="bk_phone_hint">Type a phone to find an existing patient.</div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_mr_number">Patient ID</label>
                                <input type="text" id="bk_mr_number" class="form-control" readonly
                                       value="{{ clinical_no($previewMr ?? '') }}">
                                <div class="form-text">Auto preview — confirmed on save.</div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_patient_combo">Patient</label>
                                <div class="d-flex gap-2">
                                    <div class="position-relative flex-grow-1">
                                        <input type="text" id="bk_patient_combo" class="form-select" autocomplete="off"
                                               placeholder="Search by name or mobile no…" role="combobox"
                                               aria-expanded="false" aria-autocomplete="list"
                                               aria-controls="bk_patient_listbox">
                                        <div id="bk_patient_listbox"
                                             class="list-group position-absolute w-100 shadow-sm"
                                             style="display:none;max-height:280px;overflow-y:auto;z-index:1060;"></div>
                                    </div>
                                    @if($canCreatePatient ?? false)
                                        <button type="button" class="btn btn-outline-primary flex-shrink-0"
                                                data-bs-toggle="modal" data-bs-target="#quickAddPatientModal"
                                                title="Add new patient">
                                            <i class="bi bi-person-plus"></i>
                                        </button>
                                    @endif
                                </div>
                                <select id="bk_patient_id" name="patient_id" class="d-none">
                                    <option value="">Select Patient</option>
                                    @foreach(($patients ?? []) as $patient)
                                        <option value="{{ $patient->id }}"
                                                data-search="{{ strtolower(trim($patient->full_name.' '.clinical_no($patient->mr_number).' '.($patient->phone ?? ''))) }}"
                                                data-mr="{{ clinical_no($patient->mr_number) }}"
                                                @selected((string) old('patient_id') === (string) $patient->id)>
                                            {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }}){{ $patient->age !== null ? ', '.$patient->age.'y' : '' }}@if(!empty($ipdPatientIds[$patient->id] ?? null)) [IPD]@endif
                                        </option>
                                    @endforeach
                                </select>
                                <div class="alert alert-info d-none align-items-center gap-2 mb-0 mt-2" id="bk_lookup_alert" role="alert">
                                    <i class="bi bi-person-check"></i>
                                    <span>Found <strong id="bk_lookup_name"></strong> (<span id="bk_lookup_mr"></span>)</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_doctor_id">Doctor <span class="text-danger">*</span></label>
                                <select id="bk_doctor_id" name="doctor_id" class="form-select" required>
                                    <option value="">Select Doctor</option>
                                    @foreach(($doctors ?? []) as $doctor)
                                        <option value="{{ $doctor->id }}" @selected((string) request('doctor_id') === (string) $doctor->id)>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_appointment_date">Appointment Date <span class="text-danger">*</span></label>
                                <x-tdate-input name="appointment_date" :value="old('appointment_date', request('date', date('Y-m-d')))" id="bk_appointment_date" class="form-control" required />
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_appointment_time">Appointment Time <span class="text-danger">*</span></label>
                                <input type="time" id="bk_appointment_time" name="appointment_time" class="form-control"
                                       value="{{ old('appointment_time', '09:00') }}" required>
                            </div>
                        </div>

                        @if($canCreatePatient ?? false)
                        <div class="col-12">
                            <div class="border-top pt-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                    <h6 class="mb-0"><i class="bi bi-person-lines-fill me-1"></i>Patient Details</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="bk_family_btn" style="display:none;">
                                        <i class="bi bi-people me-1"></i>Add Family Member
                                    </button>
                                </div>
                                <div class="form-text" id="bk_mode_note">No patient selected — these details register a new patient on save.</div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_first_name">First Name <span class="text-danger">*</span></label>
                                <input type="text" id="bk_first_name" name="first_name" class="form-control"
                                       maxlength="50" value="{{ old('first_name') }}">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_last_name">Last Name</label>
                                <input type="text" id="bk_last_name" name="last_name" class="form-control"
                                       maxlength="50" value="{{ old('last_name') }}">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <div class="mb-3">
                                <span class="form-label d-block">Gender <span class="text-danger">*</span></span>
                                <div class="d-flex gap-3 pt-2 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="bk_gender_male" name="gender" value="male" @checked(old('gender') === 'male')>
                                        <label class="form-check-label" for="bk_gender_male">Male</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="bk_gender_female" name="gender" value="female" @checked(old('gender') === 'female')>
                                        <label class="form-check-label" for="bk_gender_female">Female</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="bk_gender_other" name="gender" value="other" @checked(old('gender') === 'other')>
                                        <label class="form-check-label" for="bk_gender_other">Other</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_dob">Date of Birth</label>
                                <x-tdate-input name="date_of_birth" :value="old('date_of_birth')" id="bk_dob"
                                               class="form-control" data-tdate-max="{{ date('Y-m-d') }}" />
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_age">Age <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" id="bk_age" name="age" min="0" max="150"
                                           class="form-control" placeholder="e.g. 30" aria-label="Age"
                                           value="{{ old('age') }}">
                                    <select id="bk_age_unit" name="age_unit" class="form-select"
                                            style="max-width:6.5rem;flex:0 0 auto;" aria-label="Age unit">
                                        <option value="days">Days</option>
                                        <option value="months">Months</option>
                                        <option value="years" selected>Years</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_blood_group">Blood Group</label>
                                <select id="bk_blood_group" name="blood_group" class="form-select">
                                    <option value="">Select</option>
                                    @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'] as $bg)
                                        <option value="{{ $bg }}" @selected(old('blood_group') === $bg)>{{ $bg === 'UKN' ? 'UKN (Unknown)' : $bg }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_relation">Relation</label>
                                <select id="bk_relation" name="relation_to_primary" class="form-select">
                                    @foreach(['Self', 'Son', 'Daughter', 'Wife', 'Husband', 'Father', 'Mother', 'Brother', 'Sister', 'Other'] as $rel)
                                        <option value="{{ $rel }}" @selected(old('relation_to_primary', 'Self') === $rel)>{{ $rel }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Same phone? Choose the relation, or use “Add Family Member” above.</div>
                            </div>
                        </div>
                        <input type="hidden" id="bk_primary_contact_id" name="primary_contact_id" value="{{ old('primary_contact_id') }}">
                        <input type="hidden" name="present_country_id" value="{{ $defaultCountryId }}">
                        @endif

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_complaints">Complaints / Symptoms</label>
                                <textarea id="bk_complaints" name="complaints" rows="2" class="form-control">{{ old('complaints') }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_notes">Notes</label>
                                <textarea id="bk_notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-wrap">
                    <a href="{{ route('medical.appointments.create') }}" class="btn btn-link me-auto">Full booking form</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Book Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
/* Book Appointment: extra-wide on desktop, full-screen below lg
   (modal-fullscreen-lg-down on .modal-dialog). */
@media (min-width: 992px) {
    #bookAppointmentModal .modal-dialog {
        max-width: min(1320px, calc(100vw - 3rem));
    }
}
@media (max-width: 575.98px) {
    #bookAppointmentModal .modal-body { padding: 1rem; }
    #bookAppointmentModal .modal-footer {
        flex-direction: column;
        align-items: stretch;
        gap: .5rem;
    }
    #bookAppointmentModal .modal-footer > * {
        width: 100%;
        margin-inline: 0 !important;
        justify-content: center;
    }
}
/* Patient search box: keep the typed text away from the left edge so it
   lines up with the result rows that drop down under it. */
#bookAppointmentModal #bk_patient_combo {
    padding-left: 1.25rem;
}
#bookAppointmentModal #bk_patient_listbox .list-group-item {
    padding-left: 1.25rem;
}
</style>
@endpush

@push('scripts')
<script>
// Searchable patient picker: type a name or mobile number, pick from the
// list; the real <select> stays in sync (and is what gets submitted).
(function () {
    var sel = document.getElementById('bk_patient_id');
    var combo = document.getElementById('bk_patient_combo');
    var box = document.getElementById('bk_patient_listbox');
    var mrInput = document.getElementById('bk_mr_number');
    var previewMr = mrInput ? mrInput.value : '';
    var modeNote = document.getElementById('bk_mode_note');
    var form = document.getElementById('book-appointment-form');
    var canCreate = !!form && form.dataset.canCreatePatient === '1';
    if (!sel || !combo || !box) return;

    var activeIndex = -1;

    function listedOptions() {
        return Array.from(sel.querySelectorAll('option[value]:not([value=""])')).map(function (o) {
            return {
                id: o.value,
                display: o.textContent,
                search: ((o.getAttribute('data-search') || o.textContent) || '').toLowerCase()
            };
        });
    }

    function renderList(filter) {
        var q = (filter || '').trim().toLowerCase();
        var rows = listedOptions().filter(function (o) {
            return q === '' || o.search.indexOf(q) !== -1;
        });
        box.innerHTML = '';
        activeIndex = -1;
        if (rows.length === 0) {
            box.innerHTML = '<div class="list-group-item small text-muted">No matches</div>';
        } else {
            rows.slice(0, 100).forEach(function (r) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'list-group-item list-group-item-action small';
                b.textContent = r.display;
                b.setAttribute('data-id', r.id);
                b.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    pick(r.id);
                });
                box.appendChild(b);
            });
        }
        box.style.display = '';
        combo.setAttribute('aria-expanded', 'true');
    }

    function closeList() {
        box.style.display = 'none';
        combo.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    }

    function pick(id) {
        sel.value = String(id);
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        closeList();
    }

    function syncCombo() {
        if (document.activeElement === combo) return;
        var opt = sel.value ? sel.querySelector('option[value="' + sel.value + '"]') : null;
        combo.value = opt ? opt.textContent : '';
        combo.title = combo.value;
    }

    window.bkSyncPatientCombo = syncCombo;

    combo.addEventListener('focus', function () {
        try { combo.select(); } catch (e) {}
        renderList(combo.value);
    });
    combo.addEventListener('input', function () {
        renderList(combo.value);
        if (sel.value) {
            sel.value = '';
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    combo.addEventListener('blur', function () {
        setTimeout(function () {
            closeList();
            syncCombo();
        }, 120);
    });
    combo.addEventListener('keydown', function (e) {
        var items = Array.prototype.slice.call(box.querySelectorAll('button[data-id]'));
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!items.length) return;
            e.preventDefault();
            activeIndex = e.key === 'ArrowDown'
                ? Math.min(activeIndex + 1, items.length - 1)
                : Math.max(activeIndex - 1, 0);
            items.forEach(function (el, i) { el.classList.toggle('active', i === activeIndex); });
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (activeIndex > -1 && items[activeIndex]) {
                e.preventDefault();
                pick(items[activeIndex].getAttribute('data-id'));
            }
        } else if (e.key === 'Escape') {
            closeList();
            syncCombo();
        }
    });
    sel.addEventListener('change', syncCombo);
    syncCombo();

    // Required flags + Patient ID preview follow the current mode: an
    // existing patient means the inline details are inert on save.
    function syncMode() {
        var creating = !sel.value && canCreate;
        sel.required = !canCreate;
        var first = document.getElementById('bk_first_name');
        var age = document.getElementById('bk_age');
        if (first) first.required = creating;
        if (age) age.required = creating;
        ['male', 'female', 'other'].forEach(function (g) {
            var r = document.getElementById('bk_gender_' + g);
            if (r) r.required = creating;
        });
        if (modeNote) {
            modeNote.textContent = creating
                ? 'No patient selected — these details register a new patient on save.'
                : 'Existing patient selected — the details below are ignored on save.';
        }
        if (mrInput) {
            var opt = sel.value ? sel.querySelector('option[value="' + sel.value + '"]') : null;
            mrInput.value = opt ? (opt.getAttribute('data-mr') || '') : previewMr;
        }
    }
    window.bkSyncPatientMode = syncMode;
    sel.addEventListener('change', syncMode);
    syncMode();
})();

// Validation failure round-trip: reopen the popup with the messages.
@if($errors->any())
(function () {
    var m = document.getElementById('bookAppointmentModal');
    if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
})();
@endif
</script>

<script>
(function () {
    // Hijack the nested quick-add-patient form: create via JSON quick-store,
    // then attach the new patient to the booking's patient select.
    var qForm = document.getElementById('quick-add-patient-form');
    var bkSelect = document.getElementById('bk_patient_id');
    var addedBox = document.getElementById('bk_patient_added');
    var quickStoreUrl = @json(route('medical.patients.quick-store'));
    var lookupUrl = @json(route('medical.patients.lookup'));
    var bkPhone = document.getElementById('bk_phone');
    var bkHint = document.getElementById('bk_phone_hint');
    var lookupBox = document.getElementById('bk_lookup_alert');
    var familyBtn = document.getElementById('bk_family_btn');
    var primaryInput = document.getElementById('bk_primary_contact_id');
    if (!qForm || !bkSelect) return;

    function repaint() {
        if (window.bkSyncPatientCombo) window.bkSyncPatientCombo();
        if (window.bkSyncPatientMode) window.bkSyncPatientMode();
    }

    // Phone-first lookup: existing patient is selected automatically.
    var bkTimer = null;
    var bkLastLookup = '';
    function bkResetLookup() {
        bkLastLookup = '';
        if (lookupBox) { lookupBox.classList.add('d-none'); lookupBox.classList.remove('d-flex'); }
        if (familyBtn) { familyBtn.style.display = 'none'; familyBtn.removeAttribute('data-primary-id'); }
        if (primaryInput) primaryInput.value = '';
    }
    function bkRunLookup() {
        var v = (bkPhone.value || '').trim();
        if (!v || v === bkLastLookup) return;
        bkLastLookup = v;
        fetch(lookupUrl + '?phone=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { found: false }; })
            .then(function (data) {
                if (!data || !data.found) {
                    bkResetLookup(); bkLastLookup = v;
                    if (bkHint) bkHint.textContent = 'No existing patient — pick from the list or add via + .';
                    return;
                }
                var list = data.patients || (data.patient ? [data.patient] : []);
                var p = data.patient || list[0];
                if (!p) return;
                var label = ((p.first_name || '') + ' ' + (p.last_name || '')).trim() + ' (' + (p.mr_number || '') + ')';
                var exists = bkSelect.querySelector('option[value="' + p.id + '"]');
                if (!exists) {
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = label;
                    opt.setAttribute('data-search', ((p.first_name || '') + ' ' + (p.last_name || '') + ' ' + (p.mr_number || '') + ' ' + v).toLowerCase());
                    opt.setAttribute('data-mr', p.mr_number || '');
                    bkSelect.appendChild(opt);
                }
                bkSelect.value = String(p.id);
                bkSelect.dispatchEvent(new Event('change', { bubbles: true }));
                document.getElementById('bk_lookup_name').textContent = ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
                document.getElementById('bk_lookup_mr').textContent = p.mr_number || '';
                if (lookupBox) { lookupBox.classList.remove('d-none'); lookupBox.classList.add('d-flex'); }
                if (bkHint) bkHint.textContent = 'Existing patient selected automatically.';
                if (addedBox) { addedBox.classList.add('d-none'); addedBox.classList.remove('d-flex'); }

                // Relatives share this phone — offer the family shortcut.
                var primary = list.find(function (m) { return !m.is_dependent; }) || list[0];
                if (familyBtn && primary) {
                    familyBtn.style.display = '';
                    familyBtn.setAttribute('data-primary-id', primary.id);
                }
            })
            .catch(function () { /* keep manual selection on lookup failure */ });
    }
    if (bkPhone) bkPhone.addEventListener('input', function () {
        if (bkTimer) clearTimeout(bkTimer);
        var digits = (bkPhone.value || '').replace(/\D/g, '');
        if (digits.length < 7) { bkResetLookup(); repaint(); return; }
        bkTimer = setTimeout(bkRunLookup, 500);
    });

    // "Add Family Member": same phone, new person — clear the selection and
    // the details, then link the new row to the primary contact on save.
    if (familyBtn) familyBtn.addEventListener('click', function () {
        var pid = familyBtn.getAttribute('data-primary-id') || '';
        if (primaryInput) primaryInput.value = pid;
        bkSelect.value = '';
        bkSelect.dispatchEvent(new Event('change', { bubbles: true }));
        ['bk_first_name', 'bk_last_name', 'bk_age'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var dob = document.getElementById('bk_dob');
        if (dob) { dob.value = ''; if (window.tdateSync) window.tdateSync('bk_dob'); }
        ['male', 'female', 'other'].forEach(function (g) {
            var r = document.getElementById('bk_gender_' + g);
            if (r) r.checked = false;
        });
        var relation = document.getElementById('bk_relation');
        if (relation && relation.value === 'Self') relation.value = 'Other';
        if (lookupBox) { lookupBox.classList.add('d-none'); lookupBox.classList.remove('d-flex'); }
        familyBtn.style.display = 'none';
        var note = document.getElementById('bk_mode_note');
        if (note) note.textContent = 'Registering a family member on the same phone — choose the relation below.';
        repaint();
        var first = document.getElementById('bk_first_name');
        if (first) first.focus();
    });

    function showQuickError(msg, focusId) {
        var err = document.getElementById('q_phone_error');
        if (err) {
            err.textContent = msg;
            err.style.display = 'block';
        }
        var target = focusId ? document.getElementById(focusId) : null;
        if (target) {
            target.classList.add('is-invalid');
            target.focus();
        }
    }

    function resetQuickForm() {
        ['q_first_name', 'q_last_name', 'q_phone', 'q_age'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.value = ''; el.classList.remove('is-invalid', 'is-valid'); }
        });
        var dob = document.getElementById('q_date_of_birth');
        if (dob) { dob.value = ''; if (window.tdateSync) window.tdateSync('q_date_of_birth'); }
        ['male', 'female', 'other'].forEach(function (g) {
            var r = document.getElementById('q_gender_' + g);
            if (r) r.checked = false;
        });
        var bg = document.getElementById('q_blood_group');
        if (bg) bg.value = '';
        var err = document.getElementById('q_phone_error');
        if (err) err.style.display = 'none';
        var submitBtn = document.getElementById('q_submit_btn');
        if (submitBtn) submitBtn.disabled = false;
    }

    qForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var phone = document.getElementById('q_phone');
        if (phone && phone.classList.contains('is-invalid')) { phone.focus(); return; }
        if (!qForm.reportValidity()) return;

        var submitBtn = document.getElementById('q_submit_btn');
        if (submitBtn && submitBtn.disabled) return; // existing patient found — no duplicate
        if (submitBtn) submitBtn.disabled = true;

        fetch(quickStoreUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(qForm),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { status: r.status, ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                if (submitBtn) submitBtn.disabled = false;
                if (res.ok && res.data && res.data.created) {
                    var p = res.data.patient;
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name + ' (' + p.mr_number + ')';
                    opt.setAttribute('data-search', (p.name + ' ' + p.mr_number + ' ' + (p.phone || '')).toLowerCase());
                    opt.setAttribute('data-mr', p.mr_number);
                    opt.selected = true;
                    bkSelect.appendChild(opt);
                    bkSelect.value = String(p.id);
                    bkSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    var nameEl = document.getElementById('bk_patient_added_name');
                    if (nameEl) nameEl.textContent = p.name + ' (' + p.mr_number + ')';
                    if (addedBox) { addedBox.classList.remove('d-none'); addedBox.classList.add('d-flex'); }
                    resetQuickForm();
                    var qModalEl = document.getElementById('quickAddPatientModal');
                    var qModal = qModalEl && window.bootstrap ? window.bootstrap.Modal.getInstance(qModalEl) : null;
                    if (qModal) qModal.hide();
                } else if (res.status === 422 && res.data && res.data.errors) {
                    var errors = res.data.errors;
                    var firstKey = Object.keys(errors)[0];
                    var fieldMap = { first_name: 'q_first_name', last_name: 'q_last_name', phone: 'q_phone', age: 'q_age', date_of_birth: 'q_date_of_birth', gender: 'q_gender_male' };
                    showQuickError(errors[firstKey][0], fieldMap[firstKey] || null);
                } else {
                    showQuickError((res.data && res.data.message) || 'Could not create patient. Please try again.');
                }
            })
            .catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                showQuickError('Network error. Please try again.');
            });
    });
})();
</script>

<script>
// Age <-> DOB link for the inline patient block (same behaviour as the
// Add Patient popup: typing one derives the other in the chosen unit).
(function () {
    var dob = document.getElementById('bk_dob');
    var age = document.getElementById('bk_age');
    var ageUnit = document.getElementById('bk_age_unit');
    if (!dob || !age) return;

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

    function dobFromAge() {
        var a = parseInt(age.value, 10);
        if (isNaN(a) || a < 0) return;
        var u = unit();
        if (a > UNIT_MAX[u]) return;
        var d = new Date();
        if (u === 'days') d.setDate(d.getDate() - a);
        else if (u === 'months') d.setMonth(d.getMonth() - a);
        else d.setFullYear(d.getFullYear() - a);
        if (!dob.value) {
            dob.value = d.toISOString().slice(0, 10);
            if (window.tdateSync) window.tdateSync('bk_dob');
        }
    }

    function clampDob() {
        if (!dob.value) return;
        var t = new Date();
        var todayIso = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
        if (dob.value > todayIso) {
            dob.value = todayIso;
            if (window.tdateSync) window.tdateSync('bk_dob');
            ageFromDob();
        }
    }

    dob.addEventListener('change', function () { clampDob(); ageFromDob(); });
    dob.addEventListener('input', function () {
        if (!dob.value) age.value = '';
        else ageFromDob();
    });
    age.addEventListener('change', dobFromAge);
    if (ageUnit) ageUnit.addEventListener('change', function () {
        age.max = UNIT_MAX[unit()];
        age.placeholder = unit() === 'years' ? 'e.g. 30' : (unit() === 'months' ? 'e.g. 6' : 'e.g. 15');
        ageFromDob();
    });
})();
</script>
@endpush
