@extends('layouts.institute')

@section('title', 'Encounter — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $encounter->encounter_number }}
            <span class="badge bg-{{ $encounter->status === 'completed' ? 'success' : ($encounter->status === 'cancelled' ? 'secondary' : 'primary') }}">
                {{ ucfirst(str_replace('_', ' ', $encounter->status)) }}
            </span>
        </h4>
    </div>
    <div class="page-header-actions">
        @if($encounter->status === 'open')
            <form action="{{ route('medical.encounters.start', $encounter) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-play me-1"></i>Start Consultation
                </button>
            </form>
            <form action="{{ route('medical.encounters.cancel', $encounter) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Cancel this encounter? It will be retained as cancelled.')">
                @csrf
                <button type="submit" class="btn btn-secondary">Cancel</button>
            </form>
        @elseif($encounter->status === 'in_progress')
            <a class="btn btn-warning" href="{{ route('medical.encounters.edit', $encounter) }}">
                <i class="bi bi-pencil me-1"></i>Document
            </a>
            <form action="{{ route('medical.encounters.complete', $encounter) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Complete this encounter? Completed encounters become read-only.')">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-all me-1"></i>Complete
                </button>
            </form>
            <form action="{{ route('medical.encounters.cancel', $encounter) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Cancel this encounter? It will be retained as cancelled.')">
                @csrf
                <button type="submit" class="btn btn-secondary">Cancel</button>
            </form>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.encounters.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Encounter Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    @if($encounter->patient)
                        <a href="{{ route('medical.patients.show', $encounter->patient) }}">{{ $encounter->patient->full_name }}</a>
                        <span class="text-muted">({{ $encounter->patient->mr_number }})</span>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Clinician:</strong> {{ $encounter->doctor->name ?? 'N/A' }}</p>
                <p><strong>Type:</strong> {{ $encounter->encounter_type }}</p>
                <p><strong>Started:</strong> <x-tdate :value="$encounter->started_at" fallback="d M Y, h:i A" /></p>
                <p class="mb-0"><strong>Completed:</strong> <x-tdate :value="$encounter->completed_at" fallback="d M Y, h:i A" /></p>
                @if($encounter->appointment)
                    <hr>
                    <p class="mb-0"><strong>Appointment:</strong>
                        <a href="{{ route('medical.appointments.show', $encounter->appointment) }}">#{{ $encounter->appointment->id }}</a>
                        ({{ $encounter->appointment->status }})
                    </p>
                @endif
                @if($encounter->admission)
                    <hr>
                    <p class="mb-0"><strong>Admission:</strong>
                        <a href="{{ route('medical.admissions.show', $encounter->admission) }}">#{{ $encounter->admission->id }}</a>
                        ({{ $encounter->admission->status }})
                    </p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Clinical Notes</h6></div>
            <div class="card-body">
                <p><strong>Complaint:</strong> {{ $encounter->chief_complaint ?? '—' }}</p>
                <p><strong>History:</strong> {{ $encounter->history_of_present_illness ?? '—' }}</p>
                <p><strong>Examination:</strong> {{ $encounter->examination_notes ?? '—' }}</p>
                <p><strong>Assessment:</strong> {{ $encounter->assessment_notes ?? '—' }}</p>
                <p><strong>Plan:</strong> {{ $encounter->plan_notes ?? '—' }}</p>
                <p><strong>Diagnosis:</strong> {{ $encounter->diagnosis_text ?? '—' }}
                    @if($encounter->diagnosis_code)
                        <code>{{ $encounter->diagnosis_code }}</code>
                    @endif
                </p>
                <p class="mb-0"><strong>Follow-up:</strong> {{ $encounter->follow_up_notes ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Diagnoses ({{ $encounter->diagnoses->where('status', 'active')->count() }})</h6></div>
    <div class="card-body">
        @php $activeDiagnoses = $encounter->diagnoses->where('status', 'active'); @endphp
        @if($activeDiagnoses->count() > 0)
            <ul class="mb-3">
                @foreach($activeDiagnoses as $diagnosis)
                    <li>
                        <strong>{{ $diagnosis->label }}</strong>
                        <span class="badge bg-info">{{ ucfirst($diagnosis->diagnosis_type) }}</span>
                        @if($diagnosis->source === 'free_text' || $diagnosis->mapping_status === 'unresolved')
                            <span class="badge bg-secondary" title="Clinician-entered text; not mapped to an authoritative terminology">unresolved</span>
                        @else
                            <span class="badge bg-success" title="{{ $diagnosis->code_system }} {{ $diagnosis->code }}">{{ $diagnosis->code_system }} {{ $diagnosis->code }}</span>
                        @endif
                        <form action="{{ route('medical.diagnoses.remove', $diagnosis) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Remove this diagnosis? It stays preserved in history.')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted">No structured diagnoses recorded.</p>
        @endif
        @if($encounter->diagnoses->where('status', 'removed')->count() > 0)
            <p class="text-muted small mb-3">Removed (preserved): {{ $encounter->diagnoses->where('status', 'removed')->pluck('label')->join(', ') }}</p>
        @endif
        <form action="{{ route('medical.encounters.diagnoses.store', $encounter) }}" method="POST" class="row g-2">
            @csrf
            <div class="col-md-5">
                <input type="text" name="label" maxlength="255" required
                       class="form-control @error('label') is-invalid @enderror"
                       placeholder="Diagnosis (clinician-entered)" value="{{ old('label') }}">
            </div>
            <div class="col-md-2">
                <select name="diagnosis_type" class="form-select" required>
                    <option value="primary" {{ old('diagnosis_type') === 'primary' ? 'selected' : '' }}>Primary</option>
                    <option value="secondary" {{ old('diagnosis_type', 'secondary') === 'secondary' ? 'selected' : '' }}>Secondary</option>
                    <option value="differential" {{ old('diagnosis_type') === 'differential' ? 'selected' : '' }}>Differential</option>
                    <option value="symptom" {{ old('diagnosis_type') === 'symptom' ? 'selected' : '' }}>Symptom</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="reason" maxlength="2000" class="form-control"
                       placeholder="Amend reason (required — closed encounter)" value="{{ old('reason') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i>Add
                </button>
            </div>
            @error('label')<div class="col-12"><div class="invalid-feedback d-block">{{ $message }}</div></div>@enderror
            @error('diagnosis_type')<div class="col-12"><div class="invalid-feedback d-block">{{ $message }}</div></div>@enderror
        </form>
        <p class="text-muted small mt-2 mb-0">Diagnoses are clinician-entered documentation. Unresolved labels are never treated as coded terminology.</p>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Problems &amp; Follow-up</h6></div>
    <div class="card-body">
        <h6>Active patient problems</h6>
        @if(isset($patientProblems) && $patientProblems->count() > 0)
            <ul class="mb-3">
                @foreach($patientProblems as $problem)
                    <li>
                        <strong>{{ $problem->label }}</strong>
                        <span class="badge bg-info">{{ ucfirst($problem->problem_type) }}</span>
                        @if($encounter->problems->contains('id', $problem->id))
                            <span class="badge bg-primary">linked here</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted">No active longitudinal problems.</p>
        @endif
        @if($encounter->followUps->count() > 0)
            <h6>Follow-ups from this encounter</h6>
            <ul class="mb-3">
                @foreach($encounter->followUps as $followup)
                    <li>
                        <strong><x-tdate :value="$followup->planned_date" fallback="d M Y" /></strong>
                        <span class="badge bg-secondary">{{ ucfirst($followup->status) }}</span>
                        <span class="text-muted">— {{ \Illuminate\Support\Str::limit($followup->reason, 60) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="row g-2">
            <div class="col-md-6">
                <form action="{{ route('medical.patients.problems.store', $encounter->patient_id) }}" method="POST" class="row g-2">
                    @csrf
                    <input type="hidden" name="encounter_id" value="{{ $encounter->id }}">
                    <div class="col-7">
                        <input type="text" name="label" maxlength="255" required class="form-control form-control-sm" placeholder="Record problem from this visit">
                    </div>
                    <div class="col-3">
                        <select name="problem_type" class="form-select form-select-sm" required>
                            <option value="chronic">Chronic</option>
                            <option value="acute">Acute</option>
                            <option value="condition">Condition</option>
                            <option value="symptom">Symptom</option>
                            <option value="other" selected>Other</option>
                        </select>
                    </div>
                    <div class="col-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
                    </div>
                </form>
            </div>
            <div class="col-md-6">
                <form action="{{ route('medical.patients.followups.store', $encounter->patient_id) }}" method="POST" class="row g-2">
                    @csrf
                    <input type="hidden" name="encounter_id" value="{{ $encounter->id }}">
                    <div class="col-4">
                        <input type="date" name="planned_date" required class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <input type="text" name="reason" maxlength="2000" required class="form-control form-control-sm" placeholder="Follow-up reason">
                    </div>
                    <div class="col-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Plan</button>
                    </div>
                </form>
            </div>
        </div>
        <p class="text-muted small mt-2 mb-0">Problems are recorded explicitly — diagnoses are never converted automatically.</p>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Linked Orders ({{ $encounter->prescriptions->count() + $encounter->labOrders->count() }})</h6></div>
    <div class="card-body">
        @if($encounter->prescriptions->count() > 0)
            <h6>Prescriptions</h6>
            <ul class="mb-3">
                @foreach($encounter->prescriptions as $prescription)
                    <li>
                        <a href="{{ route('medical.prescriptions.show', $prescription) }}">{{ $prescription->prescription_number }}</a>
                        <span class="text-muted">— {{ $prescription->statusLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if($encounter->labOrders->count() > 0)
            <h6>Lab Orders</h6>
            <ul class="mb-3">
                @foreach($encounter->labOrders as $order)
                    <li>
                        <a href="{{ route('medical.lab.orders.show', $order) }}">{{ $order->order_number }}</a>
                        <span class="text-muted">— {{ $order->status }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if($vitals->count() > 0)
            <h6>Vitals (via admission)</h6>
            <ul class="mb-0">
                @foreach($vitals as $vital)
                    <li>
                        <x-tdate :value="$vital->recorded_at" fallback="d M Y, h:i A" />
                        <span class="text-muted">— Temp {{ $vital->temperature ?? '—' }}, BP {{ $vital->blood_pressure ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if($encounter->prescriptions->count() === 0 && $encounter->labOrders->count() === 0 && $vitals->count() === 0)
            <p class="text-muted mb-0">No linked orders yet.</p>
        @endif
    </div>
</div>

@if($encounter->status === 'completed')
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Amend Completed Record (audited)</h6></div>
    <div class="card-body">
        <form action="{{ route('medical.encounters.amend', $encounter) }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                <input type="text" id="reason" name="reason" maxlength="2000" required
                       class="form-control @error('reason') is-invalid @enderror"
                       placeholder="Why is this correction needed?">
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="amend_assessment">Assessment</label>
                        <textarea id="amend_assessment" name="assessment_notes" rows="2" class="form-control">{{ $encounter->assessment_notes }}</textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="amend_plan">Plan</label>
                        <textarea id="amend_plan" name="plan_notes" rows="2" class="form-control">{{ $encounter->plan_notes }}</textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-journal-check me-1"></i>Record Amendment
            </button>
        </form>
    </div>
</div>
@endif

@if(isset($eventTimeline) && $eventTimeline->count() > 0)
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Encounter Timeline</h6></div>
    <div class="card-body">
        <ul class="mb-0">
            @foreach($eventTimeline as $event)
                <li>
                    <x-tdate :value="$event->created_at" fallback="d M Y, h:i A" />
                    <span>— {{ str_replace('_', ' ', $event->action) }}</span>
                    @if($event->reason)
                        <span class="text-muted">({{ \Illuminate\Support\Str::limit($event->reason, 80) }})</span>
                    @endif
                    <span class="text-muted small">· {{ $event->actor_name ?? $event->user_type }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if($timeline->count() > 0)
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Patient Timeline (recent encounters)</h6></div>
    <div class="card-body">
        <ul class="mb-0">
            @foreach($timeline as $past)
                <li>
                    <a href="{{ route('medical.encounters.show', $past) }}">{{ $past->encounter_number }}</a>
                    <span class="text-muted">— {{ $past->encounter_type }} · {{ $past->status }} ·
                    <x-tdate :value="$past->started_at" fallback="d M Y" /></span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
@endsection
