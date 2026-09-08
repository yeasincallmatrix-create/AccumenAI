@extends('layouts.institute')

@section('title', 'Pharmacy — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Pharmacy</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-success me-1" href="{{ route('medical.pharmacy.dispense.index') }}">
            <i class="bi bi-check-all me-1"></i>Dispensing Queue
        </a>
        <a class="btn btn-primary" href="{{ route('medical.pharmacy.stock.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Add Stock
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Pending Scripts</h6>
            <h2 class="card-text">{{ $pending->count() }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">Low Stock Items</h6>
            <h2 class="card-text">{{ $lowStock->count() }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white"><div class="card-body">
            <h6 class="card-title">Expiry Alerts</h6>
            <h2 class="card-text">{{ $expirySummary['total'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Dispensed Today</h6>
            <h2 class="card-text">{{ $todayDispenses }}</h2>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Low Stock — Reorder Needed</h6>
                <a href="{{ route('medical.pharmacy.stock.index') }}" class="btn btn-sm btn-link">Stock</a>
            </div>
            <div class="card-body">
                @if($lowStock->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($lowStock->take(8) as $row)
                        <li class="border-bottom py-2 d-flex justify-content-between align-items-center">
                            <span>
                                <strong>{{ $row->medicine->display_name }}</strong>
                                <span class="text-muted small">({{ $row->available_stock }} / reorder at {{ $row->reorder_level }})</span>
                            </span>
                            <a href="{{ route('medical.pharmacy.stock.create', ['medicine_id' => $row->medicine->id]) }}"
                               class="btn btn-sm btn-warning">Order {{ $row->medicine->reorder_quantity }}</a>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">All medicines above reorder level.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Dispenses</h6>
                <a href="{{ route('medical.pharmacy.dispense.index') }}" class="btn btn-sm btn-link">Queue</a>
            </div>
            <div class="card-body">
                @if($recentDispenses->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($recentDispenses as $dispense)
                        <li class="border-bottom py-2">
                            <strong>{{ $dispense->stock->medicine->display_name ?? 'N/A' }}</strong>
                            <span class="text-muted">× {{ $dispense->quantity_dispensed }}</span>
                            <span class="text-muted small">→ {{ $dispense->prescriptionItem->prescription->patient->full_name ?? 'N/A' }}</span>
                            <span class="badge bg-secondary float-end"><x-tdate :value="$dispense->dispense_date" fallback="d M" /></span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">Nothing dispensed yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-4">
        <a href="{{ route('medical.pharmacy.medicines.index') }}" class="btn btn-outline-primary btn-lg w-100">
            <i class="bi bi-capsule me-1"></i>Medicine Catalog
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('medical.pharmacy.stock.index') }}" class="btn btn-outline-info btn-lg w-100">
            <i class="bi bi-boxes me-1"></i>Stock Ledger
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('medical.pharmacy.expiry-alerts') }}" class="btn btn-outline-warning btn-lg w-100">
            <i class="bi bi-alarm me-1"></i>Expiry Alerts
            @if($expirySummary['total'] > 0)
                <span class="badge bg-danger">{{ $expirySummary['total'] }}</span>
            @endif
        </a>
    </div>
</div>
@endsection
