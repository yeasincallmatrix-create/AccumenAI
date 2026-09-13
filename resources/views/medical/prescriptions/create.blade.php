@extends('layouts.institute')

@section('title', 'Write Prescription — AccumenAI')

@section('content')
@push('styles')
<style>
#rx-items-body tr.rx-dragging { opacity: .5; }
#rx-items-body tr.rx-drop-before { box-shadow: inset 0 2px 0 0 var(--bs-primary); }
#rx-items-body tr.rx-drop-after { box-shadow: inset 0 -2px 0 0 var(--bs-primary); }
.rx-drag-handle { cursor: grab; display: inline-flex; align-items: stretch; border: 1px solid var(--bs-border-color); border-radius: .375rem; background: var(--bs-tertiary-bg, #f8f9fa); color: #6c757d; touch-action: none; user-select: none; overflow: hidden; height: 28px; }
.rx-drag-handle > i { display: inline-flex; align-items: center; padding: 0 .3rem; }
.rx-drag-handle .rx-order { display: inline-flex; align-items: center; justify-content: center; min-width: 1.9em; padding: 0 .45rem; border-left: 1px solid var(--bs-border-color); background: rgba(0, 0, 0, .04); font-size: .78em; font-weight: 600; }
.rx-drag-handle:hover { background: #e9ecef; color: #212529; }
.rx-drag-handle:active { cursor: grabbing; }
.rx-drag-cell { white-space: nowrap; }
.mb-6 { margin-bottom: 4.5rem !important; }
</style>
@endpush
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
                        <label class="form-label small mb-0" for="rx-patient-combo">Patient <span class="text-danger">*</span></label>
                        <div class="position-relative">
                            <input type="text" id="rx-patient-combo" class="form-select form-select-sm" style="min-width:360px;" placeholder="Select Patient" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="rx-patient-listbox">
                            <div id="rx-patient-listbox" class="list-group position-absolute w-100 shadow-sm" style="display:none;max-height:280px;overflow-y:auto;z-index:1050;"></div>
                        </div>
                        <select id="patient_id" name="patient_id" form="prescription-form" data-info-base="{{ url('medical/prescriptions/patient-info') }}" data-options-base="{{ url('medical/prescriptions/patient-options') }}" data-queue-base="{{ url('medical/prescriptions/queue-numbers') }}" data-fee-appointment="{{ $feeAppointment->id ?? '' }}" data-walk-in-url="{{ route('medical.prescriptions.walk-in') }}" class="d-none" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}" data-search="{{ strtolower($patient->full_name.' '.$patient->mr_number.' '.clinical_no($patient->mr_number).' '.($patient->phone ?? '').' '.(!empty($bookedPatientIds[$patient->id] ?? null) ? 'regular' : 'emergency')) }}"
                                    @selected((string) old('patient_id', $selectedPatient->id ?? '') === (string) $patient->id)>
                                    {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }}){{ $patient->age !== null ? ', '.$patient->age.'y' : '' }}@if(!empty($ipdPatientIds[$patient->id] ?? null)) [IPD]@endif
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
                        <div class="col-sm-3">
                            <div class="mb-1"><span class="text-muted">MR Number: </span>{{ $infoPatient['mr_number'] }}</div>
                            <div class="mb-1"><span class="text-muted">Name: </span><strong>{{ $infoPatient['name'] }}</strong></div>
                        </div>
                        <div class="col-sm-3">
                            <div class="mb-1"><span class="text-muted">Phone: </span>{{ $infoPatient['phone'] }}</div>
                            <div class="mb-1"><span class="text-muted">Age / Gender: </span>{{ $infoPatient['age_gender'] }}</div>
                        </div>
                        <div class="col-sm-3">
                            <div class="mb-1"><span class="text-muted">Blood Group: </span>{{ $infoPatient['blood_group'] }}</div>
                            @if(!empty($infoPatient['payment']))
                                <div class="mb-1"><span class="text-muted">Payment: </span><span class="badge bg-{{ str_starts_with($infoPatient['payment'], 'Paid') ? 'success' : 'danger' }}">{{ $infoPatient['payment'] }}</span></div>
                            @endif
                        </div>
                        <div class="col-sm-3">
                            @if(!empty($infoPatient['serial']))
                                <div class="mb-1"><span class="text-muted">Serial: </span><strong>{{ $infoPatient['serial'] }}</strong></div>
                            @else
                                <div class="mb-1"><span class="text-muted">Serial: </span><strong>N/A</strong></div>
                            @endif
                            @if(!empty($infoPatient['fee']['url']) && !empty($infoPatient['fee']['collectable']))
                                <a href="{{ $infoPatient['fee']['url'] }}" class="btn btn-sm btn-success mt-1"><i class="bi bi-cash-coin me-1"></i>Accept Fee</a>
                            @elseif(!empty($infoPatient['fee']))
                                <span class="d-inline-block mt-1" title="Fee already paid">
                                    <button type="button" class="btn btn-sm btn-success" disabled><i class="bi bi-cash-coin me-1"></i>Accept Fee</button>
                                </span>
                                <div class="text-muted mt-1" style="font-size:.72em;">Fee already paid</div>
                            @elseif(!empty($selectedPatient) && !empty($cardDoctor))
                                <form action="{{ route('medical.prescriptions.walk-in') }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="patient_id" value="{{ $selectedPatient->id }}">
                                    <input type="hidden" name="doctor_id" value="{{ $cardDoctor }}">
                                    <button type="submit" class="btn btn-sm btn-success mt-1" title="No visit yet — adds a walk-in visit and opens fee collection"><i class="bi bi-cash-coin me-1"></i>Accept Fee</button>
                                </form>
                                <div class="text-muted mt-1" style="font-size:.72em;">No visit yet — creates walk-in</div>
                            @else
                                <span class="d-inline-block mt-1" title="Select patient and doctor first">
                                    <button type="button" class="btn btn-sm btn-success" disabled><i class="bi bi-cash-coin me-1"></i>Accept Fee</button>
                                </span>
                                <div class="text-muted mt-1" style="font-size:.72em;">Select patient and doctor first</div>
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
                    <div class="d-flex flex-column gap-3 w-100 h-100">
                        <div class="card flex-fill">
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
                        <div class="card flex-fill">
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
                        <div class="card flex-fill">
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
                        <div class="card flex-fill">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Investigations<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="investigations" name="investigations" aria-label="Investigations" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('investigations') is-invalid @enderror">{{ old('investigations') }}</textarea>
                                @error('investigations')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card flex-fill">
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
                        <div class="card-body d-flex flex-column">

            <h6 class="mt-3 mb-6 d-flex align-items-center justify-content-between" title="Medicines"><span><span style="font-size:5em;line-height:1;vertical-align:middle;" title="Medicines">℞</span> <span class="text-danger">*</span></span><button type="button" class="btn btn-sm btn-outline-secondary" data-rx-print-prefs title="Print preferences"><i class="bi bi-gear"></i></button></h6>
            @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="rx-items-table">
                    <thead>
                        <tr>
                            <th style="width:64px;" title="Drag to reorder">#</th>
                            <th style="min-width:220px;">Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Days</th>
                            <th>Qty</th>
                            <th style="width:104px;"></th>
                        </tr>
                    </thead>
                    <tbody id="rx-items-body">
                        {{-- Repopulated after a failed save so typed rows never
                             vanish: they survive until a real save or a manual
                             refresh, exactly like the edit form. --}}
                        @foreach(old('items', []) as $i => $item)
                        <tr data-rx-row>
                            <td class="rx-drag-cell"><span class="rx-drag-handle" title="Drag to reorder" aria-label="Drag to reorder"><i class="bi bi-grip-vertical"></i><span class="rx-order">{{ $loop->iteration }}</span></span></td>
                            <td><input type="hidden" name="items[{{ $i }}][medicine_id]" class="rx-med-id" value="{{ $item['medicine_id'] ?? '' }}">
                                <input type="text" name="items[{{ $i }}][medicine_name]" class="form-control form-control-sm rx-med-name" list="rx-medicine-list" required maxlength="200" placeholder="Type or pick medicine" value="{{ $item['medicine_name'] ?? '' }}">
                                <span class="badge rx-dgda mt-1 d-none"></span></td>
                            <td><input type="text" name="items[{{ $i }}][dosage]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 500mg" value="{{ $item['dosage'] ?? '' }}"></td>
                            <td><input type="text" name="items[{{ $i }}][frequency]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 1+0+1" value="{{ $item['frequency'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][duration_days]" class="form-control form-control-sm" min="1" placeholder="Days" value="{{ $item['duration_days'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control form-control-sm" min="1" value="{{ $item['quantity'] ?? 1 }}" required></td>
                            <td><button type="button" class="btn btn-sm btn-danger rx-remove" title="Remove">×</button> <button type="button" class="btn btn-sm btn-success rx-add-below" title="Add medicine below">+</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="rx-add-item">
                <i class="bi bi-plus-lg me-1"></i>Add Medicine
            </button>

            <div class="alert alert-info alert-dismissible fade show mt-2" role="alert" id="rx-safety-note">
                <i class="bi bi-shield-check me-1"></i>
                Allergy, contraindication and duplicate-therapy checks run on save. Blocking issues refuse the prescription; milder overlaps are shown as warnings.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>

            <div class="mt-auto pt-3 d-flex flex-wrap align-items-end justify-content-end gap-3">
                <div style="max-width:280px;">
                    <label class="form-label" for="follow_up_date">Follow-up Date</label>
                    <x-tdate-input name="follow_up_date" :value="old('follow_up_date', date('Y-m-d'))" id="follow_up_date" :class="'form-control'.($errors->has('follow_up_date') ? ' is-invalid' : '')" />
                    @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <div class="btn-group" role="group" aria-label="Save options">
                        <button type="submit" name="save_action" value="print" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save and Print
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">More save options</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-1" style="min-width:100%;">
                            <li><button type="submit" name="save_action" value="draft" class="dropdown-item bg-primary text-white rounded">Save in Draft</button></li>
                        </ul>
                    </div>
                    <a href="{{ route('medical.prescriptions.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
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

