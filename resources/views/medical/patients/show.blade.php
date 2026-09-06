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

<div class="row">
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
                    <p><strong>Date of Birth:</strong> {{ $patient->date_of_birth?->format('d M Y') ?? 'N/A' }}</p>
                    <p><strong>Age:</strong> {{ $patient->age !== null ? $patient->age.' years' : 'N/A' }}</p>
                    <p><strong>Gender:</strong> {{ ucfirst($patient->gender) }}</p>
                    <p><strong>Blood Group:</strong> {{ $patient->blood_group ?? 'N/A' }}</p>
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
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Appointments</h6>
                <a href="{{ route('medical.appointments.create', ['patient_id' => $patient->id]) }}"
                   class="btn btn-sm btn-primary">+ Add</a>
            </div>
            <div class="card-body">
                @if($patient->appointments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Date</th><th>Doctor</th><th>Serial</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->appointments->take(5) as $appointment)
                                <tr>
                                    <td>{{ $appointment->appointment_date?->format('d M Y') }}</td>
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
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Admission History</h6></div>
            <div class="card-body">
                @if($patient->admissions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Admission Date</th><th>Doctor</th><th>Status</th><th>Length of Stay</th></tr></thead>
                            <tbody>
                                @foreach($patient->admissions->take(5) as $admission)
                                <tr>
                                    <td>{{ $admission->admission_date?->format('d M Y') }}</td>
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
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Recent Prescriptions</h6></div>
            <div class="card-body">
                @if($patient->prescriptions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Date</th><th>Doctor</th><th>Diagnosis</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->prescriptions->take(5) as $prescription)
                                <tr>
                                    <td>{{ $prescription->prescription_date?->format('d M Y') }}</td>
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
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Recent Lab Orders</h6></div>
            <div class="card-body">
                @if($patient->labOrders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Order No</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->labOrders->take(5) as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->order_date?->format('d M Y') }}</td>
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
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Recent Invoices</h6></div>
            <div class="card-body">
                @if($patient->invoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Invoice No</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($patient->invoices->take(5) as $invoice)
                                <tr>
                                    <td>{{ $invoice->invoice_number }}</td>
                                    <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
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
@endsection
