@extends('layouts.institute')

@section('title', 'Patients — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    .col-filter-btn {
        border: 0;
        background: transparent;
        padding: 0 2px;
        margin-left: 2px;
        color: #adb5bd;
        font-size: 12px;
        line-height: 1;
        vertical-align: baseline;
        cursor: pointer;
    }
    .col-filter-btn:hover { color: var(--bs-primary); }
    .col-filter-btn.active { color: var(--bs-primary); }
    .col-filter-menu {
        position: fixed;
        z-index: 1080;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(0,0,0,.15);
        min-width: 180px;
        max-width: 240px;
        padding: 6px;
    }
    .col-filter-menu .menu-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #888;
        padding: 6px 10px 4px;
    }
    .col-filter-menu a.menu-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 7px 10px;
        border-radius: 7px;
        font-size: 13.5px;
        color: #212529;
        text-decoration: none;
        white-space: nowrap;
    }
    .col-filter-menu a.menu-item:hover { background: #f1f5ff; }
    .col-filter-menu a.menu-item.active { background: #e7f0ff; font-weight: 600; color: var(--bs-primary); }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .patients-filter-wrap, .table-toolbar,
        .page-header-desc, .page-header-actions, .pagination, .d-none, .card .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .card-body { padding: 0 !important; }
        .print-only { display: block !important; }
        #patientsTablePrint { width: 100%; font-size: 11px; }
        #patientsTablePrint th, #patientsTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Patients</li>
    </ol>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Patient Management</h4>
        <p class="page-header-desc">{{ $patients->total() }} registered patients</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickAddPatientModal">
            <i class="bi bi-plus-lg me-1"></i>Add Patient
        </button>
        <a class="btn btn-outline-primary" href="{{ route('medical.patients.create') }}">
            Full Registration
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="patients-filter-wrap">
            <form class="filter-layout mb-3" method="GET" action="{{ route('medical.patients.index') }}">
                <input type="hidden" name="per_page" value="{{ $perPage ?? 25 }}">

                <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="search" placeholder="Search by MR Number, Name, or Phone..." value="{{ $filters['search'] ?? '' }}">
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Gender</label>
                <select name="gender" class="form-select form-select-sm">
                    <option value="">All Genders</option>
                    @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $g => $gLabel)
                        <option value="{{ $g }}" @selected(($filters['gender'] ?? '') === $g)>{{ $gLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Blood Group</label>
                <select name="blood_group" class="form-select form-select-sm">
                    <option value="">All Groups</option>
                    @foreach (($bloodGroups ?? ['A+','A-','B+','B-','AB+','AB-','O+','O-','UKN']) as $bg)
                        <option value="{{ $bg }}" @selected(($filters['blood_group'] ?? '') === $bg)>{{ $bg === 'UKN' ? 'UKN (Unknown)' : $bg }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Patients</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach (($categories ?? array_keys(mawa_age_category_bounds())) as $cat)
                        <option value="{{ $cat }}" @selected(($filters['category'] ?? '') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('medical.patients.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
                </div>
            </form>
        </div>

        <hr class="my-3">

        <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-primary badge-soft">{{ $patients->total() }} Patients</span>
            <span class="text-muted ms-2 d-none d-lg-inline">All patients registered in this institute.</span>
        </div>
        <div class="toolbar-actions">
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
                        'serial' => '#',
                        'mr'     => 'MR Number',
                        'name'   => 'Name',
                        'age'    => 'Age',
                        'age_group' => 'Age Group',
                        'gender' => 'Gender',
                        'phone'  => 'Phone',
                        'blood'  => 'Blood Group',
                        'status' => 'Status',
                        'action' => 'Actions',
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
        <table class="table table-hover align-middle mb-0" id="patientsTable">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="mr" @if(!in_array('mr', $visibleColumns, true)) style="display:none" @endif>MR Number</th>
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Name</th>
                    <th data-col="age" @if(!in_array('age', $visibleColumns, true)) style="display:none" @endif>Age</th>
                    <th data-col="age_group" @if(!in_array('age_group', $visibleColumns, true)) style="display:none" @endif>Age Group<button type="button" class="col-filter-btn @if(!empty($filters['category'])) active @endif" data-col-filter="category" title="Filter age group"><i class="bi bi-funnel-fill"></i></button></th>
                    <th data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>Gender<button type="button" class="col-filter-btn @if(!empty($filters['gender'])) active @endif" data-col-filter="gender" title="Filter gender"><i class="bi bi-funnel-fill"></i></button></th>
                    <th data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>Phone</th>
                    <th data-col="blood" @if(!in_array('blood', $visibleColumns, true)) style="display:none" @endif>Blood Group<button type="button" class="col-filter-btn @if(!empty($filters['blood_group'])) active @endif" data-col-filter="blood_group" title="Filter blood group"><i class="bi bi-funnel-fill"></i></button></th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status<button type="button" class="col-filter-btn @if(!empty($filters['status'])) active @endif" data-col-filter="status" title="Filter status"><i class="bi bi-funnel-fill"></i></button></th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patients as $patient)
                <tr>
                    <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $patients->firstItem() + $loop->index }}</td>
                    <td data-col="mr" @if(!in_array('mr', $visibleColumns, true)) style="display:none" @endif><strong>{{ clinical_no($patient->mr_number) }}</strong></td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $patient->full_name }}</td>
                    <td data-col="age" @if(!in_array('age', $visibleColumns, true)) style="display:none" @endif>{{ $patient->age !== null ? $patient->age.' years' : 'N/A' }}</td>
                    <td data-col="age_group" @if(!in_array('age_group', $visibleColumns, true)) style="display:none" @endif>@if($patient->age_category)<span class="badge bg-secondary">{{ $patient->age_category }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>{{ ucfirst($patient->gender) }}</td>
                    <td data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>{{ $patient->phone }}</td>
                    <td data-col="blood" @if(!in_array('blood', $visibleColumns, true)) style="display:none" @endif><span class="text-danger fw-bold">{{ $patient->blood_group ?? 'N/A' }}</span></td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                        @if($patient->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </td>
                    <td data-col="action" class="text-end text-nowrap" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('medical.patients.show', $patient) }}" class="btn btn-info" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('medical.patients.edit', $patient) }}" class="btn btn-warning" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="{{ route('medical.patients.history', $patient) }}" class="btn btn-secondary" title="History">
                                <i class="bi bi-clock-history"></i>
                            </a>
                            <button type="button" class="btn btn-danger" title="Delete"
                                    onclick="confirmMedicalDelete({{ $patient->id }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="20" class="text-center text-muted py-4">
                        <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                        No patients found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $patients->links('pagination::bootstrap-5') }}
        <span class="text-muted small">
            @if($patients->total() > 0)
                Showing {{ $patients->firstItem() }}–{{ $patients->lastItem() }} of {{ $patients->total() }} patients ({{ $perPage ?? 25 }} per page)
            @else
                {{ $patients->total() }} patients
            @endif
        </span>
    </div>
    </div>
