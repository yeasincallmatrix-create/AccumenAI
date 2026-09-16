@php
$isCreate = ($mode === 'create');
$isAmend = ($mode === 'amend');
$formId = $isCreate ? 'prescription-form' : 'prescription-edit-form';
$defaultDoctor = $isCreate ? ($selectedDoctor ?? '') : $prescription->doctor_id;
$defaultPatient = $isCreate ? ($selectedPatient->id ?? '') : $prescription->patient_id;
$defaultDate = $isCreate ? date('Y-m-d') : $prescription->prescription_date?->format('Y-m-d');
$defaultFollowUp = $isCreate ? date('Y-m-d') : $prescription->follow_up_date?->format('Y-m-d');
@endphp

<div class="card mb-3">
    <div class="card-header py-1 d-flex justify-content-end bg-transparent border-0 pb-0">
        <div class="d-flex align-items-center gap-2">
            <label class="form-label small mb-0" for="doctor_id">Doctor <span class="text-danger">*</span></label>
            <select id="doctor_id" name="doctor_id" form="{{ $formId }}" class="form-select form-select-sm @error('doctor_id') is-invalid @enderror" style="width:auto;min-width:200px;" required>
                <option value="">Select Doctor</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) old('doctor_id', $defaultDoctor) === (string) $doctor->id)>
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

