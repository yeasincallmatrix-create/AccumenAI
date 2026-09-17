@extends('layouts.institute')

@section('title', 'Edit Vaccine — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-shield-check"></i> Edit Vaccine</h4>
        <a href="{{ route('medical.vaccination.vaccine-masters.show', $vaccine) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.vaccination.vaccine-masters.update', $vaccine) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Vaccine Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $vaccine->name) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control" value="{{ old('code', $vaccine->code) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Short Name</label>
                                <input type="text" name="short_name" class="form-control" value="{{ old('short_name', $vaccine->short_name) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $k => $v)
                                        <option value="{{ $k }}" {{ old('category', $vaccine->category) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Doses in Series</label>
                                <input type="number" name="doses_in_series" class="form-control" value="{{ old('doses_in_series', $vaccine->doses_in_series) }}" min="1" max="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Default Fee</label>
                                <input type="number" name="default_fee" class="form-control" value="{{ old('default_fee', $vaccine->default_fee) }}" step="0.01" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Protects Against</label>
                                <input type="text" name="protects_against" class="form-control" value="{{ old('protects_against', $vaccine->protects_against) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2">{{ old('description', $vaccine->description) }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ old('is_active', $vaccine->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Administration</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Route</label>
                            <select name="route" class="form-select">
                                <option value="">Select Route</option>
                                @foreach($routes as $k => $v)
                                    <option value="{{ $k }}" {{ old('route', $vaccine->route) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Site</label>
                            <select name="site" class="form-select">
                                <option value="">Select Site</option>
                                @foreach($sites as $k => $v)
                                    <option value="{{ $k }}" {{ old('site', $vaccine->site) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dose Volume</label>
                            <input type="text" name="dose_volume" class="form-control" value="{{ old('dose_volume', $vaccine->dose_volume) }}">
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white"><h6 class="mb-0">Age Eligibility</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Min Age (days)</label>
                            <input type="number" name="min_age_days" class="form-control" value="{{ old('min_age_days', $vaccine->min_age_days) }}" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Age (days)</label>
                            <input type="number" name="max_age_days" class="form-control" value="{{ old('max_age_days', $vaccine->max_age_days) }}" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Interval Between Doses (days)</label>
                            <input type="number" name="interval_days_min" class="form-control" value="{{ old('interval_days_min', $vaccine->interval_days_min) }}" min="0">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Update Vaccine</button>
            </div>
        </div>
    </form>
</div>
@endsection