{{-- Rx print preferences popup (gear on the Medicines card). --}}
@include('medical.prescriptions._rx_print_prefs_modal')

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
            opt.setAttribute('data-search', ((res.d.patient.name || '') + ' ' + (res.d.patient.mr_number || '') + ' emergency').toLowerCase());
            sel.appendChild(opt);
            sel.value = String(res.d.patient.id);
            sel.dispatchEvent(new Event('change'));
            if (window.rxPaintQueueSerials) window.rxPaintQueueSerials();
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
        return '<tr data-rx-row>' +
            '<td class="rx-drag-cell"><span class="rx-drag-handle" title="Drag to reorder" aria-label="Drag to reorder"><i class="bi bi-grip-vertical"></i>' +
            '<span class="rx-order">1</span></span></td>' +
            '<td><input type="hidden" name="items[' + i + '][medicine_id]" class="rx-med-id">' +
            '<input type="text" name="items[' + i + '][medicine_name]" class="form-control form-control-sm rx-med-name" list="rx-medicine-list" required maxlength="200" placeholder="Type or pick medicine">' +
            '<span class="badge rx-dgda mt-1 d-none"></span></td>' +
            '<td><input type="text" name="items[' + i + '][dosage]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 500mg"></td>' +
            '<td><input type="text" name="items[' + i + '][frequency]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 1+0+1"></td>' +
            '<td><input type="number" name="items[' + i + '][duration_days]" class="form-control form-control-sm" min="1" placeholder="Days"></td>' +
            '<td><input type="number" name="items[' + i + '][quantity]" class="form-control form-control-sm" min="1" value="1" required></td>' +
            '<td class="rx-row-actions"><button type="button" class="btn btn-sm btn-danger rx-remove" title="Remove">×</button> <button type="button" class="btn btn-sm btn-success rx-add-below" title="Add medicine below">+</button></td>' +
            '</tr>';
    }

    function renumberRows() {
        var rows = body.querySelectorAll('tr[data-rx-row]');
        rows.forEach(function (tr, idx) {
            var badge = tr.querySelector('.rx-order');
            if (badge) badge.textContent = String(idx + 1);
            tr.querySelectorAll('input[name^="items["]').forEach(function (input) {
                input.name = input.name.replace(/^items\[\d+\]/, 'items[' + idx + ']');
            });
        });
        index = rows.length;
        // Per-row + buttons cover adding; the bottom button only shows
        // when the table is empty so a row can always be added back.
        if (addBtn) addBtn.style.display = rows.length ? 'none' : '';
    }

    var dragSrc = null;

    function clearDropHints() {
        body.querySelectorAll('tr[data-rx-row]').forEach(function (r) {
            r.classList.remove('rx-drop-before', 'rx-drop-after');
        });
    }

    function bindRow(tr) {
        tr.setAttribute('data-rx-row', '');
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
            renumberRows();
        });
        var addBelow = tr.querySelector('.rx-add-below');
        if (addBelow) addBelow.addEventListener('click', function () {
            addRow(tr);
        });
        syncDgdaTag(tr);

        // Drag only via the handle: arm the row on handle press so text
        // inputs stay selectable/draggable-safe the rest of the time.
        var handle = tr.querySelector('.rx-drag-handle');
        tr.draggable = false;
        if (handle) {
            handle.addEventListener('mousedown', function () { tr.draggable = true; });
            handle.addEventListener('touchstart', function () { tr.draggable = true; }, { passive: true });
            document.addEventListener('mouseup', function () { tr.draggable = false; });
        }
        tr.addEventListener('dragstart', function (e) {
            if (!tr.draggable) { e.preventDefault(); return; }
            dragSrc = tr;
            tr.classList.add('rx-dragging');
            if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'rx-row'); } catch (err) {} }
        });
        tr.addEventListener('dragend', function () {
            tr.classList.remove('rx-dragging');
            tr.draggable = false;
            clearDropHints();
            dragSrc = null;
        });
        tr.addEventListener('dragover', function (e) {
            if (!dragSrc || dragSrc === tr) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            var rect = tr.getBoundingClientRect();
            var before = (e.clientY - rect.top) < rect.height / 2;
            tr.classList.toggle('rx-drop-before', before);
            tr.classList.toggle('rx-drop-after', !before);
        });
        tr.addEventListener('dragleave', function () {
            tr.classList.remove('rx-drop-before', 'rx-drop-after');
        });
        tr.addEventListener('drop', function (e) {
            if (!dragSrc || dragSrc === tr) return;
            e.preventDefault();
            var rect = tr.getBoundingClientRect();
            var before = (e.clientY - rect.top) < rect.height / 2;
            if (before) body.insertBefore(dragSrc, tr);
            else body.insertBefore(dragSrc, tr.nextElementSibling);
            clearDropHints();
            renumberRows();
        });
    }

    function addRow(afterTr, focus) {
        var tmp = document.createElement('tbody');
        tmp.innerHTML = rowHtml(index++);
        var tr = tmp.firstChild;
        bindRow(tr);
        if (afterTr && afterTr.parentNode === body) {
            body.insertBefore(tr, afterTr.nextSibling);
        } else {
            body.appendChild(tr);
        }
        renumberRows();
        if (focus !== false) {
            var focusInput = tr.querySelector('.rx-med-name');
            if (focusInput) focusInput.focus();
        }
    }

    addBtn.addEventListener('click', function () { addRow(); });

    // Server-repopulated rows (failed save) are bound as-is; a blank row
    // is added only when the table starts empty.
    var existingRows = body.querySelectorAll('tr');
    if (existingRows.length) {
        existingRows.forEach(bindRow);
        renumberRows();
    } else {
        addRow();
    }

    // Patient-switch reset (called from the info-cards block): drop all
    // rows back to one blank row without stealing focus.
    window.rxResetMedicineRows = function () {
        body.innerHTML = '';
        addRow(null, false);
    };

    document.getElementById('prescription-form').addEventListener('submit', function (e) {
        renumberRows();
        if (body.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            alert('Please add at least one medicine.');
        }
    });

    // Safety notice comes and goes: auto-dismiss after a few seconds so it
    // never sits on the form permanently (still closable by hand).
    setTimeout(function () {
        var note = document.getElementById('rx-safety-note');
        if (!note) return;
        if (window.bootstrap && window.bootstrap.Alert) {
            var inst = window.bootstrap.Alert.getOrCreateInstance(note);
            if (inst) inst.close();
        } else {
            note.remove();
        }
    }, 8000);
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
    function renderPatient(p, doctorId) {
        p = p || {};
        function cell(label, value, bold) {
            return '<div class="mb-1"><span class="text-muted">' + esc(label) + ': </span>' + (bold ? '<strong>' + esc(value) + '</strong>' : esc(value)) + '</div>';
        }
        var payRow = '';
        if (p.payment) {
            var cls = String(p.payment).indexOf('Paid') === 0 ? 'success' : 'danger';
            payRow = '<div class="mb-1"><span class="text-muted">Payment: </span><span class="badge bg-' + cls + '">' + esc(p.payment) + '</span></div>';
        }
        var feeBtn = '';
        if (p.fee && p.fee.url && p.fee.collectable) {
            feeBtn = '<a href="' + esc(p.fee.url) + '" class="btn btn-sm btn-success mt-1"><i class="bi bi-cash-coin me-1"></i>Accept Fee</a>';
        } else if (p.fee) {
            feeBtn = '<span class="d-inline-block mt-1" title="Fee already paid">'
                + '<button type="button" class="btn btn-sm btn-success" disabled><i class="bi bi-cash-coin me-1"></i>Accept Fee</button></span>'
                + '<div class="text-muted mt-1" style="font-size:.72em;">Fee already paid</div>';
        } else if (doctorId) {
            // Emergency / unscheduled arrival: no visit row yet — post a
            // walk-in (adds today's visit) and chain into fee collection.
            var token = document.querySelector('meta[name="csrf-token"]');
            var walkInUrl = patientSelect.getAttribute('data-walk-in-url') || '';
            feeBtn = '<form action="' + esc(walkInUrl) + '" method="POST" class="d-inline">'
                + '<input type="hidden" name="_token" value="' + esc(token ? token.getAttribute('content') : '') + '">'
                + '<input type="hidden" name="patient_id" value="' + esc(id) + '">'
                + '<input type="hidden" name="doctor_id" value="' + esc(doctorId) + '">'
                + '<button type="submit" class="btn btn-sm btn-success mt-1" title="No visit yet — adds a walk-in visit and opens fee collection"><i class="bi bi-cash-coin me-1"></i>Accept Fee</button></form>'
                + '<div class="text-muted mt-1" style="font-size:.72em;">No visit yet — creates walk-in</div>';
        } else {
            feeBtn = '<span class="d-inline-block mt-1" title="Select patient and doctor first">'
                + '<button type="button" class="btn btn-sm btn-success" disabled><i class="bi bi-cash-coin me-1"></i>Accept Fee</button></span>'
                + '<div class="text-muted mt-1" style="font-size:.72em;">Select patient and doctor first</div>';
        }
        patientCard.innerHTML = '<div class="row mb-0 small">' +
            '<div class="col-sm-3">' + cell('MR Number', p.mr_number) + cell('Name', p.name, true) + '</div>' +
            '<div class="col-sm-3">' + cell('Phone', p.phone) + cell('Age / Gender', p.age_gender) + '</div>' +
            '<div class="col-sm-3">' + cell('Blood Group', p.blood_group) + payRow + '</div>' +
            '<div class="col-sm-3">' + cell('Serial', p.serial || 'N/A', true) + feeBtn + '</div>' +
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
        var doctorSelect = document.getElementById('doctor_id');
        var doctorId = doctorSelect ? doctorSelect.value : '';
        var infoUrl = infoBase + '/' + encodeURIComponent(id);
        var query = [];
        if (feeAppointmentId) query.push('fee_appointment_id=' + encodeURIComponent(feeAppointmentId));
        // Serial/payment are doctor-scoped: the card always reflects the
        // selected prescriber, never another doctor's queue number.
        if (doctorId) query.push('doctor_id=' + encodeURIComponent(doctorId));
        if (query.length) infoUrl += '?' + query.join('&');
        return fetch(infoUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('load failed');
                return res.json();
            })
            .then(function (data) {
                renderPatient(data.patient || {}, doctorId);
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
        var next = patientSelect.value;
        // New patient's sheet: Details/Vitals reload below, but the draft
        // panels (complaints, diagnosis, findings, advice, Rx rows) belong
        // to the previous patient — reset them so stale text can never be
        // saved onto the wrong person. A dirty draft asks first.
        if (next !== lastPatientId && lastPatientId !== '' && next !== '' && prescriptionDraftDirty()) {
            if (!window.confirm('Switching patient will clear the current draft (complaints, diagnosis, findings, advice, medicines). Continue?')) {
                patientSelect.value = lastPatientId;
                var comboBack = document.getElementById('rx-patient-combo');
                var optBack = patientSelect.querySelector('option[value="' + lastPatientId + '"]');
                if (comboBack) comboBack.value = optBack ? (optBack.getAttribute('data-label') || optBack.textContent) : '';
                applyPatientList();
                return;
            }
        }
        lastPatientId = next;
        if (next !== '') resetDraftPanels();
        loadPatientInfo(next);
        applyPatientList();
    });
    var lastPatientId = patientSelect.value || '';

    // Draft text inputs that are per-patient (not patient data).
    var draftFieldIds = ['chief_complaints', 'diagnosis', 'investigations', 'examination_findings', 'advice'];

    function prescriptionDraftDirty() {
        for (var i = 0; i < draftFieldIds.length; i++) {
            var el = document.getElementById(draftFieldIds[i]);
            if (el && String(el.value || '').trim() !== '') return true;
        }
        var rows = document.querySelectorAll('#rx-items-body tr');
        if (rows.length > 1) return true;
        if (rows.length === 1) {
            var inputs = rows[0].querySelectorAll('input');
            for (var j = 0; j < inputs.length; j++) {
                var v = inputs[j].value;
                if (inputs[j].type === 'number') {
                    if (v !== '' && v !== '1') return true;
                } else if (String(v || '').trim() !== '') {
                    return true;
                }
            }
        }
        return false;
    }

    function resetDraftPanels() {
        draftFieldIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
        if (window.rxResetMedicineRows) window.rxResetMedicineRows();
    }

    // Changing the prescriber re-scopes the card serial/payment to that
    // doctor's queue (no-op until a patient is picked).
    var doctorSelect = document.getElementById('doctor_id');
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function () {
            if (patientSelect.value) loadPatientInfo(patientSelect.value);
            paintQueueSerials();
        });
    }

    // Patient dropdown follows the live queue: with a doctor picked, only
    // that doctor's queued patients are annotated (each painted with its
    // own serial for the day). Nothing is ever hidden: admitted (IPD) and
    // walk-in patients carry no queue serial but must stay pickable, and
    // the in-hand selection is always preserved, so an emergency walk-in
    // you are working with never vanishes mid-flow.
    // With no doctor picked, scoping is impossible — everyone lists.
    var lastQueueMap = null;
    function queueSerialOf(map, value) {
        if (!map) return null;
        var serial = map[value] !== undefined && map[value] !== null
            ? map[value] : map[String(value)];
        return (serial !== undefined && serial !== null && serial !== '') ? serial : null;
    }
    function applyPatientList() {
        var opts = patientSelect.querySelectorAll('option[value]');
        opts.forEach(function (opt) {
            if (!opt.getAttribute('data-label')) opt.setAttribute('data-label', opt.textContent);
            var base = opt.getAttribute('data-label');
            var serial = queueSerialOf(lastQueueMap, opt.value);
            opt.textContent = serial !== null ? '#' + serial + ' — ' + base : base;
            // Keep every option visible (IPD / walk-in patients have no
            // serial); the suffix alone marks queued patients.
            opt.style.display = '';
        });
    }
    function paintQueueSerials() {
        var queueBase = patientSelect.getAttribute('data-queue-base');
        var sel = document.getElementById('doctor_id');
        var doctorId = sel ? sel.value : '';
        if (!queueBase || !doctorId) {
            lastQueueMap = null;
            applyPatientList();
            return;
        }
        fetch(queueBase + '?doctor_id=' + encodeURIComponent(doctorId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.ok ? res.json() : {}; })
            .then(function (map) {
                lastQueueMap = map || {};
                applyPatientList();
            })
            .catch(function () {
                lastQueueMap = null;
                applyPatientList();
            });
    }
    paintQueueSerials();
    // Quick-add appends options from its own script block — let it ask
    // for a repaint so the newcomer gets its serial too.
    window.rxPaintQueueSerials = paintQueueSerials;

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

