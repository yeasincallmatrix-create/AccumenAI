@extends('layouts.institute')

@section('title', 'Curriculum & Versioning — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #curriculaTablePrint { width: 100%; font-size: 11px; }
        #curriculaTablePrint th, #curriculaTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = ['draft' => 'text-bg-warning', 'active' => 'text-bg-success', 'archived' => 'text-bg-secondary'];
    $statusNames = ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'];
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Curricula</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Curriculum &amp; Versioning</h4>
        <p class="page-header-desc">{{ $curricula->total() }} curricula across all courses</p>
    </div>
    <div class="page-header-actions">
        @if ($user->hasPermission('curriculum.manage'))
            <a href="{{ route('curricula.create', $courseId ? ['course_id' => $courseId] : []) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Curriculum
            </a>
        @endif
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('curricula.index') }}">
        <input type="hidden" name="per_page" value="{{ $perPage ?? 15 }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by course name..." value="{{ $q ?? '' }}">
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Course</label>
                <select class="form-select form-select-sm" name="course_id">
                    <option value="">All Courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) $courseId === (string) $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    @foreach ($statusNames as $slug => $label)
                        <option value="{{ $slug }}" @selected((string) $status === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('curricula.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
    </div>
@endif

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-primary badge-soft">{{ $curricula->total() }} Curricula</span>
            <span class="text-muted ms-2 d-none d-lg-inline">All curriculum versions across courses.</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false" title="Rows per page">
                    <i class="bi bi-list-ol"></i> Show: {{ $perPage ?? 15 }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="perPageMenu">
                    <li><h6 class="dropdown-header">Rows per page</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach (($perPageOptions ?? [15,25,50,75,100,200]) as $opt)
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between @if(($perPage ?? 15) === $opt) active @endif"
                               href="{{ request()->fullUrlWithQuery(['per_page' => $opt, 'page' => 1]) }}">
                                {{ $opt }}
                                @if(($perPage ?? 15) === $opt) <i class="bi bi-check-lg"></i> @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> Columns <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenu">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial'    => '#',
                        'course'    => 'Course',
                        'version'   => 'Version',
                        'title'     => 'Title',
                        'effective' => 'Effective',
                        'modules'   => 'Modules',
                        'batches'   => 'Batches',
                        'status'    => 'Status',
                        'action'    => 'Actions',
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
        <table class="table align-middle mb-0" id="curriculaTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
                    <th data-col="version" @if(!in_array('version', $visibleColumns, true)) style="display:none" @endif>Version</th>
                    <th data-col="title" @if(!in_array('title', $visibleColumns, true)) style="display:none" @endif>Title</th>
                    <th data-col="effective" @if(!in_array('effective', $visibleColumns, true)) style="display:none" @endif>Effective</th>
                    <th data-col="modules" class="text-center" @if(!in_array('modules', $visibleColumns, true)) style="display:none" @endif>Modules</th>
                    <th data-col="batches" class="text-center" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>Batches</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($curricula as $index => $curriculum)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" value="{{ $curriculum->id }}" data-id="{{ $curriculum->id }}" data-name="{{ $curriculum->title }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $curricula->firstItem() + $index }}</td>
                        <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>
                            <a class="fw-semibold text-decoration-none" href="{{ route('courses.show', $curriculum->course) }}">{{ $curriculum->course?->name ?? '—' }}</a>
                            @if ($curriculum->course?->course_code)
                                <div class="text-muted small">{{ $curriculum->course->course_code }}</div>
                            @endif
                        </td>
                        <td data-col="version" @if(!in_array('version', $visibleColumns, true)) style="display:none" @endif><span class="badge text-bg-dark">v{{ $curriculum->version }}</span></td>
                        <td data-col="title" @if(!in_array('title', $visibleColumns, true)) style="display:none" @endif>
                            <a href="{{ route('curricula.show', $curriculum) }}" class="text-decoration-none fw-semibold">{{ $curriculum->title }}</a>
                        </td>
                        <td data-col="effective" class="text-muted" @if(!in_array('effective', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->effective_date?->format('d M Y') ?? '—' }}</td>
                        <td data-col="modules" class="text-center" @if(!in_array('modules', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->modules_count }}</td>
                        <td data-col="batches" class="text-center" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->batches_count }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$curriculum->status] ?? 'text-bg-secondary' }}">{{ $statusNames[$curriculum->status] ?? $curriculum->status }}</span>
                        </td>
                        <td class="text-end text-nowrap col-action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            <a href="{{ route('curricula.show', $curriculum) }}" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if ($user->hasPermission('curriculum.manage'))
                                <a href="{{ route('curricula.edit', $curriculum) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No curricula yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $curricula->links('pagination::bootstrap-5') }}
        <span class="text-muted small">
            @if($curricula->total() > 0)
                Showing {{ $curricula->firstItem() }}–{{ $curricula->lastItem() }} of {{ $curricula->total() }} curricula ({{ $perPage ?? 15 }} per page)
            @else
                {{ $curricula->total() }} curricula
            @endif
        </span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="curriculaTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
                <th data-col="version" @if(!in_array('version', $visibleColumns, true)) style="display:none" @endif>Version</th>
                <th data-col="title" @if(!in_array('title', $visibleColumns, true)) style="display:none" @endif>Title</th>
                <th data-col="effective" @if(!in_array('effective', $visibleColumns, true)) style="display:none" @endif>Effective</th>
                <th data-col="modules" @if(!in_array('modules', $visibleColumns, true)) style="display:none" @endif>Modules</th>
                <th data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>Batches</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($curricula as $curriculum)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->course?->name ?? '—' }}</td>
                    <td data-col="version" @if(!in_array('version', $visibleColumns, true)) style="display:none" @endif>v{{ $curriculum->version }}</td>
                    <td data-col="title" @if(!in_array('title', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->title }}</td>
                    <td data-col="effective" @if(!in_array('effective', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->effective_date?->format('d M Y') ?? '—' }}</td>
                    <td data-col="modules" @if(!in_array('modules', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->modules_count }}</td>
                    <td data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ $curriculum->batches_count }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ $statusNames[$curriculum->status] ?? ucfirst($curriculum->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selectAll = document.getElementById('selectAll');

    function getSelectedChecks() {
        return document.querySelectorAll('.row-check:checked');
    }
    function updateBatchUI() {
        var count = getSelectedChecks().length;
        if (selectAll) {
            var all = document.querySelectorAll('.row-check');
            selectAll.checked = all.length > 0 && count === all.length;
            selectAll.indeterminate = count > 0 && count < all.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
            updateBatchUI();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('row-check')) updateBatchUI();
    });
    updateBatchUI();

    var tableBody = document.getElementById('curriculaTable');
    if (tableBody) { tableBody = tableBody.querySelector('tbody'); }
    if (tableBody) {
        var draggedRow = null;

        function reorderAndAnimate(target, after) {
            var rows = Array.prototype.slice.call(tableBody.children);
            var prev = new Map();
            rows.forEach(function (tr) { prev.set(tr, tr.getBoundingClientRect().top); });
            var moved = false;
            if (after && target.nextElementSibling !== draggedRow) {
                tableBody.insertBefore(draggedRow, target.nextElementSibling);
                moved = true;
            } else if (!after && target.previousElementSibling !== draggedRow) {
                tableBody.insertBefore(draggedRow, target);
                moved = true;
            }
            if (!moved) { return; }
            var afterRows = Array.prototype.slice.call(tableBody.children);
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

        tableBody.addEventListener('dragstart', function (e) {
            var handle = e.target.closest('.drag-handle');
            if (!handle) { e.preventDefault(); return; }
            draggedRow = handle.closest('tr');
            draggedRow.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'row');
        });
        tableBody.addEventListener('dragover', function (e) {
            if (!draggedRow) { return; }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var target = e.target.closest('tr');
            if (!target || target === draggedRow) { return; }
            var rect = target.getBoundingClientRect();
            reorderAndAnimate(target, (e.clientY - rect.top) > (rect.height / 2));
        });
        tableBody.addEventListener('dragend', function () {
            draggedRow = null;
            tableBody.querySelectorAll('.dragging, .drag-over').forEach(function (el) {
                el.classList.remove('dragging', 'drag-over');
                el.style.transition = '';
                el.style.transform = '';
            });
        });
    }

    var table = document.getElementById('curriculaTable');
    var colChecks = document.querySelectorAll('.col-toggle-check');
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
                var printTable = document.getElementById('curriculaTablePrint');
                if (printTable) {
                    printTable.querySelectorAll('[data-col="' + col + '"]').forEach(function (el) {
                        el.style.display = hidden ? 'none' : '';
                    });
                }
            });
        });
    }

    var filterForm = document.querySelector('.filter-layout');
    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    function exportTable(fileName) {
        var table = document.getElementById('curriculaTable');
        if (!table) { return; }
        var out = [];
        var headers = [];
        table.querySelectorAll('thead th').forEach(function (th, i) {
            if (i > 1) { headers.push(th.textContent.trim()); }
        });
        out.push(headers.join(','));
        table.querySelectorAll('tbody tr').forEach(function (tr) {
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('curricula.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('curricula.xls'); }); }
})();
</script>
@endpush
