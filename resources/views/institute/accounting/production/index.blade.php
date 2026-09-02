@extends('layouts.standalone')
@section('title', 'Production Dashboard — AccumenAI')
@section('page_title', 'Production')

@section('content')
<div class="standalone-heading">
    <h4>Production Dashboard</h4>
    <p>System health and monitoring for {{ $institute->name }}.</p>
</div>

<div class="row g-3 mb-3">
    @foreach($systemHealth as $service => $check)
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="d-flex align-items-center mb-2">
                <span class="badge {{ $check['status'] === 'ok' ? 'text-bg-success' : 'text-bg-danger' }} me-2">
                    {{ $check['status'] === 'ok' ? '&#10003;' : '&#10007;' }}
                </span>
                <strong class="text-capitalize">{{ $service }}</strong>
            </div>
            <div class="small text-muted">{{ $check['message'] }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6>Queue Status</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>Pending Jobs</td><td class="text-end">{{ $queueStatus['pending'] ?? 0 }}</td></tr>
                    <tr><td>Failed Jobs</td><td class="text-end {{ ($queueStatus['failed'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $queueStatus['failed'] ?? 0 }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6>Disk Usage</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>Total</td><td class="text-end">{{ $diskUsage['total_gb'] }} GB</td></tr>
                    <tr><td>Free</td><td class="text-end {{ $diskUsage['free_gb'] < 1 ? 'text-danger' : '' }}">{{ $diskUsage['free_gb'] }} GB</td></tr>
                    <tr><td>Used</td><td class="text-end">{{ $diskUsage['used_percent'] }}%</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="admin-card">
            <h6>Recent Errors</h6>
            @if(count($recentErrors) > 0)
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Time</th><th>Message</th></tr>
                    </thead>
                    <tbody>
                        @foreach($recentErrors as $error)
                        <tr>
                            <td class="text-nowrap">{{ $error['time'] }}</td>
                            <td class="small">{{ $error['message'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-muted mb-0">No recent errors found.</p>
            @endif
        </div>
    </div>
</div>
@endsection