// Strict scoping: the patient dropdown lists ONLY patients holding an
// appointment with the chosen doctor on the chosen date — nothing else.
// Doctor/date changes (including the tenant date picker's programmatic
// sync, caught by polling) rebuild the list; the in-hand selection
// (fee/walk-in flow) is always preserved.
(function () {
    var patientSelect = document.getElementById('patient_id');
    var doctorSelect = document.getElementById('doctor_id');
    var dateHidden = document.getElementById('prescription_date');
    var dateDisplay = document.getElementById('prescription_date_display');
    if (!patientSelect) return;
    var optionsBase = patientSelect.getAttribute('data-options-base');
    if (!optionsBase) return;
    var lastScope = '';

    function currentScope() {
        return {
            doctor_id: doctorSelect ? doctorSelect.value : '',
            date: dateHidden ? dateHidden.value : ''
        };
    }

    function setPatientOptions(rows, current, currentOpt) {
        rows = Array.isArray(rows) ? rows : [];
        var seen = {};
        patientSelect.querySelectorAll('option[value]:not([value=""])').forEach(function (o) { o.remove(); });
        rows.forEach(function (r) {
            seen[String(r.id)] = true;
            var o = document.createElement('option');
            o.value = r.id;
            o.textContent = r.label;
            if (r.search) o.setAttribute('data-search', r.search);
            patientSelect.appendChild(o);
        });
        if (current && !seen[String(current)] && currentOpt) {
            patientSelect.appendChild(currentOpt);
            patientSelect.value = current;
        } else if (current && seen[String(current)]) {
            patientSelect.value = current;
        } else {
            patientSelect.value = '';
            patientSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (window.rxPaintQueueSerials) window.rxPaintQueueSerials();
    }
    window.rxSetPatientOptions = setPatientOptions;

    function refreshPatientOptions() {
        var scope = currentScope();
        var url = optionsBase + '?doctor_id=' + encodeURIComponent(scope.doctor_id) + '&date=' + encodeURIComponent(scope.date);
        var current = patientSelect.value;
        var currentOpt = current ? patientSelect.querySelector('option[value="' + current + '"]') : null;
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('options failed');
                return res.json();
            })
            .then(function (rows) {
                setPatientOptions(rows, current, currentOpt);
            })
            .catch(function () { /* keep existing options on failure */ });
    }
    window.rxRefreshPatientOptions = refreshPatientOptions;

    function scopeChanged() {
        var scope = currentScope();
        var key = scope.doctor_id + '|' + scope.date;
        if (key !== lastScope) {
            lastScope = key;
            refreshPatientOptions();
        }
    }

    if (doctorSelect) doctorSelect.addEventListener('change', scopeChanged);
    if (dateDisplay) dateDisplay.addEventListener('change', scopeChanged);
    if (dateHidden) dateHidden.addEventListener('change', scopeChanged);
    scopeChanged();
    setInterval(scopeChanged, 2000);
})();

