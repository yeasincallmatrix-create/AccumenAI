@extends('layouts.institute')

@section('title', 'TPA Claims — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">TPA / Insurance Claims</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.tpa.claims.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Claim
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-2">
        <div class="card bg-secondary text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0">Total: {{ $stats['total_claims'] }}</h6>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-dark"><div class="card-body py-2">
            <h6 class="card-title mb-0">Pending: {{ $stats['pending'] }}</h6>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0">Approved: {{ $stats['approved'] }}</h6>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0">Rejected: {{ $stats['rejected'] }}</h6>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-primary text-white"><div class="card-body py-2">
            <h6 class="card-title mb-0">Settled: {{ $stats['settled'] }}</h6>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-dark"><div class="card-body py-2">
            <h6 class="card-title mb-0">Claimed: ৳{{ number_format($stats['total_claimed_amount'], 0) }}</h6>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'partial' => 'Partial', 'settled' => 'Settled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
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
                    <input type="text" name="tpa_company" class="form-control" placeholder="TPA company..."
                           value="{{ request('tpa_company') }}">
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.tpa.claims.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Claim No</th><th>Patient</th><th>Company</th><th>Claimed</th><th>Approved</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($claims as $claim)
                    <tr>
                        <td><strong>{{ clinical_no($claim->claim_number) }}</strong></td>
                        <td>{{ $claim->patient->full_name ?? 'N/A' }}</td>
                        <td>{{ $claim->tpa_company_name }}</td>
                        <td>৳{{ number_format($claim->claim_amount, 2) }}</td>
                        <td>{{ $claim->approved_amount !== null ? '৳'.number_format($claim->approved_amount, 2) : '—' }}</td>
                        <td><span class="badge bg-{{ $claim->status_class }}">{{ ucfirst($claim->status) }}</span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.tpa.claims.show', $claim) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($claim->status === 'pending')
                                    <a href="{{ route('medical.tpa.claims.edit', $claim) }}" class="btn btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-shield-check fs-2 d-block mb-2"></i>
                            No TPA claims found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $claims->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
