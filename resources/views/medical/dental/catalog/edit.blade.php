@extends('layouts.institute')

@section('title', 'Edit Catalog Entry — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-book"></i> Edit Catalog Entry</h4>
        <a href="{{ route('medical.dental.catalog.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('medical.dental.catalog.update', $catalog) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Code (ADA/CDT)</label>
                            <input type="text" name="code" class="form-control" value="{{ old('code', $catalog->code) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $catalog->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                @foreach($categories as $k => $v)
                                    <option value="{{ $k }}" {{ old('category', $catalog->category) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Body Site</label>
                            <input type="text" name="body_site" class="form-control" value="{{ old('body_site', $catalog->body_site) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $catalog->description) }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Default Fee</label>
                                <input type="number" name="default_fee" class="form-control" value="{{ old('default_fee', $catalog->default_fee) }}" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Default Duration (min)</label>
                                <input type="number" name="default_duration_minutes" class="form-control" value="{{ old('default_duration_minutes', $catalog->default_duration_minutes) }}" min="1">
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $catalog->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Entry</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
