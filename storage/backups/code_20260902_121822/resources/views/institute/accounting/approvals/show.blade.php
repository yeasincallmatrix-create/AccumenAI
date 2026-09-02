@extends('layouts.standalone')

@section('title', $workflow->name . ' — AccumenAI')
@section('page_title', 'Approvals')

@section('content')

<div class="standalone-heading">
    <h4>{{ $workflow->name }}</h4>
    <p>Module: <span class="badge bg-secondary">{{ $workflow->module }}</span> |
       Range: {{ number_format((float) $workflow->amount_from, 2) }} — {{ number_format((float) $workflow->amount_to, 2) }} |
       @if ($workflow->is_active) <span class="badge bg-success">Active</span> @else <span class="badge bg-secondary">Inactive</span> @endif
    </p>
    <a href="{{ route('accounting.approvals.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="admin-card">
            <h6 class="card-title">Approval Steps</h6>
            @forelse ($workflow->steps as $step)
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="badge bg-primary rounded-pill">Step {{ $step->step_order }}</span>
                    <span>Role #{{ $step->approver_role_id }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No steps configured.</p>
            @endforelse
        </div>
    </div>

    <div class="col-md-6">
        <div class="admin-card">
            <h6 class="card-title">Recent Requests ({{ $workflow->requests->count() }})</h6>
            @forelse ($workflow->requests->take(10) as $req)
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted">#{{ $req->id }}</span>
                    <span>{{ $req->ref_type }} #{{ $req->ref_id }}</span>
                    <span class="fw-semibold">{{ number_format((float) $req->amount, 2) }}</span>
                    @if ($req->status === 'approved')
                        <span class="badge bg-success">Approved</span>
                    @elseif ($req->status === 'rejected')
                        <span class="badge bg-danger">Rejected</span>
                    @else
                        <span class="badge bg-warning text-dark">Pending</span>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">No requests submitted yet.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection
