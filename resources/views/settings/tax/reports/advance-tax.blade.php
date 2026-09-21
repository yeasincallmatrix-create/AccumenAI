@extends('layouts.institute')

@section('title', 'Advance Tax Report')

@push('styles')
<style>
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

@include('settings.tds._subnav')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-calendar-check me-2"></i>Advance Tax Register <span class="badge bg-info ms-2" style="font-size:.65rem">{{ $country_code }}</span></h4>
        <p class="page-header-desc mb-0">Financial Year: {{ $financial_year }}</p>
    </div>
    <a href="{{ route('settings.corporate-tax.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Payments</div><div class="h4 mb-0">{{ $total_payments }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Amount</div><div class="h4 mb-0">{{ number_format($total_amount, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Paid</div><div class="h4 mb-0 text-success">{{ number_format($paid_amount, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Due</div><div class="h4 mb-0 text-danger">{{ number_format($due_amount, 2) }}</div></div></div>
</div>

@if(!empty($by_quarter))
<div class="admin-card p-4 mb-4">
    <h5 class="mb-3">By Quarter</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Quarter</th><th class="text-end">Count</th><th class="text-end">Amount</th><th class="text-end">Paid</th></tr></thead>
            <tbody>
                @foreach($by_quarter as $q => $data)
                <tr>
                    <td>{{ $q }}</td>
                    <td class="text-end">{{ $data['count'] }}</td>
                    <td class="text-end">{{ number_format($data['amount'], 2) }}</td>
                    <td class="text-end">{{ number_format($data['paid'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="admin-card p-4">
    <h5 class="mb-3">All Payments</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Reference</th><th>Quarter</th><th class="text-end">Amount</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td><code>{{ $p->reference_no }}</code></td>
                    <td>{{ $p->quarter }}</td>
                    <td class="text-end">{{ number_format($p->tax_amount, 2) }}</td>
                    <td>{{ $p->due_date->format('d M Y') }}</td>
                    <td>
                        @if($p->status === 'due')
                            <span class="badge bg-warning">Due</span>
                        @elseif($p->status === 'paid')
                            <span class="badge bg-success">Paid</span>
                        @else
                            <span class="badge bg-danger">Overdue</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No advance tax payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
