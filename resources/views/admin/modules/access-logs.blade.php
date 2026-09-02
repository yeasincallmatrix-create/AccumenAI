@extends('layouts.admin')

@section('title', mawa_e('modules.access_logs') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('modules.access_logs') }}</h4>
        <p class="page-header-desc">{{ mawa_e('modules.access_logs_description') }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.modules.index') }}">
            <i class="bi bi-arrow-left"></i> {{ mawa_e('modules.back_to_registry') }}
        </a>
    </div>
</div>

<div class="filter-card mb-4">
    <form class="filter-layout" method="GET" action="{{ route('admin.modules.access-logs') }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-span flex-shrink-0" style="min-width:200px">
                <label class="form-label mb-1">{{ mawa_e('modules.institute') }}</label>
                <select class="form-select form-select-sm" name="institute_id">
                    <option value="">{{ mawa_e('modules.all_institutes') }}</option>
                    @php
                        $selectedInstitute = request('institute_id');
                    @endphp
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:200px">
                <label class="form-label mb-1">{{ mawa_e('modules.module') }}</label>
                <select class="form-select form-select-sm" name="module_key">
                    <option value="">{{ mawa_e('modules.all_modules') }}</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module->key }}" @selected(request('module_key') === $module->key)>{{ $module->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-shrink-0">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> {{ mawa_e('modules.filter') }}
                </button>
                <a href="{{ route('admin.modules.access-logs') }}" class="btn btn-outline-secondary btn-sm">
                    {{ mawa_e('modules.clear') }}
                </a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-clock-history"></i> {{ mawa_e('modules.audit_trail') }}
            <span class="badge bg-secondary ms-2">{{ $logs->total() }}</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:160px">{{ mawa_e('modules.timestamp') }}</th>
                    <th>{{ mawa_e('modules.institute') }}</th>
                    <th>{{ mawa_e('modules.module') }}</th>
                    <th style="width:100px">{{ mawa_e('modules.action') }}</th>
                    <th>{{ mawa_e('modules.actor') }}</th>
                    <th style="width:100px">{{ mawa_e('modules.previous_state') }}</th>
                    <th style="width:100px">{{ mawa_e('modules.new_state') }}</th>
                    <th>{{ mawa_e('modules.notes') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>
                            <small>{{ $log->created_at?->format('M d, Y H:i') ?? '—' }}</small>
                        </td>
                        <td>
                            @if ($log->institute)
                                <a href="{{ route('admin.institutes.modules', $log->institute) }}">
                                    {{ $log->institute->name }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><code>{{ $log->module_key }}</code></td>
                        <td>
                            @php
                                $actionBadge = match($log->action) {
                                    'enable' => 'bg-success',
                                    'disable' => 'bg-danger',
                                    'override_enable' => 'bg-primary',
                                    'override_disable' => 'bg-warning text-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $actionBadge }}">{{ ucfirst($log->action) }}</span>
                        </td>
                        <td>
                            @if ($log->actor)
                                {{ $log->actor->name ?? $log->actor->email ?? 'System' }}
                            @else
                                <span class="text-muted">System</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($log->previous_state !== null)
                                @if ($log->previous_state)
                                    <span class="badge bg-success-subtle text-success">ON</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">OFF</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($log->new_state !== null)
                                @if ($log->new_state)
                                    <span class="badge bg-success-subtle text-success">ON</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger">OFF</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($log->notes)
                                <small class="text-muted">{{ $log->notes }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">{{ mawa_e('modules.no_logs') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="d-flex justify-content-center py-3">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
