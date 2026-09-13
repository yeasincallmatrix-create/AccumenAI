@extends('layouts.institute')

@section('title', 'Prescriptions — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Prescriptions</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.prescriptions.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Write Prescription
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="patient_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" @selected((string) request('patient_id') === (string) $patient->id)>
                                {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="finalized" @selected(request('status') === 'finalized')>Finalized</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <x-tdate-input name="from_date" :value="request('from_date')" class="form-control" onchange="if(window.tdateReady&&window.tdateReady('from_date'))this.form.submit()" />
                </div>
                <div class="col-md-2">
                    <x-tdate-input name="to_date" :value="request('to_date')" class="form-control" onchange="if(window.tdateReady&&window.tdateReady('to_date'))this.form.submit()" />
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.prescriptions.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Rx Number</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prescriptions as $prescription)
                    <tr>
                        <td><strong>{{ clinical_no($prescription->prescription_number) }}</strong></td>
                        <td>{{ $prescription->patient->full_name ?? 'N/A' }}</td>
                        <td>{{ $prescription->doctor->name ?? 'N/A' }}</td>
                        <td><x-tdate :value="$prescription->prescription_date" fallback="d M Y" /></td>
                        <td>{{ $prescription->pending_items_count + $prescription->dispensed_items_count }}</td>
                        <td>
                            @if($prescription->is_finalized)
                                <span class="badge bg-success">Finalized</span>
                            @else
                                <span class="badge bg-warning text-dark">Draft</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.prescriptions.show', $prescription) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if(!$prescription->is_finalized)
                                    <a href="{{ route('medical.prescriptions.edit', $prescription) }}" class="btn btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-file-earmark-medical fs-2 d-block mb-2"></i>
                            No prescriptions found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $prescriptions->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
