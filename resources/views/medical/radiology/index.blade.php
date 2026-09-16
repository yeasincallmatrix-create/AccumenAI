@extends('layouts.institute')

@section('title', 'Radiology Orders — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-radioactive"></i> Radiology Orders</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.radiology.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Worklist
            </a>
            @if($user && $user->hasPermission('medical_radiology.create'))
                <a href="{{ route('medical.radiology.orders.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Order
                </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Order #, body part, patient" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\RadiologyOrder::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Modality</label>
                    <select name="modality" class="form-select">
                        <option value="">All Modalities</option>
                        @foreach(\App\Models\Medical\RadiologyOrder::MODALITIES as $key => $label)
                            <option value="{{ $key }}" @selected(request('modality') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <x-tdate-input name="from_date" :value="request('from_date')" />
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <x-tdate-input name="to_date" :value="request('to_date')" />
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
                </div>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Patient</th>
                            <th>Study</th>
                            <th>Doctor</th>
                            <th>Scheduled</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    <strong>{{ $order->order_number }}</strong>
                                    @if($order->is_urgent)
                                        <span class="badge bg-danger ms-1" style="font-size:9px;">URGENT</span>
                                    @endif
                                </td>
                                <td>{{ $order->patient?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $order->modalityLabel() }}</span>
                                    {{ $order->body_part }}
                                    @if($order->laterality)
                                        <small class="text-muted">({{ ucfirst($order->laterality) }})</small>
                                    @endif
                                </td>
                                <td>{{ $order->doctor?->name ?? '-' }}</td>
                                <td>
                                    @if($order->scheduled_at)
                                        <x-tdate :value="$order->scheduled_at" />
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.radiology.orders.show', $order) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($user && $user->hasPermission('medical_radiology.edit') && $order->isPending())
                                            <a href="{{ route('medical.radiology.orders.edit', $order) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-radioactive fs-2 d-block mb-2"></i>
                                    No radiology orders found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $orders->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
