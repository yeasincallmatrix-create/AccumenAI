@extends('layouts.institute')

@section('title', 'Record ' . tenant_tds_label() . ' Receivable')

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
        <h4 class="page-header-title"><i class="bi bi-plus-circle me-2"></i>Record {{ tenant_tds_label() }} Receivable</h4>
        <p class="page-header-desc mb-0">Record {{ tenant_tds_label() }} deducted by a customer from your invoice.</p>
    </div>
    <a href="{{ route('settings.tds-receivable.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="admin-card p-4">
    <form method="POST" action="{{ route('settings.tds-receivable.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Party / Customer <span class="text-danger">*</span></label>
                <select name="party_id" class="form-select" required>
                    <option value="">Select party...</option>
                    @foreach($parties as $p)
                        <option value="{{ $p->id }}" @selected(old('party_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Reference No</label>
                <input type="text" name="reference_no" class="form-control" value="{{ old('reference_no') }}" maxlength="50">
            </div>
            <div class="col-md-4">
                <label class="form-label">Gross Amount <span class="text-danger">*</span></label>
                <input type="number" name="gross_amount" id="gross_amount" class="form-control" value="{{ old('gross_amount') }}" step="0.01" min="0.01" required oninput="calcTDS()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Rate (%) <span class="text-danger">*</span></label>
                <input type="number" name="rate_percent" id="rate_percent" class="form-control" value="{{ old('rate_percent', '10') }}" step="0.01" min="0" max="100" required oninput="calcTDS()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Calculated {{ tenant_tds_label() }}</label>
                <input type="text" id="tds_preview" class="form-control bg-light" readonly value="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">Deduction Date <span class="text-danger">*</span></label>
                <input type="date" name="deduction_date" class="form-control" value="{{ old('deduction_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tax Period <span class="text-danger">*</span></label>
                <input type="text" name="tax_period" class="form-control" value="{{ old('tax_period', date('M Y')) }}" maxlength="20" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Financial Year <span class="text-danger">*</span></label>
                <input type="text" name="financial_year" class="form-control" value="{{ old('financial_year', (date('m') >= 7 ? date('Y') : date('Y')-1) . '-' . (date('m') >= 7 ? date('Y')+1 : date('Y'))) }}" maxlength="20" required>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" maxlength="500">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Record Receivable</button>
        </div>
    </form>
</div>

<script>
function calcTDS() {
    var gross = parseFloat(document.getElementById('gross_amount').value) || 0;
    var rate = parseFloat(document.getElementById('rate_percent').value) || 0;
    var tds = (gross * rate / 100).toFixed(2);
    document.getElementById('tds_preview').value = tds;
}
</script>

@endsection
