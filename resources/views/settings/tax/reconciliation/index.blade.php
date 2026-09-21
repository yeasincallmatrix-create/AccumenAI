@extends('layouts.institute')

@section('title', 'Tax Return Reconciliation')

@push('styles')
<style>
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-balance-scale me-2"></i>Tax Return Reconciliation</h4>
        <p class="page-header-desc mb-0">FY {{ $fy }} — reconcile TDS payable, receivable, advance tax & corporate tax.</p>
    </div>
    <a href="{{ route('settings.tds-receivable.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card p-3 border-start border-primary border-4">
            <div class="text-muted small">TDS Payable (we deducted)</div>
            <div class="h4 mb-0">{{ number_format($computed['tds_payable_total'], 2) }}</div>
            <small class="text-muted">{{ $computed['currency_code'] }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 border-start border-success border-4">
            <div class="text-muted small">TDS Receivable (deducted from us)</div>
            <div class="h4 mb-0 text-success">{{ number_format($computed['tds_receivable_total'], 2) }}</div>
            <small class="text-muted">{{ $computed['currency_code'] }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 border-start border-info border-4">
            <div class="text-muted small">Advance Tax Paid</div>
            <div class="h4 mb-0 text-info">{{ number_format($computed['advance_tax_paid'], 2) }}</div>
            <small class="text-muted">{{ $computed['currency_code'] }}</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 border-start border-warning border-4">
            <div class="text-muted small">Corporate Tax Payable</div>
            <div class="h4 mb-0 text-warning">{{ number_format($computed['corporate_tax_payable'], 2) }}</div>
            <small class="text-muted">{{ $computed['currency_code'] }}</small>
        </div>
    </div>
</div>

<div class="admin-card p-4">
    <h5 class="mb-3">Reconciliation Summary</h5>
    <table class="table table-bordered">
        <tbody>
            <tr><td><strong>Total Tax Liability</strong></td><td class="text-end">{{ number_format($computed['total_tax_liability'], 2) }}</td></tr>
            <tr><td><strong>Total Credits</strong> (TDS Receivable + Advance Tax)</td><td class="text-end text-success">{{ number_format($computed['total_credits'], 2) }}</td></tr>
            <tr class="table {{ $computed['net_payable'] >= 0 ? 'table-warning' : 'table-success' }}">
                <td><strong>Net Payable / (Refund)</strong></td>
                <td class="text-end fw-bold h5">{{ number_format($computed['net_payable'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($computed['net_payable'] >= 0)
        <div class="alert alert-warning mb-3"><i class="bi bi-exclamation-triangle me-1"></i>You owe <strong>{{ number_format($computed['net_payable'], 2) }} {{ $computed['currency_code'] }}</strong> as net tax payable.</div>
    @else
        <div class="alert alert-success mb-3"><i class="bi bi-check-circle me-1"></i>Refund of <strong>{{ number_format(abs($computed['net_payable']), 2) }} {{ $computed['currency_code'] }}</strong> may be claimed.</div>
    @endif
</div>

@if($saved)
<div class="admin-card p-4 mt-3">
    <h5 class="mb-3">Saved Reconciliation</h5>
    <table class="table table-sm">
        <tr><td>Status</td><td><span class="badge bg-{{ $saved->status === 'filed' ? 'success' : ($saved->status === 'computed' ? 'primary' : 'secondary') }}">{{ ucfirst($saved->status) }}</span></td></tr>
        @if($saved->filing_date)<tr><td>Filing Date</td><td>{{ $saved->filing_date->format('d M Y') }}</td></tr>@endif
        @if($saved->acknowledgment_no)<tr><td>Acknowledgment</td><td><code>{{ $saved->acknowledgment_no }}</code></td></tr>@endif
    </table>

    @if($saved->status === 'computed')
    <form method="POST" action="{{ route('settings.tax-reconciliation.mark-filed', $saved) }}" class="d-inline">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Filing Date</label>
                <input type="date" name="filing_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Acknowledgment No</label>
                <input type="text" name="acknowledgment_no" class="form-control form-control-sm" maxlength="50">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-success">Mark as Filed</button>
            </div>
        </div>
    </form>
    @endif
</div>
@endif

<div class="admin-card p-4 mt-3">
    <h5 class="mb-3">Finalize Reconciliation</h5>
    <form method="POST" action="{{ route('settings.tax-reconciliation.finalize') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Financial Year</label>
                <input type="text" name="financial_year" class="form-control" value="{{ $fy }}" readonly>
            </div>
            <div class="col-md-8">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Compute & Save</button>
        </div>
    </form>
</div>

@endsection