<div class="row g-3 mb-3" id="rx-top-row">
    <div class="col-md-2">
        <div class="card h-100">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h6 class="mb-0"><i class="bi bi-heart-pulse me-1"></i>Latest Vital Signs</h6>
                <button type="button" class="btn btn-link btn-sm p-0 text-secondary" id="rx-vitals-edit" title="Record vitals" style="text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg>
                </button>
            </div>
            <div class="card-body py-2" id="rx-vitals-card">
                @if(!empty($infoVitals))
                    @if(!empty($infoVitals['has_values']))
                        <dl class="mb-0 small">
                            @php
                            $vitalRows = ['temperature' => 'Temp', 'bp' => 'BP', 'pulse' => 'Pulse', 'spo2' => 'SpO2', 'respiratory_rate' => 'RR', 'blood_sugar' => 'Sugar', 'weight' => 'Wt', 'height' => 'Ht', 'bmi' => 'BMI'];
                            @endphp
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
                        <select id="patient_id" name="patient_id" form="{{ $formId }}" data-info-base="{{ url('medical/prescriptions/patient-info') }}" data-options-base="{{ url('medical/prescriptions/patient-options') }}" data-queue-base="{{ url('medical/prescriptions/queue-numbers') }}" data-fee-appointment="{{ $isCreate ? ($feeAppointment->id ?? '') : '' }}" data-walk-in-url="{{ route('medical.prescriptions.walk-in') }}" class="d-none" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}" data-search="{{ strtolower($patient->full_name.' '.$patient->mr_number.' '.clinical_no($patient->mr_number).' '.($patient->phone ?? '').' '.(!empty($bookedPatientIds[$patient->id] ?? null) ? 'regular' : 'emergency')) }}"
                                    @selected((string) old('patient_id', $defaultPatient) === (string) $patient->id)>
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
                            <x-tdate-input name="prescription_date" :value="old('prescription_date', $defaultDate)" id="prescription_date" form="{{ $formId }}" :class="'form-control form-control-sm'.($errors->has('prescription_date') ? ' is-invalid' : '')" required />
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

        <form action="{{ $isCreate ? route('medical.prescriptions.store') : ($isAmend ? route('medical.prescriptions.amend.store', $prescription) : route('medical.prescriptions.update', $prescription)) }}" method="POST" id="{{ $formId }}">
            @csrf
            @if(!$isCreate && !$isAmend)
                @method('PUT')
            @endif
            @if($isCreate && !empty($feeAppointment))
                <input type="hidden" name="fee_appointment_id" value="{{ $feeAppointment->id }}">
            @endif
            <div class="row g-3" id="rx-main-row">
                <div class="col-md-2" id="rx-soap-col">
                    <div class="d-flex flex-column gap-3 w-100 h-100">
                        <div class="card flex-fill" data-rx-panel="complaints">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Chief Complaints<span class="d-inline-flex align-items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></span></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="chief_complaints" name="chief_complaints" aria-label="Chief Complaints" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('chief_complaints') is-invalid @enderror">{{ old('chief_complaints', $isCreate ? '' : ($prescription->chief_complaints ?? '')) }}</textarea>
                                @error('chief_complaints')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card flex-fill" data-rx-panel="findings">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Examination Findings<span class="d-inline-flex align-items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></span></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="examination_findings" name="examination_findings" aria-label="Examination Findings" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('examination_findings') is-invalid @enderror">{{ old('examination_findings', $isCreate ? '' : ($prescription->examination_findings ?? '')) }}</textarea>
                                @error('examination_findings')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card flex-fill" data-rx-panel="diagnosis">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Diagnosis<span class="d-inline-flex align-items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></span></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="diagnosis" name="diagnosis" aria-label="Diagnosis" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('diagnosis') is-invalid @enderror">{{ old('diagnosis', $isCreate ? '' : ($prescription->diagnosis ?? '')) }}</textarea>
                                @error('diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card flex-fill" data-rx-panel="investigations">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Investigations<span class="d-inline-flex align-items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></span></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="investigations" name="investigations" aria-label="Investigations" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('investigations') is-invalid @enderror">{{ old('investigations', $isCreate ? '' : ($prescription->investigations ?? '')) }}</textarea>
                                @error('investigations')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="card flex-fill" data-rx-panel="advice">
                            <div class="card-header py-2">
                                <h6 class="mb-0 d-flex align-items-center justify-content-between">Advice<span class="d-inline-flex align-items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="m9.5 13.5 8.5-8.5a1.9 1.9 0 0 1 2.7 2.7l-8.5 8.5-3.7 1.2 1.2-3.7Z"/></svg></span></h6>
                            </div>
                            <div class="card-body py-2 d-flex flex-column">
                                <textarea id="advice" name="advice" aria-label="Advice" data-autogrow rows="3"
                                          style="overflow-y:auto;max-height:600px;"
                                          class="form-control flex-fill @error('advice') is-invalid @enderror">{{ old('advice', $isCreate ? '' : ($prescription->advice ?? '')) }}</textarea>
                                @error('advice')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-10" id="rx-main-col">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">

            <h6 class="mt-3 mb-6 d-flex align-items-center justify-content-between" title="Medicines"><span><span style="font-size:5em;line-height:1;vertical-align:middle;" title="Medicines">℞</span> <span class="text-danger">*</span></span><span class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary" id="rx-quick-add-medicine" title="Add a medicine not in the database"><i class="bi bi-lightning me-1"></i>New</button><button type="button" class="btn btn-sm btn-outline-secondary" data-rx-print-prefs title="Print preferences"><i class="bi bi-gear"></i></button></span></h6>

            @if($isAmend)
            <div class="mb-3">
                <label class="form-label fw-semibold">Reason for Amendment <span class="text-danger">*</span></label>
                <textarea name="amendment_reason" class="form-control @error('amendment_reason') is-invalid @enderror" rows="3" required minlength="5" maxlength="500"
                          placeholder="e.g., Patient developed side effect, dose adjustment needed...">{{ old('amendment_reason') }}</textarea>
                @error('amendment_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @endif

            @error('parent_items')<div class="alert alert-danger">{{ $message }}</div>@enderror
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
                        @if($isCreate)
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
                        @elseif($isAmend)
                        {{-- Parent items: locked rows from previous version --}}
                        @foreach($prescription->items as $pi)
                        <tr data-rx-row data-parent-item-id="{{ $pi->id }}" class="rx-parent-row">
                            <td class="rx-drag-cell"><span class="rx-order">{{ $loop->iteration }}</span></td>
                            <td>
                                <input type="hidden" name="parent_items[{{ $pi->id }}][id]" value="{{ $pi->id }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][medicine_id]" value="{{ $pi->medicine_id ?? '' }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][medicine_name]" value="{{ $pi->medicine_name }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][dosage]" value="{{ $pi->dosage }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][frequency]" value="{{ $pi->frequency }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][duration_days]" value="{{ $pi->duration_days }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][quantity]" value="{{ $pi->quantity }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][dgda_code]" value="{{ $pi->dgda_code ?? '' }}">
                                <input type="hidden" name="parent_items[{{ $pi->id }}][special_instructions]" value="{{ $pi->special_instructions ?? '' }}">
                                <span class="rx-locked-text">{{ $pi->medicine_name }}</span>
                                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:.65em">v{{ $prescription->version }}</span>
                            </td>
                            <td><span class="rx-locked-text">{{ $pi->dosage }}</span></td>
                            <td><span class="rx-locked-text">{{ $pi->frequency }}</span></td>
                            <td><span class="rx-locked-text">{{ $pi->duration_days ?? '—' }}</span></td>
                            <td><span class="rx-locked-text">{{ $pi->quantity }}</span></td>
                            <td>
                                <div class="d-flex gap-1 align-items-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="parent_items[{{ $pi->id }}][action]" id="keep_{{ $pi->id }}" value="keep" checked>
                                        <label class="btn btn-outline-success btn-sm" for="keep_{{ $pi->id }}" title="Keep active"><i class="bi bi-check-lg"></i></label>
                                        <input type="radio" class="btn-check" name="parent_items[{{ $pi->id }}][action]" id="disc_{{ $pi->id }}" value="discontinue">
                                        <label class="btn btn-outline-danger btn-sm" for="disc_{{ $pi->id }}" title="Discontinue"><i class="bi bi-x-lg"></i></label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="rx-parent-reason d-none" data-parent-reason="{{ $pi->id }}">
                            <td></td>
                            <td colspan="5">
                                <input type="text" name="parent_items[{{ $pi->id }}][discontinued_reason]"
                                       class="form-control form-control-sm" placeholder="Discontinue reason (required)" maxlength="255">
                            </td>
                            <td></td>
                        </tr>
                        @endforeach
                        {{-- New items start empty; doctor clicks "Add Medicine" --}}
                        @else
                        @foreach(old('items', $prescription->items->map(fn ($i) => [
                            'medicine_id' => $i->medicine_id,
                            'medicine_name' => $i->medicine_name,
                            'dosage' => $i->dosage,
                            'frequency' => $i->frequency,
                            'duration_days' => $i->duration_days,
                            'quantity' => $i->quantity,
                        ])->all()) as $i => $item)
                        <tr data-rx-row>
                            <td class="rx-drag-cell"><span class="rx-drag-handle" title="Drag to reorder" aria-label="Drag to reorder"><i class="bi bi-grip-vertical"></i><span class="rx-order">{{ $loop->iteration }}</span></span></td>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][medicine_id]" class="rx-med-id" value="{{ $item['medicine_id'] ?? '' }}">
                                <input type="text" name="items[{{ $i }}][medicine_name]" class="form-control form-control-sm rx-med-name"
                                       list="rx-medicine-list" required maxlength="200" value="{{ $item['medicine_name'] ?? '' }}">
                                <span class="badge rx-dgda mt-1 d-none"></span>
                            </td>
                            <td><input type="text" name="items[{{ $i }}][dosage]" class="form-control form-control-sm" required maxlength="50" value="{{ $item['dosage'] ?? '' }}"></td>
                            <td><input type="text" name="items[{{ $i }}][frequency]" class="form-control form-control-sm" required maxlength="50" value="{{ $item['frequency'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][duration_days]" class="form-control form-control-sm" min="1" value="{{ $item['duration_days'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control form-control-sm" min="1" value="{{ $item['quantity'] ?? 1 }}" required></td>
                            <td><button type="button" class="btn btn-sm btn-danger rx-remove" title="Remove">×</button> <button type="button" class="btn btn-sm btn-success rx-add-below" title="Add medicine below">+</button></td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="rx-add-item">
                <i class="bi bi-plus-lg me-1"></i>Add Medicine
            </button>

            @if($isCreate)
            <div class="alert alert-info alert-dismissible fade show mt-2" role="alert" id="rx-safety-note">
                <i class="bi bi-shield-check me-1"></i>
                Allergy, contraindication and duplicate-therapy checks run on save. Blocking issues refuse the prescription; milder overlaps are shown as warnings.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            <div class="mt-auto pt-3 d-flex flex-wrap align-items-end justify-content-end gap-3">
                <div style="max-width:170px;">
                    <div class="mb-0">
                        <label class="form-label" for="follow_up_date">Follow-up Date</label>
                        <x-tdate-input name="follow_up_date" :value="old('follow_up_date', $defaultFollowUp)" id="follow_up_date" :class="'form-control'.($errors->has('follow_up_date') ? ' is-invalid' : '')" />
                        @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @if(!empty($infoPatient['fee']['collectable']))
                        <button type="button" class="btn btn-success"
                            data-fee-url="{{ $infoPatient['fee']['collect_url'] }}"
                            data-fee-action="{{ $infoPatient['fee']['action'] }}"
                            data-fee-patient="{{ $infoPatient['fee']['patient'] ?? $selectedPatient->full_name ?? '—' }}"
                            data-fee-type="{{ $infoPatient['fee']['fee_type'] }}"
                            data-fee-amount="{{ $infoPatient['fee']['amount'] }}"
                            data-fee-redirect="{{ url()->current() }}"
                            onclick="{{ $isCreate ? 'saveRxDraft(); ' : '' }}openFeeModal(this)">
                            <i class="bi bi-cash-coin me-1"></i>Accept Fee
                        </button>
                    @elseif(!empty($infoPatient['fee']))
                        <button type="button" class="btn btn-success" disabled><i class="bi bi-check-circle me-1"></i>Paid</button>
                    @elseif(!empty($selectedPatient) && !empty($cardDoctor))
                        <button type="button" class="btn btn-success" id="rx-walkin-fee-btn"
                            data-patient-id="{{ $selectedPatient->id }}"
                            data-doctor-id="{{ $cardDoctor }}"
                            data-url="{{ route('medical.prescriptions.walk-in') }}"
                            data-fee-redirect="{{ url()->current() }}">
                            <i class="bi bi-cash-coin me-1"></i>Accept Fee
                        </button>
                    @else
                        <button type="button" class="btn btn-success" disabled><i class="bi bi-cash-coin me-1"></i>Accept Fee</button>
                    @endif
                    @if($isCreate)
                    <button type="button" class="btn btn-outline-secondary" id="rx-reset-draft" title="Clear all fields"><i class="bi bi-arrow-counterclockwise"></i></button>
                    @endif
                    @php
                        $btnClass = $isAmend ? 'btn-warning' : 'btn-primary';
                        $draftClass = $isAmend ? 'bg-warning text-dark' : 'bg-primary text-white';
                        $primaryLabel = $isCreate ? 'Save & Print' : ($isAmend ? 'Amend & Print' : 'Update & Print');
                        $draftLabel = $isCreate ? 'Save in Draft' : ($isAmend ? 'Save as Draft' : 'Update Draft');
                    @endphp
                    <div class="btn-group" role="group" aria-label="Save options">
                        <button type="submit" name="save_action" value="print" class="btn {{ $btnClass }}">
                            <i class="bi bi-save me-1"></i>{{ $primaryLabel }}
                        </button>
                        <button type="button" class="btn {{ $btnClass }} dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">More save options</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-1" style="min-width:100%;">
                            <li><button type="submit" name="save_action" value="draft" class="dropdown-item {{ $draftClass }} rounded">{{ $draftLabel }}</button></li>
                        </ul>
                    </div>
                    <a href="{{ $isCreate ? route('medical.prescriptions.index') : route('medical.prescriptions.show', $prescription) }}" class="btn btn-secondary">Cancel</a>
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

{{-- Quick Add Medicine popup (lightning button in the Medicines card). --}}
<div class="modal fade" id="quickAddMedicineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="quick-add-medicine-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-capsule me-1"></i>Quick Add Medicine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="qmed_dosage_form">Dosage Form <span class="text-danger">*</span></label>
                        <select id="qmed_dosage_form" name="dosage_form" class="form-select" required>
                            <option value="">Select type</option>
                            @foreach(config('medicine.dosage_forms') as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="qmed_generic_name">Medicine Name <span class="text-danger">*</span></label>
                        <input type="text" id="qmed_generic_name" name="generic_name" class="form-control" required maxlength="150" placeholder="e.g. Paracetamol">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="qmed_strength">Strength</label>
                        <input type="text" id="qmed_strength" name="strength" class="form-control" maxlength="50" placeholder="e.g. 500mg">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="qmed_brand_name">Brand Name</label>
                        <input type="text" id="qmed_brand_name" name="brand_name" class="form-control" maxlength="150" placeholder="e.g. Napa">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="qmed_submit_btn">
                        <i class="bi bi-plus-circle me-1"></i>Add &amp; Select
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($isCreate)
{{-- Duplicate prescription warning modal --}}
<div class="modal fade" id="rxDuplicateModal" tabindex="-1" aria-labelledby="rxDuplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="rxDuplicateModalLabel">
                    <i class="bi bi-exclamation-triangle me-1"></i>Prescription Already Exists
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">A prescription already exists for this patient on the selected date:</p>
                <ul class="list-unstyled mb-3 ps-3">
                    <li><strong>Rx No:</strong> <span id="rx-dup-number">—</span></li>
                    <li><strong>Doctor:</strong> <span id="rx-dup-doctor">—</span></li>
                    <li><strong>Date:</strong> <span id="rx-dup-date">—</span></li>
                    <li><strong>Status:</strong> <span id="rx-dup-status">—</span></li>
                    <li><strong>Version:</strong> v<span id="rx-dup-version">1</span>
                        <span id="rx-dup-amended-badge" class="badge bg-info text-dark" style="display:none;">Amended</span>
                    </li>
                    <li><strong>Items:</strong> <span id="rx-dup-items">—</span> medicine(s)</li>
                </ul>
                <p class="text-muted small mb-0">Only one prescription is allowed per patient per day per doctor. Would you like to edit or amend the existing prescription?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="rx-dup-edit-btn" href="#" class="btn btn-primary">
                    <i class="bi bi-pencil-square me-1"></i><span id="rx-dup-action-text">Edit</span> Existing Prescription
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('rx-quick-add-patient');
    if (btn) btn.addEventListener('click', function () {
        var m = document.getElementById('quickAddPatientModal');
        if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).show();
    });
    var form = document.getElementById('quick-add-patient-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
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
@push('scripts')
<script>
(function () {
    var body = document.getElementById('rx-items-body');
    var addBtn = document.getElementById('rx-add-item');
    var catalog = {};
    document.querySelectorAll('#rx-medicine-list option').forEach(function (opt) {
        catalog[opt.value] = { id: opt.getAttribute('data-id'), dgda: opt.getAttribute('data-dgda') || '' };
    });
    window.rxCatalog = catalog;
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

    @if($isCreate)
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
    @endif

    function renumberRows() {
        var allRows = body.querySelectorAll('tr[data-rx-row]');
        var newIdx = 0;
        allRows.forEach(function (tr, visualIdx) {
            var badge = tr.querySelector('.rx-order');
            if (badge) badge.textContent = String(visualIdx + 1);
            if (tr.classList.contains('rx-parent-row')) return;
            tr.querySelectorAll('input[name^="items["]').forEach(function (input) {
                input.name = input.name.replace(/^items\[\d+\]/, 'items[' + newIdx + ']');
            });
            newIdx++;
        });
        index = newIdx;
        if (addBtn) addBtn.style.display = @json($isCreate) ? (allRows.length ? 'none' : '') : '';
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
            @if($isCreate)
            if (document.querySelectorAll('#rx-items-body tr').length <= 1) return;
            @endif
            tr.remove();
            renumberRows();
        });
        var addBelow = tr.querySelector('.rx-add-below');
        if (addBelow) addBelow.addEventListener('click', function () {
            addRow(tr);
        });
        syncDgdaTag(tr);

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
        @if($isCreate)
        tmp.innerHTML = rowHtml(index++);
        @else
        tmp.innerHTML = '<tr data-rx-row>' +
            '<td class="rx-drag-cell"><span class="rx-drag-handle" title="Drag to reorder" aria-label="Drag to reorder"><i class="bi bi-grip-vertical"></i>' +
            '<span class="rx-order">1</span></span></td>' +
            '<td><input type="hidden" name="items[' + index + '][medicine_id]" class="rx-med-id">' +
            '<input type="text" name="items[' + index + '][medicine_name]" class="form-control form-control-sm rx-med-name" list="rx-medicine-list" required maxlength="200">' +
            '<span class="badge rx-dgda mt-1 d-none"></span></td>' +
            '<td><input type="text" name="items[' + index + '][dosage]" class="form-control form-control-sm" required maxlength="50"></td>' +
            '<td><input type="text" name="items[' + index + '][frequency]" class="form-control form-control-sm" required maxlength="50"></td>' +
            '<td><input type="number" name="items[' + index + '][duration_days]" class="form-control form-control-sm" min="1"></td>' +
            '<td><input type="number" name="items[' + index + '][quantity]" class="form-control form-control-sm" min="1" value="1" required></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger rx-remove" title="Remove">×</button> <button type="button" class="btn btn-sm btn-success rx-add-below" title="Add medicine below">+</button></td>' +
            '</tr>';
        index++;
        @endif
        var tr = tmp.firstChild;
        bindRow(tr);
        if (afterTr && afterTr.parentNode === body) {
            body.insertBefore(tr, afterTr.nextSibling);
        } else {
            body.appendChild(tr);
        }
        renumberRows();
        @if($isCreate)
        if (focus !== false) {
            var focusInput = tr.querySelector('.rx-med-name');
            if (focusInput) focusInput.focus();
        }
        @else
        var focusInput = tr.querySelector('.rx-med-name');
        if (focusInput) focusInput.focus();
        @endif
    }

    addBtn.addEventListener('click', function () { addRow(); });

    var existingRows = body.querySelectorAll('tr[data-rx-row]:not(.rx-parent-row)');
    if (existingRows.length) {
        existingRows.forEach(bindRow);
    }
    renumberRows();
    if (!existingRows.length && !{{ json_encode($isAmend) }}) {
        addRow();
    }

    @if($isCreate)
    window.rxResetMedicineRows = function () {
        body.innerHTML = '';
        addRow(null, false);
    };
    @endif

    document.getElementById('{{ $formId }}').addEventListener('submit', function (e) {
        renumberRows();
        if (body.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            alert('{{ $isCreate ? "Please add at least one medicine." : "Please keep at least one medicine." }}');
        }
    });

    @if($isCreate)
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
    @endif
})();
</script>
@endpush

