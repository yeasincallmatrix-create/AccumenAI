@extends('layouts.admin')

@section('title', 'Class Batches — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #batchesTablePrint { width: 100%; font-size: 11px; }
        #batchesTablePrint th, #batchesTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'upcoming'  => 'text-bg-warning',
        'running'   => 'text-bg-success',
        'completed' => 'text-bg-secondary',
        'cancelled' => 'text-bg-danger',
        'archived'  => 'text-bg-dark',
    ];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Class Batches</h4>
        <p class="page-header-desc">Batches of academic classes across institutes</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('admin.classes._tabs', [
        'activeTab' => 'batches',
        'classesCount' => $classesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.classes.batches') }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by batch, code or class..." value="{{ $filters['q'] ?? '' }}">
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

            <div class="filter-span flex-shrink-0" style="min-width:140px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    @foreach (['upcoming', 'running', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.classes.batches') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $items->total() }} Batches</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Non-archived class batches.</span>
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
                        'class'   => 'Class',
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
        <table class="table align-middle mb-0" id="batchesTable">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>Batch</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>Class</th>
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
                        <td data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>{{ $batch->course->name ?? '—' }}</td>
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
                        <td colspan="8" class="text-center text-muted py-4">No class batches found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->withQueryString()->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} batches</span>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('batchesTable');
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
                if (saveCols) { saveCols(); }
            });
        });

        saveCols = function () {
            var visible = [];
            colChecks.forEach(function (check) {
                if (check.checked) { visible.push(check.getAttribute('data-col')); }
            });
            fetch('{{ route('admin.classes.batches-columns') }}', {
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

    var filterForm = document.querySelector('.filter-layout');
    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    function exportTable(fileName) {
        var table = document.getElementById('batchesTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('class-batches.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('class-batches.xls'); }); }
})();
</script>
@endpush