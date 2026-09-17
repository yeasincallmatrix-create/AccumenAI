@extends('layouts.institute')

@section('title', 'Vaccination Schedules — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Vaccination Schedules</h4>
        @if($user && $user->hasPermission('medical.vaccination.schedule'))
            <a href="{{ route('medical.vaccination.schedules.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Schedule
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search patient/vaccine..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(\App\Models\Medical\VaccinationSchedule::STATUSES as $k => $v)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="filter" class="form-select form-select-sm">
                        <option value="">All Schedules</option>
                        <option value="due_today" {{ request('filter') === 'due_today' ? 'selected' : '' }}>Due Today</option>
                        <option value="overdue" {{ request('filter') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
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
                            <th>Patient</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Due Date</th>
                            <th>Given Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $s)
                            <tr>
                                <td>{{ $s->patient->full_name ?? 'N/A' }}</td>
                                <td><strong>{{ $s->vaccineMaster->name ?? 'N/A' }}</strong></td>
                                <td>{{ $s->dose_number }}</td>
                                <td>{{ $s->due_date->format('d M Y') }}</td>
                                <td>{{ $s->given_date?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge bg-{{ $s->statusColor() }}">{{ $s->statusLabel() }}</span></td>
                                <td>
                                    <a href="{{ route('medical.vaccination.schedules.show', $s) }}" class="btn btn-outline-primary btn-sm">View</a>
                                    @if($s->status === 'scheduled' && $user?->hasPermission('medical.vaccination.administer'))
                                        <a href="{{ route('medical.vaccination.schedules.administer', $s) }}" class="btn btn-primary btn-sm">Administer</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No schedules found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $schedules->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
