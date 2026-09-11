@extends('layouts.institute')

@section('title', 'Write Prescription — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Write Prescription</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.prescriptions.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(!empty($feeAppointment))
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-arrow-left-right"></i>
        <span>Writing for queue visit <strong>#{{ $feeAppointment->serial_number }}</strong> — saving will open fee collection to complete the visit.</span>
    </div>
@endif

<div class="card mb-3">
    <div class="card-header py-1 d-flex justify-content-end bg-transparent border-0 pb-0">
        <div class="d-flex align-items-center gap-2">
            <label class="form-label small mb-0" for="doctor_id">Doctor <span class="text-danger">*</span></label>
            <select id="doctor_id" name="doctor_id" form="prescription-form" class="form-select form-select-sm @error('doctor_id') is-invalid @enderror" style="width:auto;min-width:200px;" required>
                <option value="">Select Doctor</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) old('doctor_id', $selectedDoctor ?? '') === (string) $doctor->id)>
                            {{ $doctor->name }}
                        </option>
                    @endforeach
            </select>
            @error('doctor_id')<div class="invalid-feedback d-block mb-0">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="card-body pt-1 pb-3 text-center" id="rx-doctor-card">
        <h3 class="fw-bold mb-0" data-dc="name">—</h3>
        <div class="text-muted" data-dc="credentials">—</div>
        <div class="fw-semibold" data-dc="registration">—</div>
        <div class="small mt-1" data-dc="practice">—</div>
        <div class="small text-muted" data-dc="contact">—</div>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var cards = @json($doctorCards ?? []);
    var practice = @json($practiceInfo ?? []);
    var sel = document.getElementById('doctor_id');
    function text(el, v) {
        if (!v) { el.style.display = 'none'; return; }
        el.style.display = '';
        el.textContent = v;
    }
    function paintDoctorCard() {
        // No selection yet: single-doctor lists auto-select themselves;
        // otherwise preview the first card without touching the form.
        if (sel && !sel.value) {
            var ids = Object.keys(cards);
            if (ids.length === 1) { sel.value = ids[0]; }
        }
        var entry = (sel && cards[sel.value]) || cards[Object.keys(cards)[0]] || {};
        var cred = [entry.qualification, entry.specialty, entry.department].filter(Boolean).join(' · ');
        var spec = entry.specialty && entry.department ? entry.specialty + ' (' + entry.department + ')' : (entry.specialty || entry.department || '');
        cred = [entry.qualification, spec].filter(Boolean).join(', ');
        var prac = [practice.clinic, practice.address].filter(Boolean).join(', ');
        var contact = [practice.phone, practice.email].filter(Boolean).join(' · ');
        var nodes = document.querySelectorAll('#rx-doctor-card [data-dc]');
        nodes.forEach(function (dd) {
            var k = dd.getAttribute('data-dc');
            if (k === 'name') text(dd, entry.name);
            else if (k === 'credentials') text(dd, cred);
            else if (k === 'registration') text(dd, entry.registration ? 'Reg. No. ' + entry.registration : '');
            else if (k === 'practice') text(dd, prac);
            else if (k === 'contact') text(dd, contact);
        });
    }
    if (sel) { sel.addEventListener('change', paintDoctorCard); paintDoctorCard(); }
})();
</script>
@endpush

