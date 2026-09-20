@extends('layouts.institute')

@section('title', 'Add Shareholder')

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
        <h4 class="page-header-title"><i class="bi bi-person-plus me-2"></i>Add Shareholder</h4>
    </div>
    <a href="{{ route('settings.business-entity.private-limited.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Cancel</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('settings.business-entity.private-limited.store') }}">
    @csrf
    <div class="admin-card p-4 mb-3">
        <div class="mb-3">
            <label class="form-label">Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control"></div>
            <div class="col-md-6 mb-3"><label class="form-label">NID</label><input type="text" name="nid" value="{{ old('nid') }}" class="form-control"></div>
        </div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Shares *</label><input type="number" min="1" step="1" name="shares" value="{{ old('shares', 1) }}" class="form-control" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Face Value *</label><input type="number" step="0.01" min="0" name="face_value" value="{{ old('face_value', 10) }}" class="form-control" required></div>
            <div class="col-md-4 mb-3"><label class="form-label">Share % *</label><input type="number" step="0.01" min="0" max="100" name="share_percent" value="{{ old('share_percent') }}" class="form-control" required></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Certificate No</label><input type="text" name="certificate_no" value="{{ old('certificate_no') }}" class="form-control"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Issued At</label><input type="date" name="issued_at" value="{{ old('issued_at', now()->format('Y-m-d')) }}" class="form-control"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="form-check mt-4">
                    <input type="checkbox" name="is_director" value="1" id="is_director" class="form-check-input" {{ old('is_director') ? 'checked' : '' }}>
                    <label for="is_director" class="form-check-label">Director</label>
                </div>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Director Designation</label><input type="text" name="director_designation" value="{{ old('director_designation') }}" class="form-control"></div>
        </div>
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
            <label for="is_active" class="form-check-label">Active</label>
        </div>
    </div>
    <button class="btn btn-primary">Save Shareholder</button>
</form>

@endsection
