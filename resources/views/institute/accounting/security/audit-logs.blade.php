@extends('layouts.standalone')

@section('title', 'Audit Logs — AccumenAI')
@section('page_title', 'Security Audit')

@section('content')

<div class="standalone-heading">
    <h4>Audit Trail</h4>
    <p>Recent security and activity audit events for {{ $institute->name }}.</p>
    <a href="{{ route('accounting.security.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time</th>
                    <th>Action</th>
                    <th>Actor Type</th>
                    <th>Actor ID</th>
                    <th>Entity Type</th>
                    <th>IP</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->id }}</td>
                        <td class="text-muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if (str_starts_with((string) $log->action, 'failed_login'))
                                <span class="badge bg-danger">{{ $log->action }}</span>
                            @elseif ($log->action === 'permission_denied')
                                <span class="badge bg-warning text-dark">{{ $log->action }}</span>
                            @elseif ($log->action === 'login')
                                <span class="badge bg-success">{{ $log->action }}</span>
                            @elseif ($log->action === 'logout')
                                <span class="badge bg-secondary">{{ $log->action }}</span>
                            @elseif (str_starts_with((string) $log->action, 'module_access'))
                                <span class="badge bg-info text-dark">{{ $log->action }}</span>
                            @else
                                <span class="badge bg-light text-dark border">{{ $log->action }}</span>
                            @endif
                        </td>
                        <td>{{ $log->actor_type ?? '—' }}</td>
                        <td>{{ $log->actor_id ?? '—' }}</td>
                        <td>{{ $log->entity_type ?? '—' }}</td>
                        <td class="text-muted">{{ $log->ip ?? '—' }}</td>
                        <td class="text-muted text-truncate" style="max-width: 200px;" title="{{ $log->user_agent }}">{{ $log->user_agent ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No audit trail entries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-3">
        {{ $logs->withQueryString()->links() }}
    </div>
</div>

@endsection
