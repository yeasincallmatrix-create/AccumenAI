@extends('layouts.standalone')

@section('title', ($isEdit ? 'Edit' : 'New') . ' Lead — AccumenAI')
@section('page_title', ($isEdit ? 'Edit' : 'New') . ' Lead')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $isEdit ? 'Edit' : 'New' }} Lead</h4>
    <a href="{{ route('sales.leads.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
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

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('sales.leads.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $lead?->first_name) }}" class="form-control" required maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" value="{{ old('last_name', $lead?->last_name) }}" class="form-control" required maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email', $lead?->email) }}" class="form-control" maxlength="191">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    @include('partials.phone', ['name' => 'phone', 'id' => 'sales_lead_phone', 'value' => old('phone', $lead?->phone)])
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status_id" class="form-select">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->id }}" {{ (old('status_id', $lead?->status_id) == $s->id) ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Source</label>
                    <select name="source_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach ($sources as $src)
                            <option value="{{ $src->id }}" {{ (old('source_id', $lead?->source_id) == $src->id) ? 'selected' : '' }}>{{ $src->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estimated Value</label>
                    <input type="number" name="value_amount" value="{{ old('value_amount', $lead?->value_amount) }}" class="form-control" min="0" step="0.01">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Interest Summary</label>
                    <textarea name="interest_summary" class="form-control" rows="4" maxlength="2000">{{ old('interest_summary', $lead?->interest_summary) }}</textarea>
                </div>
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-check-lg me-1"></i>Create Lead</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
