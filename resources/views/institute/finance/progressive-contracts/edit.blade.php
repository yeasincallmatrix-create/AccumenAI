@extends('layouts.standalone')

@section('title', 'Edit Contract ' . $progressive_contract->contract_number . ' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Edit Contract {{ $progressive_contract->contract_number }}</h4>
    <p>Update contract details. Total value and billing cannot be changed after invoices are issued.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.progressive-contracts.update', $progressive_contract) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="title" value="{{ old('title', $progressive_contract->title) }}" required maxlength="255">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control form-control-sm" name="description" rows="2">{{ old('description', $progressive_contract->description) }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Expected End Date</label>
                <input type="date" class="form-control form-control-sm" name="expected_end_date" value="{{ old('expected_end_date', $progressive_contract->expected_end_date?->format('Y-m-d')) }}">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control form-control-sm" name="notes" rows="2">{{ old('notes', $progressive_contract->notes) }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Update contract</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.progressive-contracts.show', $progressive_contract) }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection
