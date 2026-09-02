@extends('layouts.admin')

@section('title', 'Institutes — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #institutesTablePrint { width: 100%; font-size: 11px; }
        #institutesTablePrint th, #institutesTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'pending'   => 'text-bg-warning',
        'active'    => 'text-bg-success',
        'suspended' => 'text-bg-danger',
        'expired'   => 'text-bg-dark',
        'cancelled' => 'text-bg-secondary',
    ];
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Institutes</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Institutes</h4>
        <p class="page-header-desc">{{ $items->total() }} registered institutes on the platform</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.institutes.bin') }}">
            <i class="bi bi-trash-fill"></i> Recycle Bin
            @if ($recycleCount > 0)
                <span class="badge bg-danger ms-1">{{ $recycleCount }}</span>
            @endif
        </a>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.institutes.index') }}">
        <input type="hidden" name="per_page" value="{{ $perPage ?? 25 }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by name, code or email..." value="{{ $filters['q'] ?? '' }}">
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Industry</label>
                <select class="form-select form-select-sm" name="industry" id="industrySelect">
                    <option value="">All Industries</option>
                    @foreach ($industries as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['industry'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if (count($subIndustries))
                <div class="filter-span flex-shrink-0" style="min-width:180px">
                    <label class="form-label mb-1">Sub Industry</label>
                    <select class="form-select form-select-sm" name="sub_industry">
                        <option value="">All Sub Industries</option>
                        @foreach ($subIndustries as $subKey => $subLabel)
                            <option value="{{ $subKey }}" @selected(($filters['sub_industry'] ?? '') === $subKey)>{{ $subLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    @foreach (['pending', 'active', 'suspended', 'expired', 'cancelled'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
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
            <span class="badge text-bg-primary badge-soft">{{ $items->total() }} Institutes</span>
            <span class="text-muted ms-2 d-none d-lg-inline">All institutes registered on the platform.</span>
        </div>
        <div class="toolbar-actions">
            <div id="batchActions" class="d-none align-items-center gap-2">
                <span class="badge text-bg-dark" id="batchCount">0 selected</span>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-warning d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-lightning"></i> Batch Actions <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Apply to selected (<span class="batch-count-text">0</span>)</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button type="button" class="dropdown-item" id="batchApproveBtn"><i class="bi bi-check-circle text-success me-2"></i> Approve All</button></li>
                        <li><button type="button" class="dropdown-item text-danger" id="batchDeleteBtn"><i class="bi bi-trash3 me-2"></i> Delete All</button></li>
                    </ul>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false" title="Rows per page">
                    <i class="bi bi-list-ol"></i> Show: {{ $perPage ?? 25 }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="perPageMenu">
                    <li><h6 class="dropdown-header">Rows per page</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach (($perPageOptions ?? [25,50,75,100,200,500]) as $opt)
                        <li>
                            <a class="dropdown-item d-flex align-items-center justify-content-between @if(($perPage ?? 25) === $opt) active @endif"
                               href="{{ request()->fullUrlWithQuery(['per_page' => $opt, 'page' => 1]) }}">
                                {{ $opt }}
                                @if(($perPage ?? 25) === $opt) <i class="bi bi-check-lg"></i> @endif
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
                        'serial'       => '#',
                        'institute'    => 'Institute',
                        'uid'          => 'UID',
                        'owner'        => 'Owner',
                        'package'      => 'Package',
                        'students'     => 'Students',
                        'subscription' => 'Subscription',
                        'status'       => 'Status',
                        'action'       => 'Action',
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
        <table class="table align-middle mb-0" id="institutesTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="uid" @if(!in_array('uid', $visibleColumns, true)) style="display:none" @endif>UID</th>
                    <th data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>Owner</th>
                    <th data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>Package</th>
                    <th data-col="students" class="text-center" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>Students</th>
                    <th data-col="subscription" @if(!in_array('subscription', $visibleColumns, true)) style="display:none" @endif>Subscription</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $institute)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" value="{{ $institute->id }}" data-id="{{ $institute->id }}" data-name="{{ $institute->name }}" data-status="{{ $institute->status }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>
                            <a class="fw-semibold text-decoration-none" href="{{ route('admin.institutes.show', $institute) }}">{{ $institute->name }}</a>
                            <div class="text-muted small">
                                {{ $institute->institute_code ?? $institute->slug }} &middot; {{ $institute->district ?? '—' }}
                            </div>
                        </td>
                        <td data-col="uid" @if(!in_array('uid', $visibleColumns, true)) style="display:none" @endif>
                            <x-uid-with-copy :uid="$institute->uid" />
                        </td>
                        <td data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>
                            @if ($institute->users->isNotEmpty())
                                <div>{{ $institute->users->first()->name ?? '—' }}</div>
                                <div class="text-muted small">{{ $institute->users->first()->email }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>{{ $institute->package->name ?? '—' }}</td>
                        <td data-col="students" class="text-center" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>{{ $institute->students_count }}</td>
                        <td data-col="subscription" @if(!in_array('subscription', $visibleColumns, true)) style="display:none" @endif>
                            @if ($institute->subscription_expiry)
                                <span class="{{ \Illuminate\Support\Carbon::parse($institute->subscription_expiry)->lt(now()) ? 'text-danger' : '' }}">
                                    {{ \Illuminate\Support\Carbon::parse($institute->subscription_expiry)->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$institute->status] ?? 'text-bg-secondary' }}">{{ $institute->status }}</span>
                        </td>
                        <td class="text-end text-nowrap col-action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.institutes.show', $institute) }}" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.institutes.edit', $institute) }}" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            {{-- Rewired: Accept → Suspend → Activate cycle (never hide) --}}
                            @if ($institute->status === 'pending')
                                <button type="button" class="btn btn-sm btn-success single-approve-btn" title="Accept"
                                        data-id="{{ $institute->id }}" data-name="{{ $institute->name }}"
                                        data-action="{{ route('admin.institutes.action', $institute) }}">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            @elseif ($institute->status === 'active')
                                <button type="button" class="btn btn-sm btn-outline-warning single-suspend-btn" title="Suspend"
                                        data-id="{{ $institute->id }}" data-name="{{ $institute->name }}"
                                        data-action="{{ route('admin.institutes.action', $institute) }}">
                                    <i class="bi bi-pause-circle"></i>
                                </button>
                            @elseif ($institute->status === 'suspended')
                                <button type="button" class="btn btn-sm btn-outline-success single-activate-btn" title="Activate"
                                        data-id="{{ $institute->id }}" data-name="{{ $institute->name }}"
                                        data-action="{{ route('admin.institutes.action', $institute) }}">
                                    <i class="bi bi-play-circle"></i>
                                </button>
                            @endif
                            <button class="btn btn-sm btn-outline-danger del-btn" type="button" title="Delete"
                                    data-id="{{ $institute->id }}" data-name="{{ $institute->name }}"
                                    data-action="{{ route('admin.institutes.action', $institute) }}">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No institutes registered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->links('pagination::bootstrap-5') }}
        <span class="text-muted small">
            @if($items->total() > 0)
                Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }} institutes ({{ $perPage ?? 25 }} per page)
            @else
                {{ $items->total() }} institutes
            @endif
        </span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="institutesTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                <th data-col="uid" @if(!in_array('uid', $visibleColumns, true)) style="display:none" @endif>UID</th>
                <th data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>Owner</th>
                <th data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>Package</th>
                <th data-col="students" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>Students</th>
                <th data-col="subscription" @if(!in_array('subscription', $visibleColumns, true)) style="display:none" @endif>Subscription</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allItems as $institute)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $institute->name }}</td>
                    <td data-col="uid" @if(!in_array('uid', $visibleColumns, true)) style="display:none" @endif>{{ $institute->uid }}</td>
                    <td data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>{{ $institute->users->first()->name ?? '—' }}</td>
                    <td data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>{{ $institute->package->name ?? '—' }}</td>
                    <td data-col="students" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>{{ $institute->students_count }}</td>
                    <td data-col="subscription" @if(!in_array('subscription', $visibleColumns, true)) style="display:none" @endif>
                        {{ $institute->subscription_expiry ? \Illuminate\Support\Carbon::parse($institute->subscription_expiry)->format('d M Y') : '—' }}
                    </td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($institute->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="deleteForm" data-ajax-enabled>
            @csrf
            <input type="hidden" name="action" value="delete">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Move to Recycle Bin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">This will remove the institution from the active institution list. It will be moved to the Recycle Bin and can be restored from there if needed. Staff access will be suspended.</div>
                <h6 id="del_name" class="fw-bold mb-3"></h6>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Yes, delete</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="batchDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="batchDeleteForm" data-ajax-enabled>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Delete Selected Institutes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">This will move <strong id="batchDeleteCount">0</strong> selected institute(s) to the Recycle Bin. This can be restored later.</div>
                <ul id="batchDeleteList" class="small text-muted mb-3" style="max-height:120px; overflow:auto;"></ul>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Yes, delete all</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selectAll = document.getElementById('selectAll');
    var batchActions = document.getElementById('batchActions');
    var batchCountEl = document.getElementById('batchCount');

    function getSelectedChecks() {
        return document.querySelectorAll('.row-check:checked');
    }
    function getSelectedIds() {
        var ids = [];
        getSelectedChecks().forEach(function (cb) { var v = cb.value || cb.getAttribute('data-id'); if (v) ids.push(v); });
        return ids;
    }
    function updateBatchUI() {
        var count = getSelectedChecks().length;
        if (batchActions) {
            if (count >= 2) {
                batchActions.classList.remove('d-none');
                batchActions.classList.add('d-flex');
            } else {
                batchActions.classList.add('d-none');
                batchActions.classList.remove('d-flex');
            }
        }
        if (batchCountEl) batchCountEl.textContent = count + ' selected';
        document.querySelectorAll('.batch-count-text').forEach(function (el) { el.textContent = count; });
        // sync selectAll state
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
    // initial
    updateBatchUI();

    // Drag-and-drop row reordering (visual, session only)
    var tableBody = document.getElementById('institutesTable');
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
    var table = document.getElementById('institutesTable');
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
                var printTable = document.getElementById('institutesTablePrint');
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
            fetch('{{ route('admin.institutes.columns') }}', {
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

    // Auto-submit filters when a dropdown/select changes
    var filterForm = document.querySelector('.filter-layout');
    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    // CSV / Excel export from the current table
    function exportTable(fileName) {
        var table = document.getElementById('institutesTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('institutes.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('institutes.xls'); }); }

    // Delete modal wiring — uses delegated handlers so it survives Monetix.loadPage seamless swaps
    function getDeleteModal() { return document.getElementById('deleteModal'); }
    function getDeleteForm() { return document.getElementById('deleteForm'); }
    function getDeleteNameEl() { return document.getElementById('del_name'); }

    function clearDeleteErrors() {
        var f = getDeleteForm();
        if (!f) return;
        f.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        f.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
    }

    // Delegated: survives AJAX page swaps where buttons are re-rendered
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.del-btn');
        if (!btn) return;
        var modal = getDeleteModal();
        var form = getDeleteForm();
        var nameEl = getDeleteNameEl();
        if (!modal || !form) return;
        var actionUrl = btn.getAttribute('data-action');
        if (actionUrl) {
            form.action = actionUrl;
            form.setAttribute('data-action-url', actionUrl);
        }
        if (nameEl) nameEl.textContent = btn.getAttribute('data-name') || '';
        var pwField = form.querySelector('input[name="password"]');
        if (pwField) { pwField.value = ''; }
        clearDeleteErrors();
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.id !== 'deleteForm') return;
        if (!form.hasAttribute('data-ajax-enabled')) return;
        if (!window.Monetix || !Monetix.request) return;
        e.preventDefault();
        e.stopPropagation();
        clearDeleteErrors();
        var modal = getDeleteModal();
        var pwField = form.querySelector('input[name="password"]');
        // Trim password before sending to avoid leading/trailing space mismatches
        if (pwField && typeof pwField.value === 'string') { pwField.value = pwField.value.trim(); }
        var submitBtn = form.querySelector('[type="submit"]');
        var restore = Monetix.loading(submitBtn, 'Deleting…');
        var submitUrl = form.getAttribute('data-action-url') || form.action;
        Monetix.request(submitUrl, { method: 'POST', body: new FormData(form) })
            .then(function (res) {
                if (restore) { restore(); }
                if (res && res.errors) {
                    var firstField = null;
                    Object.keys(res.errors).forEach(function (key) {
                        var field = form.querySelector('[name="' + key + '"]');
                        if (field) {
                            field.classList.add('is-invalid');
                            var msg = document.createElement('div');
                            msg.className = 'text-danger small mt-1';
                            msg.textContent = (res.errors[key] || []).join(', ');
                            field.parentNode.insertBefore(msg, field.nextSibling);
                            if (!firstField) firstField = field;
                        }
                    });
                    if (Monetix.toast && res.message) { Monetix.toast(res.message, 'danger'); }
                    if (firstField) firstField.focus();
                    return;
                }
                if (res && res.success === false) {
                    if (Monetix.toast) { Monetix.toast(res.message || 'Could not delete the institute.', 'danger'); }
                    return;
                }
                var inst = modal ? bootstrap.Modal.getInstance(modal) : null;
                if (inst) { inst.hide(); }
                if (Monetix.toast) { Monetix.toast(res && res.message || 'Institute moved to recycle bin.', 'success'); }
                if (Monetix.loadPage) { Monetix.loadPage(location.pathname + location.search, { preserveFocus: false }); }
                else { location.reload(); }
            })
            .catch(function (err) {
                if (restore) { restore(); }
                console.error('[deleteInstitute] request failed', err);
                if (Monetix.toast) { Monetix.toast('Could not delete the institute. Please try again.', 'danger'); }
            });
    });

    // ── Single Accept / Suspend (rewired one-by-one) ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.single-approve-btn');
        if (!btn || btn.disabled) return;
        var url = btn.getAttribute('data-action');
        var name = btn.getAttribute('data-name') || 'this institute';
        if (!url) return;
        if (!confirm('Approve ' + name + '?')) return;
        var restore = window.Monetix && Monetix.loading ? Monetix.loading(btn, 'Approving…') : null;
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' };
        var body = JSON.stringify({ action: 'approve' });
        var doFetch = function() {
            return fetch(url, { method: 'POST', headers: headers, body: body }).then(function(r){ return r.json().then(function(j){ return {ok: r.ok, json: j}; }); });
        };
        var promise = (window.Monetix && Monetix.request)
            ? Monetix.request(url, { method: 'POST', headers: headers, body: body })
                .then(function(j){ return {ok: !(j && j.success===false), json: j}; })
                .catch(function(){ return doFetch(); })
            : doFetch();
        promise.then(function(res){
            if (restore) restore();
            var j = res.json;
            if (!res.ok || (j && j.success===false)) {
                if (window.Monetix && Monetix.toast) Monetix.toast(j && j.message || 'Could not approve.', 'danger');
                else alert(j && j.message || 'Could not approve.');
                return;
            }
            if (window.Monetix && Monetix.toast) Monetix.toast(j.message || 'Institute approved.', 'success');
            if (window.Monetix && Monetix.loadPage) Monetix.loadPage(location.pathname + location.search, {preserveFocus:false});
            else location.reload();
        }).catch(function(err){
            if (restore) restore();
            console.error('[singleApprove] failed', err);
            if (window.Monetix && Monetix.toast) Monetix.toast('Could not approve. Please try again.', 'danger');
        });
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.single-suspend-btn');
        if (!btn || btn.disabled) return;
        var url = btn.getAttribute('data-action');
        var name = btn.getAttribute('data-name') || 'this institute';
        if (!url) return;
        if (!confirm('Suspend ' + name + '?')) return;
        var restore = window.Monetix && Monetix.loading ? Monetix.loading(btn, 'Suspending…') : null;
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' };
        var body = JSON.stringify({ action: 'suspend' });
        var doFetch = function() {
            return fetch(url, { method: 'POST', headers: headers, body: body }).then(function(r){ return r.json().then(function(j){ return {ok: r.ok, json: j}; }); });
        };
        var promise = (window.Monetix && Monetix.request)
            ? Monetix.request(url, { method: 'POST', headers: headers, body: body })
                .then(function(j){ return {ok: !(j && j.success===false), json: j}; })
                .catch(function(){ return doFetch(); })
            : doFetch();
        promise.then(function(res){
            if (restore) restore();
            var j = res.json;
            if (!res.ok || (j && j.success===false)) {
                if (window.Monetix && Monetix.toast) Monetix.toast(j && j.message || 'Could not suspend.', 'danger');
                else alert(j && j.message || 'Could not suspend.');
                return;
            }
            if (window.Monetix && Monetix.toast) Monetix.toast(j.message || 'Institute suspended.', 'success');
            if (window.Monetix && Monetix.loadPage) Monetix.loadPage(location.pathname + location.search, {preserveFocus:false});
            else location.reload();
        }).catch(function(err){
            if (restore) restore();
            console.error('[singleSuspend] failed', err);
            if (window.Monetix && Monetix.toast) Monetix.toast('Could not suspend. Please try again.', 'danger');
        });
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.single-activate-btn');
        if (!btn || btn.disabled) return;
        var url = btn.getAttribute('data-action');
        var name = btn.getAttribute('data-name') || 'this institute';
        if (!url) return;
        if (!confirm('Activate ' + name + '?')) return;
        var restore = window.Monetix && Monetix.loading ? Monetix.loading(btn, 'Activating…') : null;
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' };
        var body = JSON.stringify({ action: 'reactivate' });
        var doFetch = function() {
            return fetch(url, { method: 'POST', headers: headers, body: body }).then(function(r){ return r.json().then(function(j){ return {ok: r.ok, json: j}; }); });
        };
        var promise = (window.Monetix && Monetix.request)
            ? Monetix.request(url, { method: 'POST', headers: headers, body: body })
                .then(function(j){ return {ok: !(j && j.success===false), json: j}; })
                .catch(function(){ return doFetch(); })
            : doFetch();
        promise.then(function(res){
            if (restore) restore();
            var j = res.json;
            if (!res.ok || (j && j.success===false)) {
                if (window.Monetix && Monetix.toast) Monetix.toast(j && j.message || 'Could not activate.', 'danger');
                else alert(j && j.message || 'Could not activate.');
                return;
            }
            if (window.Monetix && Monetix.toast) Monetix.toast(j.message || 'Institute activated.', 'success');
            if (window.Monetix && Monetix.loadPage) Monetix.loadPage(location.pathname + location.search, {preserveFocus:false});
            else location.reload();
        }).catch(function(err){
            if (restore) restore();
            console.error('[singleActivate] failed', err);
            if (window.Monetix && Monetix.toast) Monetix.toast('Could not activate. Please try again.', 'danger');
        });
    });

    // ── Batch Actions ──
    var batchApproveBtn = document.getElementById('batchApproveBtn');
    var batchDeleteBtn = document.getElementById('batchDeleteBtn');
    var batchDeleteModal = document.getElementById('batchDeleteModal');
    var batchDeleteForm = document.getElementById('batchDeleteForm');
    var batchUrl = '{{ route('admin.institutes.batch-action') }}';

    function batchRequest(action, extra) {
        var ids = getSelectedIds();
        if (ids.length < 2) {
            if (window.Monetix && Monetix.toast) Monetix.toast('Select at least 2 institutes.', 'warning');
            else alert('Select at least 2 institutes.');
            return;
        }
        var payload = { ids: ids, action: action };
        if (extra) Object.keys(extra).forEach(function(k){ payload[k]=extra[k]; });
        var body = JSON.stringify(payload);
        var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' };
        var btn = action === 'approve' ? batchApproveBtn : batchDeleteBtn;
        var restore = window.Monetix && Monetix.loading ? Monetix.loading(btn, action === 'approve' ? 'Approving…' : 'Deleting…') : null;
        // Prefer Monetix.request if available, else fetch
        var doFetch = function() {
            return fetch(batchUrl, { method: 'POST', headers: headers, body: body }).then(function(r){ return r.json().then(function(j){ return {ok: r.ok, json: j}; }); });
        };
        var promise = (window.Monetix && Monetix.request)
            ? Monetix.request(batchUrl, { method: 'POST', headers: headers, body: body })
                .then(function(j){ return {ok: !(j && j.success===false), json: j}; })
                .catch(function(){ return doFetch(); })
            : doFetch();

        promise.then(function(res){
            if (restore) restore();
            var j = res.json;
            if (!res.ok || (j && j.success===false)) {
                if (j && j.errors && j.errors.password && batchDeleteForm) {
                    var pf = batchDeleteForm.querySelector('input[name="password"]');
                    if (pf) {
                        pf.classList.add('is-invalid');
                        var msg = document.createElement('div');
                        msg.className = 'text-danger small mt-1';
                        msg.textContent = (j.errors.password||[]).join(', ');
                        pf.parentNode.insertBefore(msg, pf.nextSibling);
                    }
                }
                if (window.Monetix && Monetix.toast) Monetix.toast(j && j.message || 'Batch action failed.', 'danger');
                else alert(j && j.message || 'Batch action failed.');
                return;
            }
            if (batchDeleteModal) { var inst = bootstrap.Modal.getInstance(batchDeleteModal); if(inst) inst.hide(); }
            if (window.Monetix && Monetix.toast) Monetix.toast(j.message || 'Batch action completed.', 'success');
            if (window.Monetix && Monetix.loadPage) Monetix.loadPage(location.pathname + location.search, {preserveFocus:false});
            else location.reload();
        }).catch(function(err){
            if (restore) restore();
            console.error('[batchAction] failed', err);
            if (window.Monetix && Monetix.toast) Monetix.toast('Batch action failed. Please try again.', 'danger');
        });
    }

    if (batchApproveBtn) {
        batchApproveBtn.addEventListener('click', function(){
            var ids = getSelectedIds();
            if (ids.length < 2) return;
            var pending = 0;
            getSelectedChecks().forEach(function(cb){ if(cb.getAttribute('data-status')==='pending') pending++; });
            var msg = 'Approve ' + ids.length + ' selected institute(s)?' + (pending ? ' ('+pending+' pending will be approved, others skipped)' : '');
            if (!confirm(msg)) return;
            batchRequest('approve');
        });
    }
    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', function(){
            var ids = getSelectedIds();
            if (ids.length < 2) return;
            var countEl = document.getElementById('batchDeleteCount');
            var listEl = document.getElementById('batchDeleteList');
            if (countEl) countEl.textContent = ids.length;
            if (listEl) {
                listEl.innerHTML = '';
                getSelectedChecks().forEach(function(cb){
                    var li = document.createElement('li');
                    li.textContent = (cb.getAttribute('data-name')||'Institute') + ' (#' + (cb.value||cb.getAttribute('data-id')) + ')';
                    listEl.appendChild(li);
                });
            }
            if (batchDeleteForm) {
                batchDeleteForm.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
                batchDeleteForm.querySelectorAll('.text-danger.small').forEach(function(el){ el.remove(); });
                var pf = batchDeleteForm.querySelector('input[name="password"]');
                if(pf) pf.value='';
            }
            if (batchDeleteModal) bootstrap.Modal.getOrCreateInstance(batchDeleteModal).show();
        });
    }
    if (batchDeleteForm) {
        batchDeleteForm.addEventListener('submit', function(e){
            e.preventDefault();
            e.stopPropagation();
            batchDeleteForm.querySelectorAll('.is-invalid').forEach(function(el){ el.classList.remove('is-invalid'); });
            batchDeleteForm.querySelectorAll('.text-danger.small').forEach(function(el){ el.remove(); });
            var pw = batchDeleteForm.querySelector('input[name="password"]');
            if(pw) pw.value = (pw.value||'').trim();
            if(!pw || !pw.value){
                pw.classList.add('is-invalid');
                var m=document.createElement('div'); m.className='text-danger small mt-1'; m.textContent='Password is required.';
                pw.parentNode.insertBefore(m, pw.nextSibling);
                return;
            }
            batchRequest('delete', {password: pw.value});
        });
    }
})();
</script>
@endpush
