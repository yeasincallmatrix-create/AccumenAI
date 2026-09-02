@extends('layouts.admin')

@section('title', 'Courses — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #coursesTablePrint { width: 100%; font-size: 11px; }
        #coursesTablePrint th, #coursesTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
        'draft'    => 'text-bg-warning',
    ];
    $activeTab = 'courses';
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Courses</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Courses</h4>
        <p class="page-header-desc">Platform-wide course catalog</p>
    </div>
</div>

<div class="admin-card" data-ajax-table>
    @include('admin.courses._tabs', [
        'activeTab' => $activeTab,
        'coursesCount' => $courses->total(),
        'batchesCount' => $batchesCount,
        'subjectsCount' => $subjectsCount,
        'archiveCount' => $archiveCount,
    ])

    <div class="filter-card mb-3">
                <form class="filter-layout" method="GET" action="{{ route('admin.courses.index') }}" data-ajax-filter>
                    <input type="hidden" name="tab" value="courses">

                    <div class="filter-search-row align-items-end">

                        <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by course name or code..." value="{{ $filters['q'] ?? '' }}">
                        </div>

                        <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                            <label class="form-label mb-1">Category</label>
                            <select class="form-select form-select-sm" name="category_id">
                                <option value="">All Categories</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
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
                                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.courses.index', ['tab' => 'courses']) }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                        </div>

                    </div>
                </form>
            </div>

            <div class="table-toolbar">
                <div class="toolbar-info">
                    <span class="badge text-bg-success badge-soft">{{ $courses->total() }} Courses</span>
                    <span class="text-muted ms-2 d-none d-lg-inline">Click a row to open a course, or use the columns toggle to shape this list.</span>
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
                                'serial'           => '#',
                                'code'             => 'Code',
                                'course'           => 'Course',
                                'category'         => 'Category',
                                'level'            => 'Level',
                                'fee'              => 'Fee',
                                'subjects'         => 'Subjects',
                                'batches'          => 'Batches',
                                'discount'         => 'Discount',
                                'admission_fee'    => 'Admission fee',
                                'exam_fee'         => 'Exam fee',
                                'certificate_fee'  => 'Certificate fee',
                                'duration'         => 'Duration',
                                'weekly_classes'   => 'Weekly classes',
                                'total_classes'    => 'Total classes',
                                'class_duration'   => 'Class duration',
                                'mode'             => 'Mode',
                                'batch_capacity'   => 'Batch capacity',
                                'status'           => 'Status',
                                'assignment'       => 'Assignment',
                                'action'           => 'Action',
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
                <table class="table align-middle mb-0" id="coursesTable">
                    <thead>
                        <tr>
                            <th class="col-handle" style="width:42px"></th>
                            <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                            <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                            <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
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
                            <th data-col="weekly_classes" @if(!in_array('weekly_classes', $visibleColumns, true)) style="display:none" @endif>Weekly classes</th>
                            <th data-col="total_classes" @if(!in_array('total_classes', $visibleColumns, true)) style="display:none" @endif>Total classes</th>
                            <th data-col="class_duration" @if(!in_array('class_duration', $visibleColumns, true)) style="display:none" @endif>Class duration</th>
                            <th data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>Mode</th>
                            <th data-col="batch_capacity" @if(!in_array('batch_capacity', $visibleColumns, true)) style="display:none" @endif>Batch capacity</th>
                            <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                            <th data-col="assignment" @if(!in_array('assignment', $visibleColumns, true)) style="display:none" @endif>Assignment</th>
                            <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($courses as $course)
                            <tr class="table-row-click" data-row-href="{{ route('admin.courses.show', ['course' => $course, 'industry' => 'education']) }}">
                                <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                                <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $course->name }}"></td>
                                <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $courses->firstItem() + $loop->index }}</td>
                                <td data-col="code" class="text-muted" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $course->course_code }}</td>
                                <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>
                                    <a class="fw-semibold text-decoration-none" href="{{ route('admin.courses.show', ['course' => $course, 'industry' => 'education']) }}">{{ $course->name }}</a>
                                </td>
