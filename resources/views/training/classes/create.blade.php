@extends('layouts.institute')

@section('title', 'Create Class — Training — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('training.classes.index') }}" class="text-decoration-none">Classes</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
</nav>

<div class="page-header">
    <h4 class="page-header-title">Create Class</h4>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('training.classes.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Class Name *</label>
                <input type="text" id="name" name="name" class="form-control" maxlength="150" value="{{ old('name') }}" required>
                @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="code">Code</label>
                <input type="text" id="code" name="code" class="form-control" maxlength="50" value="{{ old('code') }}">
                @error('code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    <option value="draft" @selected(old('status') === 'draft')>Draft</option>
                </select>
                @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
            <a href="{{ route('training.classes.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
