@extends('layouts.institute')

@section('title', 'Edit Vaccine Stock — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-box-seam"></i> Edit Vaccine Stock</h4>
        <a href="{{ route('medical.vaccination.stocks.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>

    <form method="POST" action="{{ route('medical.vaccination.stocks.update', $stock) }}">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Vaccine</label>
                                <input type="text" class="form-control" value="{{ $stock->vaccineMaster->name ?? 'N/A' }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Batch Number</label>
                                <input type="text" class="form-control" value="{{ $stock->batch_number }}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity Received</label>
                                <input type="number" class="form-control" value="{{ $stock->quantity_received }}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity Used</label>
                                <input type="number" class="form-control" value="{{ $stock->quantity_used }}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity Available</label>
                                <input type="number" class="form-control" value="{{ $stock->quantity_available }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Storage Location</label>
                                <input type="text" name="storage_location" class="form-control" value="{{ old('storage_location', $stock->storage_location) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Temp Min (°C)</label>
                                <input type="number" name="temperature_min" class="form-control" value="{{ old('temperature_min', $stock->temperature_min) }}" step="0.1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Temp Max (°C)</label>
                                <input type="number" name="temperature_max" class="form-control" value="{{ old('temperature_max', $stock->temperature_max) }}" step="0.1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @foreach($statuses as $k => $v)
                                        <option value="{{ $k }}" {{ old('status', $stock->status) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Update Stock</button>
            </div>
        </div>
    </form>
</div>
@endsection