</div>

{{-- Column-header funnel filter menus (positioned fixed via JS so they are never clipped by .table-responsive) --}}
<div class="col-filter-menu" id="colFilter-gender" hidden>
    <div class="menu-title">Filter by gender</div>
    <a class="menu-item @if(empty($filters['gender'])) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['gender', 'page']), ['page' => 1])) }}">All genders @if(empty($filters['gender']))<i class="bi bi-check-lg"></i>@endif</a>
    @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $g => $gLabel)
        <a class="menu-item @if(($filters['gender'] ?? '') === $g) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['page']), ['gender' => $g, 'page' => 1])) }}">{{ $gLabel }} @if(($filters['gender'] ?? '') === $g)<i class="bi bi-check-lg"></i>@endif</a>
    @endforeach
</div>
<div class="col-filter-menu" id="colFilter-blood_group" hidden>
    <div class="menu-title">Filter by blood group</div>
    <a class="menu-item @if(empty($filters['blood_group'])) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['blood_group', 'page']), ['page' => 1])) }}">All groups @if(empty($filters['blood_group']))<i class="bi bi-check-lg"></i>@endif</a>
    @foreach (($bloodGroups ?? ['A+','A-','B+','B-','AB+','AB-','O+','O-','UKN']) as $bg)
        <a class="menu-item @if(($filters['blood_group'] ?? '') === $bg) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['page']), ['blood_group' => $bg, 'page' => 1])) }}">{{ $bg === 'UKN' ? 'UKN (Unknown)' : $bg }} @if(($filters['blood_group'] ?? '') === $bg)<i class="bi bi-check-lg"></i>@endif</a>
    @endforeach
