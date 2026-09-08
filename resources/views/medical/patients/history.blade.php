@extends('layouts.institute')

@section('title', 'Patient History — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Medical History — {{ $patient->full_name }} ({{ $patient->mr_number }})</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.patients.show', $patient) }}">
            <i class="bi bi-arrow-left me-1"></i>Back to Profile
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">All Appointments ({{ $patient->appointments->count() }})</h6></div>
    <div class="card-body">
        @if($patient->appointments->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Serial</th><th>Status</th><th>Complaints</th></tr></thead>
                    <tbody>
                        @foreach($patient->appointments as $appointment)
                        <tr>
                            <td><x-tdate :value="$appointment->appointment_date" fallback="d M Y" /></td>
                            <td>{{ $appointment->appointment_time ? \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') : 'N/A' }}</td>
                            <td>{{ $appointment->doctor->name ?? 'N/A' }}</td>
                            <td>#{{ $appointment->serial_number }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($appointment->complaints, 40) ?? '—' }}</td>
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
    <div class="card-header"><h6 class="mb-0">All Admissions ({{ $patient->admissions->count() }})</h6></div>
    <div class="card-body">
        @if($patient->admissions->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Admission</th><th>Discharge</th><th>Doctor</th><th>Status</th><th>Diagnosis</th></tr></thead>
                    <tbody>
                        @foreach($patient->admissions as $admission)
                        <tr>
                            <td><x-tdate :value="$admission->admission_date" fallback="d M Y" /></td>
                            <td><x-tdate :value="$admission->discharge_date" fallback="d M Y" empty="—" /></td>
                            <td>{{ $admission->admittingDoctor->name ?? 'N/A' }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($admission->status) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($admission->primary_diagnosis, 40) ?? '—' }}</td>
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
    <div class="card-header"><h6 class="mb-0">All Prescriptions ({{ $patient->prescriptions->count() }})</h6></div>
    <div class="card-body">
        @if($patient->prescriptions->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Number</th><th>Date</th><th>Doctor</th><th>Diagnosis</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($patient->prescriptions as $prescription)
                        <tr>
                            <td>{{ $prescription->prescription_number }}</td>
                            <td><x-tdate :value="$prescription->prescription_date" fallback="d M Y" /></td>
                            <td>{{ $prescription->doctor->name ?? 'N/A' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($prescription->diagnosis, 40) ?? '—' }}</td>
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
    <div class="card-header"><h6 class="mb-0">All Lab Orders ({{ $patient->labOrders->count() }})</h6></div>
    <div class="card-body">
        @if($patient->labOrders->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Order No</th><th>Date</th><th>Priority</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($patient->labOrders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td><x-tdate :value="$order->order_date" fallback="d M Y" /></td>
                            <td>{{ ucfirst($order->priority) }}</td>
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
@endsection
