@extends('layouts.institute')

@section('title', 'Vaccination Records — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-medical"></i> Vaccination Records</h4>
        @if($user && $user->hasPermission('medical.vaccination.administer'))
            <a href="{{ route('medical.vaccination.records.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Record Vaccination
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number/patient..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <input type="date" name="administered_date" class="form-control form-control-sm" value="{{ request('administered_date') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Record #</th>
                            <th>Patient</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Date</th>
                            <th>Administered By</th>
                            <th>Certificate</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $r)
                            <tr>
                                <td><strong>{{ $r->record_number }}</strong></td>
                                <td>{{ $r->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $r->vaccineMaster->name ?? 'N/A' }}</td>
                                <td>{{ $r->dose_number }}</td>
                                <td>{{ $r->administered_date->format('d M Y') }}</td>
                                <td>{{ $r->administeredBy->name ?? 'N/A' }}</td>
                                <td>{{ $r->certificate_number ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('medical.vaccination.records.show', $r) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $records->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
