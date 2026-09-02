@extends('layouts.institute')

@section('title', 'Course Master — AccumenAI')

@php
    $statusBadge = ['active' => 'text-bg-success', 'inactive' => 'text-bg-secondary', 'draft' => 'text-bg-warning'];
    $statusNames = ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'];
@endphp

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #courseMasterTablePrint { width: 100%; font-size: 11px; }
        #courseMasterTablePrint th, #courseMasterTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active" aria-current="page">Courses</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Course Master</h4>
        <p class="page-header-desc">{{ $courses->total() }} institute-owned courses — author details, pricing and curriculum here.</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('courses.manage.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Course
        </a>
        <a href="{{ route('batches.index') }}" class="btn btn-outline-primary btn-sm" title="Create batch for any course">
            <i class="bi bi-collection me-1"></i>Add Batch
        </a>
    </div>
</div>

@include('institute.course-master._tabs', [
    'activeTab' => 'courses',
    'coursesCount' => $courses->total(),
    'subjectsCount' => $subjectsCount ?? 0,
])

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('courses.manage.index') }}" data-ajax-filter>
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by name or code" value="{{ $q }}">
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach ($statusNames as $slug => $label)
                        <option value="{{ $slug }}" @selected((string) $status === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('courses.manage.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card" data-ajax-table>

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $courses->total() }} Courses</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Courses your institute owns — author details, pricing and curriculum here.</span>
        </div>
        <div class="toolbar-actions">
            <div id="batchActions" class="d-none align-items-center gap-2">
                <span class="badge text-bg-dark" id="batchCount">0 selected</span>
                <span class="text-muted small d-none d-lg-inline"><span class="batch-count-text">0</span> selected</span>
            </div>
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false" title="Rows per page">
                    <i class="bi bi-list-ol"></i> Show: {{ $courses->perPage() }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="perPageMenu">
                    <li><h6 class="dropdown-header">Rows per page</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([15,25,50,75,100,200] as $opt)
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between @if($courses->perPage() === $opt) active @endif"
                               href="{{ request()->fullUrlWithQuery(['per_page' => $opt, 'page' => 1]) }}">
                                {{ $opt }}
                                @if($courses->perPage() === $opt) <i class="bi bi-check-lg"></i> @endif
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
                        'serial'   => '#',
                        'code'     => 'Code',
                        'course'   => 'Course',
                        'category' => 'Category',
                        'fee'      => 'Fee',
                        'versions' => 'Versions',
                        'batches'  => 'Batches',
                        'status'   => 'Status',
                        'action'   => 'Actions',
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="col-toggle-{{ $col }}">
                                <input type="checkbox" id="col-toggle-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" checked>
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
        <table class="table align-middle mb-0" id="courseMasterTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted">#</th>
                    <th data-col="code">Code</th>
                    <th data-col="course">Course</th>
                    <th data-col="category">Category</th>
                    <th data-col="fee">Fee</th>
                    <th data-col="versions" class="text-center">Versions</th>
                    <th data-col="batches" class="text-center">Batches</th>
                    <th data-col="status">Status</th>
                    <th data-col="action" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courses as $index => $course)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" value="{{ $course->id }}" data-id="{{ $course->id }}" data-name="{{ $course->name }}"></td>
                        <td data-col="serial" class="text-muted">{{ $courses->firstItem() + $index }}</td>
                        <td data-col="code" class="text-muted">{{ $course->course_code ?? '—' }}</td>
                        <td data-col="course">
                            <a class="fw-semibold text-decoration-none" href="{{ route('courses.show', $course) }}">{{ $course->name }}</a>
                            @if($course->course_code)
                                <div class="text-muted small">{{ $course->course_code }}</div>
                            @endif
                        </td>
                        <td data-col="category">{{ $course->category->name ?? '—' }}</td>
                        <td data-col="fee">{{ number_format($course->fee ?? 0, 0) }}</td>
                        <td data-col="versions" class="text-center">{{ $course->curricula_count }}</td>
                        <td data-col="batches" class="text-center">{{ $course->batches_count }}</td>
                        <td data-col="status">
                            <span class="badge {{ $statusBadge[$course->status] ?? 'text-bg-secondary' }}">{{ $statusNames[$course->status] ?? $course->status }}</span>
                        </td>
                        <td data-col="action" class="text-end text-nowrap col-action">
                            <a href="{{ route('curricula.index', ['course_id' => $course->id]) }}" class="btn btn-sm btn-outline-primary">Curriculum</a>
                            <a href="{{ route('courses.manage.edit', $course) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No institute-owned courses yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $courses->links('pagination::bootstrap-5') }}
        <span class="text-muted small">
            @if($courses->total() > 0)
                Showing {{ $courses->firstItem() }}–{{ $courses->lastItem() }} of {{ $courses->total() }} courses ({{ $courses->perPage() }} per page)
            @else
                {{ $courses->total() }} courses
            @endif
        </span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="courseMasterTablePrint">
        <thead>
            <tr>
                <th data-col="serial">#</th>
                <th data-col="code">Code</th>
                <th data-col="course">Course</th>
                <th data-col="category">Category</th>
                <th data-col="fee">Fee</th>
                <th data-col="versions">Versions</th>
                <th data-col="batches">Batches</th>
                <th data-col="status">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($courses as $course)
                <tr>
                    <td data-col="serial">{{ $loop->iteration }}</td>
                    <td data-col="code">{{ $course->course_code ?? '—' }}</td>
                    <td data-col="course">{{ $course->name }}</td>
                    <td data-col="category">{{ $course->category->name ?? '—' }}</td>
                    <td data-col="fee">{{ number_format($course->fee ?? 0, 0) }}</td>
                    <td data-col="versions">{{ $course->curricula_count }}</td>
                    <td data-col="batches">{{ $course->batches_count }}</td>
                    <td data-col="status">{{ ucwords($course->status) }}</td>
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
    var batchActions = document.getElementById('batchActions');
    var batchCountEl = document.getElementById('batchCount');
    function getSelectedChecks() { return document.querySelectorAll('#courseMasterTable .row-check:checked'); }
    function updateBatchUI() {
        var count = getSelectedChecks().length;
        if (batchActions) {
            if (count >= 2) { batchActions.classList.remove('d-none'); batchActions.classList.add('d-flex'); }
            else { batchActions.classList.add('d-none'); batchActions.classList.remove('d-flex'); }
        }
        if (batchCountEl) batchCountEl.textContent = count + ' selected';
        document.querySelectorAll('.batch-count-text').forEach(function(el){ el.textContent = count; });
        if (selectAll) {
            var all = document.querySelectorAll('#courseMasterTable .row-check');
            selectAll.checked = all.length > 0 && count === all.length;
            selectAll.indeterminate = count > 0 && count < all.length;
        }
    }
    if (selectAll) { selectAll.addEventListener('change', function(){ document.querySelectorAll('#courseMasterTable .row-check').forEach(function(cb){ cb.checked = selectAll.checked; }); updateBatchUI(); }); }
    document.addEventListener('change', function(e){ if(e.target && e.target.classList.contains('row-check')) updateBatchUI(); });
    updateBatchUI();

    var table = document.getElementById('courseMasterTable');
    var tbody = table ? table.querySelector('tbody') : null;
    if (tbody) {
        var draggedRow = null;
        function reorderAndAnimate(target, after) {
            var rows = Array.prototype.slice.call(tbody.children);
            var prev = new Map();
            rows.forEach(function(tr){ prev.set(tr, tr.getBoundingClientRect().top); });
            var moved = false;
            if (after && target.nextElementSibling !== draggedRow) { tbody.insertBefore(draggedRow, target.nextElementSibling); moved = true; }
            else if (!after && target.previousElementSibling !== draggedRow) { tbody.insertBefore(draggedRow, target); moved = true; }
            if (!moved) return;
            var afterRows = Array.prototype.slice.call(tbody.children);
            afterRows.forEach(function(tr){ var delta = prev.get(tr) - tr.getBoundingClientRect().top; if(delta){ tr.style.transition='none'; tr.style.transform='translateY('+delta+'px)'; } });
            requestAnimationFrame(function(){ requestAnimationFrame(function(){ afterRows.forEach(function(tr){ tr.style.transition='transform .3s cubic-bezier(.2,.85,.35,1)'; tr.style.transform=''; }); }); });
        }
        tbody.addEventListener('dragstart', function(e){
            var handle = e.target.closest('.drag-handle');
            if(!handle){ e.preventDefault(); return; }
            draggedRow = handle.closest('tr');
            draggedRow.classList.add('dragging');
            e.dataTransfer.effectAllowed='move';
            e.dataTransfer.setData('text/plain','row');
        });
        tbody.addEventListener('dragover', function(e){
            if(!draggedRow) return;
            e.preventDefault();
            e.dataTransfer.dropEffect='move';
            var target = e.target.closest('tr');
            if(!target || target===draggedRow) return;
            var rect = target.getBoundingClientRect();
            reorderAndAnimate(target, (e.clientY - rect.top) > (rect.height/2));
        });
        tbody.addEventListener('dragend', function(){
            draggedRow = null;
            tbody.querySelectorAll('.dragging, .drag-over').forEach(function(el){ el.classList.remove('dragging','drag-over'); el.style.transition=''; el.style.transform=''; });
        });
    }

    var colChecks = document.querySelectorAll('.col-toggle-check');
    if (table && colChecks.length) {
        colChecks.forEach(function(check){
            check.addEventListener('change', function(){
                var col = check.getAttribute('data-col');
                var th = table.querySelector('th[data-col="'+col+'"]');
                if(!th) return;
                var index = Array.prototype.indexOf.call(th.parentNode.children, th);
                var hidden = !check.checked;
                th.style.display = hidden ? 'none' : '';
                table.querySelectorAll('tbody tr').forEach(function(tr){
                    var td = tr.children[index];
                    if(td) td.style.display = hidden ? 'none' : '';
                });
                var printTable = document.getElementById('courseMasterTablePrint');
                if(printTable){ printTable.querySelectorAll('[data-col="'+col+'"]').forEach(function(el){ el.style.display = hidden ? 'none' : ''; }); }
            });
        });
    }

    var filterForm = document.querySelector('.filter-layout');
    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function(select){
            select.addEventListener('change', function(){ filterForm.submit(); });
        });
    }

    function exportTable(fileName){
        var tbl = document.getElementById('courseMasterTable');
        if(!tbl) return;
        var out=[]; var headers=[];
        tbl.querySelectorAll('thead th').forEach(function(th,i){ if(i>1) headers.push(th.textContent.trim()); });
        out.push(headers.join(','));
        tbl.querySelectorAll('tbody tr').forEach(function(tr){
            var cells=tr.querySelectorAll('td');
            if(!cells.length) return;
            var row=[];
            for(var i=2;i<cells.length;i++){
                if(cells[i].style.display==='none') continue;
                row.push('"'+cells[i].textContent.trim().replace(/"/g,'""')+'"');
            }
            if(row.length) out.push(row.join(','));
        });
        var blob=new Blob(['\ufeff'+out.join('\r\n')],{type:'text/csv;charset=utf-8;'});
        var link=document.createElement('a');
        link.href=URL.createObjectURL(blob);
        link.download=fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    var csvBtn=document.getElementById('exportCsvBtn');
    if(csvBtn) csvBtn.addEventListener('click', function(){ exportTable('course-master.csv'); });
    var excelBtn=document.getElementById('exportExcelBtn');
    if(excelBtn) excelBtn.addEventListener('click', function(){ exportTable('course-master.xls'); });
})();
</script>
@endpush
