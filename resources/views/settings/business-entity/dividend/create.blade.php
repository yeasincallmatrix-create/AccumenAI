@extends('layouts.institute')

@section('title', 'Declare Dividend')

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
        <h4 class="page-header-title"><i class="bi bi-plus-circle me-2"></i>Declare Dividend</h4>
        <p class="page-header-desc mb-0">Payouts are auto-computed per shareholder by current shares.</p>
    </div>
    <a href="{{ route('settings.dividend.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Cancel</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('settings.dividend.store') }}">
    @csrf
    <div class="admin-card p-4 mb-3">
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Declared Date *</label><input type="date" name="declared_date" value="{{ old('declared_date', now()->format('Y-m-d')) }}" class="form-control" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Record Date</label><input type="date" name="record_date" value="{{ old('record_date') }}" class="form-control"></div>
            <div class="col-md-4 mb-3"><label class="form-label">Payment Date</label><input type="date" name="payment_date" value="{{ old('payment_date') }}" class="form-control"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Financial Year *</label><input type="text" name="financial_year" value="{{ old('financial_year', now()->format('Y')) }}" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">Total Dividend Amount *</label><input type="number" step="0.01" min="0.01" name="total_dividend" value="{{ old('total_dividend') }}" class="form-control" required></div>
        </div>
        <div class="mb-3"><label class="form-label">Board Resolution</label><textarea name="board_resolution" class="form-control" rows="3">{{ old('board_resolution') }}</textarea></div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
    </div>
    <button class="btn btn-primary">Declare as Draft</button>
</form>

@endsection
