@extends('layouts.institute')

@section('title', 'TPA Claim — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $claim->claim_number }}
            <span class="badge bg-{{ $claim->status_class }}">{{ ucfirst($claim->status) }}</span>
        </h4>
    </div>
    <div class="page-header-actions">
        @if($claim->status === 'pending')
            <a class="btn btn-warning" href="{{ route('medical.tpa.claims.edit', $claim) }}">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        @endif
        @if($claim->status === 'approved')
            <form action="{{ route('medical.tpa.claims.settle', $claim) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Mark this claim as settled?')">
                    <i class="bi bi-check-all me-1"></i>Settle
                </button>
            </form>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.tpa.claims.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Claim Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    @if($claim->patient)
                        <a href="{{ route('medical.patients.show', $claim->patient) }}">{{ $claim->patient->full_name }}</a>
                        <span class="text-muted">({{ $claim->patient->mr_number }})</span>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Invoice:</strong>
                    @if($claim->invoice)
                        <a href="{{ route('medical.billing.invoices.show', $claim->invoice) }}">{{ $claim->invoice->invoice_number }}</a>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Company:</strong> {{ $claim->tpa_company_name }}</p>
                <p><strong>Policy:</strong> {{ $claim->policy_number }}</p>
                <p><strong>Claim Date:</strong> {{ $claim->claim_date?->format('d M Y') }}</p>
                <p class="mb-0"><strong>Documents:</strong> {{ $claim->documents ?? '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Amounts & Timeline</h6></div>
            <div class="card-body">
                <p><strong>Claimed:</strong> ৳{{ number_format($claim->claim_amount, 2) }}</p>
                <p><strong>Approved:</strong> {{ $claim->approved_amount !== null ? '৳'.number_format($claim->approved_amount, 2) : '—' }}</p>
                <p><strong>Approval Date:</strong> {{ $claim->approval_date?->format('d M Y') ?? '—' }}</p>
                <p><strong>Settlement Date:</strong> {{ $claim->settlement_date?->format('d M Y') ?? '—' }}</p>
                <p class="mb-0"><strong>Remarks:</strong> {{ $claim->remarks ?? '—' }}</p>
            </div>
        </div>

        @if($claim->status === 'pending')
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Process Claim</h6></div>
            <div class="card-body">
                <form action="{{ route('medical.tpa.claims.approve', $claim) }}" method="POST" class="mb-3">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-7">
                            <input type="number" name="approved_amount" min="0.01" step="0.01"
                                   max="{{ $claim->claim_amount }}" value="{{ $claim->claim_amount }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-success w-100"
                                    onclick="return confirm('Approve this claim? The linked invoice will be credited.')">
                                <i class="bi bi-check-lg me-1"></i>Approve
                            </button>
                        </div>
                    </div>
                </form>
                <form action="{{ route('medical.tpa.claims.reject', $claim) }}" method="POST">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-7">
                            <input type="text" name="remarks" maxlength="2000"
                                   class="form-control" required placeholder="Rejection reason">
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-danger w-100"
                                    onclick="return confirm('Reject this claim?')">
                                <i class="bi bi-x-lg me-1"></i>Reject
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
