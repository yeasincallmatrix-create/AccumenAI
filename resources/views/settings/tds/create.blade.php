@extends('layouts.institute')

@section('title', 'Record TDS Deduction')

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
        <h4 class="page-header-title"><i class="bi bi-plus-circle me-2"></i>Record TDS Deduction</h4>
        <p class="page-header-desc mb-0">Record a new Tax Deducted at Source entry.</p>
    </div>
    <a href="{{ route('settings.tds.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="admin-card p-4">
    <form method="POST" action="{{ route('settings.tds.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">TDS Type *</label>
                <select name="type" class="form-select" required>
                    <option value="">Select Type</option>
                    @php
                        $rules = \App\Models\TaxDeductionRule::where('country_code', $country)->active()->get();
                    @endphp
                    @foreach($rules as $rule)
                        <option value="{{ $rule->code }}">{{ $rule->name }} ({{ $rule->rate }}%)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Payee Name *</label>
                <input type="text" name="payee_name" class="form-control" required value="{{ old('payee_name') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Payee TIN</label>
                <input type="text" name="payee_tin" class="form-control" value="{{ old('payee_tin') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Gross Amount ({{ $currency }}) *</label>
                <input type="number" name="gross_amount" class="form-control" step="0.01" min="0" required value="{{ old('gross_amount') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Deduction Date *</label>
                <input type="date" name="deduction_date" class="form-control" required value="{{ old('deduction_date', date('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Record Deduction</button>
            <a href="{{ route('settings.tds.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
    </form>
</div>

@endsection
