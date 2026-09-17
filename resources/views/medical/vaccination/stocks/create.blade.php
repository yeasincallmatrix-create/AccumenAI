@extends('layouts.institute')

@section('title', 'Add Vaccine Stock — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-box-seam"></i> Add Vaccine Stock</h4>
        <a href="{{ route('medical.vaccination.stocks.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.vaccination.stocks.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Vaccine *</label>
                                <select name="vaccine_master_id" class="form-select" required>
                                    <option value="">Select Vaccine</option>
                                    @foreach($vaccines as $v)
                                        <option value="{{ $v->id }}" {{ old('vaccine_master_id') == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Batch Number *</label>
                                <input type="text" name="batch_number" class="form-control" value="{{ old('batch_number') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Manufacture Date</label>
                                <input type="date" name="manufacture_date" class="form-control" value="{{ old('manufacture_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expiry Date *</label>
                                <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity Received *</label>
                                <input type="number" name="quantity_received" class="form-control" value="{{ old('quantity_received') }}" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Storage Location</label>
                                <input type="text" name="storage_location" class="form-control" value="{{ old('storage_location') }}" placeholder="e.g. Cold Room A">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Temp Min (°C)</label>
                                <input type="number" name="temperature_min" class="form-control" value="{{ old('temperature_min') }}" step="0.1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Temp Max (°C)</label>
                                <input type="number" name="temperature_max" class="form-control" value="{{ old('temperature_max') }}" step="0.1">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Stock</button>
            </div>
        </div>
    </form>
</div>
@endsection
