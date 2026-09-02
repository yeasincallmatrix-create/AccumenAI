@extends('layouts.admin')

@section('title', 'Academic Subjects — AccumenAI')

@php
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
        'draft'    => 'text-bg-warning',
    ];
    $editing = session('editing_subject_id');
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Academic Subjects</h4>
        <p class="page-header-desc">Platform-wide academic subject master. Build the curriculum by assigning subjects to classes in the assignment manager.</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary btn-sm" href="{{ route('admin.academic.subjects.assign', ['industry' => 'education']) }}">
            <i class="bi bi-link-45deg"></i> Assignment Manager
        </a>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.academic.subjects.index') }}">

        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by name, short name or code..." value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                <label class="form-label mb-1">Institute</label>
                <select class="form-select form-select-sm" name="institute_id">
                    <option value="">All Institutes</option>
                    @foreach ($institutes as $inst)
                        <option value="{{ $inst->id }}" @selected(($filters['institute_id'] ?? '') == $inst->id)>{{ $inst->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:160px">
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
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.academic.subjects.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>

    </form>
</div>

@if ($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="admin-card mt-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-secondary badge-soft">{{ $items->total() }} Subjects</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Academic subject catalog.</span>
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
                        'serial'       => '#',
                        'name'         => 'Subject',
                        'code'         => 'Code',
                        'type'         => 'Type',
                        'category'     => 'Category',
                        'institute'    => 'Institute',
                        'assignments'  => 'Classes',
                        'status'       => 'Status',
                        'actions'      => 'Actions',
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="ac-subj-col-{{ $col }}">
                                <input type="checkbox" id="ac-subj-col-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="bi bi-plus-lg"></i> Add Subject
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="subjectsTable">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Subject</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                    <th data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>Type</th>
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Category</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="assignments" @if(!in_array('assignments', $visibleColumns, true)) style="display:none" @endif>Classes</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="actions" class="text-end" @if(!in_array('actions', $visibleColumns, true)) style="display:none" @endif>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <span class="fw-semibold">{{ $item->name }}</span>
                            @if ($item->short_name)
                                <div class="text-muted small">{{ $item->short_name }}</div>
                            @endif
                        </td>
                        <td class="text-muted" data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $item->subject_code }}</td>
                        <td data-col="type" @if(!in_array('type', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($item->subject_type) }}</td>
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $item->category->name ?? '—' }}</td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $item->institute->name ?? '-' }}</td>
                        <td data-col="assignments" @if(!in_array('assignments', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge text-bg-primary badge-soft">{{ $item->academic_assignments_count }}</span> classes
                        </td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$item->status] ?? 'text-bg-secondary' }}">{{ $item->status }}</span>
                        </td>
                        <td data-col="actions" class="text-end" @if(!in_array('actions', $visibleColumns, true)) style="display:none" @endif>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editSubjectModal" data-subject-id="{{ $item->id }}" data-name="{{ $item->name }}" data-short-name="{{ $item->short_name }}" data-code="{{ $item->subject_code }}" data-description="{{ $item->description }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.academic.subjects.toggle', $item) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn {{ $item->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}" title="{{ $item->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                        <i class="bi {{ $item->status === 'active' ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No academic subjects in the catalog yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} subjects</span>
    </div>
</div>

{{-- Add Subject modal --}}
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.academic.subjects.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="addSubjectModalLabel">Add Academic Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="add_name">Subject name</label>
                    <input type="text" id="add_name" name="name" class="form-control" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="add_short_name">Short name</label>
                    <input type="text" id="add_short_name" name="short_name" class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="add_code">Code</label>
                    <input type="text" id="add_code" name="subject_code" class="form-control" maxlength="50" placeholder="Leave blank to auto-generate">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="add_description">Description</label>
                    <textarea id="add_description" name="description" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Add Subject</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Subject modal --}}
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.academic.subjects.index') }}">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title" id="editSubjectModalLabel">Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="mb-3">
                    <label class="form-label" for="edit_name">Subject name</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_short_name">Short name</label>
                    <input type="text" id="edit_short_name" name="short_name" class="form-control" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_code">Code</label>
                    <input type="text" id="edit_code" name="subject_code" class="form-control" maxlength="50">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('subjectsTable');
    var saveCols = null;
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
                if (saveCols) { saveCols(); }
            });
        });
        saveCols = function () {
            var visible = [];
            colChecks.forEach(function (check) { if (check.checked) { visible.push(check.getAttribute('data-col')); } });
            fetch('{{ route('admin.academic.subjects.save-columns') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
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

    var editModal = document.getElementById('editSubjectModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            var form = editModal.querySelector('form');
            form.setAttribute('action', '{{ route('admin.academic.subjects.index') }}/' + btn.getAttribute('data-subject-id'));
            editModal.querySelector('#edit_subject_id').value = btn.getAttribute('data-subject-id');
            editModal.querySelector('#edit_name').value = btn.getAttribute('data-name');
            editModal.querySelector('#edit_short_name').value = btn.getAttribute('data-short-name') || '';
            editModal.querySelector('#edit_code').value = btn.getAttribute('data-code') || '';
            editModal.querySelector('#edit_description').value = btn.getAttribute('data-description') || '';
        });
    }
})();
</script>
@endpush