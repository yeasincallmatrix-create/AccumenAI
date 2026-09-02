@extends('layouts.admin')

@section('title', 'Archive — AccumenAI')

@section('content')
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
@php
    $statusBadge = [
        'archived' => 'text-bg-dark',
    ];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Archive</h4>
        <p class="page-header-desc">Platform-wide archived batches</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('admin.courses._tabs', [
        'activeTab' => 'archive',
        'coursesCount' => $coursesCount,
        'batchesCount' => $batchesCount,
        'subjectsCount' => $subjectsCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.courses.archive') }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by batch, code or course..." value="{{ $filters['q'] ?? '' }}">
            </div>

            <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                <label class="form-label mb-1">Institute</label>
                <select class="form-select form-select-sm" name="institute_id" id="instSelect">
                    <option value="">All Institutes</option>
                    @foreach ($institutes as $inst)
                        <option value="{{ $inst->id }}" @selected(($filters['institute_id'] ?? '') == $inst->id)>{{ $inst->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.courses.archive') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-dark badge-soft">{{ $items->total() }} Archived</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Batches that have been archived by their institute.</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> Columns <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenu">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial'  => '#',
                        'batch'   => 'Batch',
                        'institute' => 'Institute',
                        'course'  => 'Course',
                        'shift'   => 'Shift',
                        'schedule' => 'Schedule',
                        'capacity' => 'Capacity',
                        'status'  => 'Status',
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
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                <button type="button" class="btn btn-outline-success" id="exportCsvBtn"><i class="bi bi-filetype-csv"></i> CSV</button>
                <button type="button" class="btn btn-outline-success" id="exportExcelBtn"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="archiveTable">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>Batch</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
                    <th data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>Shift</th>
                    <th data-col="schedule" @if(!in_array('schedule', $visibleColumns, true)) style="display:none" @endif>Schedule</th>
                    <th data-col="capacity" @if(!in_array('capacity', $visibleColumns, true)) style="display:none" @endif>Capacity</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $batch)
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>
                            <span class="fw-semibold">{{ $batch->name }}</span>
                            <div class="text-muted small">{{ $batch->batch_code }}</div>
                        </td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $batch->institute->name ?? '—' }}</td>
                        <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $batch->course->name ?? '—' }}</td>
                        <td data-col="shift" @if(!in_array('shift', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($batch->shift) }}</td>
                        <td data-col="schedule" @if(!in_array('schedule', $visibleColumns, true)) style="display:none" @endif>
                            {{ $batch->start_date ? \Illuminate\Support\Carbon::parse($batch->start_date)->format('d M Y') : '—' }}
                            @if ($batch->end_date)
                                – {{ \Illuminate\Support\Carbon::parse($batch->end_date)->format('d M Y') }}
                            @endif
                        </td>
                        <td data-col="capacity" @if(!in_array('capacity', $visibleColumns, true)) style="display:none" @endif>{{ $batch->seat_filled }} / {{ $batch->seat_capacity }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$batch->status] ?? 'text-bg-secondary' }}">{{ ucwords($batch->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No archived batches found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->withQueryString()->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} archived batches</span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="archiveTablePrint">
        <thead>
            <tr>
                <th data-col="serial">#</th>
                <th data-col="batch">Batch</th>
                <th data-col="institute">Institute</th>
                <th data-col="course">Course</th>
                <th data-col="shift">Shift</th>
                <th data-col="schedule">Schedule</th>
                <th data-col="capacity">Capacity</th>
                <th data-col="status">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allItems as $batch)
                <tr>
                    <td data-col="serial">{{ $loop->iteration }}</td>
                    <td data-col="batch">{{ $batch->name }} ({{ $batch->batch_code }})</td>
                    <td data-col="institute">{{ $batch->institute->name ?? '—' }}</td>
                    <td data-col="course">{{ $batch->course->name ?? '—' }}</td>
                    <td data-col="shift">{{ ucwords($batch->shift) }}</td>
                    <td data-col="schedule">
                        {{ $batch->start_date ? \Illuminate\Support\Carbon::parse($batch->start_date)->format('d M Y') : '—' }}
                        @if ($batch->end_date)
                            – {{ \Illuminate\Support\Carbon::parse($batch->end_date)->format('d M Y') }}
                        @endif
                    </td>
                    <td data-col="capacity">{{ $batch->seat_filled }} / {{ $batch->seat_capacity }}</td>
                    <td data-col="status">{{ ucwords($batch->status) }}</td>
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
            fetch('{{ route('admin.courses.batches-columns') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ columns: visible })
            });
        };
    }

    function exportTable(fileName) {
        var table = document.getElementById('archiveTable');
        if (!table) { return; }
        var out = [];
        var headers = [];
        table.querySelectorAll('thead th').forEach(function (th) { headers.push(th.textContent.trim()); });
        out.push(headers.join(','));
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var cells = tr.querySelectorAll('td');
            if (!cells.length) { return; }
            var row = [];
            cells.forEach(function (cell) { row.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"'); });
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('archive.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('archive.xls'); }); }
})();
</script>
@endpush