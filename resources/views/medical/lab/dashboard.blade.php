@extends('layouts.institute')

@section('title', 'Lab Dashboard — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Laboratory</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.lab.orders.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Order
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.lab.tests.index') }}">
            <i class="bi bi-clipboard2-pulse me-1"></i>Test Catalog
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Pending Total</h6><h2 class="card-text">{{ $pending['total'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white"><div class="card-body">
            <h6 class="card-title">Ordered</h6><h2 class="card-text">{{ $pending['ordered'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-dark"><div class="card-body">
            <h6 class="card-title">Collected</h6><h2 class="card-text">{{ $pending['collected'] }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">Processing</h6><h2 class="card-text">{{ $pending['processing'] }}</h2>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Pending Orders</h6>
                <a href="{{ route('medical.lab.orders.index') }}" class="btn btn-sm btn-link">View all</a>
            </div>
            <div class="card-body">
                @if($pending['list']->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($pending['list']->take(8) as $order)
                        <li class="border-bottom py-2">
                            <a href="{{ route('medical.lab.orders.show', $order) }}"><strong>{{ clinical_no($order->order_number) }}</strong></a>
                            <span class="text-muted">· {{ $order->patient->full_name ?? 'N/A' }}</span>
                            <span class="badge bg-{{ $order->status_class }} float-end">{{ ucfirst($order->status) }}</span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">No pending orders.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recently Completed</h6></div>
            <div class="card-body">
                @if($recentCompleted->count() > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($recentCompleted as $order)
                        <li class="border-bottom py-2">
                            <a href="{{ route('medical.lab.orders.show', $order) }}"><strong>{{ clinical_no($order->order_number) }}</strong></a>
                            <span class="text-muted">· {{ $order->patient->full_name ?? 'N/A' }}</span>
                            <span class="text-muted small float-end"><x-tdate :value="$order->completed_at" fallback="d M H:i" :datetime="true" /></span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">Nothing completed yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