@push('scripts')
<script>
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
        patientCard.innerHTML = '<div class="row mb-0 small">' +
            '<div class="col-sm-3">' + cell('MR Number', p.mr_number) + cell('Name', p.name, true) + '</div>' +
            '<div class="col-sm-3">' + cell('Phone', p.phone) + cell('Age / Gender', p.age_gender) + '</div>' +
            '<div class="col-sm-3">' + cell('Blood Group', p.blood_group) + payRow + '</div>' +
            '<div class="col-sm-3">' + cell('Serial', p.serial || 'N/A', true) + '</div>' +
            '</div>';
    }
    function renderVitals(v) {
        if (!v) {
            vitalsCard.innerHTML = '<p class="text-muted mb-0 small">No vitals recorded for this patient yet.</p>';
            return;
        }
        var rows = [
            ['Temp', v.temperature], ['BP', v.bp], ['Pulse', v.pulse], ['SpO2', v.spo2],
            ['RR', v.respiratory_rate], ['Sugar', v.blood_sugar], ['Wt', v.weight],
            ['Ht', v.height], ['BMI', v.bmi]
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

    @if($isCreate)
    var lastPatientId = patientSelect.value || '';
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

    patientSelect.addEventListener('change', function () {
        var next = patientSelect.value;
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
    @else
    patientSelect.addEventListener('change', function () {
        loadPatientInfo(patientSelect.value);
        applyPatientList();
    });
    @endif

    var doctorSelect = document.getElementById('doctor_id');
    if (doctorSelect) {
        doctorSelect.addEventListener('change', function () {
            if (patientSelect.value) loadPatientInfo(patientSelect.value);
            paintQueueSerials();
        });
    }
    window.rxLoadPatientInfo = loadPatientInfo;

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
})();

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
            .catch(function () { });
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
        try { combo.select(); } catch (e) {}
        renderList('');
    });
    combo.addEventListener('input', function () {
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

    var rxForm = document.getElementById('{{ $formId }}');
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
                @if($isCreate)
                loadPatientInfo(pidInput.value);
                @else
                if (window.rxLoadPatientInfo) window.rxLoadPatientInfo(pidInput.value);
                @endif
            })
                .catch(function () {
                    alert('Could not save vitals (HTTP ' + httpStatus + '). Please try again.');
                });
    }, true);

    document.querySelectorAll('textarea[data-autogrow]').forEach(function (ta) {
        function grow() {
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 600) + 'px';
        }
        ta.addEventListener('input', grow);
        grow();
    });

    var walkinBtn = document.getElementById('rx-walkin-fee-btn');
    if (walkinBtn) {
        walkinBtn.addEventListener('click', function () {
            var token = document.querySelector('meta[name="csrf-token"]');
            walkinBtn.disabled = true;
            walkinBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creating visit...';
            fetch(walkinBtn.getAttribute('data-url'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '', 'Accept': 'application/json' },
                body: JSON.stringify({ patient_id: walkinBtn.getAttribute('data-patient-id'), doctor_id: walkinBtn.getAttribute('data-doctor-id') })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.collect_url) {
                    @if($isCreate)
                    saveRxDraft();
                    @endif
                    var map = { 'data-fee-url': data.collect_url, 'data-fee-action': data.action, 'data-fee-patient': data.patient, 'data-fee-type': data.fee_type, 'data-fee-amount': data.amount, 'data-fee-redirect': walkinBtn.getAttribute('data-fee-redirect') || '' };
                    openFeeModal({ getAttribute: function (k) { return map[k] || ''; } });
                }
                walkinBtn.disabled = false;
                walkinBtn.innerHTML = '<i class="bi bi-cash-coin me-1"></i>Accept Fee';
            })
            .catch(function () {
                walkinBtn.disabled = false;
                walkinBtn.innerHTML = '<i class="bi bi-cash-coin me-1"></i>Accept Fee';
                alert('Could not create walk-in visit. Please try again.');
            });
        });
    }
