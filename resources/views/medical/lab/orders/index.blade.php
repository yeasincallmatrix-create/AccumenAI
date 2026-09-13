@extends('layouts.institute')

@section('title', 'Lab Orders — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Lab Orders</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.lab.orders.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Order
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Status</option>
                        @foreach(['ordered' => 'Ordered', 'collected' => 'Collected', 'processing' => 'Processing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="patient_id" class="form-select" onchange="guardTdateSubmit(this)">
                        <option value="">All Patients</option>
                        @foreach($patients as $patient)
                            <option value="{{ $patient->id }}" @selected((string) request('patient_id') === (string) $patient->id)>
                                {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <x-tdate-input name="from_date" :value="request('from_date')" class="form-control" onchange="guardTdateSubmit(this)" />
                </div>
                <div class="col-md-2">
                    <x-tdate-input name="to_date" :value="request('to_date')" class="form-control" onchange="guardTdateSubmit(this)" />
                </div>
                <div class="col-md-1 text-end">
                    <a href="{{ route('medical.lab.orders.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Order No</th><th>Patient</th><th>Date</th><th>Priority</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td><strong>{{ clinical_no($order->order_number) }}</strong></td>
                        <td>{{ $order->patient->full_name ?? 'N/A' }}</td>
                        <td><x-tdate :value="$order->order_date" fallback="d M Y" /></td>
                        <td>
                            <span class="badge bg-{{ $order->priority === 'emergency' ? 'danger' : ($order->priority === 'urgent' ? 'warning text-dark' : 'secondary') }}">
                                {{ ucfirst($order->priority) }}
                            </span>
                        </td>
                        <td><span class="badge bg-{{ $order->status_class }}">{{ ucfirst($order->status) }}</span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.lab.orders.show', $order) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($order->status === 'ordered')
                                    <a href="{{ route('medical.lab.orders.edit', $order) }}" class="btn btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2"></i>
                            No lab orders found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $orders->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
