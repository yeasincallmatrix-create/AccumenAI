@extends('layouts.institute')

@section('title', mawa_lang('classes.tab_archive') . ' — AccumenAI')

@php
    $statusBadge = [
        'upcoming'  => 'bg-secondary',
        'running'   => 'bg-success',
        'completed' => 'bg-primary',
        'cancelled' => 'bg-danger',
        'archived'  => 'bg-dark',
    ];
    $statusNames = [
        'upcoming'  => mawa_lang('status.upcoming'),
        'running'   => mawa_lang('status.running'),
        'completed' => mawa_lang('status.completed'),
        'cancelled' => mawa_lang('status.cancelled'),
        'archived'  => mawa_lang('status.archived'),
    ];
    $shiftNames = [
        'morning' => mawa_lang('options.shift_morning'),
        'day'     => mawa_lang('options.shift_day'),
        'evening' => mawa_lang('options.shift_evening'),
        'weekend' => mawa_lang('options.shift_weekend'),
        'online'  => mawa_lang('options.shift_online'),
    ];
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
@endphp

@section('content')
@push('styles')
<style>
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .page-header, .monetix-print-hidden { display: none !important; }
        .layout { display: block !important; min-height: 0 !important; }
        .content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; }
        .print-header { display: block !important; margin-bottom: 12px; }
        .table-responsive { overflow: visible !important; }
        .table { width: 100% !important; border-collapse: collapse; }
    }
</style>
@endpush
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('classes.tab_archive') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('classes.archive_desc') }} — {{ $institute->name ?? '' }}</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('classes._tabs', [
        'activeTab' => 'archive',
        'classesCount' => $classesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="admin-card" data-ajax-table>

    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — {{ mawa_e('classes.tab_archive') }}</h4>
        <p class="mb-0 text-muted">{{ $archiveCount }} archived classes · {{ now()->format('d M Y') }}</p>
    </div>

    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end monetix-print-hidden" method="GET" action="{{ route('classes.archive') }}" data-ajax-filter>
        <div style="flex:1 1 280px;min-width:220px">
            <input type="text" name="q" class="form-control" value="{{ $q }}"
                   placeholder="{{ mawa_e('batches.search_placeholder') }}">
        </div>
        <div style="width:200px">
            <select name="branch_id" class="form-select">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:220px">
            <select name="course_id" class="form-select">
                <option value="">{{ mawa_e('batches.all_courses') }}</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) $courseId === (string) $course->id)>{{ $course->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ mawa_e('actions.search') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('classes.archive') }}" title="{{ mawa_e('actions.reset') }}">
            <i class="bi bi-arrow-counterclockwise"></i>
        </a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <div class="dropdown">
                <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i>{{ mawa_e('actions.columns') }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu">
                    <li><h6 class="dropdown-header">{{ mawa_e('actions.show_hide_columns') }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial' => '#',
                        'code'   => mawa_e('batches.table_code'),
                        'name'   => mawa_e('batches.table_name'),
                        'class'  => mawa_e('classes.table_class'),
                        'shift'  => mawa_e('batches.table_shift'),
                        'start'  => mawa_e('batches.table_start'),
                        'seats'  => mawa_e('batches.table_seats'),
                        'status' => mawa_e('batches.table_status'),
                        'action' => mawa_e('actions.actions'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="archive-col-{{ $col }}">
                                <input type="checkbox" id="archive-col-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" onclick="window.print()" title="{{ mawa_e('actions.print') }}">
                <i class="bi bi-printer"></i>{{ mawa_e('actions.print') }}
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_code') }}</th>
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_name') }}</th>
                    <th data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_class') }}</th>
                    <th data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_shift') }}</th>
                    <th data-col="start" @if(!in_array('start', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_start') }}</th>
                    <th data-col="seats" @if(!in_array('seats', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_seats') }}</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_status') }}</th>
                    <th class="text-end monetix-print-hidden" data-col="action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('actions.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $batches->firstItem() + $loop->index }}</td>
                        <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>
                            <a class="text-decoration-none" href="{{ route('batches.show', $batch) }}"><span class="badge bg-dark bg-opacity-75">{{ $batch->batch_code }}</span></a>
                        </td>
                        <td class="fw-semibold" data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <a class="fw-semibold text-decoration-none" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>
                        </td>
                        <td data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>{{ $batch->course?->name ?? '—' }}</td>
                        <td data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ $shiftNames[$batch->shift] ?? $batch->shift }}</td>
                        <td data-col="start" @if(!in_array('start', $visibleColumns, true)) style="display:none" @endif>{{ $fmtDate($batch->start_date) }}</td>
                        <td data-col="seats" @if(!in_array('seats', $visibleColumns, true)) style="display:none" @endif>
                            {{ $batch->seat_filled }} / {{ $batch->seat_capacity }}
                            <small class="text-muted d-block">{{ mawa_e('batches.filled') }}</small>
                        </td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }}">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
                        </td>
                        <td class="text-end monetix-print-hidden" data-col="action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            @if ($user->hasPermission('batches.manage'))
                                <form class="d-inline" method="POST" action="{{ route('batches.unarchive', $batch) }}"
                                      data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.confirm_archive') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-dark" type="submit" title="{{ mawa_lang('batches.unarchived') }}" style="min-height:32px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">{{ mawa_e('classes.archive_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2 monetix-print-hidden" data-ajax-pagination>
        {{ $batches->links('pagination::bootstrap-5') }}
        @if ($batches->total() > 0)
            <span class="text-muted small">
                Showing {{ $batches->firstItem() ?? 0 }}–{{ $batches->lastItem() ?? 0 }} of {{ $batches->total() }} archived classes
            </span>
        @endif
    </nav>

</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.querySelector('.table-responsive .table');
    var checks = document.querySelectorAll('.col-toggle-check');
    if (!table || !checks.length) { return; }
    checks.forEach(function (check) {
        check.addEventListener('change', function () {
            var col = check.getAttribute('data-col');
            var th = table.querySelector('th[data-col="' + col + '"]');
            if (!th) { return; }
            var index = Array.prototype.indexOf.call(th.parentNode.children, th);
            var hidden = !check.checked;
            th.style.display = hidden ? 'none' : '';
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                var td = tr.children[index];
                if (td) { td.style.display = hidden ? 'none' : ''; }
            });
            var visible = [];
            checks.forEach(function (c) { if (c.checked) { visible.push(c.getAttribute('data-col')); } });
            fetch('{{ route('ui.columns.save') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ key: 'classes_archive', columns: visible })
            });
        });
    });
})();
</script>
@endpush