<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-header py-2">
                <h6 class="mb-0 d-flex align-items-center justify-content-between">Chief Complaints<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></h6>
            </div>
            <div class="card-body py-2 d-flex flex-column">
                <textarea id="chief_complaints" name="chief_complaints" form="prescription-form" aria-label="Chief Complaints" data-autogrow rows="3"
                          style="overflow-y:auto;max-height:600px;"
                          class="form-control flex-fill @error('chief_complaints') is-invalid @enderror">{{ old('chief_complaints') }}</textarea>
                @error('chief_complaints')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="col-md-10">
        <div class="card h-100">
            <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
                <h6 class="mb-0"><i class="bi bi-person me-1"></i>Patient Details</h6>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label small mb-0" for="patient_id">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" form="prescription-form" data-info-base="{{ url('medical/prescriptions/patient-info') }}" data-fee-appointment="{{ $feeAppointment->id ?? '' }}" class="form-select form-select-sm @error('patient_id') is-invalid @enderror" style="width:auto;min-width:220px;" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}"
                                    @selected((string) old('patient_id', $selectedPatient->id ?? '') === (string) $patient->id)>
                                    {{ $patient->full_name }} ({{ $patient->mr_number }})
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="invalid-feedback d-block mb-0">{{ $message }}</div>@enderror
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="rx-quick-add-patient" title="Quick add patient">
                        <i class="bi bi-person-plus"></i>
                    </button>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label small mb-0" for="prescription_date">Date <span class="text-danger">*</span></label>
                        <div style="width:220px;">
                            <x-tdate-input name="prescription_date" :value="old('prescription_date', date('Y-m-d'))" id="prescription_date" form="prescription-form" :class="'form-control form-control-sm'.($errors->has('prescription_date') ? ' is-invalid' : '')" required />
                        </div>
                        @error('prescription_date')<div class="invalid-feedback d-block mb-0">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-body py-2" id="rx-patient-card">
                <div id="rx-patient-info">
                @if(!empty($infoPatient))
                    <div class="row mb-0 small">
                        <div class="col-sm-4">
                            <div class="mb-1"><span class="text-muted">MR Number: </span>{{ $infoPatient['mr_number'] }}</div>
                            <div class="mb-1"><span class="text-muted">Name: </span><strong>{{ $infoPatient['name'] }}</strong></div>
                        </div>
                        <div class="col-sm-4">
                            <div class="mb-1"><span class="text-muted">Phone: </span>{{ $infoPatient['phone'] }}</div>
                            <div class="mb-1"><span class="text-muted">Age / Gender: </span>{{ $infoPatient['age_gender'] }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="mb-1"><span class="text-muted">Blood Group: </span>{{ $infoPatient['blood_group'] }}</div>
                            @if(!empty($infoPatient['payment']))
                                <div class="mb-1"><span class="text-muted">Payment: </span><span class="badge bg-{{ str_starts_with($infoPatient['payment'], 'Paid') ? 'success' : 'danger' }}">{{ $infoPatient['payment'] }}</span></div>
                            @endif
                            @if(!empty($infoPatient['serial']))
                                <div class="mb-1"><span class="text-muted">Serial: </span><strong>{{ $infoPatient['serial'] }}</strong></div>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-muted mb-0 small">Select a patient below to view details.</p>
                @endif
                </div>
            </div>
        </div>
    </div>
</div>

        <form action="{{ route('medical.prescriptions.store') }}" method="POST" id="prescription-form">
            @csrf
            @if(!empty($feeAppointment))
                <input type="hidden" name="fee_appointment_id" value="{{ $feeAppointment->id }}">
            @endif
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="d-flex flex-column gap-3 align-self-start w-100">
                        <div class="card">
                            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                <h6 class="mb-0"><i class="bi bi-heart-pulse me-1"></i>Latest Vital Signs</h6>
                                <button type="button" class="btn btn-link btn-sm p-0 text-secondary" id="rx-vitals-edit" title="Record vitals" style="text-decoration:none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>
                                        <path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="card-body py-2" id="rx-vitals-card">
                                @if(!empty($infoVitals))
                                    @if(!empty($infoVitals['has_values']))
                                        <dl class="mb-0 small">
                                            @php($vitalRows = ['temperature' => 'Temp', 'bp' => 'BP', 'pulse' => 'Pulse', 'spo2' => 'SpO2', 'respiratory_rate' => 'RR', 'blood_sugar' => 'Sugar', 'weight' => 'Wt', 'height' => 'Ht', 'bmi' => 'BMI'])
                                            @foreach($vitalRows as $key => $label)
                                                @if(isset($infoVitals[$key]) && $infoVitals[$key] !== '' && $infoVitals[$key] !== null)
                                                    <div class="d-flex justify-content-between gap-2 border-bottom py-1"><dt>{{ $label }}</dt><dd class="mb-0">{{ $infoVitals[$key] }}</dd></div>
                                                @endif
                                            @endforeach
                                        </dl>
                                    @else
                                        <p class="text-muted mb-0 small">No vital values recorded yet.</p>
                                    @endif
                                    <p class="text-muted small mb-0 mt-1">{{ $infoVitals['recorded_at'] }}</p>
                                @else
                                    <p class="text-muted mb-0 small">{{ !empty($infoPatient) ? 'No vitals recorded for this patient yet.' : 'Select a patient below to view vitals.' }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Diagnosis<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="diagnosis" name="diagnosis" aria-label="Diagnosis" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('diagnosis') is-invalid @enderror">{{ old('diagnosis') }}</textarea>
                                @error('diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Examination Findings<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="examination_findings" name="examination_findings" aria-label="Examination Findings" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('examination_findings') is-invalid @enderror">{{ old('examination_findings') }}</textarea>
                                @error('examination_findings')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Advice<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="advice" name="advice" aria-label="Advice" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('advice') is-invalid @enderror">{{ old('advice') }}</textarea>
                                @error('advice')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-10">
                    <div class="card h-100">
                        <div class="card-body">

            <h6 class="mt-3 mb-2" title="Medicines"><span style="font-size:5em;line-height:1;vertical-align:middle;" title="Medicines">℞</span> <span class="text-danger">*</span></h6>
            @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="rx-items-table">
                    <thead>
                        <tr>
                            <th style="min-width:220px;">Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Days</th>
                            <th>Qty</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="rx-items-body">
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="rx-add-item">
                <i class="bi bi-plus-lg me-1"></i>Add Medicine
            </button>

            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_date">Follow-up Date</label>
                        <x-tdate-input name="follow_up_date" :value="old('follow_up_date')" id="follow_up_date" :class="'form-control'.($errors->has('follow_up_date') ? ' is-invalid' : '')" />
                        @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-2">
                <i class="bi bi-shield-check me-1"></i>
                Allergy, contraindication and duplicate-therapy checks run on save. Blocking issues refuse the prescription; milder overlaps are shown as warnings.
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Prescription (Draft)
                </button>
                <a href="{{ route('medical.prescriptions.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

<datalist id="rx-medicine-list">
    @php($rxDgda = mawa_dgda_enabled())
    @foreach($medicines as $medicine)
        <option data-id="{{ $medicine->id }}" data-dgda="{{ $rxDgda ? ($medicine->dgda_code ?? '') : '' }}" value="{{ $medicine->display_name }}" label="{{ $rxDgda ? ($medicine->dgda_code ? 'DGDA: '.$medicine->dgda_code : 'No DGDA code') : $medicine->display_name }}">{{ $rxDgda ? $medicine->display_name.' — '.($medicine->dgda_code ? 'DGDA: '.$medicine->dgda_code : 'No DGDA code') : $medicine->display_name }}</option>
    @endforeach
</datalist>

{{-- Shared Record Vitals popup (pencil on the Latest Vitals card). --}}
@include('medical.appointments._vitals_modal')

{{-- Shared Quick Add Patient popup (plus button in the Patient Details header). --}}
@include('medical.patients._quick_create_modal')
@push('scripts')
<script>
(function () {
    var btn = document.getElementById('rx-quick-add-patient');
    if (btn) btn.addEventListener('click', function () {
        var m = document.getElementById('quickAddPatientModal');
        if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
    });
    // Submit the popup via the JSON quick-store endpoint so the
    // prescription form state is never lost; the new patient is appended
    // to the dropdown and selected (patient card refreshes on change).
    var form = document.getElementById('quick-add-patient-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return; // modal's own phone validation refused
        e.preventDefault();
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(@json(route('medical.patients.quick-store')), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            if (!res.ok || !res.d.created) {
                var errs = res.d.errors ? Object.values(res.d.errors).flat().join(' ') : (res.d.message || 'Could not add patient.');
                alert(errs);
                return;
            }
            var sel = document.getElementById('patient_id');
            var opt = document.createElement('option');
            opt.value = res.d.patient.id;
            opt.textContent = res.d.patient.name + ' (' + res.d.patient.mr_number + ')';
            sel.appendChild(opt);
            sel.value = String(res.d.patient.id);
            sel.dispatchEvent(new Event('change'));
            var m = document.getElementById('quickAddPatientModal');
            if (m && window.bootstrap) { var inst = window.bootstrap.Modal.getInstance(m); if (inst) inst.hide(); }
            form.reset();
        })
        .catch(function () { alert('Could not add patient. Please try again.'); });
    });
})();
</script>
@endpush
@endsection

@push('scripts')
<script>
(function () {
    var body = document.getElementById('rx-items-body');
    var addBtn = document.getElementById('rx-add-item');
    var catalog = {};
    document.querySelectorAll('#rx-medicine-list option').forEach(function (opt) {
        catalog[opt.value] = { id: opt.getAttribute('data-id'), dgda: opt.getAttribute('data-dgda') || '' };
    });
    var index = 0;
    var dgdaOn = @json(mawa_dgda_enabled());

    function syncDgdaTag(tr) {
        if (!dgdaOn) return;
        var tag = tr.querySelector('.rx-dgda');
        var nameInput = tr.querySelector('.rx-med-name');
        if (!tag || !nameInput) return;
        var entry = catalog[nameInput.value];
        if (entry && entry.dgda) {
            tag.textContent = 'DGDA: ' + entry.dgda;
            tag.title = 'DGDA registry code: ' + entry.dgda;
            tag.className = 'badge rx-dgda mt-1 bg-success';
        } else if (entry) {
            tag.textContent = 'DGDA sync pending';
            tag.title = 'No DGDA code — registry sync pending';
            tag.className = 'badge rx-dgda mt-1 bg-warning text-dark';
        } else {
            tag.textContent = '';
            tag.title = '';
            tag.className = 'badge rx-dgda mt-1 d-none';
        }
    }

    function rowHtml(i) {
        return '<tr>' +
            '<td><input type="hidden" name="items[' + i + '][medicine_id]" class="rx-med-id">' +
            '<input type="text" name="items[' + i + '][medicine_name]" class="form-control form-control-sm rx-med-name" list="rx-medicine-list" required maxlength="200" placeholder="Type or pick medicine">' +
            '<span class="badge rx-dgda mt-1 d-none"></span></td>' +
            '<td><input type="text" name="items[' + i + '][dosage]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 500mg"></td>' +
            '<td><input type="text" name="items[' + i + '][frequency]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 1+0+1"></td>' +
            '<td><input type="number" name="items[' + i + '][duration_days]" class="form-control form-control-sm" min="1" placeholder="Days"></td>' +
            '<td><input type="number" name="items[' + i + '][quantity]" class="form-control form-control-sm" min="1" value="1" required></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger rx-remove">×</button></td>' +
            '</tr>';
    }

    function bindRow(tr) {
        var nameInput = tr.querySelector('.rx-med-name');
        var idInput = tr.querySelector('.rx-med-id');
        var update = function () {
            var entry = catalog[nameInput.value];
            idInput.value = (entry && entry.id) || '';
            syncDgdaTag(tr);
        };
        nameInput.addEventListener('change', update);
        nameInput.addEventListener('input', update);
        tr.querySelector('.rx-remove').addEventListener('click', function () {
            tr.remove();
        });
    }

    function addRow() {
        var tmp = document.createElement('tbody');
        tmp.innerHTML = rowHtml(index++);
        var tr = tmp.firstChild;
        bindRow(tr);
        body.appendChild(tr);
    }

    addBtn.addEventListener('click', addRow);
    addRow();

    document.getElementById('prescription-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            alert('Please add at least one medicine.');
        }
    });
})();

