@extends('layouts.institute')

@section('title', 'Diet Plans — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-journal-medical"></i> Diet Plans</h4>
        @if($user && $user->hasPermission('medical.diet.plan.create'))
            <a href="{{ route('medical.diet.plans.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Diet Plan
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number/name/patient..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="diet_type" class="form-select form-select-sm">
                        <option value="">All diet types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('diet_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
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
                            <th>Plan #</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Patient</th>
                            <th>Calories</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td><strong>{{ $plan->plan_number }}</strong></td>
                                <td>{{ $plan->plan_name }}</td>
                                <td><span class="badge bg-{{ $plan->dietTypeColor() }}">{{ $plan->dietTypeLabel() }}</span></td>
                                <td>{{ $plan->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $plan->daily_calories ?? '—' }}</td>
                                <td>{{ $plan->start_date->format('d M Y') }} → {{ $plan->end_date?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge bg-{{ $plan->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $plan->status)) }}</span></td>
                                <td>
                                    <a href="{{ route('medical.diet.plans.show', $plan) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No diet plans found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $plans->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
