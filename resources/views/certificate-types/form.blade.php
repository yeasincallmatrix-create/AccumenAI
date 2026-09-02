@extends('layouts.institute')

@section('title', ($type ? 'Edit' : 'Create') . ' Certificate Type — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $type ? 'Edit Certificate Type' : 'Create Certificate Type' }}</h4>
        <p class="page-header-desc mb-0">Define a certificate category for your institute.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('certificate-types.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="admin-card">
    <form action="{{ $type ? route('certificate-types.update', $type) : route('certificate-types.store') }}" method="POST">
        @csrf
        @if ($type)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Type Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $type->name ?? '') }}" required maxlength="100" placeholder="e.g. Course Completion Certificate">
            </div>
            <div class="col-md-3">
                <label for="display_order" class="form-label">Display Order</label>
                <input type="number" class="form-control" id="display_order" name="display_order" value="{{ old('display_order', $type->display_order ?? 0) }}" min="0" max="65535">
            </div>
            <div class="col-md-3">
                <label for="is_active" class="form-label">Status</label>
                <select class="form-select" id="is_active" name="is_active">
                    <option value="1" @selected(old('is_active', $type->is_active ?? true))>Active</option>
                    <option value="0" @selected(!old('is_active', $type->is_active ?? true))>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="500" placeholder="Optional description of when this certificate type is used">{{ old('description', $type->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>{{ $type ? 'Update Type' : 'Create Type' }}
            </button>
            <a href="{{ route('certificate-types.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
    </form>
</div>
@endsection
