@extends('layouts.institute')

@section('title', 'Dispensing Queue — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Dispensing Queue</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Pharmacy
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($prescriptions->count() > 0)
            @foreach($prescriptions as $prescription)
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <strong>{{ clinical_no($prescription->prescription_number) }}</strong>
                        <span class="text-muted">· {{ $prescription->patient->full_name ?? 'N/A' }}</span>
                        <span class="text-muted">· <x-tdate :value="$prescription->prescription_date" fallback="d M Y" /></span>
                    </div>
                    <form action="{{ route('medical.pharmacy.dispense.batch', $prescription) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success"
                                onclick="return confirm('Dispense all available items of this prescription?')">
                            <i class="bi bi-check-all me-1"></i>Dispense All
                        </button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Medicine</th><th>Qty</th><th>Stock</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @foreach($prescription->items as $item)
                                <tr>
                                    <td>{{ $item->medicine_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>
                                        @if(!$item->medicine_id)
                                            <span class="badge bg-secondary">Free text</span>
                                        @elseif(($item->is_available ?? false))
                                            <span class="badge bg-success">{{ $item->available_stock }} available</span>
                                        @else
                                            <span class="badge bg-danger">Short ({{ $item->available_stock ?? 0 }})</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $item->status === 'dispensed' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($item->status === 'pending')
                                            <a href="{{ route('medical.pharmacy.dispense.show', $item->id) }}"
                                               class="btn btn-sm btn-primary">Dispense</a>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach
        @else
            <p class="text-muted mb-0 text-center py-4">
                <i class="bi bi-check-circle fs-2 d-block mb-2"></i>
                No pending prescriptions. The queue is clear.
            </p>
        @endif
    </div>
</div>
@endsection
