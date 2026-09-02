@extends('layouts.standalone')
@section('title', 'Inventory Movements — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Inventory Movement Ledger</h4>
    <p>Track all inventory in and out movements for the selected period.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Total Movements</small>
            <h5 class="mb-0">{{ number_format($summary['total_movements']) }}</h5>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Inbound</small>
            <h5 class="mb-0 text-success">{{ number_format($summary['inbound_count']) }}</h5>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Outbound</small>
            <h5 class="mb-0 text-danger">{{ number_format($summary['outbound_count']) }}</h5>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Net Value</small>
            <h5 class="mb-0">{{ number_format($summary['inbound_value'] - $summary['outbound_value'], 2) }}</h5>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Warehouse</th>
                    <th>Type</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Unit Cost</th>
                    <th class="text-end">Total Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td>{{ $m->occurred_at?->format('Y-m-d') }}</td>
                        <td>{{ $m->item?->name ?? '-' }}</td>
                        <td>{{ $m->warehouse?->name ?? '-' }}</td>
                        <td><span class="badge bg-{{ $m->quantity > 0 ? 'success' : 'danger' }}">{{ $m->movement_type }}</span></td>
                        <td class="text-end">{{ number_format(abs((float) $m->quantity), 2) }}</td>
                        <td class="text-end">{{ number_format((float) $m->unit_cost, 2) }}</td>
                        <td class="text-end">{{ number_format(abs((float) $m->quantity) * (float) $m->unit_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No inventory movements found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