</script>
@endpush

@push('scripts')
<script>
@if($isCreate)
(function () {
    function rxDraftKey() { return 'rxDraft_create'; }
    function saveRxDraft() {
        var draft = {};
        ['chief_complaints','examination_findings','diagnosis','investigations','advice','follow_up_date','doctor_id','notes'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) draft[id] = el.value;
        });
        var rows = [];
        document.querySelectorAll('#rx-items-body tr').forEach(function (tr) {
            var med = tr.querySelector('[name$="[medicine_id]"]');
            var qty = tr.querySelector('[name$="[quantity]"]');
            var freq = tr.querySelector('[name$="[frequency]"]');
            var dur = tr.querySelector('[name$="[duration]"]');
            var instr = tr.querySelector('[name$="[instructions]"]');
            if (med) rows.push({
                medicine_id: med.value,
                quantity: qty ? qty.value : '',
                frequency: freq ? freq.value : '',
                duration: dur ? dur.value : '',
                instructions: instr ? instr.value : ''
            });
        });
        draft.items = rows;
        try { localStorage.setItem(rxDraftKey(), JSON.stringify(draft)); } catch (e) {}
    }
    window.saveRxDraft = saveRxDraft;
    document.getElementById('rx-reset-draft').addEventListener('click', function () {
        if (!confirm('Clear all fields?')) return;
        ['chief_complaints','examination_findings','diagnosis','investigations','advice','follow_up_date','notes'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.value = ''; el.dispatchEvent(new Event('input')); }
        });
        var rows = document.querySelectorAll('#rx-items-body tr');
        for (var i = rows.length - 1; i > 0; i--) rows[i].remove();
        var last = document.querySelector('#rx-items-body tr');
        if (last) last.querySelectorAll('input, select, textarea').forEach(function (el) { el.value = ''; });
        renumberRows();
        try { localStorage.removeItem(rxDraftKey()); } catch (e) {}
    });
    function restoreRxDraft() {
        var raw;
        try { raw = localStorage.getItem(rxDraftKey()); } catch (e) { return; }
        if (!raw) return;
        try { var draft = JSON.parse(raw); } catch (e) { return; }
        localStorage.removeItem(rxDraftKey());
        ['chief_complaints','examination_findings','diagnosis','investigations','advice','follow_up_date','doctor_id','notes'].forEach(function (id) {
            if (draft[id] !== undefined) {
                var el = document.getElementById(id);
                if (el) { el.value = draft[id]; el.dispatchEvent(new Event('input')); }
            }
        });
        if (draft.doctor_id) {
            var sel = document.getElementById('doctor_id');
            if (sel) sel.value = draft.doctor_id;
        }
        if (draft.items && draft.items.length) {
            draft.items.forEach(function (item) {
                var addBtn = document.getElementById('rx-add-item');
                if (addBtn) addBtn.click();
                var lastRow = document.querySelector('#rx-items-body tr:last-child');
                if (!lastRow) return;
                var med = lastRow.querySelector('[name$="[medicine_id]"]');
                var qty = lastRow.querySelector('[name$="[quantity]"]');
                var freq = lastRow.querySelector('[name$="[frequency]"]');
                var dur = lastRow.querySelector('[name$="[duration]"]');
                var instr = lastRow.querySelector('[name$="[instructions]"]');
                if (med) med.value = item.medicine_id || '';
                if (qty) qty.value = item.quantity || '';
                if (freq) freq.value = item.frequency || '';
                if (dur) dur.value = item.duration || '';
                if (instr) instr.value = item.instructions || '';
            });
        }
    }
    restoreRxDraft();
    document.querySelectorAll('#rx-items-body, [data-rx-panel] textarea, #doctor_id, #follow_up_date').forEach(function (el) {
        el.addEventListener('input', saveRxDraft);
        el.addEventListener('change', saveRxDraft);
    });
    var observer = new MutationObserver(function () { saveRxDraft(); });
    observer.observe(document.getElementById('rx-items-body') || document.body, { childList: true, subtree: true });
})();
</script>
@endif
@endpush

