@extends('layouts.institute')

@section('title', 'Lab Order — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ $order->order_number }}
            <span class="badge bg-{{ $order->status_class }}">{{ ucfirst($order->status) }}</span>
            <span class="badge bg-{{ $order->priority === 'emergency' ? 'danger' : ($order->priority === 'urgent' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($order->priority) }}</span>
        </h4>
    </div>
    <div class="page-header-actions">
        @if($order->status === 'ordered')
            <form action="{{ route('medical.lab.orders.collect', $order) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info">
                    <i class="bi bi-droplet me-1"></i>Collect Sample
                </button>
            </form>
            <a class="btn btn-warning" href="{{ route('medical.lab.orders.edit', $order) }}">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        @endif
        @if($order->readyForResults())
            <a class="btn btn-primary" href="{{ route('medical.lab.orders.result.form', $order) }}">
                <i class="bi bi-clipboard2-pulse me-1"></i>Enter Results
            </a>
        @endif
        @if($order->status === 'completed')
            <a class="btn btn-success" href="{{ route('medical.lab.orders.report', $order) }}">
                <i class="bi bi-file-earmark-pdf me-1"></i>Report (PDF)
            </a>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.lab.orders.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Order Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    @if($order->patient)
                        <a href="{{ route('medical.patients.show', $order->patient) }}">{{ $order->patient->full_name }}</a>
                        <span class="text-muted">({{ $order->patient->mr_number }})</span>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Doctor:</strong> {{ $order->doctor->name ?? 'N/A' }}</p>
                <p><strong>Order Date:</strong> <x-tdate :value="$order->order_date" fallback="d M Y" /></p>
                <p><strong>Collected:</strong>
                    <x-tdate :value="$order->collected_at" fallback="d M Y h:i A" :datetime="true" />
                    @if($order->collectedBy) by {{ $order->collectedBy->name }} @endif
                </p>
                <p class="mb-0"><strong>Completed:</strong>
                    <x-tdate :value="$order->completed_at" fallback="d M Y h:i A" :datetime="true" />
                    @if($order->completedBy) by {{ $order->completedBy->name }} @endif
                </p>
                @if($order->clinical_notes)
                    <hr><p class="mb-0"><strong>Clinical Notes:</strong> {{ $order->clinical_notes }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Results ({{ $order->results->count() }})</h6></div>
            <div class="card-body">
                @if($order->results->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Test</th><th>Result</th><th>Range</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach($order->results as $result)
                                <tr class="{{ in_array($result->status, ['abnormal', 'critical'], true) ? 'table-warning' : '' }}">
                                    <td>{{ $result->labTest->display_name ?? 'N/A' }}</td>
                                    <td>
                                        {{ $result->result_value ?? '—' }}
                                        @if($result->result_text)<br><small class="text-muted">{{ $result->result_text }}</small>@endif
                                    </td>
                                    <td class="small">{{ $result->normal_range ?? $result->labTest->normal_range ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $result->status === 'normal' ? 'success' : ($result->status === 'pending' ? 'secondary' : ($result->status === 'critical' ? 'danger' : 'warning text-dark')) }}">
                                            {{ ucfirst($result->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No tests on this order.</p>
                @endif
                @if($order->result_notes)
                    <hr><p class="mb-0"><strong>Result Notes:</strong> {{ $order->result_notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
