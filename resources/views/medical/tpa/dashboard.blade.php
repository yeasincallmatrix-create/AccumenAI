@extends('layouts.institute')

@section('title', 'TPA — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">TPA / Insurance</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.tpa.claims.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Claim
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.tpa.claims.index') }}">
            <i class="bi bi-list me-1"></i>All Claims
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-2">
        <div class="card bg-secondary text-white"><div class="card-body py-2">
            <h6 class="card-title">Total</h6><h3 class="card-text">{{ $stats['total_claims'] }}</h3>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-dark"><div class="card-body py-2">
            <h6 class="card-title">Pending</h6><h3 class="card-text">{{ $stats['pending'] }}</h3>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white"><div class="card-body py-2">
            <h6 class="card-title">Approved</h6><h3 class="card-text">{{ $stats['approved'] }}</h3>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white"><div class="card-body py-2">
            <h6 class="card-title">Rejected</h6><h3 class="card-text">{{ $stats['rejected'] }}</h3>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-primary text-white"><div class="card-body py-2">
            <h6 class="card-title">Settled</h6><h3 class="card-text">{{ $stats['settled'] }}</h3>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-dark"><div class="card-body py-2">
            <h6 class="card-title">Claimed</h6><h3 class="card-text">৳{{ number_format($stats['total_claimed_amount'], 0) }}</h3>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Recent Claims</h6>
        <a href="{{ route('medical.tpa.claims.index') }}" class="btn btn-sm btn-link">View all</a>
    </div>
    <div class="card-body">
        @if($recentClaims->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Claim No</th><th>Patient</th><th>Company</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($recentClaims as $claim)
                        <tr>
                            <td><a href="{{ route('medical.tpa.claims.show', $claim) }}"><strong>{{ clinical_no($claim->claim_number) }}</strong></a></td>
                            <td>{{ $claim->patient->full_name ?? 'N/A' }}</td>
                            <td>{{ $claim->tpa_company_name }}</td>
                            <td>৳{{ number_format($claim->claim_amount, 2) }}</td>
                            <td><span class="badge bg-{{ $claim->status_class }}">{{ ucfirst($claim->status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No claims yet.</p>
        @endif
    </div>
</div>
@endsection
