@extends('layouts.standalone')
@php $backUrl = route('admin.platform-settings.index'); @endphp
@section('title', 'Platform Audit Logs — Accumen AI')
@section('page_title', 'Platform Audit Logs')

@section('content')
<div class="standalone-heading">
    <h4><i class="bi bi-journal-text"></i> Platform Audit Logs</h4>
    <p>Read-only history of Super Admin configuration changes. Secrets are never displayed.</p>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-funnel"></i> Filters
        </div>
        @if(request()->hasAny(['section','action','admin_id','from','to']))
            <a href="{{ route('admin.platform-audit.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Clear Filters
            </a>
        @endif
    </div>
    <form method="GET" class="filter-layout">
        <div class="filter-search-row">
            <div class="filter-span" style="min-width:150px">
                <label class="form-label mb-1">Section</label>
                <select name="section" class="form-select form-select-sm">
                    <option value="">All Sections</option>
                    @foreach($sections as $s)
                        <option value="{{ $s }}" {{ request('section')===$s?'selected':'' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span" style="min-width:150px">
                <label class="form-label mb-1">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" {{ request('action')===$a?'selected':'' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span" style="min-width:200px">
                <label class="form-label mb-1">Admin</label>
                <select name="admin_id" class="form-select form-select-sm">
                    <option value="">All Admins</option>
                    @foreach($admins as $adm)
                        <option value="{{ $adm->id }}" {{ (string)request('admin_id')===(string)$adm->id?'selected':'' }}>{{ $adm->email }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span" style="min-width:140px">
                <label class="form-label mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="filter-span" style="min-width:140px">
                <label class="form-label mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-primary badge-soft">{{ $logs->total() }} Records</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Admin</th>
                    <th>Section</th>
                    <th>Key</th>
                    <th>Action</th>
                    <th>IP</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="small text-muted text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="small">{{ $log->admin?->email ?? '—' }}</td>
                    <td><span class="badge bg-secondary">{{ $log->section }}</span></td>
                    <td class="small font-monospace">{{ $log->setting_key }}</td>
                    <td>
                        @if($log->action === 'credential_changed')
                            <span class="badge bg-warning text-dark"><i class="bi bi-key me-1"></i>Credential changed</span>
                        @elseif($log->action === 'updated')
                            <span class="badge bg-info"><i class="bi bi-pencil me-1"></i>Updated</span>
                        @else
                            <span class="badge bg-secondary">{{ $log->action }}</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                    <td class="small text-muted" style="max-width:200px;">
                        @if($log->action === 'credential_changed')
                            <span class="text-warning">Credential changed — value hidden</span>
                        @else
                            {{ Str::limit(json_encode($log->meta), 80) }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-journal-text" style="font-size:2rem;opacity:0.3;"></i>
                            <p class="mt-2 mb-0">No audit records found.</p>
                            @if(request()->hasAny(['section','action','admin_id','from','to']))
                                <a href="{{ route('admin.platform-audit.index') }}" class="btn btn-sm btn-outline-primary mt-2">Clear Filters</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="mt-3 d-flex justify-content-center">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
