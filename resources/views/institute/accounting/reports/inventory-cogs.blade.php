@extends('layouts.standalone')
@section('title', 'COGS Report — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Cost of Goods Sold Report</h4>
    <p>COGS breakdown by item for the selected period.</p>
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

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total COGS</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_cogs ?? 0, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Units Sold</small>
            <h4 class="mb-0 mt-1">{{ number_format($total_units ?? 0, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Average Unit Cost</small>
            <h4 class="mb-0 mt-1">{{ number_format($avg_unit_cost ?? 0, 2) }}</h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">COGS Detail ({{ $from }} to {{ $to }})</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Item</th>
                    <th class="text-end">Units Sold</th>
                    <th class="text-end">Avg Cost</th>
                    <th class="text-end">Total COGS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines ?? [] as $line)
                    <tr>
                        <td>{{ $line->sku }}</td>
                        <td>{{ $line->item_name }}</td>
                        <td class="text-end">{{ number_format($line->units_sold, 2) }}</td>
                        <td class="text-end">{{ number_format($line->avg_cost, 2) }}</td>
                        <td class="text-end">{{ number_format($line->total_cogs, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No COGS data found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
