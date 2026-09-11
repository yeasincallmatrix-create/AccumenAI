@extends('layouts.institute')

@section('title', 'Patient Profile — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Patient Profile</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.patients.edit', $patient) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.patients.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row align-items-stretch">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-person-circle display-1 text-secondary"></i>
                </div>
                <h4>{{ $patient->full_name }}</h4>
                <p class="text-muted">MR: <strong>{{ $patient->mr_number }}</strong></p>
                <hr>
                <div class="text-start">
                    <p><strong>Date of Birth:</strong> <x-tdate :value="$patient->date_of_birth" fallback="d M Y" empty="N/A" /></p>
                    <p><strong>Age:</strong> {{ $patient->age !== null ? $patient->age.' years' : 'N/A' }}@if($patient->age_category) <span class="badge bg-secondary">{{ $patient->age_category }}</span>@endif</p>
                    <p><strong>Gender:</strong> {{ ucfirst($patient->gender) }}</p>
                    <p><strong>Blood Group:</strong> @if($patient->blood_group === 'UKN') UKN (Unknown) @else {{ $patient->blood_group ?? 'N/A' }} @endif</p>
                    <p><strong>Phone:</strong> {{ $patient->phone }}</p>
                    <p><strong>Email:</strong> {{ $patient->email ?? 'N/A' }}</p>
                    <p class="mb-0"><strong>Status:</strong>
                        @if($patient->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Quick Actions</h6></div>
            <div class="card-body">
                <a href="{{ route('medical.appointments.create', ['patient_id' => $patient->id]) }}"
                   class="btn btn-primary btn-sm w-100 mb-2">
                    <i class="bi bi-calendar-plus me-1"></i>Book Appointment
                </a>
                <a href="{{ route('medical.patients.history', $patient) }}"
                   class="btn btn-info btn-sm w-100">
                    <i class="bi bi-clock-history me-1"></i>Full History
                </a>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-people me-1"></i>Family Members</h6></div>
            <div class="card-body small">
                @if($familyPrimary)
                    <p class="mb-1 text-muted">Primary contact</p>
                    <p><a href="{{ route('medical.patients.show', $familyPrimary) }}">{{ $familyPrimary->full_name }}</a>
                    <span class="text-muted">({{ $familyPrimary->mr_number }})</span></p>
                @endif
                @if($patient->relation_to_primary)
                    <p class="mb-1"><strong>Relation:</strong> {{ $patient->relation_to_primary }}</p>
                @endif
                @forelse($familyDependents as $dependent)
                    @if($loop->first)<p class="mb-1 text-muted">Dependents</p>@endif
                    <p class="mb-1"><a href="{{ route('medical.patients.show', $dependent) }}">{{ $dependent->family_label }}</a>
                    <span class="text-muted">({{ $dependent->mr_number }})</span></p>
                @empty
                    @if(! $familyPrimary)<p class="text-muted mb-0">No linked family members.</p>@endif
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-8 d-flex">
        <div class="card flex-fill w-100 d-flex flex-column">
            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto px-2 pt-2" id="patientHistoryTabs" role="tablist" style="white-space:nowrap;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-appointments" data-bs-toggle="tab"
                            data-bs-target="#pane-appointments" type="button" role="tab"
                            aria-controls="pane-appointments" aria-selected="true">
                            <i class="bi bi-calendar-event me-1"></i>Appointments
                            <span class="badge bg-primary ms-1">{{ $patient->appointments->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-admissions" data-bs-toggle="tab"
                            data-bs-target="#pane-admissions" type="button" role="tab"
                            aria-controls="pane-admissions" aria-selected="false">
                            <i class="bi bi-hospital me-1"></i>Admissions
                            <span class="badge bg-primary ms-1">{{ $patient->admissions->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-prescriptions" data-bs-toggle="tab"
                            data-bs-target="#pane-prescriptions" type="button" role="tab"
                            aria-controls="pane-prescriptions" aria-selected="false">
                            <i class="bi bi-file-medical me-1"></i>Prescriptions
                            <span class="badge bg-primary ms-1">{{ $patient->prescriptions->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-laborders" data-bs-toggle="tab"
                            data-bs-target="#pane-laborders" type="button" role="tab"
                            aria-controls="pane-laborders" aria-selected="false">
                            <i class="bi bi-flask me-1"></i>Lab Orders
                            <span class="badge bg-primary ms-1">{{ $patient->labOrders->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-invoices" data-bs-toggle="tab"
                            data-bs-target="#pane-invoices" type="button" role="tab"
                            aria-controls="pane-invoices" aria-selected="false">
                            <i class="bi bi-receipt me-1"></i>Invoices
                            <span class="badge bg-primary ms-1">{{ $patient->invoices->count() }}</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body flex-fill d-flex flex-column">
                <div class="tab-content flex-fill" id="patientHistoryTabsContent">
                    {{-- Appointments pane --}}
                    <div class="tab-pane fade show active" id="pane-appointments" role="tabpanel" aria-labelledby="tab-appointments" tabindex="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Recent Appointments</h6>
                            <a href="{{ route('medical.appointments.create', ['patient_id' => $patient->id]) }}"
                               class="btn btn-sm btn-primary">+ Add</a>
                        </div>
                @if($patient->appointments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Date</th><th>Doctor</th><th>Serial</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->appointments->take(5) as $appointment)
                                <tr>
                                    <td><x-tdate :value="$appointment->appointment_date" fallback="d M Y" /></td>
                                    <td>{{ $appointment->doctor->name ?? 'N/A' }}</td>
                                    <td>#{{ $appointment->serial_number }}</td>
                                    <td>
                                        <span class="badge bg-{{ $appointment->status === 'completed' ? 'success' : ($appointment->status === 'scheduled' ? 'primary' : 'secondary') }}">
                                            {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No appointments found.</p>
                @endif
                    </div>

                    {{-- Admissions pane --}}
                    <div class="tab-pane fade" id="pane-admissions" role="tabpanel" aria-labelledby="tab-admissions" tabindex="0">
                        <h6 class="mb-3">Admission History</h6>
                @if($patient->admissions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Admission Date</th><th>Doctor</th><th>Status</th><th>Length of Stay</th></tr></thead>
                            <tbody>
                                @foreach($patient->admissions->take(5) as $admission)
                                <tr>
                                    <td><x-tdate :value="$admission->admission_date" fallback="d M Y" /></td>
                                    <td>{{ $admission->admittingDoctor->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $admission->status === 'active' ? 'danger' : 'success' }}">
                                            {{ ucfirst($admission->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $admission->length_of_stay }} days</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No admissions found.</p>
                @endif
                    </div>

                    {{-- Prescriptions pane --}}
                    <div class="tab-pane fade" id="pane-prescriptions" role="tabpanel" aria-labelledby="tab-prescriptions" tabindex="0">
                        <h6 class="mb-3">Recent Prescriptions</h6>
                @if($patient->prescriptions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Date</th><th>Doctor</th><th>Diagnosis</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->prescriptions->take(5) as $prescription)
                                <tr>
                                    <td><x-tdate :value="$prescription->prescription_date" fallback="d M Y" /></td>
                                    <td>{{ $prescription->doctor->name ?? 'N/A' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($prescription->diagnosis, 30) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $prescription->is_finalized ? 'success' : 'warning' }}">
                                            {{ $prescription->is_finalized ? 'Finalized' : 'Draft' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No prescriptions found.</p>
                @endif
                    </div>

                    {{-- Lab Orders pane --}}
                    <div class="tab-pane fade" id="pane-laborders" role="tabpanel" aria-labelledby="tab-laborders" tabindex="0">
                        <h6 class="mb-3">Recent Lab Orders</h6>
                @if($patient->labOrders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Order No</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->labOrders->take(5) as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td><x-tdate :value="$order->order_date" fallback="d M Y" /></td>
                                    <td><span class="badge bg-secondary">{{ ucfirst($order->status) }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No lab orders found.</p>
                @endif
                    </div>

                    {{-- Invoices pane --}}
                    <div class="tab-pane fade" id="pane-invoices" role="tabpanel" aria-labelledby="tab-invoices" tabindex="0">
                        <h6 class="mb-3">Recent Invoices</h6>
                @if($patient->invoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Invoice No</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->invoices->take(5) as $invoice)
                                <tr>
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td><x-tdate :value="$invoice->invoice_date" fallback="d M Y" /></td>
                                    <td>{{ $invoice->total }}</td>
                                    <td><span class="badge bg-secondary">{{ ucfirst($invoice->status) }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No invoices found.</p>
                @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Clinical Problems ({{ $patient->problems->count() }})</h6></div>
            <div class="card-body">
                @if($patient->problems->count() > 0)
                    <ul class="mb-3">
                        @foreach($patient->problems as $problem)
                            <li>
                                <strong>{{ $problem->label }}</strong>
                                <span class="badge bg-{{ $problem->status === 'active' ? 'danger' : ($problem->status === 'resolved' ? 'success' : 'secondary') }}">{{ ucfirst($problem->status) }}</span>
                                <span class="badge bg-info">{{ ucfirst($problem->problem_type) }}</span>
                                @if($problem->status === 'active')
                                    <form action="{{ route('medical.problems.inactivate', $problem) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-link p-0">inactivate</button>
                                    </form>
                                    <form action="{{ route('medical.problems.resolve', $problem) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Mark this problem resolved? The record is preserved.')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-link p-0">resolve</button>
                                    </form>
                                @elseif($problem->status === 'inactive')
                                    <form action="{{ route('medical.problems.reactivate', $problem) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-link p-0">reactivate</button>
                                    </form>
                                    <form action="{{ route('medical.problems.resolve', $problem) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Mark this problem resolved? The record is preserved.')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-link p-0">resolve</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted">No longitudinal problems recorded.</p>
                @endif
                <form action="{{ route('medical.patients.problems.store', $patient) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-md-6">
                        <input type="text" name="label" maxlength="255" required class="form-control" placeholder="Problem (clinician-entered)">
                    </div>
                    <div class="col-md-4">
                        <select name="problem_type" class="form-select" required>
                            <option value="chronic">Chronic</option>
                            <option value="acute">Acute</option>
                            <option value="historical">Historical</option>
                            <option value="symptom">Symptom</option>
                            <option value="condition">Condition</option>
                            <option value="other" selected>Other</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Follow-up Plan ({{ $patient->followUps->count() }})</h6></div>
            <div class="card-body">
                @if($patient->followUps->count() > 0)
                    <ul class="mb-3">
                        @foreach($patient->followUps as $followup)
                            <li>
                                <strong><x-tdate :value="$followup->planned_date" fallback="d M Y" /></strong>
                                <span class="badge bg-{{ $followup->status === 'planned' ? 'primary' : ($followup->status === 'completed' ? 'success' : 'secondary') }}">{{ ucfirst($followup->status) }}</span>
                                <span class="text-muted">— {{ \Illuminate\Support\Str::limit($followup->reason, 60) }}</span>
                                @if($followup->status === 'planned')
                                    <form action="{{ route('medical.followups.complete', $followup) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-link p-0">complete</button>
                                    </form>
                                    <form action="{{ route('medical.followups.cancel', $followup) }}" method="POST" class="d-inline"
                                          onsubmit="var r = prompt('Cancellation reason (required):'); if (r === null || r.trim() === '') { return false; } this.querySelector('input[name=reason]').value = r; return true;">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">cancel</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted">No follow-up planned.</p>
                @endif
                <form action="{{ route('medical.patients.followups.store', $patient) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-md-4">
                        <input type="date" name="planned_date" required class="form-control">
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="reason" maxlength="2000" required class="form-control" placeholder="Why follow-up is needed">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Plan</button>
                    </div>
                </form>
                <p class="text-muted small mt-2 mb-0">Planning record only — it never books an appointment.</p>
            </div>
        </div>
    </div>
</div>
@endsection
