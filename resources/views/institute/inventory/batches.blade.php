@extends('layouts.standalone')
@section('title', 'Batch Tracker — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Batch Tracker</h4>
    <p>Track inventory batches with expiry status. Filter by valid, near-expiry (within 30 days), or expired batches.</p>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.batches') }}">
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
                <label class="form-label mb-1">Expiry Status</label>
                <select class="form-select form-select-sm" name="expiry_status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="valid" @selected(request('expiry_status')==='valid')>Valid</option>
                    <option value="near_expiry" @selected(request('expiry_status')==='near_expiry')>Near Expiry</option>
                    <option value="expired" @selected(request('expiry_status')==='expired')>Expired</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.batches') }}">Reset</a>
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
                    <th>Batch #</th>
                    <th>Item</th>
                    <th>Warehouse</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Unit Cost</th>
                    <th>Manufacture Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $idx => $batch)
                    <tr>
                        <td class="text-muted">{{ $idx + 1 }}</td>
                        <td><span class="fw-semibold">{{ $batch['batch_number'] }}</span></td>
                        <td>{{ $batch['item_name'] ?? '—' }}</td>
                        <td>{{ $batch['warehouse_name'] ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($batch['quantity'], 2) }}</td>
                        <td class="text-end">{{ number_format($batch['unit_cost'], 2) }}</td>
                        <td>{{ $batch['manufacture_date'] ?? '—' }}</td>
                        <td>{{ $batch['expiry_date'] ?? '—' }}</td>
                        <td>
                            @if($batch['status'] === 'expired')
                                <span class="badge text-bg-danger">Expired</span>
                            @elseif($batch['status'] === 'near_expiry')
                                <span class="badge text-bg-warning text-dark">Near Expiry</span>
                            @else
                                <span class="badge text-bg-success">Valid</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No batches found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
