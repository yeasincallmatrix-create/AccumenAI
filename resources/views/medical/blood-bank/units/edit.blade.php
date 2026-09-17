@extends('layouts.institute')

@section('title', 'Edit Blood Unit — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-pencil"></i> Edit {{ $unit->unit_number }}</h4>
        <a href="{{ route('medical.blood-bank.units.show', $unit) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('medical.blood-bank.units.update', $unit) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-droplet"></i> Unit Details</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Blood Group *</label>
                                <select name="blood_group" class="form-select @error('blood_group') is-invalid @enderror" required>
                                    @foreach($bloodGroups as $key => $label)
                                        <option value="{{ $key }}" @selected(old('blood_group', $unit->blood_group) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('blood_group')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Component *</label>
                                <select name="component" class="form-select @error('component') is-invalid @enderror" required>
                                    @foreach($components as $key => $label)
                                        <option value="{{ $key }}" @selected(old('component', $unit->component) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('component')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Volume (ml)</label>
                                <input type="number" name="volume_ml" class="form-control @error('volume_ml') is-invalid @enderror" value="{{ old('volume_ml', $unit->volume_ml) }}" min="0">
                                @error('volume_ml')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}" @selected(old('status', $unit->status) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header"><i class="bi bi-calendar"></i> Dates & Notes</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Collection Date *</label>
                                <x-tdate-input name="collection_date" :value="old('collection_date', $unit->collection_date?->format('Y-m-d'))" />
                                @error('collection_date')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expiry Date *</label>
                                <x-tdate-input name="expiry_date" :value="old('expiry_date', $unit->expiry_date?->format('Y-m-d'))" />
                                @error('expiry_date')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input type="checkbox" name="crossmatch_required" value="1" class="form-check-input" id="crossmatch_required" @checked(old('crossmatch_required', $unit->crossmatch_required))>
                                    <label class="form-check-label" for="crossmatch_required">Crossmatch Required</label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $unit->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Changes</button>
                <a href="{{ route('medical.blood-bank.units.show', $unit) }}" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
