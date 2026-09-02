@extends('layouts.institute')

@section('title', mawa_lang('courses.tab_archive') . ' — AccumenAI')

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
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #archiveTablePrint { width: 100%; font-size: 11px; }
        #archiveTablePrint th, #archiveTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@endpush
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('courses.tab_archive') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('courses.archive_desc') }} — {{ $institute->name ?? '' }}</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('courses._tabs', [
        'activeTab' => 'archive',
        'coursesCount' => $coursesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="admin-card" data-ajax-table>

    <div class="filter-card">
        <form class="filter-layout" method="GET" action="{{ route('courses.archive') }}" data-ajax-filter>

            <div class="filter-search-row align-items-end">

                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" name="q" value="{{ $q }}"
                           placeholder="{{ mawa_e('batches.search_placeholder') }}">
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('batches.table_branch') }}</label>
                    <select class="form-select form-select-sm" name="branch_id">
                        <option value="">{{ mawa_e('batches.all_branches') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span flex-shrink-0" style="min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('courses.table_course') }}</label>
                    <select class="form-select form-select-sm" name="course_id">
                        <option value="">{{ mawa_e('batches.all_courses') }}</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) $courseId === (string) $course->id)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> {{ mawa_e('actions.search') }}</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('courses.archive') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>

            </div>

        </form>
    </div>

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-dark badge-soft">{{ $archiveCount ?? 0 }} Archived</span>
            <span class="text-muted ms-2 d-none d-lg-inline">{{ mawa_lang('courses.archive_desc') }}</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> {{ mawa_e('actions.columns') }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenu">
                    <li><h6 class="dropdown-header">{{ mawa_e('actions.show_hide_columns') }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial' => '#',
                        'code'   => mawa_e('batches.table_code'),
                        'name'   => mawa_e('batches.table_name'),
                        'course' => mawa_e('courses.table_course'),
                        'shift'  => mawa_e('batches.table_shift'),
                        'start'  => mawa_e('batches.table_start'),
                        'seats'  => mawa_e('batches.table_seats'),
                        'status' => mawa_e('batches.table_status'),
                        'action' => mawa_e('actions.actions'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="col-toggle-{{ $col }}">
                                <input type="checkbox" id="col-toggle-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i> {{ mawa_e('actions.print') }}</button>
                <button type="button" class="btn btn-outline-success" id="exportCsvBtn"><i class="bi bi-filetype-csv"></i> CSV</button>
                <button type="button" class="btn btn-outline-success" id="exportExcelBtn"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="archiveTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_code') }}</th>
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_name') }}</th>
                    <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('courses.table_course') }}</th>
                    <th data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_shift') }}</th>
                    <th data-col="start" @if(!in_array('start', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_start') }}</th>
                    <th data-col="seats" @if(!in_array('seats', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_seats') }}</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_status') }}</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('actions.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $batch->name }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $batches->firstItem() + $loop->index }}</td>
                        <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>
                            <a class="text-decoration-none" href="{{ route('batches.show', $batch) }}"><span class="badge bg-dark bg-opacity-75">{{ $batch->batch_code }}</span></a>
                        </td>
                        <td class="fw-semibold" data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <a class="fw-semibold text-decoration-none" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>
                        </td>
                        <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $batch->course?->name ?? '—' }}</td>
                        <td data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ $shiftNames[$batch->shift] ?? $batch->shift }}</td>
                        <td data-col="start" @if(!in_array('start', $visibleColumns, true)) style="display:none" @endif>{{ $fmtDate($batch->start_date) }}</td>
                        <td data-col="seats" @if(!in_array('seats', $visibleColumns, true)) style="display:none" @endif>
                            {{ $batch->seat_filled }} / {{ $batch->seat_capacity }}
                            <small class="text-muted d-block">{{ mawa_e('batches.filled') }}</small>
                        </td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }}">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
                        </td>
                        <td class="text-end text-nowrap col-action" data-col="action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
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
                        <td colspan="13" class="text-center text-muted py-4">{{ mawa_e('courses.archive_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $batches->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $batches->total() }} archived courses</span>
    </div>

</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="archiveTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_code') }}</th>
                <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_name') }}</th>
                <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('courses.table_course') }}</th>
                <th data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_shift') }}</th>
                <th data-col="start" @if(!in_array('start', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_start') }}</th>
                <th data-col="seats" @if(!in_array('seats', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_seats') }}</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('batches.table_status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allBatches as $batch)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $batch->batch_code }}</td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $batch->name }}</td>
                    <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $batch->course?->name ?? '—' }}</td>
                    <td data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ $shiftNames[$batch->shift] ?? $batch->shift }}</td>
                    <td data-col="start" @if(!in_array('start', $visibleColumns, true)) style="display:none" @endif>{{ $fmtDate($batch->start_date) }}</td>
                    <td data-col="seats" @if(!in_array('seats', $visibleColumns, true)) style="display:none" @endif>{{ $batch->seat_filled }} / {{ $batch->seat_capacity }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ $statusNames[$batch->status] ?? $batch->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('archiveTable');

    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('#archiveTable .row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
        });
    }

    var tbody = table ? table.querySelector('tbody') : null;
    if (tbody) {
        var draggedRow = null;

        function reorderAndAnimate(target, after) {
            var rows = Array.prototype.slice.call(tbody.children);
            var prev = new Map();
            rows.forEach(function (tr) { prev.set(tr, tr.getBoundingClientRect().top); });
            var moved = false;
            if (after && target.nextElementSibling !== draggedRow) {
                tbody.insertBefore(draggedRow, target.nextElementSibling);
                moved = true;
            } else if (!after && target.previousElementSibling !== draggedRow) {
                tbody.insertBefore(draggedRow, target);
                moved = true;
            }
            if (!moved) { return; }
            var afterRows = Array.prototype.slice.call(tbody.children);
            afterRows.forEach(function (tr) {
                var delta = prev.get(tr) - tr.getBoundingClientRect().top;
                if (delta) {
                    tr.style.transition = 'none';
                    tr.style.transform = 'translateY(' + delta + 'px)';
                }
            });
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    afterRows.forEach(function (tr) {
                        tr.style.transition = 'transform .3s cubic-bezier(.2,.85,.35,1)';
                        tr.style.transform = '';
                    });
                });
            });
        }

        tbody.addEventListener('dragstart', function (e) {
            var handle = e.target.closest('.drag-handle');
            if (!handle) { e.preventDefault(); return; }
            draggedRow = handle.closest('tr');
            draggedRow.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'row');
        });
        tbody.addEventListener('dragover', function (e) {
            if (!draggedRow) { return; }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var target = e.target.closest('tr');
            if (!target || target === draggedRow) { return; }
            var rect = target.getBoundingClientRect();
            reorderAndAnimate(target, (e.clientY - rect.top) > (rect.height / 2));
        });
        tbody.addEventListener('dragend', function () {
            draggedRow = null;
            tbody.querySelectorAll('.dragging, .drag-over').forEach(function (el) {
                el.classList.remove('dragging', 'drag-over');
                el.style.transition = '';
                el.style.transform = '';
            });
        });
    }

    var colChecks = document.querySelectorAll('.col-toggle-check');
    var saveCols = null;
    if (table && colChecks.length) {
        colChecks.forEach(function (check) {
            check.addEventListener('change', function () {
                var col = check.getAttribute('data-col');
                var th = table.querySelector('th[data-col="' + col + '"]');
                if (! th) { return; }
                var index = Array.prototype.indexOf.call(th.parentNode.children, th);
                var hidden = ! check.checked;
                th.style.display = hidden ? 'none' : '';
                table.querySelectorAll('tbody tr').forEach(function (tr) {
                    var td = tr.children[index];
                    if (td) { td.style.display = hidden ? 'none' : ''; }
                });
                var printTable = document.getElementById('archiveTablePrint');
                if (printTable) {
                    printTable.querySelectorAll('[data-col="' + col + '"]').forEach(function (el) {
                        el.style.display = hidden ? 'none' : '';
                    });
                }
                if (saveCols) { saveCols(); }
            });
        });

        saveCols = function () {
            var visible = [];
            colChecks.forEach(function (check) {
                if (check.checked) { visible.push(check.getAttribute('data-col')); }
            });
            fetch('{{ route('ui.columns.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ key: 'course_archive', columns: visible })
            });
        };
    }

    function exportTable(fileName) {
        var tbl = document.getElementById('archiveTable');
        if (!tbl) { return; }
        var out = [];
        var headers = [];
        tbl.querySelectorAll('thead th').forEach(function (th, i) {
            if (i > 1) { headers.push(th.textContent.trim()); }
        });
        out.push(headers.join(','));
        tbl.querySelectorAll('tbody tr').forEach(function (tr) {
            var cells = tr.querySelectorAll('td');
            if (!cells.length) { return; }
            var row = [];
            for (var i = 2; i < cells.length; i++) {
                row.push('"' + cells[i].textContent.trim().replace(/"/g, '""') + '"');
            }
            out.push(row.join(','));
        });
        var blob = new Blob(['\ufeff' + out.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    var csvBtn = document.getElementById('exportCsvBtn');
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('archived-courses.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('archived-courses.xls'); }); }
})();
</script>
@endpush
