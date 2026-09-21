@extends('layouts.institute')

@section('title', tenant_tds_label() . ' Summary Report')

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
        <h4 class="page-header-title"><i class="bi bi-file-earmark-bar-graph me-2"></i>{{ tenant_tds_label() }} Summary Report <span class="badge bg-info ms-2" style="font-size:.65rem">{{ $country_code }}</span></h4>
        <p class="page-header-desc mb-0">Period: {{ $period }}</p>
    </div>
    <a href="{{ route('settings.tds.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back to {{ tenant_tds_label() }}</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Deductions</div><div class="h4 mb-0">{{ $total_deductions }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Gross</div><div class="h4 mb-0">{{ number_format($total_gross, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Tax</div><div class="h4 mb-0">{{ number_format($total_tax, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Pending Deposit</div><div class="h4 mb-0 text-warning">{{ number_format($pending_deposit ?? 0, 2) }}</div></div></div>
</div>

@if(!empty($by_type))
<div class="admin-card p-4 mb-4">
    <h5 class="mb-3">By Type</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Type</th><th class="text-end">Count</th><th class="text-end">Gross</th><th class="text-end">Tax</th></tr></thead>
            <tbody>
                @foreach($by_type as $type => $data)
                <tr>
                    <td>{{ $type }}</td>
                    <td class="text-end">{{ $data['count'] }}</td>
                    <td class="text-end">{{ number_format($data['gross'], 2) }}</td>
                    <td class="text-end">{{ number_format($data['tax'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
