@extends('layouts.institute')

@section('title', 'Capital Settings')

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
        <h4 class="page-header-title"><i class="bi bi-gear me-2"></i>Capital Settings</h4>
        <p class="page-header-desc mb-0">Authorized capital, face value and incorporation details.</p>
    </div>
    <a href="{{ route('settings.share-capital.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back</a>
</div>

<form method="POST" action="{{ route('settings.share-capital.settings.update') }}">
    @csrf @method('PUT')
    <div class="admin-card p-4 mb-3">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Authorized Capital *</label>
                <input type="number" step="0.01" min="0" name="authorized_capital" value="{{ old('authorized_capital', $institute->authorized_capital ?? 0) }}" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Share Face Value *</label>
                <input type="number" step="0.01" min="0.01" name="share_face_value" value="{{ old('share_face_value', $institute->share_face_value ?? 10) }}" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Incorporation Date</label>
                <input type="date" name="incorporation_date" value="{{ old('incorporation_date', $institute->incorporation_date?->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Registration No (CIN)</label>
                <input type="text" name="registration_no" value="{{ old('registration_no', $institute->registration_no) }}" class="form-control">
            </div>
        </div>
    </div>
    <button class="btn btn-primary">Save Settings</button>
</form>

@endsection
