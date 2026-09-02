@extends('layouts.admin')
@section('title','Database Audit Logs — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-journal-text me-2"></i>Database Audit Logs</h1>
        <div class="small text-muted">System and database operations audit trail</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>Module</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Record</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->created_at ?? '—' }}</td>
                        <td class="small fw-bold">{{ $log->action ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $log->module ?? '—' }}</span></td>
                        <td class="small">{{ $log->user_id ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $log->user_type ?? '—' }}</span></td>
                        <td>
                            @php
                                $action = $log->action ?? '';
                                $badgeClass = 'secondary';
                                if (str_contains($action, 'created') || str_contains($action, 'completed') || str_contains($action, 'verified') || str_contains($action, 'healthy')) $badgeClass = 'success';
                                elseif (str_contains($action, 'failed') || str_contains($action, 'error')) $badgeClass = 'danger';
                                elseif (str_contains($action, 'started') || str_contains($action, 'pending')) $badgeClass = 'warning';
                            @endphp
                            <span class="badge bg-{{ $badgeClass }}">{{ str_replace('_', ' ', $action) }}</span>
                        </td>
                        <td class="small">{{ $log->record_id ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center small text-muted">No audit logs found</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="small text-muted">Showing {{ count($logs) }} most recent entries from the existing audit_logs table.</div>
@endsection
