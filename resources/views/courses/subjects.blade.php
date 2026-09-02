@extends('layouts.institute')

@section('title', mawa_lang('courses.tab_subjects') . ' — AccumenAI')

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
        #subjectsTablePrint { width: 100%; font-size: 11px; }
        #subjectsTablePrint th, #subjectsTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@endpush
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('courses.tab_subjects') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('courses.subjects_desc') }} — {{ $institute->name ?? '' }}</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('courses._tabs', [
        'activeTab' => 'subjects',
        'coursesCount' => $coursesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="admin-card" data-ajax-table>

    <div class="filter-card">
        <form class="filter-layout" method="GET" action="{{ route('courses.subjects') }}" data-ajax-filter>

            <div class="filter-search-row align-items-end">

                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" name="q" placeholder="{{ mawa_e('classes.subject_search') }}" value="{{ $q }}">
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">Category</label>
                    <select class="form-select form-select-sm" name="category_id">
                        <option value="">All Categories</option>
                        @foreach ($filterCategories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) $categoryId === (string) $cat->id)>{{ $cat->name ?? '—' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">All Status</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('courses.subjects') }}"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                </div>

            </div>

        </form>
    </div>

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $subjectsCount ?? 0 }} Subjects</span>
            <span class="text-muted ms-2 d-none d-lg-inline">{{ mawa_lang('courses.subjects_desc') }}</span>
        </div>
        <div class="toolbar-actions">
            @if ($user->hasPermission('courses.manage'))
                <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-2" data-subject-request-new>
                    <i class="bi bi-plus-lg"></i> New Subject
                </button>
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
                        'category' => 'Category',
                        'status' => 'Status',
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
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Category</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
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
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $subject->category->name ?? '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $subject->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucwords($subject->status) }}</span>
                        </td>
                        @if ($user->hasPermission('courses.manage'))
                            <td class="text-end col-actions">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-subject-request-edit
                                        data-href="{{ route('courses.subjects.update', $subject) }}"
                                        data-name="{{ $subject->name }}"
                                        data-short_name="{{ $subject->short_name }}"
                                        data-subject_code="{{ $subject->subject_code }}"
                                        data-category="{{ $subject->category->name ?? '' }}">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">{{ mawa_e('courses.subjects_empty') }}</td>
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
                <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Category</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allSubjects as $subject)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $subject->name }}@if ($subject->short_name) ({{ $subject->short_name }})@endif</td>
                    <td data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $subject->subject_code }}</td>
                    <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $subject->category->name ?? '—' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($subject->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($user->hasPermission('courses.manage'))
    {{-- New subject request --}}
    <div class="modal fade" id="subjectRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" id="subjectRequestForm" action="{{ route('courses.subjects.request.global') }}" data-ajax-enabled>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-lg me-1"></i>New Subject
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i>{{ mawa_lang('courses.subject_request_hint') }}
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="sr_name">Subject Name *</label>
                            <input type="text" id="sr_name" name="name" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_type">Type</label>
                            <select id="sr_type" name="subject_type" class="form-select">
                                <option value="professional" selected>Professional</option>
                            </select>
                            <div class="form-text">Academic subjects are only managed by the platform admin.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_short_name">Short Name</label>
                            <input type="text" id="sr_short_name" name="short_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_code">Subject Code</label>
                            <input type="text" id="sr_code" name="subject_code" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_category">Category *</label>
                            <input type="text" id="sr_category" name="category_name" class="form-control" list="srCategoryOptions"
                                   placeholder="Type or select a category" autocomplete="off" required maxlength="255">
                            <input type="hidden" name="category_id" id="sr_category_id">
                            <datalist id="srCategoryOptions">
                                @foreach ($requestCategories as $cat)
                                    <option value="{{ $cat->name }}" data-category-id="{{ $cat->id }}" data-subject-type="{{ $cat->subject_type }}"></option>
                                @endforeach
                            </datalist>
                            <div class="form-text">Select an existing category or type a new one to request.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_description">Description</label>
                            <textarea id="sr_description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit approved subject (category locked) --}}
    <div class="modal fade" id="subjectEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" id="subjectEditForm" data-ajax-enabled>
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-1"></i>Edit Subject
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="se_name">Subject Name *</label>
                            <input type="text" id="se_name" name="name" class="form-control" required maxlength="255" readonly>
                            <div class="form-text text-warning">
                                <i class="bi bi-lock me-1"></i>Subject name cannot be changed.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="se_short_name">Short Name</label>
                            <input type="text" id="se_short_name" name="short_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="se_code">Subject Code</label>
                            <input type="text" id="se_code" name="subject_code" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" id="se_category" class="form-control" readonly>
                            <div class="form-text text-warning">
                                <i class="bi bi-lock me-1"></i>{{ mawa_lang('courses.subject_course_locked_hint') }}
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="se_description">Description</label>
                            <textarea id="se_description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
@endif
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
                body: JSON.stringify({ key: 'course_subjects', columns: visible })
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('professional-subjects.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('professional-subjects.xls'); }); }
})();
</script>
@endpush

