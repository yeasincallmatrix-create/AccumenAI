@extends('layouts.institute')

@section('title', mawa_lang('subjects.tab_subjects') . ' — AccumenAI')

@push('styles')
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
    .subject-type-badge {
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
    }
    .usage-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .usage-indicator .count {
        font-size: 0.75rem;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('subjects.tab_subjects') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('subjects.management_desc') }} — {{ $institute->name ?? '' }}</p>
    </div>
</div>

@include('institute.course-master._tabs', [
    'activeTab' => 'subjects',
    'coursesCount' => $stats['total'] ?? 0,
    'subjectsCount' => $stats['total'] ?? 0,
])

<div class="admin-card" data-ajax-table>

    <div class="filter-card">
        <form class="filter-layout" method="GET" action="{{ route('courses.manage.subjects.index') }}" data-ajax-filter>

            <div class="filter-search-row align-items-end">

                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" name="q" placeholder="{{ mawa_e('subjects.search_placeholder') }}" value="{{ $q }}">
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:150px;">
                    <label class="form-label mb-1">Type</label>
                    <select class="form-select form-select-sm" name="subject_type">
                        <option value="">All Types</option>
                        @foreach ($allSubjectTypes as $type)
                            <option value="{{ $type }}" @selected((string) $subjectType === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">Category</label>
                    <select class="form-select form-select-sm" name="category_id">
                        <option value="">All Categories</option>
                        @foreach ($filterCategories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) $categoryId === (string) $cat->id)>{{ $cat->name }} <span class="badge text-bg-light text-dark subject-type-badge">{{ $cat->subject_type }}</span></option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:150px;">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Status</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:120px;">
                    <label class="form-label mb-1">View</label>
                    <select class="form-select form-select-sm" name="trashed">
                        <option value="0" @selected(!$trashed)>Active</option>
                        <option value="1" @selected($trashed)>{{ $stats['trashed'] > 0 ? 'Archived (' . $stats['trashed'] . ')' : 'Archived' }}</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('courses.manage.subjects.index') }}"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>

            </div>

        </form>
    </div>

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $stats['academic'] ?? 0 }} Academic</span>
            <span class="badge text-bg-info badge-soft ms-1">{{ $stats['professional'] ?? 0 }} Professional</span>
            <span class="text-muted ms-2 d-none d-lg-inline">{{ mawa_lang('subjects.management_desc') }}</span>
        </div>
        <div class="toolbar-actions">
            @if ($user->hasPermission('courses.manage'))
                <a href="{{ route('courses.manage.subjects.create') }}" class="btn btn-sm btn-primary d-flex align-items-center gap-2">
                    <i class="bi bi-plus-lg"></i> {{ mawa_e('subjects.add_subject') }}
                </a>
            @endif
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> Columns <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenu">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial' => '#',
                        'name' => 'Subject',
                        'code' => 'Code',
                        'type' => 'Type',
                        'category' => 'Category',
                        'status' => 'Status',
                        'usage' => 'Usage',
                        'created_at' => 'Created',
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
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="usage" @if(!in_array('usage', $visibleColumns, true)) style="display:none" @endif>Usage</th>
                    <th data-col="created_at" @if(!in_array('created_at', $visibleColumns, true)) style="display:none" @endif>Created</th>
                    @if ($user->hasPermission('courses.manage'))
                        <th class="text-end col-actions">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $subject->name }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $subjects->firstItem() + $loop->index }}</td>
                        <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $subject->name }}</div>
                            @if ($subject->short_name)
                                <div class="text-muted small">{{ $subject->short_name }}</div>
                            @endif
                        </td>
                        <td data-col="code" class="text-muted" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $subject->subject_code }}</td>
                        <td data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $subject->subject_type === 'academic' ? 'text-bg-primary' : 'text-bg-info' }} subject-type-badge">{{ ucfirst($subject->subject_type) }}</span>
                        </td>
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $subject->category->name ?? '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $subject->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucwords($subject->status) }}</span>
                        </td>
                        <td data-col="usage" @if(!in_array('usage', $visibleColumns, true)) style="display:none" @endif>
                            <div class="usage-indicator">
                                <span class="count">
                                    {{ $subject->courses_count ?? 0 }} courses,
                                    {{ $subject->academic_assignments_count ?? 0 }} academic
                                </span>
                                <a href="{{ route('courses.manage.subjects.dependencies', $subject) }}" class="btn btn-sm btn-outline-primary p-0 text-decoration-none" title="View dependencies">
                                    <i class="bi bi-diagram-3"></i>
                                </a>
                            </div>
                        </td>
                        <td data-col="created_at" class="text-muted" @if(!in_array('created_at', $visibleColumns, true)) style="display:none" @endif>{{ $subject->created_at?->format('Y-m-d') }}</td>
                        @if ($user->hasPermission('courses.manage'))
                            <td class="text-end col-actions">
                                <a href="{{ route('courses.manage.subjects.edit', $subject) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                @if (!$subject->trashed())
                                    <form method="POST" action="{{ route('courses.manage.subjects.destroy', $subject) }}" class="d-inline" onsubmit="return confirm('{{ mawa_lang('subjects.delete_confirm') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('courses.manage.subjects.restore', $subject) }}" class="d-inline" onsubmit="return confirm('{{ mawa_lang('subjects.restore_confirm') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">{{ mawa_e('subjects.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $subjects->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $subjects->total() }} subjects</span>
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
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                <th data-col="usage" @if(!in_array('usage', $visibleColumns, true)) style="display:none" @endif>Usage</th>
                <th data-col="created_at" @if(!in_array('created_at', $visibleColumns, true)) style="display:none" @endif>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $subject)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $subject->name }}@if ($subject->short_name) ({{ $subject->short_name }})@endif</td>
                    <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $subject->subject_code }}</td>
                    <td data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>{{ ucfirst($subject->subject_type) }}</td>
                    <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $subject->category->name ?? '—' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($subject->status) }}</td>
                    <td data-col="usage" @if(!in_array('usage', $visibleColumns, true)) style="display:none" @endif>{{ $subject->courses_count ?? 0 }} courses, {{ $subject->academic_assignments_count ?? 0 }} academic</td>
                    <td data-col="created_at" @if(!in_array('created_at', $visibleColumns, true)) style="display:none" @endif>{{ $subject->created_at?->format('Y-m-d') }}</td>
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
            document.querySelectorAll('#subjectsTable .row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
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

    // Column visibility toggle (mirrors into the print table) + persistence
    var table = document.getElementById('subjectsTable');
    var colChecks = document.querySelectorAll('.col-toggle-check');
    var saveCols = null;
    if (table && colChecks.length) {
        colChecks.forEach(function (check) {
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
            fetch('{{ route('ui.columns.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ key: 'subject_management', columns: visible })
            });
        };
    }

    // CSV / Excel export from the current (visible) table
    function exportTable(fileName) {
        var table = document.getElementById('subjectsTable');
        if (!table) { return; }
        var out = [];
        var headers = [];
        table.querySelectorAll('thead th').forEach(function (th, i) {
            if (i > 1 && !th.classList.contains('col-actions')) { headers.push(th.textContent.trim()); }
        });
        out.push(headers.join(','));
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var cells = tr.querySelectorAll('td');
            if (!cells.length) { return; }
            var row = [];
            for (var i = 2; i < cells.length; i++) {
                if (cells[i].classList.contains('col-actions')) { continue; }
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