@extends('layouts.institute')

@section('title', 'Radiology Worklist — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-radioactive"></i> Radiology Worklist</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical_radiology.create'))
                <a href="{{ route('medical.radiology.orders.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Order
                </a>
            @endif
            <a href="{{ route('medical.radiology.orders.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> All Orders
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold">{{ $todayStats['total'] }}</div>
                    <small class="text-muted">Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-secondary">{{ $todayStats['ordered'] }}</div>
                    <small class="text-muted">Ordered</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-info">{{ $todayStats['scheduled'] }}</div>
                    <small class="text-muted">Scheduled</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning">{{ $todayStats['in_progress'] }}</div>
                    <small class="text-muted">In Progress</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary">{{ $todayStats['completed'] }}</div>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success">{{ $todayStats['reported'] }}</div>
                    <small class="text-muted">Reported</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Worklist Columns -->
    @php
        $statusColors = [
            'ordered' => 'secondary',
            'scheduled' => 'info',
            'in_progress' => 'warning',
            'completed' => 'primary',
        ];
    @endphp

    <div class="row g-2">
        @foreach($statusColors as $status => $color)
            <div class="col-lg col-md-4 col-6">
                <div class="card border-{{ $color }} shadow-sm">
                    <div class="card-header bg-{{ $color }} text-white py-1">
                        <strong>{{ \App\Models\Medical\RadiologyOrder::STATUSES[$status] }} ({{ $byStatus[$status]->count() }})</strong>
                    </div>
                    <div class="card-body p-1" style="max-height: 400px; overflow-y: auto;">
                        @forelse($byStatus[$status] as $order)
                            <div class="card mb-1 border-{{ $order->is_urgent ? 'danger' : $color }}">
                                @if($order->is_urgent)
                                    <div class="card-header bg-danger text-white py-0" style="font-size:10px;"><i class="bi bi-exclamation-triangle"></i> URGENT</div>
                                @endif
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <small class="fw-bold">{{ $order->order_number }}</small><br>
                                            <small>{{ $order->patient?->name ?? 'N/A' }}</small>
                                        </div>
                                        <a href="{{ route('medical.radiology.orders.show', $order) }}" class="btn btn-sm btn-outline-{{ $color }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size:11px;">
                                        {{ $order->modalityLabel() }} — {{ $order->body_part }}
                                    </small>
                                    @if($order->scheduled_at)
                                        <small class="text-info d-block" style="font-size:10px;">
                                            <i class="bi bi-clock"></i> {{ $order->scheduled_at->format('d M H:i') }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-3">
                                <small>No orders</small>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
