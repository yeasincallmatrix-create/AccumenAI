@extends('layouts.institute')

@section('title', 'TPA Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">TPA Claim Book</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.tpa.index') }}">
            <i class="bi bi-arrow-left me-1"></i>TPA Dashboard
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
            <h6 class="card-title">Approved ৳</h6><h3 class="card-text">{{ number_format($stats['total_approved_amount'], 0) }}</h3>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Recent Claims (100)</h6></div>
    <div class="card-body">
        @if($claims->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Claim No</th><th>Patient</th><th>Company</th><th>Claimed</th><th>Approved</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($claims as $claim)
                        <tr>
                            <td><a href="{{ route('medical.tpa.claims.show', $claim) }}">{{ $claim->claim_number }}</a></td>
                            <td>{{ $claim->patient->full_name ?? 'N/A' }}</td>
                            <td>{{ $claim->tpa_company_name }}</td>
                            <td>৳{{ number_format($claim->claim_amount, 2) }}</td>
                            <td>{{ $claim->approved_amount !== null ? '৳'.number_format($claim->approved_amount, 2) : '—' }}</td>
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
