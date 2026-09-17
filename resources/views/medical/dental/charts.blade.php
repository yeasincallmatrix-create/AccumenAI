@extends('layouts.institute')

@section('title', 'Dental Charts — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-medical"></i> Dental Charts</h4>
    </div>

    <p class="text-muted small">Select a patient to open their dental chart.</p>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search MR number / name / phone..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Search</button>
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
                            <th>MR Number</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($patients as $patient)
                            <tr>
                                <td><strong>{{ $patient->mr_number ?? '—' }}</strong></td>
                                <td>{{ $patient->full_name }}</td>
                                <td>{{ $patient->phone ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('medical.dental.chart.show', $patient) }}" class="btn btn-outline-primary btn-sm">Open Chart</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No patients found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $patients->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
