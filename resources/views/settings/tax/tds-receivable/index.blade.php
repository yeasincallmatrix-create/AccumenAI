@extends('layouts.institute')

@section('title', 'TDS Receivable')

@push('styles')
<style>
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

@include('settings.tds._subnav')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-box-arrow-in-down me-2"></i>TDS Receivable</h4>
        <p class="page-header-desc mb-0">Track TDS deducted by customers from your invoices.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.tds-receivable.create') }}" class="btn btn-primary rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Record Receivable</a>
        <a href="{{ route('settings.tds-certificates-received.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Certificates Received</a>
        <a href="{{ route('settings.tax-reconciliation.index') }}" class="btn btn-outline-info rounded-pill px-3">Reconciliation</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="admin-card p-3">
            <div class="text-muted small">Gross Total (FY {{ $fy }})</div>
            <div class="h4 mb-0">{{ number_format($totals['gross'], 2) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-3">
            <div class="text-muted small">TDS Receivable</div>
            <div class="h4 mb-0 text-primary">{{ number_format($totals['tds'], 2) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-3">
            <div class="text-muted small">Pending Certificate</div>
            <div class="h4 mb-0 text-warning">{{ $totals['pending'] }}</div>
        </div>
    </div>
</div>

<div class="admin-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">TDS Receivables</h5>
        <form class="d-flex gap-2" method="GET">
            <select name="fy" class="form-select form-select-sm" onchange="this.form.submit()">
                @for($y = (int)date('Y'); $y >= (int)date('Y')-3; $y--)
                    <option value="{{ $y }}-{{ $y+1 }}" @selected($fy === $y.'-'.($y+1))>{{ $y }}-{{ $y+1 }}</option>
                @endfor
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Party</th>
                    <th>Reference</th>
                    <th class="text-end">Gross</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">TDS</th>
                    <th class="text-end">Net</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                <tr>
                    <td>{{ $r->deduction_date->format('d M Y') }}</td>
                    <td>{{ $r->party?->name ?? '-' }}</td>
                    <td><code>{{ $r->reference_no ?? '-' }}</code></td>
                    <td class="text-end">{{ number_format($r->gross_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($r->rate_percent, 2) }}%</td>
                    <td class="text-end fw-bold">{{ number_format($r->tds_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($r->net_amount, 2) }}</td>
                    <td>
                        @if($r->status === 'pending_certificate')
                            <span class="badge bg-warning">Pending</span>
                        @elseif($r->status === 'certified')
                            <span class="badge bg-success">Certified</span>
                        @else
                            <span class="badge bg-info">Reconciled</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No TDS receivables recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $rows->links() }}
</div>

@endsection
