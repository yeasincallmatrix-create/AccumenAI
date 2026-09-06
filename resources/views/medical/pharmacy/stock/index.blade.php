@extends('layouts.institute')

@section('title', 'Pharmacy Stock — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Pharmacy Stock</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning me-1" href="{{ route('medical.pharmacy.expiry-alerts') }}">
            <i class="bi bi-alarm me-1"></i>Expiry Alerts
        </a>
        <a class="btn btn-primary" href="{{ route('medical.pharmacy.stock.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Add Stock
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="medicine_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Medicines</option>
                        @foreach($medicines as $medicine)
                            <option value="{{ $medicine->id }}" @selected((string) request('medicine_id') === (string) $medicine->id)>
                                {{ $medicine->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Batches</option>
                        <option value="available" @selected(request('status') === 'available')>Available</option>
                        <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                        <option value="empty" @selected(request('status') === 'empty')>Empty</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <a href="{{ route('medical.pharmacy.stock.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th>Qty</th>
                        <th>Sell Price</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stock as $batch)
                    <tr class="{{ $batch->is_expired ? 'table-danger' : '' }}">
                        <td>{{ $batch->medicine->display_name ?? 'N/A' }}</td>
                        <td><strong>{{ $batch->batch_number }}</strong></td>
                        <td>
                            {{ $batch->expiry_date?->format('d M Y') }}
                            @if($batch->is_expired)
                                <span class="badge bg-danger">Expired</span>
                            @elseif($batch->days_to_expiry <= 30)
                                <span class="badge bg-warning text-dark">{{ $batch->days_to_expiry }}d</span>
                            @endif
                        </td>
                        <td>{{ $batch->current_quantity }}</td>
                        <td>৳{{ number_format($batch->selling_price, 2) }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.pharmacy.stock.show', $batch) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.pharmacy.stock.edit', $batch) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-boxes fs-2 d-block mb-2"></i>
                            No stock batches found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $stock->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
