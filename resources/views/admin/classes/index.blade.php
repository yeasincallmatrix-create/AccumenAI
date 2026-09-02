@extends('layouts.admin')

@section('title', 'Classes — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #classesTablePrint { width: 100%; font-size: 11px; }
        #classesTablePrint th, #classesTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
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
        <h4 class="page-header-title">Classes</h4>
        <p class="page-header-desc">Academic classes (courses) across institutes</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('admin.classes._tabs', [
        'activeTab' => 'classes',
        'classesCount' => $classesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.classes.index') }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by class name or code..." value="{{ $filters['q'] ?? '' }}">
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
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.classes.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $classes->total() }} Classes</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Academic classes = courses in academic categories.</span>
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
                        'serial'   => '#',
                        'code'     => 'Code',
                        'class'    => 'Class',
                        'category' => 'Category',
                        'level'    => 'Level',
                        'fee'      => 'Fee',
                        'subjects' => 'Subjects',
                        'batches'  => 'Batches',
                        'discount' => 'Discount',
                        'admission_fee' => 'Admission fee',
                        'exam_fee' => 'Exam fee',
                        'certificate_fee' => 'Certificate fee',
                        'duration' => 'Duration',
                        'mode'     => 'Mode',
                        'status'   => 'Status',
                        'assignment' => 'Assignment',
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
        <table class="table align-middle mb-0" id="classesTable">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                    <th data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>Class</th>
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Category</th>
                    <th data-col="level" @if(!in_array('level', $visibleColumns, true)) style="display:none" @endif>Level</th>
                    <th data-col="fee" @if(!in_array('fee', $visibleColumns, true)) style="display:none" @endif>Fee</th>
                    <th data-col="subjects" @if(!in_array('subjects', $visibleColumns, true)) style="display:none" @endif>Subjects</th>
                    <th data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>Batches</th>
                    <th data-col="discount" @if(!in_array('discount', $visibleColumns, true)) style="display:none" @endif>Discount</th>
                    <th data-col="admission_fee" @if(!in_array('admission_fee', $visibleColumns, true)) style="display:none" @endif>Admission fee</th>
                    <th data-col="exam_fee" @if(!in_array('exam_fee', $visibleColumns, true)) style="display:none" @endif>Exam fee</th>
                    <th data-col="certificate_fee" @if(!in_array('certificate_fee', $visibleColumns, true)) style="display:none" @endif>Certificate fee</th>
                    <th data-col="duration" @if(!in_array('duration', $visibleColumns, true)) style="display:none" @endif>Duration</th>
                    <th data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>Mode</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="assignment" @if(!in_array('assignment', $visibleColumns, true)) style="display:none" @endif>Assignment</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $class)
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $classes->firstItem() + $loop->index }}</td>
                        <td data-col="code" class="text-muted" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $class->course_code }}</td>
                        <td data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $class->name }}</div>
                        </td>
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>
                            {{ $class->category->name ?? '—' }}
                            @if($class->category?->subject_type)
                                <span class="badge text-bg-info badge-soft ms-1">Academic</span>
                            @endif
                        </td>
                        <td data-col="level" @if(!in_array('level', $visibleColumns, true)) style="display:none" @endif>{{ ucwords(str_replace('_', ' ', $class->level)) }}</td>
                        <td data-col="fee" @if(!in_array('fee', $visibleColumns, true)) style="display:none" @endif>{{ mawa_currency_symbol() }} {{ number_format($class->fee, 0) }}</td>
                        <td data-col="subjects" @if(!in_array('subjects', $visibleColumns, true)) style="display:none" @endif>{{ $class->subjects->count() }}</td>
                        <td data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ $class->batches_count }}</td>
                        <td data-col="discount" @if(!in_array('discount', $visibleColumns, true)) style="display:none" @endif>{{ $class->discount ? number_format($class->discount, 0) : '—' }}</td>
                        <td data-col="admission_fee" @if(!in_array('admission_fee', $visibleColumns, true)) style="display:none" @endif>{{ $class->admission_fee ? number_format($class->admission_fee, 0) : '—' }}</td>
                        <td data-col="exam_fee" @if(!in_array('exam_fee', $visibleColumns, true)) style="display:none" @endif>{{ $class->exam_fee ? number_format($class->exam_fee, 0) : '—' }}</td>
                        <td data-col="certificate_fee" @if(!in_array('certificate_fee', $visibleColumns, true)) style="display:none" @endif>{{ $class->certificate_fee ? number_format($class->certificate_fee, 0) : '—' }}</td>
                        <td data-col="duration" @if(!in_array('duration', $visibleColumns, true)) style="display:none" @endif>
                            {{ $class->duration_value ? $class->duration_value . ' ' . ucwords(str_replace('_', ' ', $class->duration_type)) : '—' }}
                        </td>
                        <td data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>{{ $class->mode ? ucwords(str_replace('_', ' ', $class->mode)) : '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$class->status] ?? 'text-bg-secondary' }}">{{ $class->status }}</span>
                        </td>
                        <td data-col="assignment" @if(!in_array('assignment', $visibleColumns, true)) style="display:none" @endif>{{ $class->institutes_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="text-center text-muted py-4">No academic classes found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $classes->withQueryString()->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $classes->total() }} classes</span>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('classesTable');
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
            fetch('{{ route('admin.classes.index-columns') }}', {
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
        var table = document.getElementById('classesTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('classes.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('classes.xls'); }); }
})();
</script>
@endpush