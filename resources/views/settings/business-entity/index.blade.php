@extends('layouts.institute')

@section('title', 'Business Entity Type')

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
        <h4 class="page-header-title"><i class="bi bi-building me-2"></i>Business Entity Type</h4>
        <p class="page-header-desc mb-0">Select your business entity type for tailored settings.</p>
    </div>
    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back to Settings</a>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('settings.business-entity.update') }}">
    @csrf
    @method('PUT')
    <div class="admin-card p-4 mb-3">
        <div class="row g-3">
            @foreach(\App\Enums\BusinessEntityType::cases() as $case)
                <div class="col-md-4">
                    <label class="border rounded p-3 d-block h-100 {{ $type === $case ? 'border-primary bg-light' : '' }}">
                        <input type="radio" name="business_entity_type" value="{{ $case->value }}" {{ $type === $case ? 'checked' : '' }} class="form-check-input me-2">
                        <strong>{{ $case->label() }}</strong>
                        <div class="text-muted small mt-1">{{ $case->description() }}</div>
                    </label>
                </div>
            @endforeach
        </div>
        @error('business_entity_type')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
        <button type="submit" class="btn btn-primary mt-3">Save &amp; Continue</button>
    </div>
</form>

@endsection
