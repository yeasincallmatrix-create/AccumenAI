@extends('layouts.institute')

@section('title', 'Stock Batch — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Batch {{ $stock->batch_number }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.pharmacy.stock.edit', $stock) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.stock.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Batch Info</h6></div>
            <div class="card-body">
                <p><strong>Medicine:</strong>
                    @if($stock->medicine)
                        <a href="{{ route('medical.pharmacy.medicines.show', $stock->medicine) }}">
                            {{ $stock->medicine->display_name }}
                        </a>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Batch:</strong> {{ $stock->batch_number }}</p>
                <p><strong>Mfg / Expiry:</strong>
                    {{ $stock->manufacturing_date?->format('d M Y') ?? '—' }} /
                    {{ $stock->expiry_date?->format('d M Y') }}
                    @if($stock->is_expired)
                        <span class="badge bg-danger">Expired</span>
                    @elseif($stock->days_to_expiry <= 30)
                        <span class="badge bg-warning text-dark">{{ $stock->days_to_expiry }} days left</span>
                    @endif
                </p>
                <p><strong>Received / Current:</strong> {{ $stock->quantity_received }} / {{ $stock->current_quantity }}</p>
                <p><strong>Buy / Sell:</strong> ৳{{ number_format($stock->purchase_price, 2) }} / ৳{{ number_format($stock->selling_price, 2) }}</p>
                <p class="mb-0"><strong>Supplier Invoice:</strong> {{ $stock->supplier_invoice_no ?? '—' }}</p>
                @if($stock->notes)<hr><p class="mb-0"><strong>Notes:</strong><br>{{ $stock->notes }}</p>@endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Stock Adjustment</h6></div>
            <div class="card-body">
                <p class="text-muted small">Correct the quantity after a physical count, damage or expiry write-off. The reason is appended to the batch notes.</p>
                <form action="{{ route('medical.pharmacy.stock.adjust', $stock) }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label" for="new_quantity">New Quantity <span class="text-danger">*</span></label>
                                <input type="number" id="new_quantity" name="new_quantity" min="0"
                                       class="form-control" value="{{ $stock->current_quantity }}" required>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                                <input type="text" id="reason" name="reason" maxlength="500"
                                       class="form-control" required placeholder="e.g. Physical count Jan">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm"
                            onclick="return confirm('Adjust stock quantity?')">
                        <i class="bi bi-sliders me-1"></i>Apply Adjustment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
