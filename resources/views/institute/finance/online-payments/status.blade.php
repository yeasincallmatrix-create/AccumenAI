@extends('layouts.institute')

@section('title', 'Payment Status — ' . ($institute->name ?? 'AccumenAI'))

@section('content')
<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-header-title"><i class="bi bi-credit-card me-1"></i>Payment Status</h4>
    </div>
    <div>
        <a href="{{ route('online-payments.history') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history me-1"></i>History</a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">Attempt #{{ $attempt->id }}</h6>
                @php
                    $statusColors = [
                        'pending' => 'secondary',
                        'processing' => 'info',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'warning',
                        'expired' => 'secondary',
                    ];
                @endphp
                <span class="badge bg-{{ $statusColors[$attempt->status] ?? 'secondary' }}">{{ ucfirst($attempt->status) }}</span>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Gateway</th><td>{{ $attempt->gateway?->name ?? '-' }}</td></tr>
                    <tr><th>Invoice</th><td>{{ $attempt->invoice?->invoice_number ?? '-' }}</td></tr>
                    <tr><th>Student</th><td>{{ $attempt->student?->first_name }} {{ $attempt->student?->last_name }}</td></tr>
                    <tr><th>Amount</th><td>{{ number_format((float) $attempt->amount, 2) }} {{ $attempt->currency_code ?? '' }}</td></tr>
                    <tr><th>Reference</th><td><code>{{ $attempt->gateway_reference ?? 'N/A' }}</code></td></tr>
                    <tr><th>Initiated</th><td>{{ $attempt->initiated_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                    <tr><th>Completed</th><td>{{ $attempt->completed_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                    @if ($attempt->payment_id)
                        <tr><th>Payment</th><td><a href="{{ route('finance.payments.index') }}">#{{ $attempt->payment_id }}</a></td></tr>
                    @endif
                    @if ($attempt->failure_reason)
                        <tr><th>Failure</th><td class="text-danger">{{ $attempt->failure_reason }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
