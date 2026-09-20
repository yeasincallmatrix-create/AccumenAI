@extends('layouts.institute')

@section('title', 'Dividend Register')

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
        <h4 class="page-header-title"><i class="bi bi-journal-check me-2"></i>Dividend Register</h4>
        <p class="page-header-desc mb-0">Shareholder-wise dividend totals.</p>
    </div>
    <a href="{{ route('settings.dividend.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back</a>
</div>

<form method="GET" action="{{ route('settings.dividend.register') }}" class="admin-card p-3 mb-3 d-flex gap-2 flex-wrap">
    <div>
        <label class="form-label mb-1">Financial Year</label>
        <input type="text" name="fy" value="{{ $fy }}" placeholder="e.g. 2026" class="form-control form-control-sm">
    </div>
    <div class="align-self-end d-flex gap-2">
        <button class="btn btn-sm btn-primary">Filter</button>
        <a href="{{ route('settings.dividend.register') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
    </div>
</form>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Shareholder</th><th class="text-end">Shares</th><th class="text-end">Gross Total</th><th class="text-end">Tax Total</th><th class="text-end">Net Total</th><th class="text-end">Dividends</th></tr></thead>
            <tbody>
                @forelse($register as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="text-end">{{ $row['total_shares'] }}</td>
                        <td class="text-end">{{ number_format($row['gross_total'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['tax_total'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['net_total'], 2) }}</td>
                        <td class="text-end">{{ $row['dividend_count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No dividend records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