@push('scripts')
<script>
(function () {
    var patientSelect = document.getElementById('patient_id');
    var dateInput = document.getElementById('prescription_date');
    var checkBase = '{{ route("medical.prescriptions.check-existing") }}';
    if (!patientSelect || !dateInput) return;

    var checkTimer = null;

    function checkExistingPrescription() {
        var patientId = patientSelect.value;
        var dateVal = dateInput.value;
        if (!patientId || !dateVal) return;

        clearTimeout(checkTimer);
        checkTimer = setTimeout(function () {
            var doctorSel = document.getElementById('doctor_id');
            var doctorId = doctorSel ? doctorSel.value : '';
            var url = checkBase + '?patient_id=' + encodeURIComponent(patientId) + '&date=' + encodeURIComponent(dateVal) + '&doctor_id=' + encodeURIComponent(doctorId);
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (!data || !data.exists) return;
                    showDuplicateModal(data);
                })
                .catch(function () {});
        }, 400);
    }

    patientSelect.addEventListener('change', checkExistingPrescription);
    dateInput.addEventListener('change', checkExistingPrescription);

    function showDuplicateModal(data) {
        document.getElementById('rx-dup-number').textContent = data.prescription_number || '—';
        document.getElementById('rx-dup-doctor').textContent = data.doctor_name || '—';
        document.getElementById('rx-dup-date').textContent = data.date || '—';
        document.getElementById('rx-dup-status').textContent = data.status || '—';
        document.getElementById('rx-dup-version').textContent = (data.version !== undefined && data.version !== null) ? data.version : '1';
        document.getElementById('rx-dup-items').textContent = data.items_count || '0';
        var amendedBadge = document.getElementById('rx-dup-amended-badge');
        if (amendedBadge) amendedBadge.style.display = data.is_amended ? '' : 'none';
        var editBtn = document.getElementById('rx-dup-edit-btn');
        if (editBtn) editBtn.href = data.edit_url;
        var actionText = document.getElementById('rx-dup-action-text');
        if (actionText) actionText.textContent = data.status === 'Finalized' ? 'Amend' : 'Edit';
        var modal = document.getElementById('rxDuplicateModal');
        if (modal && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    }

    if (dateInput.form) {
        dateInput.form.addEventListener('submit', function (e) {
            if (patientSelect.value && dateInput.value) {
                var doctorSel = document.getElementById('doctor_id');
                var doctorId = doctorSel ? doctorSel.value : '';
                var url = checkBase + '?patient_id=' + encodeURIComponent(patientSelect.value) + '&date=' + encodeURIComponent(dateInput.value) + '&doctor_id=' + encodeURIComponent(doctorId);
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, false);
                xhr.setRequestHeader('Accept', 'application/json');
                try { xhr.send(); } catch (ex) {}
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.exists) {
                            e.preventDefault();
                            showDuplicateModal(data);
                        }
                    } catch (ex) {}
                }
            }
        });
    }
})();
</script>
@endpush

