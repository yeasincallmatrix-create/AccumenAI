@extends('layouts.institute')

@section('title', 'Corporate Tax Return')

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
        <h4 class="page-header-title"><i class="bi bi-file-earmark-ruled me-2"></i>Corporate Tax Return <span class="badge bg-info ms-2" style="font-size:.65rem">{{ $country_code }}</span></h4>
        <p class="page-header-desc mb-0">Financial Year: {{ $financial_year }}</p>
    </div>
    <a href="{{ route('settings.corporate-tax.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Tax Computed</div><div class="h4 mb-0">{{ number_format($total_tax_computed, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Advance Tax Paid</div><div class="h4 mb-0 text-success">{{ number_format($total_advance_paid, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Net Tax Payable</div><div class="h4 mb-0 text-danger">{{ number_format($net_tax_payable, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Computations</div><div class="h4 mb-0">{{ $computations->count() }}</div></div></div>
</div>

<div class="admin-card p-4">
    <h5 class="mb-3">Computations</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Entity</th>
                    <th class="text-end">Total Income</th>
                    <th class="text-end">Deductions</th>
                    <th class="text-end">Taxable</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Min Tax</th>
                    <th class="text-end">Final Tax</th>
                    <th class="text-end">Advance Paid</th>
                    <th class="text-end">Payable</th>
                </tr>
            </thead>
            <tbody>
                @forelse($computations as $c)
                <tr>
                    <td><code>{{ $c->reference_no }}</code></td>
                    <td>{{ str_replace('_', ' ', ucfirst($c->entity_type)) }}</td>
                    <td class="text-end">{{ number_format($c->total_income, 2) }}</td>
                    <td class="text-end">{{ number_format($c->deductions, 2) }}</td>
                    <td class="text-end">{{ number_format($c->taxable_income, 2) }}</td>
                    <td class="text-end">{{ number_format($c->tax_rate, 2) }}%</td>
                    <td class="text-end">{{ number_format($c->minimum_tax, 2) }}</td>
                    <td class="text-end">{{ number_format($c->final_tax, 2) }}</td>
                    <td class="text-end">{{ number_format($c->advance_tax_paid, 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($c->tax_payable, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No computations for this financial year.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
