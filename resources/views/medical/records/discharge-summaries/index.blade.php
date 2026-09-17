@extends('layouts.institute')

@section('title', 'Discharge Summaries — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-box-arrow-right"></i> Discharge Summaries</h4>
        @if($user && $user->hasPermission('medical.records.discharge.create'))
            <a href="{{ route('medical.records.discharge-summaries.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Summary
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number/diagnosis/patient..." value="{{ request('search') }}">
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
                            <th>Summary #</th>
                            <th>Patient</th>
                            <th>Admission</th>
                            <th>Discharge</th>
                            <th>LOS</th>
                            <th>Condition</th>
                            <th>Follow-up</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summaries as $s)
                            <tr>
                                <td><strong>{{ $s->summary_number }}</strong></td>
                                <td>{{ $s->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $s->admission_date->format('d M Y') }}</td>
                                <td>{{ $s->discharge_date->format('d M Y') }}</td>
                                <td>{{ $s->length_of_stay_days }} days</td>
                                <td><span class="badge bg-{{ $s->conditionColor() }}">{{ ucfirst(str_replace('_', ' ', $s->condition_on_discharge)) }}</span></td>
                                <td>{{ $s->follow_up_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('medical.records.discharge-summaries.show', $s) }}" class="btn btn-outline-primary btn-sm">View</a>
                                    <a href="{{ route('medical.records.discharge-summaries.pdf', $s) }}" target="_blank" class="btn btn-outline-secondary btn-sm">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No discharge summaries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $summaries->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
