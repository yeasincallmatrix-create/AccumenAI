@extends('layouts.admin')

@section('title', 'Access Log — ' . $institute->name . ' — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.institutes.modules-overview') }}" class="text-decoration-none">Modules Overview</a></li>
        <li class="breadcrumb-item active">Access Log — {{ $institute->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Access Log — {{ $institute->name }}</h4>
        <p class="page-header-desc">
            Industry: <strong>{{ $institute->industry ?? '—' }}</strong>
            | Country: <strong>{{ $institute->country_code ?? 'BD' }}</strong>
            — Every module enable/disable, package change, and entitlement action is audited here.
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.modules', $institute) }}">
            <i class="bi bi-grid"></i> Module Access
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.show', $institute) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-funnel"></i> Filters
        </div>
        <div class="toolbar-actions">
            <form method="GET" action="{{ route('admin.institutes.access-log', $institute) }}" class="d-flex align-items-center gap-2 flex-wrap">
                <select name="action" class="form-select form-select-sm" style="width:auto;min-width:150px">
                    <option value="">All Actions</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
                <input type="date" name="from_date" class="form-control form-control-sm" style="width:auto" value="{{ request('from_date') }}" title="From date">
                <input type="date" name="to_date" class="form-control form-control-sm" style="width:auto" value="{{ request('to_date') }}" title="To date">
                <input type="text" name="module_key" class="form-control form-control-sm" style="width:auto;min-width:150px" placeholder="Module key…" value="{{ request('module_key') }}">
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Filter</button>
                @if (request()->hasAny(['action', 'from_date', 'to_date', 'module_key']))
                    <a href="{{ route('admin.institutes.access-log', $institute) }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:160px">Timestamp</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th class="text-center" style="width:100px">State</th>
                    <th>Actor</th>
                    <th>Reason / Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-muted"><small>{{ $log->created_at }}</small></td>
                        <td>
                            @php $actionBadge = match($log->action) {
                                'enable' => 'success',
                                'disable' => 'danger',
                                'package_added' => 'info',
                                'package_removed' => 'warning',
                                'entitlement_granted', 'grant' => 'success',
                                'entitlement_denied', 'entitlement_revoked' => 'danger',
                                default => 'secondary',
                            }; @endphp
                            <span class="badge bg-{{ $actionBadge }}">{{ $log->action }}</span>
                        </td>
                        <td><code>{{ $log->module_key }}</code></td>
                        <td class="text-center">
                            <small class="text-muted">{{ $log->previous_state ?? '—' }} → {{ $log->new_state ?? '—' }}</small>
                        </td>
                        <td>
                            <small>
                                {{ $log->actor_id ? '#' . $log->actor_id : 'system' }}
                                @if ($log->actor_type)
                                    <span class="text-muted">({{ $log->actor_type }})</span>
                                @endif
                            </small>
                        </td>
                        <td>
                            <small class="text-muted">
                                {{ $log->reason ?: $log->notes ?: '—' }}
                                @if ($log->request_id)
                                    <br><code style="font-size:10px">{{ $log->request_id }}</code>
                                @endif
                            </small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No log entries for this tenant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center">
    {{ $logs->links() }}
</div>
@endsection