// Searchable patient combobox: type name / MR / phone to filter the
// scoped options; picking sets the hidden select (cards, validation and
// queue serials all keep working off it).
(function () {
    var patientSelect = document.getElementById('patient_id');
    var combo = document.getElementById('rx-patient-combo');
    var box = document.getElementById('rx-patient-listbox');
    if (!patientSelect || !combo || !box) return;
    var activeIndex = -1;

    function listedOptions() {
        return Array.from(patientSelect.querySelectorAll('option[value]:not([value=""])'))
            .map(function (o) {
                return {
                    id: o.value,
                    label: o.getAttribute('data-label') || o.textContent,
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
        patientSelect.value = id;
        var sel = patientSelect.querySelector('option[value="' + id + '"]');
        combo.value = sel ? (sel.getAttribute('data-label') || sel.textContent) : '';
        combo.title = combo.value;
        closeList();
        patientSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function syncCombo() {
        if (document.activeElement === combo) return;
        var sel = patientSelect.querySelector('option[value="' + patientSelect.value + '"]');
        combo.value = (patientSelect.value && sel) ? (sel.getAttribute('data-label') || sel.textContent) : '';
        combo.title = combo.value;
    }

    combo.addEventListener('focus', function () {
        // Populated field stays editable: select-all for instant overwrite
        // plus the full list, so switching patients is always one action.
        try { combo.select(); } catch (e) {}
        renderList('');
    });
    combo.addEventListener('input', function () {
        // Instant client filter first; debounced server search reaches the
        // whole accessible pool (name / MR / phone) beyond the scoped list.
        renderList(combo.value);
        clearTimeout(combo.dataset.timer ? Number(combo.dataset.timer) : 0);
        var q = combo.value;
        var optionsBase = patientSelect.getAttribute('data-options-base');
        combo.dataset.timer = String(setTimeout(function () {
            if ((q || '').trim() === '') {
                if (window.rxRefreshPatientOptions) window.rxRefreshPatientOptions();
                renderList('');
                return;
            }
            if (!optionsBase || !window.rxSetPatientOptions) return;
            var current = patientSelect.value;
            var currentOpt = current ? patientSelect.querySelector('option[value="' + current + '"]') : null;
            fetch(optionsBase + '?search=' + encodeURIComponent(q.trim()), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('search failed');
                    return res.json();
                })
                .then(function (rows) {
                    window.rxSetPatientOptions(rows, current, currentOpt);
                    renderList(q);
                })
                .catch(function () { renderList(q); });
        }, 300));
    });
    combo.addEventListener('keydown', function (e) {
        var items = box.querySelectorAll('button[data-id]');
        if (e.key === 'Escape') {
            closeList();
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length === 0) return;
            activeIndex = e.key === 'ArrowDown'
                ? Math.min(activeIndex + 1, items.length - 1)
                : Math.max(activeIndex - 1, 0);
            items.forEach(function (b, i) { b.classList.toggle('active', i === activeIndex); });
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && items[activeIndex]) {
                e.preventDefault();
                pick(items[activeIndex].getAttribute('data-id'));
            }
        }
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#rx-patient-combo') && !e.target.closest('#rx-patient-listbox')) closeList();
    });
    patientSelect.addEventListener('change', function () { setTimeout(syncCombo, 0); });
    syncCombo();

    // The native select is hidden, so browsers skip its `required`
    // validation — guard the submit here instead.
    var rxForm = document.getElementById('prescription-form');
    if (rxForm) rxForm.addEventListener('submit', function (e) {
        if (!patientSelect.value) {
            e.preventDefault();
            combo.classList.add('is-invalid');
            setTimeout(function () { combo.classList.remove('is-invalid'); }, 2000);
            combo.focus();
            renderList('');
        }
    });
})();

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
