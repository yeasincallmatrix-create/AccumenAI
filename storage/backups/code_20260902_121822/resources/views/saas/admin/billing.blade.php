@extends('layouts.standalone')

@section('title', 'SaaS Billing Report — AccumenAI')
@section('page_title', 'Billing & Revenue Report')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-success">
            <div class="text-muted small">Today</div>
            <div class="fs-4 fw-bold text-success">{{ number_format($revenueByPeriod['today'], 2) }} BDT</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-primary">
            <div class="text-muted small">This Week</div>
            <div class="fs-4 fw-bold text-primary">{{ number_format($revenueByPeriod['this_week'], 2) }} BDT</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-info">
            <div class="text-muted small">This Month</div>
            <div class="fs-4 fw-bold text-info">{{ number_format($revenueByPeriod['this_month'], 2) }} BDT</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-warning">
            <div class="text-muted small">This Year</div>
            <div class="fs-4 fw-bold text-warning">{{ number_format($revenueByPeriod['this_year'], 2) }} BDT</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card p-3 text-center">
            <div class="text-muted small">Total Attempts</div>
            <div class="fs-3 fw-bold">{{ $totalAttempts }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-success">
            <div class="text-muted small">Successful</div>
            <div class="fs-3 fw-bold text-success">{{ $successfulAttempts }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-danger">
            <div class="text-muted small">Failed</div>
            <div class="fs-3 fw-bold text-danger">{{ $failedAttempts }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-warning">
            <div class="text-muted small">Success Rate</div>
            <div class="fs-3 fw-bold text-warning">{{ $successRate }}%</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-credit-card"></i> Recent Payment Attempts
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Institute</th>
                    <th>Amount</th>
                    <th>Currency</th>
                    <th>Status</th>
                    <th>Gateway Ref</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentPayments as $payment)
                    <tr>
                        <td>{{ $payment->id }}</td>
                        <td>{{ $payment->institute->name ?? '—' }}</td>
                        <td>{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->currency_code ?? 'BDT' }}</td>
                        <td>
                            @if($payment->status === \App\Models\OnlinePaymentAttempt::STATUS_COMPLETED)
                                <span class="badge bg-success">Success</span>
                            @elseif($payment->status === \App\Models\OnlinePaymentAttempt::STATUS_FAILED)
                                <span class="badge bg-danger">Failed</span>
                            @elseif($payment->status === \App\Models\OnlinePaymentAttempt::STATUS_PENDING)
                                <span class="badge bg-warning">Pending</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($payment->status) }}</span>
                            @endif
                        </td>
                        <td><code>{{ $payment->gateway_reference ?? '—' }}</code></td>
                        <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No payment attempts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
