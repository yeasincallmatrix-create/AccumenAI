{{-- Book Appointment popup for the appointments index page.
     Includes appointment fields + a nested Add Patient popup (quick-create modal
     hijacked to AJAX quick-store) so a new patient can be created without leaving. --}}
<div class="modal fade" id="bookAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('medical.appointments.store') }}" method="POST" id="book-appointment-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Book Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success d-none align-items-center gap-2" id="bk_patient_added" role="alert">
                        <i class="bi bi-person-check"></i>
                        <span>New patient <strong id="bk_patient_added_name"></strong> added and selected.</span>
                    </div>
                    @php
                        $bkDefaultCountry = collect($countries ?? [])->firstWhere('id', (int) ($defaultCountryId ?? 0));
                        $bkDefaultPhoneCode = $bkDefaultCountry->phone_code ?? '880';
                    @endphp
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_phone">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">+{{ $bkDefaultPhoneCode }}</span>
                                    <input type="tel" id="bk_phone" class="form-control" autocomplete="off"
                                           placeholder="Type phone to find patient">
                                </div>
                                <div class="form-text" id="bk_phone_hint">Existing patient is selected automatically.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info d-none align-items-center gap-2 mb-3" id="bk_lookup_alert" role="alert">
                                <i class="bi bi-person-check"></i>
                                <span>Found <strong id="bk_lookup_name"></strong> (<span id="bk_lookup_mr"></span>)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_patient_id">Patient <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2">
                                    <select id="bk_patient_id" name="patient_id" class="form-select" required>
                                        <option value="">Select Patient</option>
                                        @foreach(($patients ?? []) as $patient)
                                            <option value="{{ $patient->id }}">{{ $patient->full_name }} ({{ $patient->mr_number }})</option>
                                        @endforeach
                                    </select>
                                    @if($canCreatePatient ?? false)
                                        <button type="button" class="btn btn-outline-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#quickAddPatientModal" title="Add new patient">
                                            <i class="bi bi-person-plus"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_appointment_date">Appointment Date <span class="text-danger">*</span></label>
                                <x-tdate-input name="appointment_date" :value="request('date', date('Y-m-d'))" id="bk_appointment_date" class="form-control" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="bk_appointment_time">Appointment Time <span class="text-danger">*</span></label>
                                <input type="time" id="bk_appointment_time" name="appointment_time" class="form-control" value="09:00" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="bk_complaints">Complaints / Symptoms</label>
                                <textarea id="bk_complaints" name="complaints" rows="2" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="bk_notes">Notes</label>
                                <textarea id="bk_notes" name="notes" rows="2" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
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

@push('scripts')
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
    if (!qForm || !bkSelect) return;

    // Phone-first lookup: existing patient is selected automatically.
    var bkTimer = null;
    var bkLastLookup = '';
    function bkResetLookup() {
        bkLastLookup = '';
        if (lookupBox) { lookupBox.classList.add('d-none'); lookupBox.classList.remove('d-flex'); }
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
                var p = data.patient;
                var label = ((p.first_name || '') + ' ' + (p.last_name || '')).trim() + ' (' + (p.mr_number || '') + ')';
                var exists = bkSelect.querySelector('option[value="' + p.id + '"]');
                if (!exists) {
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = label;
                    bkSelect.appendChild(opt);
                }
                bkSelect.value = String(p.id);
                document.getElementById('bk_lookup_name').textContent = ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
                document.getElementById('bk_lookup_mr').textContent = p.mr_number || '';
                if (lookupBox) { lookupBox.classList.remove('d-none'); lookupBox.classList.add('d-flex'); }
                if (bkHint) bkHint.textContent = 'Existing patient selected automatically.';
                if (addedBox) { addedBox.classList.add('d-none'); addedBox.classList.remove('d-flex'); }
            })
            .catch(function () { /* keep manual selection on lookup failure */ });
    }
    if (bkPhone) bkPhone.addEventListener('input', function () {
        if (bkTimer) clearTimeout(bkTimer);
        var digits = (bkPhone.value || '').replace(/\D/g, '');
        if (digits.length < 7) { bkResetLookup(); return; }
        bkTimer = setTimeout(bkRunLookup, 500);
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
                    opt.selected = true;
                    bkSelect.appendChild(opt);
                    bkSelect.value = String(p.id);
                    var nameEl = document.getElementById('bk_patient_added_name');
                    if (nameEl) nameEl.textContent = p.name + ' (' + p.mr_number + ')';
                    if (addedBox) { addedBox.classList.remove('d-none'); addedBox.classList.add('d-flex'); }
                    resetQuickForm();
                    var qModalEl = document.getElementById('quickAddPatientModal');
                    var qModal = qModalEl && window.bootstrap ? window.bootstrap.Modal.getInstance(qModalEl) : null;
                    if (qModal) qModal.hide();
                    bkSelect.focus();
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
@endpush
