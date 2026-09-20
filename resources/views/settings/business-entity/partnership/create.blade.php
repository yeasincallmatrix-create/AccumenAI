@extends('layouts.institute')

@section('title', 'Add Partner')

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
        <h4 class="page-header-title"><i class="bi bi-person-plus me-2"></i>Add Partner</h4>
    </div>
    <a href="{{ route('settings.business-entity.partnership.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Cancel</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('settings.business-entity.partnership.store') }}">
    @csrf
    <div class="admin-card p-4 mb-3">
        <div class="mb-3">
            <label class="form-label">Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email') }}" class="form-control"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" value="{{ old('phone') }}" class="form-control"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">NID</label><input type="text" name="nid" value="{{ old('nid') }}" class="form-control"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Joined At</label><input type="date" name="joined_at" value="{{ old('joined_at', now()->format('Y-m-d')) }}" class="form-control"></div>
        </div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Capital *</label><input type="number" step="0.01" min="0" name="capital" value="{{ old('capital', 0) }}" class="form-control" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">Share % *</label><input type="number" step="0.01" min="0" max="100" name="share_percent" value="{{ old('share_percent') }}" class="form-control" required></div>
        </div>
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
            <label for="is_active" class="form-check-label">Active</label>
        </div>
    </div>
    <button class="btn btn-primary">Save Partner</button>
</form>

@endsection
