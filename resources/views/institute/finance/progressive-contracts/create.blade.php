@extends('layouts.standalone')

@section('title', 'New Progressive Contract — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>New Progressive Contract</h4>
    <p>Create a contract for milestone-based or progress billing.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.progressive-contracts.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Client <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="party_id" required>
                    <option value="">— Select client —</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((string) old('party_id') === (string) $party->id)>{{ $party->name }}</option>
                    @endforeach
                </select>
                @error('party_id') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="title" value="{{ old('title') }}" required maxlength="255" placeholder="e.g. Website Redesign Project">
                @error('title') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control form-control-sm" name="description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Value <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="total_value" value="{{ old('total_value') }}" required>
                @error('total_value') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Currency</label>
                <input type="text" class="form-control form-control-sm" name="currency" value="{{ old('currency', 'BDT') }}" maxlength="3">
            </div>
            <div class="col-md-3">
                <label class="form-label">Retention %</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="retention_percentage" value="{{ old('retention_percentage', 0) }}">
                <small class="text-muted">Holdback amount billed on final invoice only.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tax Method</label>
                <select class="form-select form-select-sm" name="tax_method">
                    <option value="exclusive" @selected(old('tax_method') === 'exclusive')>Exclusive</option>
                    <option value="inclusive" @selected(old('tax_method') === 'inclusive')>Inclusive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Expected End Date</label>
                <input type="date" class="form-control form-control-sm" name="expected_end_date" value="{{ old('expected_end_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tax Group</label>
                <select class="form-select form-select-sm" name="tax_group_id">
                    <option value="">— None —</option>
                    @foreach ($taxGroups as $tg)
                        <option value="{{ $tg->id }}" @selected((string) old('tax_group_id') === (string) $tg->id)>{{ $tg->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control form-control-sm" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Create contract</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.progressive-contracts.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection
