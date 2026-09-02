@extends('layouts.admin')

@section('title', 'Recycle Bin — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #institutesBinTablePrint { width: 100%; font-size: 11px; }
        #institutesBinTablePrint th, #institutesBinTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
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
        <li class="breadcrumb-item"><a href="{{ route('admin.institutes.index') }}" class="text-decoration-none">Institutes</a></li>
        <li class="breadcrumb-item active" aria-current="page">Recycle Bin</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Recycle Bin</h4>
        <p class="page-header-desc">{{ $institutes->total() }} trashed institutes · {{ $certificates->total() }} trashed certificates</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.institutes.index') }}">
            <i class="bi bi-arrow-left"></i> Back to Institutes
        </a>
    </div>
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.institutes.bin') }}">
        <input type="hidden" name="per_page" value="{{ $perPage ?? 25 }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by name, code or email..." value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Industry</label>
                <select class="form-select form-select-sm" name="industry" id="industrySelectBin">
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
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.bin') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
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
            <span class="badge text-bg-primary badge-soft">{{ $institutes->total() }} Trashed Institutes</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Soft-deleted institutes awaiting review.</span>
        </div>
        <div class="toolbar-actions">
            <div id="batchActionsBin" class="d-none align-items-center gap-2">
                <span class="badge text-bg-dark" id="batchCountBin">0 selected</span>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-warning d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-lightning"></i> Batch Actions <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Apply to selected (<span class="batch-count-text-bin">0</span>)</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button type="button" class="dropdown-item" id="batchRestoreBtn"><i class="bi bi-arrow-counterclockwise text-success me-2"></i> Restore All</button></li>
                        <li><button type="button" class="dropdown-item text-danger" id="batchForceDeleteBtn"><i class="bi bi-x-octagon me-2"></i> Delete Permanently All</button></li>
                    </ul>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false" title="Rows per page">
                    <i class="bi bi-list-ol"></i> Show: {{ $perPage ?? 25 }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="perPageMenuBin">
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
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenuBin">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial'       => '#',
                        'institute'    => 'Institute',
                        'owner'        => 'Owner',
                        'package'      => 'Package',
                        'students'     => 'Students',
                        'status'       => 'Status',
                        'deleted_at'   => 'Deleted At',
                        'action'       => 'Action',
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="col-toggle-bin-{{ $col }}">
                                <input type="checkbox" id="col-toggle-bin-{{ $col }}" class="form-check-input me-2 col-toggle-check-bin" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                <button type="button" class="btn btn-outline-success" id="exportCsvBtnBin"><i class="bi bi-filetype-csv"></i> CSV</button>
                <button type="button" class="btn btn-outline-success" id="exportExcelBtnBin"><i class="bi bi-file-earmark-excel"></i> Excel</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="institutesBinTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAllBin" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>Owner</th>
                    <th data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>Package</th>
                    <th data-col="students" class="text-center" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>Students</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="deleted_at" @if(!in_array('deleted_at', $visibleColumns, true)) style="display:none" @endif>Deleted At</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($institutes as $institute)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check-bin" value="{{ $institute->id }}" data-id="{{ $institute->id }}" data-name="{{ $institute->name }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $institutes->firstItem() + $loop->index }}</td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $institute->name }}</div>
                            <div class="text-muted small">{{ $institute->institute_code ?? $institute->slug }} &middot; {{ $institute->district ?? '—' }}</div>
                        </td>
                        <td data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>
                            @php
                                $legacyOwner = $institute->users->firstWhere(fn($u) => $u->role?->slug === 'institute-owner') ?? $institute->users->first();
                                $memOwner = $institute->memberships->firstWhere(fn($m) => $m->role?->slug === 'institute-owner');
                                $ownerName = $memOwner?->user?->name ?? $legacyOwner?->name ?? '—';
                                $ownerEmail = $memOwner?->user?->email ?? $legacyOwner?->email ?? '';
                            @endphp
                            @if ($ownerName !== '—' || $ownerEmail !== '')
                                <div>{{ $ownerName }}</div>
                                @if($ownerEmail)<div class="text-muted small">{{ $ownerEmail }}</div>@endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>{{ $institute->package->name ?? '—' }}</td>
                        <td data-col="students" class="text-center" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>{{ $institute->students_count }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif><span class="badge {{ $statusBadge[$institute->status] ?? 'text-bg-secondary' }}">{{ $institute->status }}</span></td>
                        <td data-col="deleted_at" @if(!in_array('deleted_at', $visibleColumns, true)) style="display:none" @endif class="text-muted">{{ \Illuminate\Support\Carbon::parse($institute->deleted_at)->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap col-action" data-col="action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            <button type="button" class="btn btn-sm btn-success single-restore-btn" title="Restore" data-id="{{ $institute->id }}" data-name="{{ $institute->name }}" data-action="{{ route('admin.institutes.restore', $institute) }}">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger force-del-btn" type="button" title="Delete permanently"
                                data-name="{{ $institute->name }}"
                                data-owner="{{ $institute->_e24_owner_name ?? '' }}"
                                data-owner-email="{{ $institute->_e24_owner_email ?? '' }}"
                                data-other="{{ $institute->_e24_other_businesses ?? 0 }}"
                                data-other-active="{{ $institute->_e24_other_active ?? 0 }}"
                                data-action="{{ route('admin.institutes.force-delete', $institute) }}">
                                <i class="bi bi-x-octagon"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No trashed institutes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $institutes->links('pagination::bootstrap-5') }}
        <span class="text-muted small">
            @if($institutes->total() > 0)
                Showing {{ $institutes->firstItem() }}–{{ $institutes->lastItem() }} of {{ $institutes->total() }} trashed institutes ({{ $perPage ?? 25 }} per page)
            @else
                {{ $institutes->total() }} trashed institutes
            @endif
        </span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="institutesBinTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                <th data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>Owner</th>
                <th data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>Package</th>
                <th data-col="students" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>Students</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                <th data-col="deleted_at" @if(!in_array('deleted_at', $visibleColumns, true)) style="display:none" @endif>Deleted At</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allInstitutes as $institute)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $institute->name }}</td>
                    <td data-col="owner" @if(!in_array('owner', $visibleColumns, true)) style="display:none" @endif>{{ $institute->users->first()->name ?? '—' }}</td>
                    <td data-col="package" @if(!in_array('package', $visibleColumns, true)) style="display:none" @endif>{{ $institute->package->name ?? '—' }}</td>
                    <td data-col="students" @if(!in_array('students', $visibleColumns, true)) style="display:none" @endif>{{ $institute->students_count }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($institute->status) }}</td>
                    <td data-col="deleted_at" @if(!in_array('deleted_at', $visibleColumns, true)) style="display:none" @endif>{{ \Illuminate\Support\Carbon::parse($institute->deleted_at)->format('d M Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="admin-card mt-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-patch-check"></i> {{ $certificates->total() }} trashed certificates</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Certificate No</th>
                    <th>Course</th>
                    <th>Institute</th>
                    <th>Deleted At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($certificates as $certificate)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $certificate->student->full_name ?? '—' }}</div>
                            @if ($certificate->certificate_number)
                                <div class="text-muted small">{{ $certificate->certificate_number }}</div>
                            @endif
                        </td>
                        <td>{{ $certificate->certificate_number ?? '—' }}</td>
                        <td>{{ $certificate->course->name ?? '—' }}</td>
                        <td>{{ $certificate->institute->name ?? '—' }}</td>
                        <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($certificate->deleted_at)->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-success single-cert-restore-btn" title="Restore" data-action="{{ route('admin.certificates.restore', $certificate) }}">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger force-del-btn" type="button" title="Delete permanently" data-name="{{ $certificate->student->full_name ?? $certificate->certificate_number ?? 'certificate' }}" data-action="{{ route('admin.certificates.force-delete', $certificate) }}">
                                <i class="bi bi-x-octagon"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No trashed certificates.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $certificates->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $certificates->total() }} trashed certificates</span>
    </div>
</div>

<div class="modal fade" id="forceDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="forceDeleteForm" data-ajax-enabled>
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Permanent Delete — Business</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-shield-check"></i>
                    <strong>This permanently deletes this business and its business data. The Owner/User account will NOT be deleted automatically.</strong>
                    <div class="small mt-1">Tenant-owned data (courses, batches, students, finance, etc.) will be removed. Shared identity (login, email, phone, 2FA) survives.</div>
                </div>
                <div class="card card-body bg-light mb-3 small">
                    <div><strong>Business:</strong> <span id="fd_name" class="fw-bold"></span></div>
                    <div><strong>Owner:</strong> <span id="fd_owner"></span> <span id="fd_owner_email" class="text-muted"></span></div>
                    <div><strong>Other businesses owned/managed by this owner:</strong> <span id="fd_other" class="badge text-bg-secondary">0</span></div>
                    <div id="fd_warning" class="alert alert-info mt-2 mb-0 py-2 small d-none">
                        <i class="bi bi-exclamation-triangle"></i> This owner has other active businesses. Deleting this business will not delete the owner's account or other businesses.
                    </div>
                    <div id="fd_no_other" class="text-muted mt-1 small">This owner has no other businesses. Account will remain as orphaned/inactive — not automatically deleted — and is recoverable by Super Admin.</div>
                </div>
                <label class="form-label">Confirm with your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
                <div class="form-text">Password is verified via <code>PasswordHash::safeCheck</code> and never logged.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon"></i> Delete permanently</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="batchForceDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="batchForceDeleteForm" data-ajax-enabled>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Delete Selected Permanently</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">This permanently deletes the selected businesses and their business data. <strong>Owner/User accounts will NOT be deleted automatically.</strong> Cannot be undone.</div>
                <div class="alert alert-danger">This will permanently delete <strong id="batchForceDeleteCount">0</strong> selected institute(s). Cannot be undone.</div>
                <ul id="batchForceDeleteList" class="small text-muted mb-3" style="max-height:120px; overflow:auto;"></ul>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon"></i> Delete permanently all</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var selectAllBin = document.getElementById('selectAllBin');
    var batchActionsBin = document.getElementById('batchActionsBin');
    var batchCountBin = document.getElementById('batchCountBin');

    function getSelectedChecksBin() { return document.querySelectorAll('.row-check-bin:checked'); }
    function getSelectedIdsBin() {
        var ids = [];
        getSelectedChecksBin().forEach(function (cb) { var v = cb.value || cb.getAttribute('data-id'); if (v) ids.push(v); });
        return ids;
    }
    function updateBatchUIBin() {
        var count = getSelectedChecksBin().length;
        if (batchActionsBin) {
            if (count >= 2) { batchActionsBin.classList.remove('d-none'); batchActionsBin.classList.add('d-flex'); }
            else { batchActionsBin.classList.add('d-none'); batchActionsBin.classList.remove('d-flex'); }
        }
        if (batchCountBin) batchCountBin.textContent = count + ' selected';
        document.querySelectorAll('.batch-count-text-bin').forEach(function (el) { el.textContent = count; });
        if (selectAllBin) {
            var all = document.querySelectorAll('.row-check-bin');
            selectAllBin.checked = all.length > 0 && count === all.length;
            selectAllBin.indeterminate = count > 0 && count < all.length;
        }
    }
    if (selectAllBin) {
        selectAllBin.addEventListener('change', function () {
            document.querySelectorAll('.row-check-bin').forEach(function (cb) { cb.checked = selectAllBin.checked; });
            updateBatchUIBin();
        });
    }
    document.addEventListener('change', function (e) { if (e.target && e.target.classList.contains('row-check-bin')) updateBatchUIBin(); });
    updateBatchUIBin();

    // Drag-and-drop for bin table
    var tableBodyBin = document.getElementById('institutesBinTable');
    if (tableBodyBin) { tableBodyBin = tableBodyBin.querySelector('tbody'); }
    if (tableBodyBin) {
        var draggedRow = null;
        function reorderAndAnimate(target, after) {
            var rows = Array.prototype.slice.call(tableBodyBin.children);
            var prev = new Map();
            rows.forEach(function (tr) { prev.set(tr, tr.getBoundingClientRect().top); });
            var moved = false;
            if (after && target.nextElementSibling !== draggedRow) { tableBodyBin.insertBefore(draggedRow, target.nextElementSibling); moved = true; }
            else if (!after && target.previousElementSibling !== draggedRow) { tableBodyBin.insertBefore(draggedRow, target); moved = true; }
            if (!moved) return;
            var afterRows = Array.prototype.slice.call(tableBodyBin.children);
            afterRows.forEach(function (tr) {
                var delta = prev.get(tr) - tr.getBoundingClientRect().top;
                if (delta) { tr.style.transition = 'none'; tr.style.transform = 'translateY(' + delta + 'px)'; }
            });
            requestAnimationFrame(function(){ requestAnimationFrame(function(){ afterRows.forEach(function(tr){ tr.style.transition='transform .3s cubic-bezier(.2,.85,.35,1)'; tr.style.transform=''; }); }); });
        }
        tableBodyBin.addEventListener('dragstart', function (e) {
            var handle = e.target.closest('.drag-handle');
            if (!handle) { e.preventDefault(); return; }
            draggedRow = handle.closest('tr'); draggedRow.classList.add('dragging'); e.dataTransfer.effectAllowed='move'; e.dataTransfer.setData('text/plain','row');
        });
        tableBodyBin.addEventListener('dragover', function (e) {
            if (!draggedRow) return; e.preventDefault(); e.dataTransfer.dropEffect='move';
            var target = e.target.closest('tr'); if (!target || target===draggedRow) return;
            var rect = target.getBoundingClientRect(); reorderAndAnimate(target, (e.clientY-rect.top)>(rect.height/2));
        });
        tableBodyBin.addEventListener('dragend', function(){ draggedRow=null; tableBodyBin.querySelectorAll('.dragging, .drag-over').forEach(function(el){ el.classList.remove('dragging','drag-over'); el.style.transition=''; el.style.transform=''; }); });
    }

    // Columns toggle for bin
    var tableBin = document.getElementById('institutesBinTable');
    var colChecksBin = document.querySelectorAll('.col-toggle-check-bin');
    var saveColsBin = null;
    if (tableBin && colChecksBin.length) {
        colChecksBin.forEach(function (check) {
            check.addEventListener('change', function () {
                var col = check.getAttribute('data-col');
                var th = tableBin.querySelector('th[data-col="' + col + '"]');
                if (!th) return;
                var index = Array.prototype.indexOf.call(th.parentNode.children, th);
                var hidden = !check.checked;
                th.style.display = hidden ? 'none' : '';
                tableBin.querySelectorAll('tbody tr').forEach(function (tr) { var td = tr.children[index]; if (td) td.style.display = hidden ? 'none' : ''; });
                var printTable = document.getElementById('institutesBinTablePrint');
                if (printTable) { printTable.querySelectorAll('[data-col="' + col + '"]').forEach(function (el) { el.style.display = hidden ? 'none' : ''; }); }
                if (saveColsBin) saveColsBin();
            });
        });
        saveColsBin = function () {
            var visible = []; colChecksBin.forEach(function (check) { if (check.checked) visible.push(check.getAttribute('data-col')); });
            fetch('{{ route('admin.institutes.bin.columns') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ columns: visible })
            });
        };
    }

    // Auto-submit filters
    var filterForm = document.querySelector('.filter-layout');
    if (filterForm) { filterForm.querySelectorAll('select[name]').forEach(function (select) { select.addEventListener('change', function(){ filterForm.submit(); }); }); }

    // Export CSV/Excel for bin
    function exportTableBin(fileName) {
        var table = document.getElementById('institutesBinTable');
        if (!table) return;
        var out = []; var headers=[];
        table.querySelectorAll('thead th').forEach(function(th,i){ if(i>1) headers.push(th.textContent.trim()); });
        out.push(headers.join(','));
        table.querySelectorAll('tbody tr').forEach(function(tr){
            var cells=tr.querySelectorAll('td'); if(!cells.length) return;
            var row=[]; for(var i=2;i<cells.length;i++){ row.push('"'+cells[i].textContent.trim().replace(/"/g,'""')+'"'); }
            out.push(row.join(','));
        });
        var blob=new Blob(['\ufeff'+out.join('\r\n')],{type:'text/csv;charset=utf-8;'});
        var link=document.createElement('a'); link.href=URL.createObjectURL(blob); link.download=fileName; document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }
    var csvBtnBin=document.getElementById('exportCsvBtnBin'); if(csvBtnBin) csvBtnBin.addEventListener('click', function(){ exportTableBin('institutes-bin.csv'); });
    var excelBtnBin=document.getElementById('exportExcelBtnBin'); if(excelBtnBin) excelBtnBin.addEventListener('click', function(){ exportTableBin('institutes-bin.xls'); });

    // Single restore (icon-only)
    document.addEventListener('click', function(e){
        var btn=e.target.closest('.single-restore-btn'); if(!btn) return;
        var url=btn.getAttribute('data-action'); var name=btn.getAttribute('data-name')||'this institute';
        if(!url) return; if(!confirm('Restore '+name+'?')) return;
        var restore=window.Monetix&&Monetix.loading?Monetix.loading(btn,'Restoring…'):null;
        var headers={'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'};
        var doFetch=function(){ return fetch(url,{method:'POST',headers:headers}).then(function(r){return r.json().then(function(j){return {ok:r.ok,json:j};});}); };
        var promise=(window.Monetix&&Monetix.request)?Monetix.request(url,{method:'POST',headers:headers}).then(function(j){return {ok:!(j&&j.success===false),json:j};}).catch(function(){return doFetch();}):doFetch();
        promise.then(function(res){
            if(restore) restore();
            var j=res.json;
            if(!res.ok||(j&&j.success===false)){ if(window.Monetix&&Monetix.toast) Monetix.toast(j&&j.message||'Could not restore.','danger'); else alert(j&&j.message||'Could not restore.'); return; }
            if(window.Monetix&&Monetix.toast) Monetix.toast(j.message||'Institute restored.','success');
            if(window.Monetix&&Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
        }).catch(function(err){ if(restore) restore(); console.error('[singleRestore] failed',err); if(window.Monetix&&Monetix.toast) Monetix.toast('Could not restore.','danger'); });
    });

    // Certificates single restore
    document.addEventListener('click', function(e){
        var btn=e.target.closest('.single-cert-restore-btn'); if(!btn) return;
        var url=btn.getAttribute('data-action'); if(!url) return; if(!confirm('Restore certificate?')) return;
        var restore=window.Monetix&&Monetix.loading?Monetix.loading(btn,'Restoring…'):null;
        var headers={'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'};
        var doFetch=function(){ return fetch(url,{method:'POST',headers:headers}).then(function(r){return r.json().then(function(j){return {ok:r.ok,json:j};});}); };
        var promise=(window.Monetix&&Monetix.request)?Monetix.request(url,{method:'POST',headers:headers}).then(function(j){return {ok:!(j&&j.success===false),json:j};}).catch(function(){return doFetch();}):doFetch();
        promise.then(function(res){
            if(restore) restore();
            var j=res.json;
            if(!res.ok||(j&&j.success===false)){ if(window.Monetix&&Monetix.toast) Monetix.toast(j&&j.message||'Could not restore.','danger'); else alert(j&&j.message||'Could not restore.'); return; }
            if(window.Monetix&&Monetix.toast) Monetix.toast(j.message||'Certificate restored.','success');
            if(window.Monetix&&Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
        }).catch(function(err){ if(restore) restore(); if(window.Monetix&&Monetix.toast) Monetix.toast('Could not restore.','danger'); });
    });

    // Batch actions for bin
    var batchRestoreBtn=document.getElementById('batchRestoreBtn');
    var batchForceDeleteBtn=document.getElementById('batchForceDeleteBtn');
    var batchForceDeleteModal=document.getElementById('batchForceDeleteModal');
    var batchForceDeleteForm=document.getElementById('batchForceDeleteForm');
    var batchBinUrl='{{ route('admin.institutes.bin.batch-action') }}';
    function batchBinRequest(action, extra){
        var ids=getSelectedIdsBin(); if(ids.length<2){ if(window.Monetix&&Monetix.toast) Monetix.toast('Select at least 2 institutes.','warning'); else alert('Select at least 2.'); return; }
        var payload={ids:ids,action:action}; if(extra) Object.keys(extra).forEach(function(k){payload[k]=extra[k];});
        var body=JSON.stringify(payload);
        var headers={'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'};
        var btn=action==='restore'?batchRestoreBtn:batchForceDeleteBtn;
        var restore=window.Monetix&&Monetix.loading?Monetix.loading(btn, action==='restore'?'Restoring…':'Deleting…'):null;
        var doFetch=function(){ return fetch(batchBinUrl,{method:'POST',headers:headers,body:body}).then(function(r){return r.json().then(function(j){return {ok:r.ok,json:j};});}); };
        var promise=(window.Monetix&&Monetix.request)?Monetix.request(batchBinUrl,{method:'POST',headers:headers,body:body}).then(function(j){return {ok:!(j&&j.success===false),json:j};}).catch(function(){return doFetch();}):doFetch();
        promise.then(function(res){
            if(restore) restore();
            var j=res.json;
            if(!res.ok||(j&&j.success===false)){
                if(j&&j.errors&&j.errors.password&&batchForceDeleteForm){
                    var pf=batchForceDeleteForm.querySelector('input[name="password"]');
                    if(pf){ pf.classList.add('is-invalid'); var msg=document.createElement('div'); msg.className='text-danger small mt-1'; msg.textContent=(j.errors.password||[]).join(', '); pf.parentNode.insertBefore(msg, pf.nextSibling); }
                }
                if(window.Monetix&&Monetix.toast) Monetix.toast(j&&j.message||'Batch action failed.','danger'); else alert(j&&j.message||'Batch action failed.'); return;
            }
            if(batchForceDeleteModal){ var inst=bootstrap.Modal.getInstance(batchForceDeleteModal); if(inst) inst.hide(); }
            if(window.Monetix&&Monetix.toast) Monetix.toast(j.message||'Batch action completed.','success');
            if(window.Monetix&&Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
        }).catch(function(err){ if(restore) restore(); console.error('[batchBin] failed',err); if(window.Monetix&&Monetix.toast) Monetix.toast('Batch action failed.','danger'); });
    }
    if(batchRestoreBtn){
        batchRestoreBtn.addEventListener('click', function(){
            var ids=getSelectedIdsBin(); if(ids.length<2) return;
            if(!confirm('Restore '+ids.length+' selected institute(s)?')) return;
            batchBinRequest('restore');
        });
    }
    if(batchForceDeleteBtn){
        batchForceDeleteBtn.addEventListener('click', function(){
            var ids=getSelectedIdsBin(); if(ids.length<2) return;
            var countEl=document.getElementById('batchForceDeleteCount'); var listEl=document.getElementById('batchForceDeleteList');
            if(countEl) countEl.textContent=ids.length;
            if(listEl){ listEl.innerHTML=''; getSelectedChecksBin().forEach(function(cb){ var li=document.createElement('li'); li.textContent=(cb.getAttribute('data-name')||'Institute')+' (#'+(cb.value||cb.getAttribute('data-id'))+')'; listEl.appendChild(li); }); }
            if(batchForceDeleteForm){ batchForceDeleteForm.querySelectorAll('.is-invalid').forEach(function(el){el.classList.remove('is-invalid');}); batchForceDeleteForm.querySelectorAll('.text-danger.small').forEach(function(el){el.remove();}); var pf=batchForceDeleteForm.querySelector('input[name="password"]'); if(pf) pf.value=''; }
            if(batchForceDeleteModal) bootstrap.Modal.getOrCreateInstance(batchForceDeleteModal).show();
        });
    }
    if(batchForceDeleteForm){
        batchForceDeleteForm.addEventListener('submit', function(e){
            e.preventDefault(); e.stopPropagation();
            batchForceDeleteForm.querySelectorAll('.is-invalid').forEach(function(el){el.classList.remove('is-invalid');});
            batchForceDeleteForm.querySelectorAll('.text-danger.small').forEach(function(el){el.remove();});
            var pw=batchForceDeleteForm.querySelector('input[name="password"]'); if(pw) pw.value=(pw.value||'').trim();
            if(!pw||!pw.value){ pw.classList.add('is-invalid'); var m=document.createElement('div'); m.className='text-danger small mt-1'; m.textContent='Password is required.'; pw.parentNode.insertBefore(m, pw.nextSibling); return; }
            batchBinRequest('forceDelete',{password:pw.value});
        });
    }

    // Force delete single (delegated, survives Monetix.loadPage)
    var forceModal=document.getElementById('forceDeleteModal');
    var forceForm=document.getElementById('forceDeleteForm');
    var forceNameEl=document.getElementById('fd_name');
    function clearForceErrors(){ if(!forceForm) return; forceForm.querySelectorAll('.is-invalid').forEach(function(el){el.classList.remove('is-invalid');}); forceForm.querySelectorAll('.text-danger.small').forEach(function(el){el.remove();}); }
    document.addEventListener('click', function(e){
        var btn=e.target.closest('.force-del-btn'); if(!btn) return;
        if(!forceModal||!forceForm) return;
        var actionUrl=btn.getAttribute('data-action');
        if(actionUrl){ forceForm.action=actionUrl; forceForm.setAttribute('data-action-url', actionUrl); }
        if(forceNameEl) forceNameEl.textContent=btn.getAttribute('data-name')||'';
        var fdOwner=document.getElementById('fd_owner');
        var fdOwnerEmail=document.getElementById('fd_owner_email');
        var fdOther=document.getElementById('fd_other');
        var fdWarning=document.getElementById('fd_warning');
        var fdNoOther=document.getElementById('fd_no_other');
        var owner=(btn.getAttribute('data-owner')||'').trim();
        var ownerEmail=(btn.getAttribute('data-owner-email')||'').trim();
        var other=parseInt(btn.getAttribute('data-other')||'0',10);
        if(fdOwner) fdOwner.textContent=owner||'—';
        if(fdOwnerEmail) fdOwnerEmail.textContent=ownerEmail?('('+ownerEmail+')'):'';
        if(fdOther) fdOther.textContent=other;
        if(fdWarning){ if(other>0){ fdWarning.classList.remove('d-none'); } else { fdWarning.classList.add('d-none'); } }
        if(fdNoOther){ if(other>0){ fdNoOther.classList.add('d-none'); } else { fdNoOther.classList.remove('d-none'); } }
        var pw=forceForm.querySelector('input[name="password"]'); if(pw) pw.value='';
        clearForceErrors();
        bootstrap.Modal.getOrCreateInstance(forceModal).show();
    });
    if(forceForm){
        forceForm.addEventListener('submit', function(e){
            if(!forceForm.hasAttribute('data-ajax-enabled')) return;
            if(!window.Monetix||!Monetix.request){ return; }
            e.preventDefault(); clearForceErrors();
            var pw=forceForm.querySelector('input[name="password"]'); if(pw&&typeof pw.value==='string') pw.value=pw.value.trim();
            var submitBtn=forceForm.querySelector('[type="submit"]'); var restore=Monetix.loading(submitBtn,'Deleting…');
            var submitUrl=forceForm.getAttribute('data-action-url')||forceForm.action;
            Monetix.request(submitUrl,{method:'DELETE',body:new FormData(forceForm)}).then(function(res){
                if(restore) restore();
                if(res&&res.errors){ Object.keys(res.errors).forEach(function(key){ var field=forceForm.querySelector('[name="'+key+'"]'); if(field){ field.classList.add('is-invalid'); var msg=document.createElement('div'); msg.className='text-danger small mt-1'; msg.textContent=(res.errors[key]||[]).join(', '); field.parentNode.insertBefore(msg,field.nextSibling); } }); if(Monetix.toast&&res.message) Monetix.toast(res.message,'danger'); return; }
                if(res&&res.success===false){ if(Monetix.toast) Monetix.toast(res.message||'Could not delete.','danger'); return; }
                var m=bootstrap.Modal.getInstance(forceModal); if(m) m.hide();
                if(Monetix.toast) Monetix.toast(res&&res.message||'Permanently deleted.','success');
                if(Monetix.loadPage) Monetix.loadPage(location.pathname+location.search,{preserveFocus:false}); else location.reload();
            }).catch(function(){ if(restore) restore(); if(Monetix.toast) Monetix.toast('Could not delete. Please try again.','danger'); });
        });
    }
})();
</script>
@endpush
