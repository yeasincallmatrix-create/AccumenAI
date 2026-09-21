@extends('layouts.institute')

@section('title', tenant_tds_label() . ' & Tax')

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
        <h4 class="page-header-title"><i class="bi bi-file-earmark-text me-2"></i>{{ tenant_tds_label() }} & Tax <span class="badge bg-info ms-2" style="font-size:.65rem">{{ $country }}</span></h4>
        <p class="page-header-desc mb-0">{{ tenant_tds_label() }} — deductions, deposits, and certificates.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.tds.create') }}" class="btn btn-primary rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Record {{ tenant_tds_label() }}</a>
        <a href="{{ route('settings.tds.certificates') }}" class="btn btn-outline-secondary rounded-pill px-3">Certificates</a>
        <a href="{{ route('settings.tax-reports.tds-summary') }}" class="btn btn-outline-info rounded-pill px-3">Reports</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Deductions</div><div class="h4 mb-0">{{ $summary['total_deductions'] }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Gross</div><div class="h4 mb-0">{{ number_format($summary['total_gross'], 2) }} {{ $currency }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Tax</div><div class="h4 mb-0">{{ number_format($summary['total_tax'], 2) }} {{ $currency }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Pending Deposit</div><div class="h4 mb-0 text-warning">{{ $summary['pending_deposit'] }}</div></div></div>
</div>

<div class="admin-card p-4">
    <h5 class="mb-3">{{ tenant_tds_label() }} Deductions</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Payee</th>
                    <th class="text-end">Gross Amount</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Tax Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deductions as $d)
                <tr>
                    <td><code>{{ $d->reference_no }}</code></td>
                    <td>{{ $d->type }}</td>
                    <td>{{ $d->payee_name }}</td>
                    <td class="text-end">{{ number_format($d->gross_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($d->tax_rate, 2) }}%</td>
                    <td class="text-end">{{ number_format($d->tax_amount, 2) }}</td>
                    <td>{{ $d->deduction_date->format('d M Y') }}</td>
                    <td>
                        @if($d->status === 'pending')
                            <span class="badge bg-warning">Pending</span>
                        @elseif($d->status === 'deposited')
                            <span class="badge bg-success">Deposited</span>
                        @else
                            <span class="badge bg-info">Certificate Issued</span>
                        @endif
                    </td>
                    <td>
                        @if($d->status === 'pending')
                        <form method="POST" action="{{ route('settings.tds.deposit', $d) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="deposit_date" value="{{ date('Y-m-d') }}">
                            <input type="hidden" name="challan_no" value="CHL-{{ $d->reference_no }}">
                            <button type="submit" class="btn btn-sm btn-outline-success">Mark Deposited</button>
                        </form>
                        @endif
                        @if($d->status === 'deposited')
                        <form method="POST" action="{{ route('settings.tds.certificates.generate', $d) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-info">Generate Certificate</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No {{ tenant_tds_label() }} deductions recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $deductions->links() }}
</div>

@endsection
