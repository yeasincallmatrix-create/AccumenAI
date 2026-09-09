@extends('layouts.institute')

@section('title', 'Medicine Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $medicine->display_name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-success" href="{{ route('medical.pharmacy.stock.create', ['medicine_id' => $medicine->id]) }}">
            <i class="bi bi-plus-lg me-1"></i>Add Stock
        </a>
        <a class="btn btn-warning" href="{{ route('medical.pharmacy.medicines.edit', $medicine) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.medicines.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Catalog Info</h6></div>
            <div class="card-body">
                <p><strong>Code:</strong> {{ $medicine->code }}</p>
                <p><strong>Generic:</strong> {{ $medicine->generic_name }}</p>
                <p><strong>Brand:</strong> {{ $medicine->brand_name ?? '—' }}</p>
                <p><strong>Category:</strong> {{ $medicine->category ?? '—' }}</p>
                <p><strong>Form / Strength:</strong> {{ $medicine->dosage_form }}{{ $medicine->strength ? ' '.$medicine->strength : '' }}</p>
                <p><strong>Unit / Pack:</strong> {{ $medicine->unit }} × {{ $medicine->pack_size }}</p>
                <p><strong>Buy / Sell:</strong> ৳{{ number_format($medicine->purchase_price, 2) }} / ৳{{ number_format($medicine->selling_price, 2) }}</p>
                <p class="mb-0"><strong>Reorder Level / Qty:</strong> {{ $medicine->reorder_level }} / {{ $medicine->reorder_quantity }}</p>
                <hr>
                <p class="mb-1"><strong>DGDA Code:</strong>
                    @if($medicine->dgda_code)
                        <span class="badge bg-success">DGDA: {{ $medicine->dgda_code }}</span>
                    @else
                        <span class="badge bg-warning text-dark" title="No DGDA code — registry sync pending">DGDA sync pending</span>
                    @endif
                </p>
                @if($medicine->dgda_dar_number)
                    <p class="mb-1"><strong>DAR No:</strong> <code>{{ $medicine->dgda_dar_number }}</code></p>
                @endif
                @if($medicine->dgda_concept_id)
                    <p class="mb-1"><strong>Registry Concept:</strong> <code>{{ $medicine->dgda_concept_id }}</code></p>
                @endif
                @if($medicine->dgda_status)
                    <p class="mb-1"><strong>Sync Status:</strong>
                        <span class="badge bg-{{ $medicine->dgda_status === 'synced' ? 'success' : ($medicine->dgda_status === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($medicine->dgda_status) }}</span>
                        @if($medicine->dgda_synced_at)
                            <small class="text-muted">· {{ $medicine->dgda_synced_at->format('d M Y, h:i A') }}</small>
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Stock Summary</h6></div>
            <div class="card-body text-center">
                <h2>{{ $medicine->available_stock }} <small class="text-muted fs-6">available</small></h2>
                <p class="text-muted">Total across batches: {{ $medicine->total_stock }}</p>
                @if($medicine->available_stock <= $medicine->reorder_level)
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        At or below reorder level ({{ $medicine->reorder_level }}). Suggested order: {{ $medicine->reorder_quantity }} units.
                    </div>
                @endif
            </div>
        </div>

        @if($medicine->side_effects || $medicine->contraindications || $medicine->storage_conditions)
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Safety Info</h6></div>
            <div class="card-body">
                @if($medicine->side_effects)<p><strong>Side Effects:</strong> {{ $medicine->side_effects }}</p>@endif
                @if($medicine->contraindications)<p><strong>Contraindications:</strong> {{ $medicine->contraindications }}</p>@endif
                @if($medicine->storage_conditions)<p class="mb-0"><strong>Storage:</strong> {{ $medicine->storage_conditions }}</p>@endif
            </div>
        </div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Batches ({{ $medicine->stocks->count() }})</h6></div>
    <div class="card-body">
        @if($medicine->stocks->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Batch</th><th>Expiry</th><th>Qty</th><th>Sell Price</th><th></th></tr></thead>
                    <tbody>
                        @foreach($medicine->stocks as $batch)
                        <tr class="{{ $batch->is_expired ? 'table-danger' : '' }}">
                            <td>{{ $batch->batch_number }}</td>
                            <td>
                                <x-tdate :value="$batch->expiry_date" fallback="d M Y" />
                                @if($batch->is_expired)
                                    <span class="badge bg-danger">Expired</span>
                                @elseif($batch->days_to_expiry <= 30)
                                    <span class="badge bg-warning text-dark">{{ $batch->days_to_expiry }}d left</span>
                                @endif
                            </td>
                            <td>{{ $batch->current_quantity }}</td>
                            <td>৳{{ number_format($batch->selling_price, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('medical.pharmacy.stock.show', $batch) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No batches recorded.</p>
        @endif
    </div>
</div>
@endsection
