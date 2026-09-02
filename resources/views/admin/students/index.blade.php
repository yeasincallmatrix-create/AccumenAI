@extends('layouts.admin')

@section('title', 'Student Registration — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #studentsTablePrint { width: 100%; font-size: 11px; }
        #studentsTablePrint th, #studentsTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'active'    => 'text-bg-success',
        'completed' => 'text-bg-info',
        'dropped'   => 'text-bg-warning',
        'suspended' => 'text-bg-danger',
    ];
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Students</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Student Registration</h4>
        <p class="page-header-desc">All students registered across the platform</p>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.students.index') }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by name, ID, phone or email..." value="{{ $filters['q'] ?? '' }}">
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

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="type">
                    <option value="">All Types</option>
                    <option value="professional" @selected(($filters['type'] ?? '') === 'professional')>Professional</option>
                    <option value="academic" @selected(($filters['type'] ?? '') === 'academic')>Academic</option>
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                    <option value="dropped" @selected(($filters['status'] ?? '') === 'dropped')>Dropped</option>
                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.students.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-primary badge-soft">{{ $items->total() }} Students</span>
            <span class="text-muted ms-2 d-none d-lg-inline">All students registered across the platform.</span>
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
                        'serial'     => '#',
                        'student_id' => 'Student No',
                        'reg_no'     => 'Reg No',
                        'name'       => 'Name',
                        'institute'  => 'Institute',
                        'gender'     => 'Gender',
                        'phone'      => 'Phone',
                        'email'      => 'Email',
                        'admission'  => 'Admission',
                        'status'     => 'Status',
                        'action'     => 'Action',
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
        <table class="table align-middle mb-0" id="studentsTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="student_id" @if(!in_array('student_id', $visibleColumns, true)) style="display:none" @endif>Student No</th>
                    <th data-col="reg_no" @if(!in_array('reg_no', $visibleColumns, true)) style="display:none" @endif>Reg No</th>
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Name</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>Gender</th>
                    <th data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>Phone</th>
                    <th data-col="email" @if(!in_array('email', $visibleColumns, true)) style="display:none" @endif>Email</th>
                    <th data-col="admission" @if(!in_array('admission', $visibleColumns, true)) style="display:none" @endif>Admission</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $student)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $student->full_name }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td class="text-muted" data-col="student_id" @if(!in_array('student_id', $visibleColumns, true)) style="display:none" @endif>{{ $student->student_id }}</td>
                        <td data-col="reg_no" @if(!in_array('reg_no', $visibleColumns, true)) style="display:none" @endif>{{ $student->reg_no ?? '—' }}</td>
                        <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <a class="fw-semibold text-decoration-none" href="{{ route('admin.students.show', $student) }}">{{ $student->full_name }}</a>
                        </td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $student->institute->name ?? '—' }}</td>
                        <td data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>{{ $student->gender ? ucfirst($student->gender) : '—' }}</td>
                        <td data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>{{ $student->phone ?? '—' }}</td>
                        <td data-col="email" @if(!in_array('email', $visibleColumns, true)) style="display:none" @endif>{{ $student->email ?? '—' }}</td>
                        <td data-col="admission" @if(!in_array('admission', $visibleColumns, true)) style="display:none" @endif>{{ $student->admission_date?->format('d M Y') ?? '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$student->status] ?? 'text-bg-secondary' }}">{{ $student->status }}</span>
                        </td>
                        <td class="text-end text-nowrap col-action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.students.show', $student) }}">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No students found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} students</span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="studentsTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="student_id" @if(!in_array('student_id', $visibleColumns, true)) style="display:none" @endif>Student No</th>
                <th data-col="reg_no" @if(!in_array('reg_no', $visibleColumns, true)) style="display:none" @endif>Reg No</th>
                <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Name</th>
                <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                <th data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>Gender</th>
                <th data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>Phone</th>
                <th data-col="email" @if(!in_array('email', $visibleColumns, true)) style="display:none" @endif>Email</th>
                <th data-col="admission" @if(!in_array('admission', $visibleColumns, true)) style="display:none" @endif>Admission</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allItems as $student)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="student_id" @if(!in_array('student_id', $visibleColumns, true)) style="display:none" @endif>{{ $student->student_id }}</td>
                    <td data-col="reg_no" @if(!in_array('reg_no', $visibleColumns, true)) style="display:none" @endif>{{ $student->reg_no ?? '—' }}</td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $student->full_name }}</td>
                    <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $student->institute->name ?? '—' }}</td>
                    <td data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>{{ $student->gender ? ucfirst($student->gender) : '—' }}</td>
                    <td data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>{{ $student->phone ?? '—' }}</td>
                    <td data-col="email" @if(!in_array('email', $visibleColumns, true)) style="display:none" @endif>{{ $student->email ?? '—' }}</td>
                    <td data-col="admission" @if(!in_array('admission', $visibleColumns, true)) style="display:none" @endif>{{ $student->admission_date?->format('d M Y') ?? '—' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($student->status) }}</td>
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
    var tableBody = document.getElementById('studentsTable');
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
    var table = document.getElementById('studentsTable');
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
                var printTable = document.getElementById('studentsTablePrint');
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
            fetch('{{ route('admin.students.columns') }}', {
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
                // Non-destructive guard: data-value is numeric ID, never "[object HTMLInputElement]"
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
            }, 'mtx-admin-students-dropdown');
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
        var table = document.getElementById('studentsTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('students.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('students.xls'); }); }
})();
</script>
@endpush