@extends('layouts.institute')

@section('title', 'Dividends')

@push('styles')
<style>
  /* Match settings index: hide navbar/sidebar on settings sub-pages */
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-cash-coin me-2"></i>Dividends</h4>
        <p class="page-header-desc mb-0">Declare, track and pay shareholder dividends.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.dividend.register') }}" class="btn btn-outline-secondary rounded-pill px-3">Register</a>
        <a href="{{ route('settings.dividend.create') }}" class="btn btn-primary rounded-pill px-3">+ New Dividend</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('settings.dividend.index') }}" class="admin-card p-3 mb-3 d-flex gap-2 flex-wrap">
    <div>
        <label class="form-label mb-1">Financial Year</label>
        <input type="text" name="fy" value="{{ $fy }}" placeholder="e.g. 2026" class="form-control form-control-sm">
    </div>
    <div class="align-self-end d-flex gap-2">
        <button class="btn btn-sm btn-primary">Filter</button>
        <a href="{{ route('settings.dividend.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Dividends</div><div class="h4 mb-0">{{ $summary['total_dividends'] }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Declared Amount</div><div class="h4 mb-0">{{ number_format($summary['total_declared'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Paid (net)</div><div class="h4 mb-0">{{ number_format($summary['total_paid'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Pending Payouts</div><div class="h4 mb-0">{{ $summary['pending_count'] }}</div></div></div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Reference</th><th>FY</th><th>Declared</th><th class="text-end">Total</th><th class="text-end">Tax</th><th class="text-end">Net</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($dividends as $d)
                    <tr>
                        <td class="font-monospace">{{ $d->reference_no }}</td>
                        <td>{{ $d->financial_year }}</td>
                        <td>{{ $d->declared_date?->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($d->total_dividend, 2) }}</td>
                        <td class="text-end">{{ number_format($d->total_tax, 2) }}</td>
                        <td class="text-end">{{ number_format($d->total_net, 2) }}</td>
                        <td><span class="badge bg-{{ $d->status === 'paid' ? 'success' : ($d->status === 'draft' ? 'secondary' : 'warning') }}">{{ ucfirst($d->status) }}</span></td>
                        <td class="text-end"><a href="{{ route('settings.dividend.show', $d) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">No dividends declared yet. <a href="{{ route('settings.dividend.create') }}">Declare first dividend</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($dividends->hasPages())
        <div class="p-2 border-top">{{ $dividends->links() }}</div>
    @endif
</div>

@endsection
