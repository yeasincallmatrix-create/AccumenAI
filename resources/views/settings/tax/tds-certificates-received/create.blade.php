@extends('layouts.institute')

@section('title', 'Record Certificate Received')

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
        <h4 class="page-header-title"><i class="bi bi-file-earmark-plus me-2"></i>Record Certificate Received</h4>
        <p class="page-header-desc mb-0">Record a {{ tenant_tds_label() }} certificate received from a customer/deductor.</p>
    </div>
    <a href="{{ route('settings.tds-certificates-received.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="admin-card p-4">
    <form method="POST" action="{{ route('settings.tds-certificates-received.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Party / Deductor <span class="text-danger">*</span></label>
                <select name="party_id" class="form-select" required>
                    <option value="">Select party...</option>
                    @foreach($parties as $p)
                        <option value="{{ $p->id }}" @selected(old('party_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Certificate No <span class="text-danger">*</span></label>
                <input type="text" name="certificate_no" class="form-control" value="{{ old('certificate_no') }}" maxlength="50" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Certificate Date <span class="text-danger">*</span></label>
                <input type="date" name="certificate_date" class="form-control" value="{{ old('certificate_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tax Period <span class="text-danger">*</span></label>
                <input type="text" name="tax_period" class="form-control" value="{{ old('tax_period', date('M Y')) }}" maxlength="20" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Financial Year <span class="text-danger">*</span></label>
                <input type="text" name="financial_year" class="form-control" value="{{ old('financial_year', (date('m') >= 7 ? date('Y') : date('Y')-1) . '-' . (date('m') >= 7 ? date('Y')+1 : date('Y'))) }}" maxlength="20" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Total Base Amount <span class="text-danger">*</span></label>
                <input type="number" name="total_base" class="form-control" value="{{ old('total_base') }}" step="0.01" min="0" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Total {{ tenant_tds_label() }} <span class="text-danger">*</span></label>
                <input type="number" name="total_tds" class="form-control" value="{{ old('total_tds') }}" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Attachment Path</label>
                <input type="text" name="attachment_path" class="form-control" value="{{ old('attachment_path') }}" maxlength="500" placeholder="storage/certificates/cert.pdf">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" maxlength="500">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Record Certificate</button>
        </div>
    </form>
</div>

@endsection
