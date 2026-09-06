@extends('layouts.institute')

@section('title', 'Pharmacy Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Pharmacy Report</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Pharmacy
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Low Stock ({{ $lowStock->count() }})</h6></div>
            <div class="card-body">
                @if($lowStock->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($lowStock->take(10) as $row)
                        <li class="border-bottom py-2 d-flex justify-content-between">
                            <span>{{ $row->medicine->display_name }}</span>
                            <span class="badge bg-warning text-dark">{{ $row->available_stock }} / {{ $row->reorder_level }}</span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">All medicines above reorder level.</p>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Top Dispensed Medicines</h6></div>
            <div class="card-body">
                @if($topMedicines->count() > 0)
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Medicine</th><th class="text-end">Units</th></tr></thead>
                        <tbody>
                            @foreach($topMedicines as $row)
                            <tr>
                                <td>{{ $row->generic_name }}{{ $row->brand_name ? ' ('.$row->brand_name.')' : '' }}</td>
                                <td class="text-end">{{ $row->total_dispensed }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted mb-0">Nothing dispensed yet.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Expiry Watch</h6></div>
            <div class="card-body">
                <p>Critical (≤ 7d): <strong class="text-danger">{{ count($expiryAlerts['critical']) }}</strong></p>
                <p>Warning (8–30d): <strong class="text-warning">{{ count($expiryAlerts['warning']) }}</strong></p>
                <p class="mb-0">Expired on hand: <strong>{{ count($expiryAlerts['info']) }}</strong></p>
                <a href="{{ route('medical.pharmacy.expiry-alerts') }}" class="btn btn-sm btn-warning mt-2">
                    <i class="bi bi-alarm me-1"></i>Open Expiry Alerts
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
