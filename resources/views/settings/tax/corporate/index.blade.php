@extends('layouts.institute')

@section('title', 'Corporate Tax')

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
        <h4 class="page-header-title"><i class="bi bi-building me-2"></i>Corporate Tax <span class="badge bg-info ms-2" style="font-size:.65rem">{{ $country }}</span></h4>
        <p class="page-header-desc mb-0">Corporate tax computations and advance tax payments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.tax-reports.corporate-return') }}" class="btn btn-outline-info rounded-pill px-3">Tax Return</a>
        <a href="{{ route('settings.tax-reports.advance-tax') }}" class="btn btn-outline-secondary rounded-pill px-3">Advance Tax Report</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="admin-card p-4">
            <h5><i class="bi bi-calculator me-2"></i>Compute Corporate Tax</h5>
            <form method="POST" action="{{ route('settings.corporate-tax.compute') }}">
                @csrf
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">Financial Year *</label>
                        <input type="text" name="financial_year" class="form-control" required value="{{ old('financial_year', date('Y')) }}" placeholder="e.g. 2026">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Entity Type *</label>
                        <select name="entity_type" class="form-select" required>
                            <option value="private_limited">Private Limited</option>
                            <option value="public_limited">Public Limited</option>
                            <option value="bank">Bank / Financial Institution</option>
                            <option value="sole_proprietorship">Sole Proprietorship</option>
                            <option value="partnership">Partnership</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total Income ({{ $currency }}) *</label>
                        <input type="number" name="total_income" class="form-control" step="0.01" min="0" required value="{{ old('total_income') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Deductions ({{ $currency }})</label>
                        <input type="number" name="deductions" class="form-control" step="0.01" min="0" value="{{ old('deductions', 0) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Advance Tax Paid ({{ $currency }})</label>
                        <input type="number" name="advance_tax_paid" class="form-control" step="0.01" min="0" value="{{ old('advance_tax_paid', 0) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3 px-4"><i class="bi bi-calculator me-1"></i>Compute</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-4">
            <h5><i class="bi bi-calendar-date me-2"></i>Record Advance Tax Payment</h5>
            <form method="POST" action="{{ route('settings.corporate-tax.advance') }}">
                @csrf
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">Financial Year *</label>
                        <input type="text" name="financial_year" class="form-control" required value="{{ old('financial_year', date('Y')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Quarter *</label>
                        <select name="quarter" class="form-select" required>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estimated Income ({{ $currency }}) *</label>
                        <input type="number" name="estimated_income" class="form-control" step="0.01" min="0" required value="{{ old('estimated_income') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-control" required value="{{ old('due_date') }}">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3 px-4"><i class="bi bi-plus-lg me-1"></i>Record Advance</button>
            </form>
        </div>
    </div>
</div>

<div class="admin-card p-4 mb-4">
    <h5 class="mb-3">Computations</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>FY</th>
                    <th>Entity</th>
                    <th class="text-end">Total Income</th>
                    <th class="text-end">Taxable</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Final Tax</th>
                    <th class="text-end">Payable</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($computations as $c)
                <tr>
                    <td><code>{{ $c->reference_no }}</code></td>
                    <td>{{ $c->financial_year }}</td>
                    <td>{{ str_replace('_', ' ', ucfirst($c->entity_type)) }}</td>
                    <td class="text-end">{{ number_format($c->total_income, 2) }}</td>
                    <td class="text-end">{{ number_format($c->taxable_income, 2) }}</td>
                    <td class="text-end">{{ number_format($c->tax_rate, 2) }}%</td>
                    <td class="text-end">{{ number_format($c->final_tax, 2) }}</td>
                    <td class="text-end">{{ number_format($c->tax_payable, 2) }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($c->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No computations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card p-4">
    <h5 class="mb-3">Advance Tax Payments</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>FY</th>
                    <th>Quarter</th>
                    <th class="text-end">Est. Income</th>
                    <th class="text-end">Tax Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($advancePayments as $a)
                <tr>
                    <td><code>{{ $a->reference_no }}</code></td>
                    <td>{{ $a->financial_year }}</td>
                    <td>{{ $a->quarter }}</td>
                    <td class="text-end">{{ number_format($a->estimated_income, 2) }}</td>
                    <td class="text-end">{{ number_format($a->tax_amount, 2) }}</td>
                    <td>{{ $a->due_date->format('d M Y') }}</td>
                    <td>
                        @if($a->status === 'due')
                            <span class="badge bg-warning">Due</span>
                        @elseif($a->status === 'paid')
                            <span class="badge bg-success">Paid</span>
                        @else
                            <span class="badge bg-danger">Overdue</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No advance payments recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
