@extends('layouts.standalone')
@section('title', 'Adjustment ' . ($adjustment->adjustment_no ?? '') . ' — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>{{ $adjustment->adjustment_no ?? 'Stock Adjustment' }} <span class="badge text-bg-{{ $adjustment->status === 'posted' ? 'success' : 'secondary' }}">{{ ucfirst($adjustment->status) }}</span> <span class="badge text-bg-light border">{{ ucfirst($adjustment->adjustment_type) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Adjustment Details</h6>
                <p class="mb-1"><strong>Warehouse:</strong> {{ $adjustment->warehouse?->name ?? '—' }} @if($adjustment->warehouse?->code) <small class="text-muted">({{ $adjustment->warehouse->code }})</small>@endif</p>
                <p class="mb-1"><strong>Type:</strong> {{ ucfirst($adjustment->adjustment_type) }}</p>
                <p class="mb-1"><strong>Reason:</strong> {{ $adjustment->reason }}</p>
                @if($adjustment->journal)<p class="mb-1"><strong>Journal:</strong> {{ $adjustment->journal->journal_number ?? $adjustment->journal_id }}</p>@endif
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Created:</strong> {{ $adjustment->created_at?->format('Y-m-d H:i') }}</p>
                @if($adjustment->posted_at)<p class="mb-1 text-success"><strong>Posted:</strong> {{ $adjustment->posted_at->format('Y-m-d H:i') }}</p>@endif
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>#</th><th>Item</th><th class="text-end">System Qty</th><th class="text-end">Counted Qty</th><th class="text-end">Difference</th><th class="text-end">Unit Cost</th></tr></thead>
            <tbody>
                @forelse($adjustment->items as $idx => $ai)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $ai->item?->name ?? '—' }} @if($ai->item?->sku) <small class="text-muted">({{ $ai->item->sku }})</small>@endif</td>
                        <td class="text-end">{{ number_format($ai->system_qty, 4) }}</td>
                        <td class="text-end">{{ number_format($ai->counted_qty, 4) }}</td>
                        <td class="text-end fw-semibold {{ $ai->difference > 0 ? 'text-success' : ($ai->difference < 0 ? 'text-danger' : '') }}">{{ number_format($ai->difference, 4) }}</td>
                        <td class="text-end">{{ number_format($ai->unit_cost, 4) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No adjustment items.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
