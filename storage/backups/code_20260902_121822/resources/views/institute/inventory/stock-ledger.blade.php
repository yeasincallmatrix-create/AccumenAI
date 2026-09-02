@extends('layouts.standalone')
@section('title', 'Stock Ledger — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Stock Ledger</h4>
    <p>Inventory movement audit trail. Shows every stock change with item, warehouse, type, quantity and running balance.</p>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.stock-ledger') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Warehouse</label>
                <select class="form-select form-select-sm" name="warehouse_id" onchange="this.form.submit()">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" @selected(request('warehouse_id')==$wh->id)>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Movement Type</label>
                <select class="form-select form-select-sm" name="movement_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    @foreach(['receipt','issue','transfer_in','transfer_out','adjustment_in','adjustment_out','return_in','return_out','wastage_out'] as $type)
                        <option value="{{ $type }}" @selected(request('movement_type')===$type)>{{ ucfirst(str_replace('_',' ',$type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ request('from') }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ request('to') }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.stock-ledger') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Movement #</th>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Warehouse</th>
                    <th>Type</th>
                    <th class="text-end">Qty In</th>
                    <th class="text-end">Qty Out</th>
                    <th class="text-end">Unit Cost</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $m)
                    <tr>
                        <td class="text-muted">{{ $movements->firstItem() + $loop->index }}</td>
                        <td><span class="fw-semibold">{{ $m->movement_no }}</span></td>
                        <td>{{ $m->occurred_at?->format('Y-m-d') }}</td>
                        <td>{{ $m->item?->name ?? '—' }}</td>
                        <td>{{ $m->warehouse?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', $m->movement_type)) }}</span></td>
                        <td class="text-end text-success fw-semibold">{{ $m->quantity > 0 ? number_format($m->quantity, 2) : '—' }}</td>
                        <td class="text-end text-danger fw-semibold">{{ $m->quantity < 0 ? number_format(abs($m->quantity), 2) : '—' }}</td>
                        <td class="text-end">{{ number_format($m->unit_cost, 2) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($m->reason, 40) ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No movements found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movements->hasPages())
        <div class="p-2 border-top">{{ $movements->links() }}</div>
    @endif
</div>
@endsection