@if ($user->hasPermission('courses.manage'))
@push('scripts')
<script>
(function () {
    var allCategories = [
        @foreach ($requestCategories as $cat)
            { id: {{ $cat->id }}, name: @json($cat->name), type: @json($cat->subject_type) },
        @endforeach
    ];
    function refreshCategoryOptions() {
        var datalist = document.getElementById('srCategoryOptions');
        var typeEl = document.getElementById('sr_type');
        if (!datalist) { return; }
        datalist.innerHTML = '';
        var type = typeEl ? typeEl.value : 'professional';
        allCategories.filter(function (c) { return c.type === type; }).forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.name;
            opt.setAttribute('data-category-id', c.id);
            opt.setAttribute('data-subject-type', c.type);
            datalist.appendChild(opt);
        });
    }

    function syncCategoryId() {
        var catId = document.getElementById('sr_category_id');
        var cat = document.getElementById('sr_category');
        if (!catId || !cat) { return; }
        var value = (cat.value || '').trim();
        var match = allCategories.find(function (c) { return c.name === value; });
        catId.value = match ? match.id : '';
    }

    var srType = document.getElementById('sr_type');
    if (srType) {
        srType.addEventListener('change', function () {
            var cat = document.getElementById('sr_category');
            if (cat) { cat.value = ''; }
            var catId = document.getElementById('sr_category_id');
            if (catId) { catId.value = ''; }
            refreshCategoryOptions();
        });
    }
    var srCategory = document.getElementById('sr_category');
    if (srCategory) {
        srCategory.addEventListener('input', syncCategoryId);
    }

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
        var errBox = form.querySelector('.subject-form-errors');
        if (errBox) { errBox.remove(); }
    }

    function showErrors(form, errors) {
        var box = form.querySelector('.subject-form-errors');
        if (!box && errors) {
            box = document.createElement('div');
            box.className = 'alert alert-danger m-3 mb-0 py-2 subject-form-errors';
            box.textContent = Object.values(errors).flat().join(' ');
            form.querySelector('.modal-body').insertAdjacentElement('beforebegin', box);
        }
    }

    function submitForm(form) {
        if (!window.Monetix || !Monetix.request) { return; }
        clearErrors(form);
        var submitBtn = form.querySelector('[type="submit"]');
        var restore = Monetix.loading && Monetix.loading(submitBtn);
        Monetix.request(form.action, { method: form.method === 'PUT' ? 'PUT' : 'POST', body: new FormData(form) })
            .then(function (res) {
                if (restore) { restore(); }
                if (res && res.errors) {
                    form.querySelector('[name]') && form.querySelector('[name]').focus();
                    showErrors(form, res.errors);
                    return;
                }
                if (res && res.success === false) {
                    if (Monetix.toast) { Monetix.toast(res.message || 'Could not save subject.', 'danger'); }
                    return;
                }
                var modal = bootstrap.Modal.getInstance(form.closest('.modal'));
                if (modal) { modal.hide(); }
                if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false }); }
            })
            .catch(function () {
                if (restore) { restore(); }
                if (Monetix.toast) { Monetix.toast('Could not save subject. Please try again.', 'danger'); }
            });
    }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-subject-request-new], [data-subject-request-edit]', function (e, btn) {
            if (btn.hasAttribute('data-subject-request-new')) {
                var newModalEl = document.getElementById('subjectRequestModal');
                if (!newModalEl) { return; }
                var form = document.getElementById('subjectRequestForm');
                if (form) { clearErrors(form); form.reset(); }
                var typeEl = document.getElementById('sr_type');
                if (typeEl) { typeEl.value = 'professional'; }
                refreshCategoryOptions();
                syncCategoryId();
                bootstrap.Modal.getOrCreateInstance(newModalEl).show();
                return;
            }

            var editModalEl = document.getElementById('subjectEditModal');
            if (!editModalEl) { return; }
            var form = document.getElementById('subjectEditForm');
            if (!form) { return; }
                clearErrors(form);
                form.action = btn.getAttribute('data-href');
                document.getElementById('se_name').value = btn.getAttribute('data-name') || '';
                document.getElementById('se_short_name').value = btn.getAttribute('data-short_name') || '';
                document.getElementById('se_code').value = btn.getAttribute('data-subject_code') || '';
                document.getElementById('se_category').value = btn.getAttribute('data-category') || '';
                document.getElementById('se_description').value = '';
                bootstrap.Modal.getOrCreateInstance(editModalEl).show();
            }, 'mtx-subject-request');
    }

    var requestForm = document.getElementById('subjectRequestForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function (e) {
            if (!requestForm.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            if (!requestForm.checkValidity()) { requestForm.reportValidity(); return; }
            submitForm(requestForm);
        });
    }

    var editForm = document.getElementById('subjectEditForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            if (!editForm.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            if (!editForm.checkValidity()) { editForm.reportValidity(); return; }
            submitForm(editForm);
        });
    }
})();
</script>
@endpush
@endif