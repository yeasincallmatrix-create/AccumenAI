@extends('layouts.admin')

@section('title', 'Subject Requests — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
    }
</style>
@php
    $statusBadge = [
        'pending'   => 'text-bg-warning',
        'approved'  => 'text-bg-success',
        'rejected'  => 'text-bg-danger',
        'cancelled' => 'text-bg-secondary',
    ];
    $statusColumns = [
        'serial'       => '#',
        'institute'    => 'Institute',
        'subject'      => 'Subject',
        'code'         => 'Code',
        'category'     => 'Course / Category',
        'requested_by' => 'Requested by',
        'status'       => 'Status',
        'requested_at' => 'Requested at',
        'review_note'  => 'Review note',
        'reviewed_by'  => 'Reviewed by',
        'reviewed_at'  => 'Reviewed at',
        'action'       => 'Action',
    ];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Subject Requests</h4>
        <p class="page-header-desc">New subject proposals submitted by institutes awaiting approval</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('admin.courses._tabs', [
        'activeTab' => 'subject_requests',
        'coursesCount' => $coursesCount,
        'batchesCount' => $batchesCount,
        'subjectsCount' => $subjectsCount,
        'archiveCount' => $archiveCount,
        'subjectRequestsCount' => $pendingCount,
    ])
</div>

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.courses.subjects-requests') }}">

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by subject name, code or institute..." value="{{ $filters['q'] ?? '' }}">
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

            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    @foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.courses.subjects-requests') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-warning badge-soft">{{ $pendingCount }} Pending</span>
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $items->total() }} Total</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Approve subject proposals to make them available to the institute.</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> Columns <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenu">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ($statusColumns as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="col-toggle-{{ $col }}">
                                <input type="checkbox" id="col-toggle-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0" id="subjectRequestsTable">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="subject" @if(!in_array('subject', $visibleColumns, true)) style="display:none" @endif>Subject</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>Code</th>
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>Course / Category</th>
                    <th data-col="requested_by" @if(!in_array('requested_by', $visibleColumns, true)) style="display:none" @endif>Requested by</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="requested_at" @if(!in_array('requested_at', $visibleColumns, true)) style="display:none" @endif>Requested at</th>
                    <th data-col="review_note" @if(!in_array('review_note', $visibleColumns, true)) style="display:none" @endif>Review note</th>
                    <th data-col="reviewed_by" @if(!in_array('reviewed_by', $visibleColumns, true)) style="display:none" @endif>Reviewed by</th>
                    <th data-col="reviewed_at" @if(!in_array('reviewed_at', $visibleColumns, true)) style="display:none" @endif>Reviewed at</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $item->institute->name ?? '—' }}</td>
                        <td @if(!in_array('subject', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $item->name }}</div>
                            @if ($item->short_name)
                                <div class="text-muted small">{{ $item->short_name }}</div>
                            @endif
                        </td>
                        <td class="text-muted" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $item->subject_code ?? '—' }}</td>
                        <td @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $item->category->name ?? '—' }}</td>
                        <td @if(!in_array('requested_by', $visibleColumns, true)) style="display:none" @endif>
                            {{ $item->requestedBy->name ?? '—' }}
                            <div class="text-muted small">{{ $item->requestedBy->email ?? '' }}</div>
                        </td>
                        <td @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$item->status] ?? 'text-bg-secondary' }}">{{ $item->status }}</span>
                        </td>
                        <td class="text-muted" @if(!in_array('requested_at', $visibleColumns, true)) style="display:none" @endif>{{ $item->created_at->format('d M Y') }}</td>
                        <td class="text-muted" @if(!in_array('review_note', $visibleColumns, true)) style="display:none" @endif>{{ $item->review_note ?? '—' }}</td>
                        <td @if(!in_array('reviewed_by', $visibleColumns, true)) style="display:none" @endif>{{ $item->reviewedBy->name ?? '—' }}</td>
                        <td class="text-muted" @if(!in_array('reviewed_at', $visibleColumns, true)) style="display:none" @endif>{{ $item->reviewed_at?->format('d M Y') ?? '—' }}</td>
                        <td class="text-end text-nowrap col-action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            @if ($item->status === 'pending')
                                <form class="d-inline" method="POST" action="{{ route('admin.courses.subjects-requests.action', $item) }}"
                                      data-ajax-action="1" data-confirm="Approve this subject request?">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check-circle"></i> Approve</button>
                                </form>
                                <form class="d-inline" method="POST" action="{{ route('admin.courses.subjects-requests.action', $item) }}"
                                      data-ajax-action="1" data-confirm="Reject this subject request?">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i> Reject</button>
                                </form>
                            @else
                                <span class="text-muted small">Reviewed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No subject requests yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} subject requests</span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th>#</th>
                <th>Institute</th>
                <th>Subject</th>
                <th>Code</th>
                <th>Course / Category</th>
                <th>Requested by</th>
                <th>Status</th>
                <th>Requested at</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allItems as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->institute->name ?? '—' }}</td>
                    <td>{{ $item->name }}@if ($item->short_name) ({{ $item->short_name }})@endif</td>
                    <td>{{ $item->subject_code ?? '—' }}</td>
                    <td>{{ $item->category->name ?? '—' }}</td>
                    <td>{{ $item->requestedBy->name ?? '—' }}</td>
                    <td>{{ ucfirst($item->status) }}</td>
                    <td>{{ $item->created_at->format('d M Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
(function () {
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
                search.value = li.textContent.trim();
                value.value = li.getAttribute('data-value') || '';
                items.forEach(function (x) { x.classList.remove('active'); });
                li.classList.add('active');
                toggle(false);
                if (filterForm) { filterForm.submit(); }
            });
        });

        if (window.Monetix && Monetix.delegate) {
            Monetix.delegate('click', null, function (e) {
                if (!dropdown.contains(e.target)) toggle(false);
            }, 'mtx-subject-requests-dropdown');
        }
    }

    if (filterForm) {
        filterForm.querySelectorAll('select[name]').forEach(function (select) {
            select.addEventListener('change', function () { filterForm.submit(); });
        });
    }

    // Column visibility toggle
    var table = document.getElementById('subjectRequestsTable');
    var colChecks = document.querySelectorAll('.col-toggle-check');
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
                var visible = [];
                colChecks.forEach(function (c) { if (c.checked) { visible.push(c.getAttribute('data-col')); } });
                fetch('{{ route('admin.courses.subjects-requests-columns') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ columns: visible })
                });
            });
        });
    }
})();
</script>
@endpush