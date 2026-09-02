@extends('layouts.standalone')

@section('title', 'Security Dashboard — AccumenAI')
@section('page_title', 'Security Audit')

@section('content')

<div class="standalone-heading">
    <h4>Security Dashboard</h4>
    <p>Security overview for {{ $institute->name }} — permission integrity, audit events, rate limiting and password policy.</p>
</div>

{{-- Score & Health --}}
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Security Score</div>
            <div class="fs-2 fw-bold {{ $summary['overall_healthy'] ? 'text-success' : 'text-warning' }}">
                {{ $summary['score'] }}%
            </div>
            <div class="small text-muted">{{ $summary['checks_passed'] }} / {{ $summary['checks_total'] }} checks passed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Permissions</div>
            <div class="fs-4 fw-semibold">{{ $summary['permissions']['total_permissions'] }}</div>
            <div class="small {{ $summary['permissions']['orphaned_permissions'] > 0 ? 'text-danger' : 'text-success' }}">
                {{ $summary['permissions']['orphaned_permissions'] }} orphaned
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Failed Logins (24h)</div>
            <div class="fs-4 fw-semibold {{ $summary['audit_logs']['failed_logins'] > 0 ? 'text-danger' : '' }}">
                {{ $summary['audit_logs']['failed_logins'] }}
            </div>
            <div class="small text-muted">{{ $summary['audit_logs']['total_events'] }} total events</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Permission Denials (24h)</div>
            <div class="fs-4 fw-semibold {{ $summary['audit_logs']['permission_denials'] > 0 ? 'text-warning' : '' }}">
                {{ $summary['audit_logs']['permission_denials'] }}
            </div>
            <div class="small text-muted">of {{ $summary['audit_logs']['total_events'] }} total events</div>
        </div>
    </div>
</div>

{{-- Check Details --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Permission Integrity</h6>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Total permissions</span>
                <span class="fw-semibold">{{ $summary['permissions']['total_permissions'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Assigned to roles</span>
                <span class="fw-semibold">{{ $summary['permissions']['assigned_permissions'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Orphaned</span>
                <span class="fw-semibold {{ $summary['permissions']['orphaned_permissions'] > 0 ? 'text-danger' : 'text-success' }}">{{ $summary['permissions']['orphaned_permissions'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Roles configured</span>
                <span class="fw-semibold">{{ $summary['permissions']['roles_with_permissions_count'] }}</span>
            </div>
            @if (!empty($summary['permissions']['orphaned_list']))
                <div class="mt-2">
                    <small class="text-muted">Orphaned slugs:</small>
                    @foreach ($summary['permissions']['orphaned_list'] as $slug)
                        <span class="badge bg-danger me-1">{{ $slug }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Rate Limiting</h6>
            @foreach ($summary['rate_limiting']['throttled_routes'] as $key => $route)
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">{{ $route['route'] }}</span>
                    <span class="fw-semibold">{{ $route['max_attempts'] }} req / {{ $route['decay_minutes'] }}m</span>
                </div>
            @endforeach
            <div class="d-flex justify-content-between mb-1 mt-2 pt-2 border-top">
                <span class="text-muted">Routes configured</span>
                <span class="fw-semibold">{{ $summary['rate_limiting']['routes_count'] }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Password Policy</h6>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Minimum length</span>
                <span class="fw-semibold">{{ $summary['password_strength']['config']['min_length'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Hash algorithm</span>
                <span class="fw-semibold">{{ $summary['password_strength']['config']['hash_algo'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Total users</span>
                <span class="fw-semibold">{{ $summary['password_strength']['total_users'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Properly hashed</span>
                <span class="fw-semibold text-success">{{ $summary['password_strength']['hashed_users'] }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">Plain-text (risk)</span>
                <span class="fw-semibold {{ $summary['password_strength']['plain_text_passwords'] > 0 ? 'text-danger' : 'text-success' }}">{{ $summary['password_strength']['plain_text_passwords'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Audit Events (24h)</h6>
            @forelse ($summary['audit_logs']['events_by_action'] as $action => $count)
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">{{ $action }}</span>
                    <span class="fw-semibold">{{ $count }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No audit events in the last 24 hours.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Recent Activity --}}
<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Recent Failed Logins</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentFailedLogins as $log)
                            <tr>
                                <td class="text-muted">{{ $log->created_at?->diffForHumans() }}</td>
                                <td>{{ $log->actor_id ?? '—' }}</td>
                                <td class="text-muted">{{ $log->ip ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No failed logins.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <h6 class="card-title">Recent Permission Denials</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPermissionDenials as $log)
                            <tr>
                                <td class="text-muted">{{ $log->created_at?->diffForHumans() }}</td>
                                <td>{{ $log->actor_id ?? '—' }}</td>
                                <td class="text-muted">{{ $log->ip ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No permission denials.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('accounting.security.audit-logs') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-list-ul"></i> View Full Audit Trail
    </a>
</div>

@endsection