</div>
<div class="col-filter-menu" id="colFilter-category" hidden>
    <div class="menu-title">Filter by age group</div>
    <a class="menu-item @if(empty($filters['category'])) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['category', 'page']), ['page' => 1])) }}">All age groups @if(empty($filters['category']))<i class="bi bi-check-lg"></i>@endif</a>
    @foreach (($categories ?? []) as $cat)
        <a class="menu-item @if(($filters['category'] ?? '') === $cat) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['page']), ['category' => $cat, 'page' => 1])) }}">{{ $cat }} @if(($filters['category'] ?? '') === $cat)<i class="bi bi-check-lg"></i>@endif</a>
    @endforeach
</div>
<div class="col-filter-menu" id="colFilter-status" hidden>
    <div class="menu-title">Filter by status</div>
    <a class="menu-item @if(empty($filters['status'])) active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['status', 'page']), ['page' => 1])) }}">All patients @if(empty($filters['status']))<i class="bi bi-check-lg"></i>@endif</a>
    <a class="menu-item @if(($filters['status'] ?? '') === 'active') active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['page']), ['status' => 'active', 'page' => 1])) }}">Active @if(($filters['status'] ?? '') === 'active')<i class="bi bi-check-lg"></i>@endif</a>
    <a class="menu-item @if(($filters['status'] ?? '') === 'inactive') active @endif" href="{{ route('medical.patients.index', array_merge(request()->except(['page']), ['status' => 'inactive', 'page' => 1])) }}">Inactive @if(($filters['status'] ?? '') === 'inactive')<i class="bi bi-check-lg"></i>@endif</a>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="patientsTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="mr" @if(!in_array('mr', $visibleColumns, true)) style="display:none" @endif>MR Number</th>
                <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>Name</th>
                <th data-col="age" @if(!in_array('age', $visibleColumns, true)) style="display:none" @endif>Age</th>
                <th data-col="age_group" @if(!in_array('age_group', $visibleColumns, true)) style="display:none" @endif>Age Group</th>
                <th data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>Gender</th>
                <th data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>Phone</th>
                <th data-col="blood" @if(!in_array('blood', $visibleColumns, true)) style="display:none" @endif>Blood Group</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allPatients as $patient)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="mr" @if(!in_array('mr', $visibleColumns, true)) style="display:none" @endif>{{ clinical_no($patient->mr_number) }}</td>
                    <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ $patient->full_name }}</td>
                    <td data-col="age" @if(!in_array('age', $visibleColumns, true)) style="display:none" @endif>{{ $patient->age !== null ? $patient->age.' years' : 'N/A' }}</td>
                    <td data-col="age_group" @if(!in_array('age_group', $visibleColumns, true)) style="display:none" @endif>{{ $patient->age_category ?? '—' }}</td>
                    <td data-col="gender" @if(!in_array('gender', $visibleColumns, true)) style="display:none" @endif>{{ ucfirst($patient->gender) }}</td>
                    <td data-col="phone" @if(!in_array('phone', $visibleColumns, true)) style="display:none" @endif>{{ $patient->phone }}</td>
                    <td data-col="blood" @if(!in_array('blood', $visibleColumns, true)) style="display:none" @endif>{{ $patient->blood_group ?? 'N/A' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ $patient->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@foreach($patients as $patient)
<form id="medical-delete-form-{{ $patient->id }}"
      action="{{ route('medical.patients.destroy', $patient) }}"
      method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endforeach

@include('medical.patients._quick_create_modal')
@endsection

@push('scripts')
<script>
function confirmMedicalDelete(id) {
    if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
        document.getElementById('medical-delete-form-' + id).submit();
    }
}
</script>
<script>
(function () {
    // Column visibility toggle (same effect as admin institutes index).
    var table = document.getElementById('patientsTable');
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
                var printTable = document.getElementById('patientsTablePrint');
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
                body: JSON.stringify({ key: 'patients', columns: visible })
            });
        };
    }

    // Auto-submit filters when a dropdown/select changes (same as institutes).
    var filterForm = document.querySelector('.filter-layout');
    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    // Column-header solid funnel filters (gender / blood group / age group / status).
    // Menus are position:fixed + appended at page level so .table-responsive never clips them.
    (function () {
        var buttons = document.querySelectorAll('.col-filter-btn[data-col-filter]');
        if (!buttons.length) { return; }

        function menuFor(name) { return document.getElementById('colFilter-' + name); }

        function closeAll(except) {
            document.querySelectorAll('.col-filter-menu').forEach(function (menu) {
                if (menu !== except) { menu.hidden = true; }
            });
        }

        function positionMenu(btn, menu) {
            var rect = btn.getBoundingClientRect();
            menu.hidden = false;
            var menuWidth = menu.offsetWidth || 180;
            var menuHeight = menu.offsetHeight || 200;
            var left = Math.max(8, Math.min(rect.left, window.innerWidth - menuWidth - 8));
            var top = rect.bottom + 6;
            if (top + menuHeight > window.innerHeight - 8) {
                top = Math.max(8, rect.top - menuHeight - 6);
            }
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var menu = menuFor(btn.getAttribute('data-col-filter'));
                if (!menu) { return; }
                var willOpen = menu.hidden;
                closeAll();
                if (willOpen) { positionMenu(btn, menu); }
                else { menu.hidden = true; }
            });
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest && (e.target.closest('.col-filter-menu') || e.target.closest('.col-filter-btn'))) { return; }
            closeAll();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeAll(); }
        });
        window.addEventListener('resize', function () { closeAll(); });
        var scrollContainer = document.querySelector('.card .table-responsive');
        if (scrollContainer) {
            scrollContainer.addEventListener('scroll', function () { closeAll(); }, { passive: true });
        }
    })();

    // CSV / Excel export from the current table (visible columns, no Actions).
    function exportTable(fileName) {
        var tbl = document.getElementById('patientsTable');
        if (!tbl) { return; }
        var headCells = Array.prototype.slice.call(tbl.querySelectorAll('thead th'));
        // Drop the Actions column from export.
        var exportIndexes = [];
        headCells.forEach(function (th, i) {
            if (th.getAttribute('data-col') === 'action') { return; }
            if (th.style.display === 'none') { return; }
            exportIndexes.push(i);
        });
        var out = [];
        out.push(exportIndexes.map(function (i) {
            return '"' + headCells[i].textContent.trim().replace(/"/g, '""') + '"';
        }).join(','));
        tbl.querySelectorAll('tbody tr').forEach(function (tr) {
            var cells = tr.querySelectorAll('td, th');
            if (!cells.length) { return; }
            // Skip the "No patients found" empty row.
            if (cells.length === 1) { return; }
            out.push(exportIndexes.map(function (i) {
                var cell = cells[i];
                return '"' + (cell ? cell.textContent.trim().replace(/"/g, '""') : '') + '"';
            }).join(','));
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('patients.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('patients.xls'); }); }
})();
</script>
@endpush
