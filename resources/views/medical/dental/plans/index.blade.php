@extends('layouts.institute')

@section('title', 'Treatment Plans — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-list-check"></i> Dental Treatment Plans</h4>
        @if($user && $user->hasPermission('medical.dental.plan.manage'))
            <a href="{{ route('medical.dental.plans.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Plan
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number/patient..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(\App\Models\Medical\DentalTreatmentPlan::STATUSES as $k => $v)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
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
                            <th>Plan Number</th>
                            <th>Patient</th>
                            <th>Dentist</th>
                            <th>Complaint</th>
                            <th>Progress</th>
                            <th>Start</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td><strong>{{ $plan->plan_number }}</strong></td>
                                <td>{{ $plan->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $plan->dentist->name ?? 'N/A' }}</td>
                                <td>{{ Str::limit($plan->chief_complaint, 50) }}</td>
                                <td>
                                    <div class="progress" style="height:20px; min-width:100px;">
                                        <div class="progress-bar bg-success" style="width:{{ $plan->progressPercent() }}%">{{ $plan->progressPercent() }}%</div>
                                    </div>
                                    <small class="text-muted">{{ $plan->completed_steps }}/{{ $plan->total_steps }} steps</small>
                                </td>
                                <td>{{ $plan->start_date->format('d M Y') }}</td>
                                <td><span class="badge bg-{{ $plan->statusColor() }}">{{ $plan->statusLabel() }}</span></td>
                                <td>
                                    <a href="{{ route('medical.dental.plans.show', $plan) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No treatment plans found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $plans->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
