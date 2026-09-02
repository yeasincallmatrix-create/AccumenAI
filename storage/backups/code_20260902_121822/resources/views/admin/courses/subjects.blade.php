@extends('layouts.admin')

@section('title', 'Subjects — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #subjectsTablePrint { width: 100%; font-size: 11px; }
        #subjectsTablePrint th, #subjectsTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
        'draft'    => 'text-bg-warning',
    ];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Professional Subjects</h4>
        <p class="page-header-desc">Platform-wide professional subject catalog</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('admin.courses._tabs', [
        'activeTab' => 'subjects',
        'coursesCount' => $coursesCount,
        'batchesCount' => $batchesCount,
        'subjectsCount' => $subjectsCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.courses.subjects') }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by subject name, short name or code..." value="{{ $filters['q'] ?? '' }}">
            </div>

            <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                <label class="form-label mb-1">Institute</label>
                <div class="inst-dropdown" id="instDropdown">
                    <input type="text" class="form-control form-control-sm" id="instSearch" autocomplete="off"
                           placeholder="Search institute..." value="{{ $institutes->firstWhere('id', $selectedInstituteId ?? 0)->name ?? '' }}">
                    <input type="hidden" name="institute_id" id="instValue" value="{{ $selectedInstituteId ?? '' }}">
                    <span class="inst-caret"><i class="bi bi-chevron-down"></i></span>
                    <ul class="inst-list" id="instList">
                        @foreach ($institutes as $inst)
                            <li class="inst-item {{ ($selectedInstituteId ?? null) == $inst->id ? 'active' : '' }}" data-value="{{ $inst->id }}">{{ $inst->name }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Category</label>
                <select class="form-select form-select-sm" name="category_id">
                    <option value="">All Categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.courses.subjects') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-secondary badge-soft">{{ $items->total() }} Subjects</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Platform-wide subject catalog.</span>
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
                        'serial'    => '#',
                        'name'      => 'Subject',
                        'code'      => 'Code',
                        'type'      => 'Type',
                        'category'  => 'Category',
                        'institute' => 'Institute',
                        'status'    => 'Status',
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
        <table class="table align-middle mb-0" id="subjectsTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Subject</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                    <th data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>Type</th>
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Category</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $item->name }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $item->name }}</div>
                            @if ($item->short_name)
                                <div class="text-muted small">{{ $item->short_name }}</div>
                            @endif
                        </td>
                        <td class="text-muted" data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $item->subject_code }}</td>
                        <td data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($item->subject_type) }}</td>
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $item->category->name ?? '—' }}</td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $item->institute->name ?? '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$item->status] ?? 'text-bg-secondary' }}">{{ $item->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No subjects in the catalog yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->appends(array_filter(['q' => $filters['q'] ?? null, 'institute_id' => $filters['institute_id'] ?? null, 'category_id' => $filters['category_id'] ?? null, 'type' => $filters['type'] ?? null, 'status' => $filters['status'] ?? null]))->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} subjects</span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="subjectsTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Subject</th>
                <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                <th data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>Type</th>
                <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Category</th>
                <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allItems as $item)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $item->name }}@if ($item->short_name) ({{ $item->short_name }})@endif</td>
                    <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $item->subject_code }}</td>
                    <td data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($item->subject_type) }}</td>
                    <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $item->category->name ?? '—' }}</td>
                    <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $item->institute->name ?? '—' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($item->status) }}</td>
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
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
        });
    }

    // Drag-and-drop row reordering (visual, session only)
    var tableBody = document.getElementById('subjectsTable');
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

    // Column visibility toggle
    var table = document.getElementById('subjectsTable');
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
                var printTable = document.getElementById('subjectsTablePrint');
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
            fetch('{{ route('admin.courses.subjects-columns') }}', {
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

    // Searchable institute dropdown
    var dropdown = document.getElementById('instDropdown');
    var search = document.getElementById('instSearch');
    var list = document.getElementById('instList');
    var value = document.getElementById('instValue');
    var filterForm = document.querySelector('.filter-layout');
    if (dropdown && search && list && value) {
        var items = list.querySelectorAll('.inst-item');

        function toggle(open) {
            if (open) { list.classList.add('open'); search.focus(); }
            else { list.classList.remove('open'); }
        }

        search.addEventListener('focus', function () { toggle(true); });
        search.addEventListener('click', function () { toggle(true); });
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            items.forEach(function (li) {
                li.style.display = li.textContent.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
            });
            list.classList.add('open');
        });

        items.forEach(function (li) {
            li.addEventListener('click', function () {
                search.value = String(li.textContent || '').trim();
                var raw = li.getAttribute('data-value');
                var safe = raw ? String(raw).trim() : '';
                if (safe.indexOf('[object') !== -1 || safe.indexOf('%5Bobject') !== -1) { safe = ''; }
                value.value = safe;
                items.forEach(function (x) { x.classList.remove('active'); });
                li.classList.add('active');
                toggle(false);
                if (filterForm) { filterForm.submit(); }
            });
        });

        if (window.Monetix && Monetix.delegate) {
            Monetix.delegate('click', null, function (e) {
                if (!dropdown.contains(e.target)) toggle(false);
            }, 'mtx-admin-subjects-dropdown');
        }
    }

    // Auto-submit filters when a dropdown/select changes
    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    // CSV / Excel export from the current table
    function exportTable(fileName) {
        var table = document.getElementById('subjectsTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('subjects.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('subjects.xls'); }); }
})();
</script>
@endpush
