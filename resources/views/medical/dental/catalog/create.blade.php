@extends('layouts.institute')

@section('title', 'Add Catalog Entry — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-book"></i> Add Catalog Entry</h4>
        <a href="{{ route('medical.dental.catalog.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('medical.dental.catalog.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Code (ADA/CDT)</label>
                            <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="e.g. D2391">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                @foreach($categories as $k => $v)
                                    <option value="{{ $k }}" {{ old('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Body Site</label>
                            <input type="text" name="body_site" class="form-control" value="{{ old('body_site') }}" placeholder="e.g. tooth, gum, full_mouth">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Default Fee</label>
                                <input type="number" name="default_fee" class="form-control" value="{{ old('default_fee', 0) }}" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Default Duration (min)</label>
                                <input type="number" name="default_duration_minutes" class="form-control" value="{{ old('default_duration_minutes', 30) }}" min="1">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Create Entry</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
