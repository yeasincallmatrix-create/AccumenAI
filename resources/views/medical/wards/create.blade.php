@extends('layouts.institute')

@section('title', 'Add Ward — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Ward</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.wards.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.wards.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="name">Ward Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" maxlength="100"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required placeholder="e.g. General Ward - Floor 1">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="type">Ward Type <span class="text-danger">*</span></label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">Select Type</option>
                            @foreach(['general' => 'General', 'cabin' => 'Cabin', 'icu' => 'ICU', 'ccu' => 'CCU', 'nicu' => 'NICU', 'private' => 'Private'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="total_beds">Total Beds <span class="text-danger">*</span></label>
                        <input type="number" id="total_beds" name="total_beds" min="1"
                               class="form-control @error('total_beds') is-invalid @enderror"
                               value="{{ old('total_beds') }}" required>
                        @error('total_beds')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="daily_rate">Daily Rate (৳) <span class="text-danger">*</span></label>
                        <input type="number" id="daily_rate" name="daily_rate" min="0" step="0.01"
                               class="form-control @error('daily_rate') is-invalid @enderror"
                               value="{{ old('daily_rate', 0) }}" required>
                        @error('daily_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="form-check-input" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Create Ward
                </button>
                <a href="{{ route('medical.wards.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