@if($isAmend)
@push('scripts')
<style>
.rx-parent-row { background-color: rgba(var(--bs-warning-rgb), .08); }
.rx-parent-row .rx-locked-text { color: var(--bs-secondary); }
.rx-parent-reason td { padding-top: 0 !important; padding-bottom: .5rem !important; }
</style>
<script>
(function(){
    document.querySelectorAll('input[name$="[action]"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var match = this.name.match(/parent_items\[(\d+)\]/);
            if (!match) return;
            var itemId = match[1];
            var reasonRow = document.querySelector('tr.rx-parent-reason[data-parent-reason="' + itemId + '"]');
            if (!reasonRow) return;
            var input = reasonRow.querySelector('input');
            if (this.value === 'discontinue') {
                reasonRow.classList.remove('d-none');
                input.required = true;
            } else {
                reasonRow.classList.add('d-none');
                input.required = false;
                input.value = '';
            }
        });
    });
})();
</script>
@endpush
@endif

@push('scripts')
<script>
(function () {
    var quickBtn = document.getElementById('rx-quick-add-medicine');
    var quickModal = document.getElementById('quickAddMedicineModal');
    var quickForm = document.getElementById('quick-add-medicine-form');
    var quickSubmit = document.getElementById('qmed_submit_btn');

    if (quickBtn && quickModal) {
        quickBtn.addEventListener('click', function () {
            if (window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(quickModal).show();
        });
    }

    if (!quickForm) return;

    /* Track which medicine-name input is active so we can fill it after save */
    var activeMedInput = null;
    document.addEventListener('focusin', function (e) {
        if (e.target.classList && e.target.classList.contains('rx-med-name')) {
            activeMedInput = e.target;
        }
    });

    quickForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        quickSubmit.disabled = true;
        quickSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';

        fetch(@json(route('medical.pharmacy.medicines.quick-store')), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(quickForm)
        })
        .then(function (r) { return r.ok ? r.json() : r.json().then(function (b) { throw b; }); })
        .then(function (data) {
            /* Add to datalist */
            var dl = document.getElementById('rx-medicine-list');
            if (dl) {
                var opt = document.createElement('option');
                opt.setAttribute('data-id', data.id);
                opt.setAttribute('data-dgda', '');
                opt.value = data.display_name;
                opt.textContent = data.display_name;
                dl.appendChild(opt);
            }

            /* Add to JS catalog map so bindRow picks it up */
            if (window.rxCatalog) {
                window.rxCatalog[data.display_name] = { id: String(data.id), dgda: '' };
            }

            /* Fill the active row or the last row */
            var targetInput = activeMedInput;
            if (!targetInput || !targetInput.closest('table')) {
                var rows = document.querySelectorAll('#rx-items-body tr[data-rx-row]:not(.rx-parent-row)');
                if (rows.length) targetInput = rows[rows.length - 1].querySelector('.rx-med-name');
            }
            if (targetInput) {
                targetInput.value = data.display_name;
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                targetInput.focus();
            }

            /* Reset form & close */
            quickForm.reset();
            if (window.bootstrap) {
                var inst = window.bootstrap.Modal.getInstance(quickModal);
                if (inst) inst.hide();
            }
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Could not add medicine.';
            if (err && err.errors) {
                var first = Object.values(err.errors)[0];
                if (first && first[0]) msg = first[0];
            }
            alert(msg);
        })
        .finally(function () {
            quickSubmit.disabled = false;
            quickSubmit.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add & Select';
        });
    });
})();
</script>
@endpush

@include('medical.appointments._fee_modal')
