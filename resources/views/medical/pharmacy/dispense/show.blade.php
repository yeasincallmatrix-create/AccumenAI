@extends('layouts.institute')

@section('title', 'Dispense Medicine — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Dispense — {{ $prescriptionItem->medicine_name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.dispense.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Queue
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Prescription Item</h6></div>
            <div class="card-body">
                <p><strong>Rx:</strong> {{ clinical_no($prescriptionItem->prescription->prescription_number ?? '') }}</p>
                <p><strong>Patient:</strong> {{ $prescriptionItem->prescription->patient->full_name ?? 'N/A' }}</p>
                <p><strong>Medicine:</strong> {{ $prescriptionItem->medicine_name }}</p>
                <p><strong>Dosage / Frequency:</strong> {{ $prescriptionItem->dosage }} / {{ $prescriptionItem->frequency }}</p>
                <p class="mb-0"><strong>Quantity Required:</strong> {{ $prescriptionItem->quantity }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Dispense From Stock</h6></div>
            <div class="card-body">
                @if($unmapped)
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This is a free-text item with no catalog medicine linked, so it cannot be dispensed from stock.
                    </div>
                @elseif(count($batches) === 0)
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle me-1"></i>
                        Insufficient unexpired stock to cover {{ $prescriptionItem->quantity }} unit(s).
                    </div>
                @else
                    <form action="{{ route('medical.pharmacy.dispense', $prescriptionItem->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="prescription_item_id" value="{{ $prescriptionItem->id }}">
                        <div class="mb-3">
                            <label class="form-label">Batch (FEFO order)</label>
                            @foreach($batches as $i => $batch)
                                <div class="form-check">
                                    <input type="radio" id="batch-{{ $batch['stock_id'] }}" name="stock_id"
                                           value="{{ $batch['stock_id'] }}" class="form-check-input"
                                           @checked($i === 0) required>
                                    <label class="form-check-label" for="batch-{{ $batch['stock_id'] }}">
                                        {{ $batch['batch_number'] }} — {{ $batch['quantity'] }} unit(s),
                                        exp <x-tdate :value="$batch['expiry_date']" fallback="d M Y" />
                                    </label>
                                </div>
                            @endforeach
                            @error('stock_id')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="quantity_dispensed">Quantity (must equal {{ $prescriptionItem->quantity }})</label>
                            <input type="number" id="quantity_dispensed" name="quantity_dispensed" min="1"
                                   class="form-control @error('quantity_dispensed') is-invalid @enderror"
                                   value="{{ old('quantity_dispensed', $prescriptionItem->quantity) }}" required>
                            @error('quantity_dispensed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notes">Notes</label>
                            <input type="text" id="notes" name="notes" class="form-control" value="{{ old('notes') }}">
                        </div>
                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('Dispense {{ $prescriptionItem->quantity }} unit(s)? Stock will be deducted.') ">
                            <i class="bi bi-check-all me-1"></i>Confirm Dispense
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
