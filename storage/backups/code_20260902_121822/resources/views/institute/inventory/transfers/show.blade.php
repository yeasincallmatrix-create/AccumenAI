@extends('layouts.standalone')
@section('title', 'Transfer ' . ($transfer->transfer_no ?? '') . ' — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>{{ $transfer->transfer_no ?? 'Stock Transfer' }} <span class="badge text-bg-{{ $transfer->status === 'posted' ? 'success' : 'secondary' }}">{{ ucfirst($transfer->status) }}</span></h4>
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.transfers.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Transfer Details</h6>
                <p class="mb-1"><strong>From:</strong> {{ $transfer->sourceWarehouse?->name ?? '—' }} @if($transfer->sourceWarehouse?->code) <small class="text-muted">({{ $transfer->sourceWarehouse->code }})</small>@endif</p>
                <p class="mb-1"><strong>To:</strong> {{ $transfer->destinationWarehouse?->name ?? '—' }} @if($transfer->destinationWarehouse?->code) <small class="text-muted">({{ $transfer->destinationWarehouse->code }})</small>@endif</p>
                @if($transfer->notes)<p class="mb-1"><strong>Notes:</strong> {{ $transfer->notes }}</p>@endif
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Created:</strong> {{ $transfer->created_at?->format('Y-m-d H:i') }}</p>
                @if($transfer->posted_at)<p class="mb-1 text-success"><strong>Posted:</strong> {{ $transfer->posted_at->format('Y-m-d H:i') }}</p>@endif
                @if($transfer->approved_at)<p class="mb-1"><strong>Approved:</strong> {{ $transfer->approved_at->format('Y-m-d H:i') }}</p>@endif
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>#</th><th>Item</th><th class="text-end">Quantity</th><th class="text-end">Unit Cost</th></tr></thead>
            <tbody>
                @forelse($transfer->items as $idx => $ti)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $ti->item?->name ?? '—' }} @if($ti->item?->sku) <small class="text-muted">({{ $ti->item->sku }})</small>@endif</td>
                        <td class="text-end">{{ number_format($ti->quantity, 4) }}</td>
                        <td class="text-end">{{ number_format($ti->unit_cost, 4) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No transfer items.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