// Read-only info cards: patient details + latest vitals, refreshed from
// the patient-info endpoint (same payload the server renders initially).
// The pencil on the vitals card opens the shared Record Vitals popup for
// the current patient; saving posts via AJAX and refreshes the card, so
// the prescription form state is never lost.
(function () {
    var patientSelect = document.getElementById('patient_id');
    var patientCard = document.getElementById('rx-patient-info');
    var vitalsCard = document.getElementById('rx-vitals-card');
    var editBtn = document.getElementById('rx-vitals-edit');
    if (!patientSelect || !patientCard || !vitalsCard) return;
    var infoBase = patientSelect.getAttribute('data-info-base');
    var rxCache = { id: '', fetched: false, name: '', raw: null };

    function esc(s) {
        return String(s === null || s === undefined ? '—' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function renderPatient(p) {
        p = p || {};
        function cell(label, value, bold) {
            return '<div class="mb-1"><span class="text-muted">' + esc(label) + ': </span>' + (bold ? '<strong>' + esc(value) + '</strong>' : esc(value)) + '</div>';
        }
        var payRow = '';
        if (p.payment) {
            var cls = String(p.payment).indexOf('Paid') === 0 ? 'success' : 'danger';
            payRow = '<div class="mb-1"><span class="text-muted">Payment: </span><span class="badge bg-' + cls + '">' + esc(p.payment) + '</span></div>';
        }
        patientCard.innerHTML = '<div class="row mb-0 small">' +
            '<div class="col-sm-4">' + cell('MR Number', p.mr_number) + cell('Name', p.name, true) + '</div>' +
            '<div class="col-sm-4">' + cell('Phone', p.phone) + cell('Age / Gender', p.age_gender) + '</div>' +
            '<div class="col-sm-4">' + cell('Blood Group', p.blood_group) + payRow + (p.serial ? cell('Serial', p.serial, true) : '') + '</div>' +
            '</div>';
    }
    function renderVitals(v) {
        if (!v) {
            vitalsCard.innerHTML = '<p class="text-muted mb-0 small">No vitals recorded for this patient yet.</p>';
            return;
        }
        // Only mentioned values are shown — empty ones are hidden, not dashed.
        var rows = [
            ['Temp', v.temperature],
            ['BP', v.bp],
            ['Pulse', v.pulse],
            ['SpO2', v.spo2],
            ['RR', v.respiratory_rate],
            ['Sugar', v.blood_sugar],
            ['Wt', v.weight],
            ['Ht', v.height],
            ['BMI', v.bmi]
        ].filter(function (r) {
            return r[1] !== null && r[1] !== undefined && r[1] !== '';
        });
        if (rows.length === 0) {
            vitalsCard.innerHTML = '<p class="text-muted mb-0 small">No vital values recorded yet.</p>' +
                '<p class="text-muted small mb-0 mt-1">' + esc(v.recorded_at) + '</p>';
            return;
        }
        vitalsCard.innerHTML = '<dl class="mb-0 small">' +
            rows.map(function (r, i) {
                return '<div class="d-flex justify-content-between gap-2' + (i === rows.length - 1 ? ' py-1">' : ' border-bottom py-1">') +
                    '<dt>' + esc(r[0]) + '</dt><dd class="mb-0">' + esc(r[1]) + '</dd></div>';
            }).join('') +
            '</dl>' +
            '<p class="text-muted small mb-0 mt-1">' + esc(v.recorded_at) + '</p>';
    }
    function loadPatientInfo(id) {
        if (!id || !infoBase) {
            patientCard.innerHTML = '<p class="text-muted mb-0 small">Select a patient below to view details.</p>';
            vitalsCard.innerHTML = '<p class="text-muted mb-0 small">Select a patient below to view vitals.</p>';
            rxCache = { id: '', fetched: false, name: '', raw: null };
            return Promise.resolve(null);
        }
        vitalsCard.innerHTML = '<p class="text-muted mb-0 small"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</p>';
        var feeAppointmentId = patientSelect.getAttribute('data-fee-appointment');
        var infoUrl = infoBase + '/' + encodeURIComponent(id) +
            (feeAppointmentId ? '?fee_appointment_id=' + encodeURIComponent(feeAppointmentId) : '');
        return fetch(infoUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('load failed');
                return res.json();
            })
            .then(function (data) {
                renderPatient(data.patient || {});
                renderVitals(data.vitals);
                rxCache = {
                    id: id, fetched: true,
                    name: (data.patient || {}).name || '',
                    raw: (data.vitals || {}).raw || null
                };
                return data;
            })
            .catch(function () {
                vitalsCard.innerHTML = '<p class="text-danger mb-0 small">Could not load patient info.</p>';
                return null;
            });
    }

    patientSelect.addEventListener('change', function () {
        loadPatientInfo(patientSelect.value);
    });

    function openRxVitalsModal(name, raw) {
        var modalForm = document.getElementById('vitals-quick-form');
        if (!modalForm) return;
        modalForm.reset();
        document.getElementById('vq_appointment_id').value = '';
        var pidInput = document.getElementById('vq_patient_id');
        if (pidInput) pidInput.value = patientSelect.value;
        var unitSel = document.getElementById('vq_height_unit');
        if (unitSel) unitSel.value = 'cm';
        var context = document.getElementById('vq_context');
        if (context) context.textContent = name || '—';
        raw = raw || {};
        Object.keys(raw).forEach(function (k) {
            var input = modalForm.querySelector('[name="' + k + '"]');
            if (input && raw[k] !== null && raw[k] !== undefined && raw[k] !== '') input.value = raw[k];
        });
        var hint = document.getElementById('vq_height_hint');
        if (hint) hint.textContent = '';
        var modalEl = document.getElementById('vitalsQuickModal');
        if (modalEl && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            var pid = patientSelect.value;
            if (!pid) {
                alert('Please select a patient first.');
                return;
            }
            if (rxCache.fetched && rxCache.id === pid) {
                openRxVitalsModal(rxCache.name, rxCache.raw);
                return;
            }
            loadPatientInfo(pid).then(function (data) {
                if (data) openRxVitalsModal((data.patient || {}).name || '', (data.vitals || {}).raw || null);
                else openRxVitalsModal('', null);
            });
        });
    }

    // AJAX save for patient-mode vitals (pencil flow): window-capture runs
    // before the popup's own submit converter, so feet are converted here
    // and the popup converter is skipped via stopPropagation. The
    // appointments-page flow (no patient linkage) is left untouched.
    window.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.id !== 'vitals-quick-form' || !document.getElementById('rx-vitals-card')) return;
        var pidInput = document.getElementById('vq_patient_id');
        if (!pidInput || !pidInput.value) return;
        e.preventDefault();
        e.stopPropagation();
        var hInput = document.getElementById('vq_height');
        var hUnit = document.getElementById('vq_height_unit');
        if (hInput && hUnit && hUnit.value === 'ft') {
            var hv = parseFloat(hInput.value);
            if (!isNaN(hv)) hInput.value = Math.round(hv * 30.48 * 10) / 10;
        }
        var token = document.querySelector('meta[name="csrf-token"]');
            var httpStatus = 0;
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                },
                credentials: 'same-origin',
                body: new FormData(form)
            })
                .then(function (res) {
                    httpStatus = res.status;
                    return res.json()
                        .catch(function () { return {}; })
                        .then(function (body) { return { status: res.status, body: body }; });
                })
            .then(function (out) {
                if (out.status === 422) {
                    var errs = out.body && out.body.errors
                        ? Object.keys(out.body.errors).map(function (k) { return out.body.errors[k].join(' '); }).join('\n')
                        : ((out.body && out.body.message) || 'Validation failed.');
                    alert(errs);
                    return;
                }
                if (!out.body || !out.body.ok) throw new Error('save failed');
                var modalEl = document.getElementById('vitalsQuickModal');
                if (modalEl && window.bootstrap) {
                    var inst = window.bootstrap.Modal.getInstance(modalEl);
                    if (inst) inst.hide();
                }
                loadPatientInfo(pidInput.value);
            })
                .catch(function () {
                    alert('Could not save vitals (HTTP ' + httpStatus + '). Please try again.');
                });
    }, true);

    // Note cards share the column equally at first; any textarea with
    // text stretches its own card (capped with a scrollbar).
    document.querySelectorAll('textarea[data-autogrow]').forEach(function (ta) {
        function grow() {
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 600) + 'px';
        }
        ta.addEventListener('input', grow);
        grow();
    });
})();
</script>
@endpush