<td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>
                                    {{ $course->category->name ?? '—' }}
                                    @if($course->category?->subject_type)
                                        <span class="badge {{ $course->category->subject_type === 'academic' ? 'text-bg-info' : 'text-bg-primary' }} badge-soft ms-1">{{ ucwords($course->category->subject_type) }}</span>
                                    @endif
                                </td>
                                <td data-col="level" @if(!in_array('level', $visibleColumns, true)) style="display:none" @endif>{{ ucwords(str_replace('_', ' ', $course->level)) }}</td>
                                <td data-col="fee" @if(!in_array('fee', $visibleColumns, true)) style="display:none" @endif>{{ mawa_currency_symbol() }} {{ number_format($course->fee, 0) }}</td>
                                <td data-col="subjects" @if(!in_array('subjects', $visibleColumns, true)) style="display:none" @endif>{{ $course->subjects->count() }}</td>
                                <td data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ $course->batches_count }}</td>
                                <td data-col="discount" @if(!in_array('discount', $visibleColumns, true)) style="display:none" @endif>{{ $course->discount ? number_format($course->discount, 0) : '—' }}</td>
                                <td data-col="admission_fee" @if(!in_array('admission_fee', $visibleColumns, true)) style="display:none" @endif>{{ $course->admission_fee ? number_format($course->admission_fee, 0) : '—' }}</td>
                                <td data-col="exam_fee" @if(!in_array('exam_fee', $visibleColumns, true)) style="display:none" @endif>{{ $course->exam_fee ? number_format($course->exam_fee, 0) : '—' }}</td>
                                <td data-col="certificate_fee" @if(!in_array('certificate_fee', $visibleColumns, true)) style="display:none" @endif>{{ $course->certificate_fee ? number_format($course->certificate_fee, 0) : '—' }}</td>
                                <td data-col="duration" @if(!in_array('duration', $visibleColumns, true)) style="display:none" @endif>
                                    {{ $course->duration_value ? $course->duration_value . ' ' . ucwords(str_replace('_', ' ', $course->duration_type)) : '—' }}
                                </td>
                                <td data-col="weekly_classes" @if(!in_array('weekly_classes', $visibleColumns, true)) style="display:none" @endif>{{ $course->weekly_classes ?? '—' }}</td>
                                <td data-col="total_classes" @if(!in_array('total_classes', $visibleColumns, true)) style="display:none" @endif>{{ $course->total_classes ?? '—' }}</td>
                                <td data-col="class_duration" @if(!in_array('class_duration', $visibleColumns, true)) style="display:none" @endif>{{ $course->class_duration_minutes ? $course->class_duration_minutes . ' min' : '—' }}</td>
                                <td data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>{{ $course->mode ? ucwords(str_replace('_', ' ', $course->mode)) : '—' }}</td>
                                <td data-col="batch_capacity" @if(!in_array('batch_capacity', $visibleColumns, true)) style="display:none" @endif>{{ $course->batch_capacity_default ?? '—' }}</td>
                                <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                                    <span class="badge {{ $statusBadge[$course->status] ?? 'text-bg-secondary' }}">{{ $course->status }}</span>
                                </td>
                                <td data-col="assignment" @if(!in_array('assignment', $visibleColumns, true)) style="display:none" @endif>{{ $course->institutes_count }}</td>
                                <td class="text-end text-nowrap col-action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                                    <a class="btn btn-outline-primary btn-sm btn-icon" href="{{ route('admin.courses.show', ['course' => $course, 'industry' => 'education']) }}" title="View"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="22" class="text-center text-muted py-4">No courses found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
                {{ $courses->appends(request()->query())->links('pagination::bootstrap-5') }}
                <span class="text-muted small">{{ $courses->total() }} courses</span>
            </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="coursesTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
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
                <th data-col="weekly_classes" @if(!in_array('weekly_classes', $visibleColumns, true)) style="display:none" @endif>Weekly classes</th>
                <th data-col="total_classes" @if(!in_array('total_classes', $visibleColumns, true)) style="display:none" @endif>Total classes</th>
                <th data-col="class_duration" @if(!in_array('class_duration', $visibleColumns, true)) style="display:none" @endif>Class duration</th>
                <th data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>Mode</th>
                <th data-col="batch_capacity" @if(!in_array('batch_capacity', $visibleColumns, true)) style="display:none" @endif>Batch capacity</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                <th data-col="assignment" @if(!in_array('assignment', $visibleColumns, true)) style="display:none" @endif>Assignment</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allCourses as $course)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $course->course_code }}</td>
                    <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $course->name }}</td>
                    <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $course->category->name ?? '—' }}
                                    @if($course->category?->subject_type)
                                        <span class="badge {{ $course->category->subject_type === 'academic' ? 'text-bg-info' : 'text-bg-primary' }} badge-soft ms-1">{{ ucwords($course->category->subject_type) }}</span>
                                    @endif
                                </td>
                    <td data-col="level" @if(!in_array('level', $visibleColumns, true)) style="display:none" @endif>{{ ucwords(str_replace('_', ' ', $course->level)) }}</td>
                    <td data-col="fee" @if(!in_array('fee', $visibleColumns, true)) style="display:none" @endif>{{ mawa_currency_symbol() }} {{ number_format($course->fee, 0) }}</td>
                    <td data-col="subjects" @if(!in_array('subjects', $visibleColumns, true)) style="display:none" @endif>{{ $course->subjects->count() }}</td>
                    <td data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ $course->batches_count }}</td>
                    <td data-col="discount" @if(!in_array('discount', $visibleColumns, true)) style="display:none" @endif>{{ $course->discount ? number_format($course->discount, 0) : '—' }}</td>
                    <td data-col="admission_fee" @if(!in_array('admission_fee', $visibleColumns, true)) style="display:none" @endif>{{ $course->admission_fee ? number_format($course->admission_fee, 0) : '—' }}</td>
                    <td data-col="exam_fee" @if(!in_array('exam_fee', $visibleColumns, true)) style="display:none" @endif>{{ $course->exam_fee ? number_format($course->exam_fee, 0) : '—' }}</td>
                    <td data-col="certificate_fee" @if(!in_array('certificate_fee', $visibleColumns, true)) style="display:none" @endif>{{ $course->certificate_fee ? number_format($course->certificate_fee, 0) : '—' }}</td>
                    <td data-col="duration" @if(!in_array('duration', $visibleColumns, true)) style="display:none" @endif>{{ $course->duration_value ? $course->duration_value . ' ' . ucwords(str_replace('_', ' ', $course->duration_type)) : '—' }}</td>
                    <td data-col="weekly_classes" @if(!in_array('weekly_classes', $visibleColumns, true)) style="display:none" @endif>{{ $course->weekly_classes ?? '—' }}</td>
                    <td data-col="total_classes" @if(!in_array('total_classes', $visibleColumns, true)) style="display:none" @endif>{{ $course->total_classes ?? '—' }}</td>
                    <td data-col="class_duration" @if(!in_array('class_duration', $visibleColumns, true)) style="display:none" @endif>{{ $course->class_duration_minutes ? $course->class_duration_minutes . ' min' : '—' }}</td>
                    <td data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>{{ $course->mode ? ucwords(str_replace('_', ' ', $course->mode)) : '—' }}</td>
                    <td data-col="batch_capacity" @if(!in_array('batch_capacity', $visibleColumns, true)) style="display:none" @endif>{{ $course->batch_capacity_default ?? '—' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($course->status) }}</td>
                    <td data-col="assignment" @if(!in_array('assignment', $visibleColumns, true)) style="display:none" @endif>{{ $course->institutes_count }}</td>
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
            document.querySelectorAll('#coursesTable .row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
        });
    }

    // Drag-and-drop row reordering (visual, session only)
    var tbody = document.getElementById('coursesTable');
    if (tbody) { tbody = tbody.querySelector('tbody'); }
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

    // Column visibility toggle
    var table = document.getElementById('coursesTable');
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
                var printTable = document.getElementById('coursesTablePrint');
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
            fetch('{{ route('admin.courses.index-columns') }}', {
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

    // CSV / Excel export from the current table
    function exportTable(fileName) {
        var table = document.getElementById('coursesTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('courses.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('courses.xls'); }); }
})();

if (window.Monetix && Monetix.delegate) {
    Monetix.delegate('click', '[data-row-href]', function (e, row) {
        if (e.target.closest('a, button, input, select, textarea, label, .drag-handle')) { return; }
        window.location.href = row.getAttribute('data-row-href');
    }, 'mtx-course-row-href');
}
</script>
@endpush
