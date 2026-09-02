@extends('layouts.admin')

@section('title', 'Certificates — AccumenAI')

@section('content')
<style>
    .text-gold {
        background: linear-gradient(45deg, #b8860b, #ffd700 45%, #fff7c2 60%, #ffd700 75%, #b8860b);
        background-size: 200% auto;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: #ffd700;
        animation: goldShine 10s linear infinite;
    }
    @keyframes goldShine {
        0% { background-position: 200% center; }
        100% { background-position: -200% center; }
    }
    .btn-shine-outline {
        border: 1px solid #ffd700 !important;
        background: transparent;
        color: #ffd700;
    }
    .btn-shine-outline:hover, .btn-shine-outline:focus {
        border-color: #ffd700 !important;
        background: rgba(255, 215, 0, .1);
        color: #ffd700 !important;
    }
    .btn-shine-outline i { color: #ffd700; }
    .qr-thumb { display: inline-block; line-height: 0; }
    .qr-thumb svg { width: 52px; height: 52px; display: block; }
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #certificatesTablePrint { width: 100%; font-size: 11px; }
        #certificatesTablePrint th, #certificatesTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'pending'  => 'text-bg-warning',
        'active'   => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        'revoked'  => 'text-bg-secondary',
    ];
@endphp
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Certificates</h4>
        <p class="page-header-desc">All issued certificates across the platform</p>
    </div>
</div>

@include('admin.certificates._tabs', ['activeTab' => 'certificates'])

<div data-ajax-table>
<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('admin.certificates.index') }}" data-ajax-filter>

        <div class="filter-search-row align-items-end">

            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by student, certificate no or course..." value="{{ $filters['q'] ?? '' }}">
            </div>

            <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                <label class="form-label mb-1">Institute</label>
                <div class="inst-dropdown" id="instDropdown">
                    <input type="text" class="form-control form-control-sm" id="instSearch" autocomplete="off"
                           placeholder="Search institute..." value="{{ $institutes->firstWhere('id', $selectedInstituteId ?? 0)->name ?? '' }}" data-ajax-ignore>
                    <input type="hidden" name="institute_id" id="instValue" value="{{ $selectedInstituteId ?? '' }}" data-ajax-reload>
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
                    @foreach (['active', 'revoked'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.certificates.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>

        </div>

    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-secondary badge-soft">{{ $items->total() }} Certificates</span>
            <span class="text-muted ms-2 d-none d-lg-inline">All issued certificates across the platform.</span>
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
                        'serial'         => '#',
                        'certificate_no' => 'Certificate No',
                        'student'        => 'Student',
                        'course'         => 'Course',
                        'batch'          => 'Batch',
                        'institute'      => 'Institute',
                        'issue_date'     => 'Issue Date',
                        'status'         => 'Status',
                        'design'         => 'Design',
                        'remarks'        => 'Remarks',
                        'qr'             => 'QR Code',
                        'action'         => 'Action',
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
        <table class="table align-middle mb-0" id="certificatesTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="certificate_no" @if(!in_array('certificate_no', $visibleColumns, true)) style="display:none" @endif>Certificate No</th>
                    <th data-col="student" @if(!in_array('student', $visibleColumns, true)) style="display:none" @endif>Student</th>
                    <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
                    <th data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>Batch</th>
                    <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                    <th data-col="issue_date" @if(!in_array('issue_date', $visibleColumns, true)) style="display:none" @endif>Issue Date</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                    <th data-col="design" @if(!in_array('design', $visibleColumns, true)) style="display:none" @endif>Design</th>
                    <th data-col="remarks" @if(!in_array('remarks', $visibleColumns, true)) style="display:none" @endif>Remarks</th>
                    <th data-col="qr" @if(!in_array('qr', $visibleColumns, true)) style="display:none" @endif>QR Code</th>
                    <th data-col="action" class="text-end" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                        <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $item->student->full_name ?? $item->certificate_number }}"></td>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $items->firstItem() + $loop->index }}</td>
                        <td data-col="certificate_no" class="text-muted" @if(!in_array('certificate_no', $visibleColumns, true)) style="display:none" @endif>{{ $item->certificate_number ?? '—' }}</td>
                        <td data-col="student" @if(!in_array('student', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $item->student->full_name ?? '—' }}</div>
                            @if ($item->student_id ?? null)
                                <div class="text-muted small">{{ $item->student_id }}</div>
                            @endif
                        </td>
                        <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $item->course->name ?? '—' }}</td>
                        <td data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>{{ $item->batch->name ?? '—' }}</td>
                        <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $item->institute->name ?? '—' }}</td>
                        <td data-col="issue_date" @if(!in_array('issue_date', $visibleColumns, true)) style="display:none" @endif>{{ $item->issue_date?->format('d M Y') ?? '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$item->status] ?? 'text-bg-secondary' }}">{{ $item->status }}</span>
                        </td>
                        <td data-col="design" @if(!in_array('design', $visibleColumns, true)) style="display:none" @endif>Design {{ $item->template_id ?? 1 }}</td>
                        <td data-col="remarks" @if(!in_array('remarks', $visibleColumns, true)) style="display:none" @endif>
                            {{ $item->revoked_reason ?? $item->review_note ?? '—' }}
                        </td>
                        <td data-col="qr" @if(!in_array('qr', $visibleColumns, true)) style="display:none" @endif>
                            @if ($item->certificate_number)
                                <a class="btn btn-outline-primary btn-sm btn-icon" href="{{ route('admin.certificates.qr', $item) }}" title="Download QR code">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td data-col="action" class="text-end text-nowrap col-action" @if(!in_array('action', $visibleColumns, true)) style="display:none" @endif>
                            @if ($item->certificate_number)
                                <a class="btn btn-sm btn-icon btn-shine-outline" href="{{ route('admin.certificates.show', $item) }}" title="View certificate">
                                    <i class="bi bi-award"></i>
                                </a>
                            @endif
                            @if ($item->status === 'pending')
                                <form class="d-inline" method="POST" action="{{ route('admin.certificates.action', $item) }}"
                                      data-ajax-action="1" data-confirm="Approve and issue this certificate?">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-success btn-sm btn-icon" type="submit" title="Approve"><i class="bi bi-check-circle"></i></button>
                                </form>
                                <button class="btn btn-outline-danger btn-sm btn-icon rev-btn" type="button"
                                        data-id="{{ $item->id }}" data-action="reject"
                                        data-action-url="{{ route('admin.certificates.action', $item) }}" title="Reject">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            @elseif ($item->status === 'active')
                                <button class="btn btn-outline-danger btn-sm btn-icon rev-btn" type="button"
                                        data-id="{{ $item->id }}" data-action="revoke"
                                        data-action-url="{{ route('admin.certificates.action', $item) }}" title="Revoke">
                                    <i class="bi bi-ban"></i>
                                </button>
                            @elseif ($item->status === 'revoked')
                                <form class="d-inline" method="POST" action="{{ route('admin.certificates.action', $item) }}"
                                      data-ajax-action="1" data-confirm="Cancel the revocation and restore this certificate?">
                                    @csrf
                                    <input type="hidden" name="action" value="revoke-cancel">
                                    <button class="btn btn-outline-success btn-sm btn-icon" type="submit" title="Cancel Revoke">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            @endif
                            <form class="d-inline" method="POST" action="{{ route('admin.certificates.destroy', $item) }}"
                                  data-ajax-delete="1" data-confirm="Delete this certificate? This cannot be undone.">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm btn-icon" type="submit" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted py-4">No certificates found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $items->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $items->total() }} certificates</span>
    </div>
</div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="certificatesTablePrint">
        <thead>
            <tr>
                <th data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                <th data-col="certificate_no" @if(!in_array('certificate_no', $visibleColumns, true)) style="display:none" @endif>Certificate No</th>
                <th data-col="student" @if(!in_array('student', $visibleColumns, true)) style="display:none" @endif>Student</th>
                <th data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>Course</th>
                <th data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>Batch</th>
                <th data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>Institute</th>
                <th data-col="issue_date" @if(!in_array('issue_date', $visibleColumns, true)) style="display:none" @endif>Issue Date</th>
                <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>Status</th>
                <th data-col="design" @if(!in_array('design', $visibleColumns, true)) style="display:none" @endif>Design</th>
                <th data-col="remarks" @if(!in_array('remarks', $visibleColumns, true)) style="display:none" @endif>Remarks</th>
                <th data-col="qr" @if(!in_array('qr', $visibleColumns, true)) style="display:none" @endif>QR Code</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allItems as $item)
                <tr>
                    <td data-col="serial" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $loop->iteration }}</td>
                    <td data-col="certificate_no" @if(!in_array('certificate_no', $visibleColumns, true)) style="display:none" @endif>{{ $item->certificate_number ?? '—' }}</td>
                    <td data-col="student" @if(!in_array('student', $visibleColumns, true)) style="display:none" @endif>{{ $item->student->full_name ?? '—' }}</td>
                    <td data-col="course" @if(!in_array('course', $visibleColumns, true)) style="display:none" @endif>{{ $item->course->name ?? '—' }}</td>
                    <td data-col="batch" @if(!in_array('batch', $visibleColumns, true)) style="display:none" @endif>{{ $item->batch->name ?? '—' }}</td>
                    <td data-col="institute" @if(!in_array('institute', $visibleColumns, true)) style="display:none" @endif>{{ $item->institute->name ?? '—' }}</td>
                    <td data-col="issue_date" @if(!in_array('issue_date', $visibleColumns, true)) style="display:none" @endif>{{ $item->issue_date?->format('d M Y') ?? '—' }}</td>
                    <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ ucwords($item->status) }}</td>
                    <td data-col="design" @if(!in_array('design', $visibleColumns, true)) style="display:none" @endif>Design {{ $item->template_id ?? 1 }}</td>
                    <td data-col="remarks" @if(!in_array('remarks', $visibleColumns, true)) style="display:none" @endif>{{ $item->revoked_reason ?? $item->review_note ?? '—' }}</td>
                    <td data-col="qr" @if(!in_array('qr', $visibleColumns, true)) style="display:none" @endif>
                        @if ($item->certificate_number)
                            <span class="qr-thumb">{!! qr_svg(route('verify.certificate', $item->certificate_number), 3) !!}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="reviewForm">
            @csrf
            <input type="hidden" name="action" id="rv_action" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="rv_title">Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="rv_reason">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" id="rv_reason" name="reason" rows="3" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="rv_confirm">Confirm</button>
            </div>
        </form>
    </div>
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
    var tableBody = document.getElementById('certificatesTable');
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
    var table = document.getElementById('certificatesTable');
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
                var printTable = document.getElementById('certificatesTablePrint');
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
            fetch('{{ route('admin.certificates.columns') }}', {
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
                var raw = li.getAttribute('data-value');
                var safe = raw ? String(raw).trim() : '';
                if (safe.indexOf('[object') !== -1 || safe.indexOf('%5Bobject') !== -1) { safe = ''; }
                value.value = safe;
                items.forEach(function (x) { x.classList.remove('active'); });
                li.classList.add('active');
                toggle(false);
                value.dispatchEvent(new Event('change'));
            });
        });

        if (window.Monetix && Monetix.delegate) {
            Monetix.delegate('click', null, function (e) {
                if (!dropdown.contains(e.target)) toggle(false);
            }, 'mtx-certificates-dropdown');
        }
    }

    // CSV / Excel export from the current table
    function exportTable(fileName) {
        var table = document.getElementById('certificatesTable');
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
    if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable('certificates.csv'); }); }
    var excelBtn = document.getElementById('exportExcelBtn');
    if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable('certificates.xls'); }); }

    // Reject / Revoke reason modal
    var modal = document.getElementById('reviewModal');
    var form = document.getElementById('reviewForm');
    var title = document.getElementById('rv_title');
    var action = document.getElementById('rv_action');
    var confirmBtn = document.getElementById('rv_confirm');
    if (modal && form) {
        document.querySelectorAll('.rev-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.action = btn.getAttribute('data-action-url');
                var act = btn.getAttribute('data-action');
                action.value = act;
                if (act === 'revoke') {
                    title.textContent = 'Revoke certificate';
                    confirmBtn.textContent = 'Revoke';
                    confirmBtn.className = 'btn btn-warning';
                } else {
                    title.textContent = 'Reject certificate';
                    confirmBtn.textContent = 'Reject';
                    confirmBtn.className = 'btn btn-danger';
                }
                bootstrap.Modal.getOrCreateInstance(modal).show();
            });
        });

        form.addEventListener('submit', function (e) {
            if (!window.Monetix || !Monetix.request) { return; }
            e.preventDefault();
            if (confirmBtn) { confirmBtn.disabled = true; }
            Monetix.request(form.action, { method: 'POST', body: new FormData(form) })
                .then(function (res) {
                    if (confirmBtn) { confirmBtn.disabled = false; }
                    if (res && res.errors) {
                        if (Monetix.toast) { Monetix.toast((res.errors.reason || ['Please provide a reason.'])[0], 'danger'); }
                        return;
                    }
                    if (res && res.success === false) {
                        if (Monetix.toast) { Monetix.toast(res.message || 'Action failed.', 'danger'); }
                        return;
                    }
                    var inst = bootstrap.Modal.getInstance(modal);
                    if (inst) { inst.hide(); }
                    if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                    if (Monetix.loadPage) { Monetix.loadPage(location.pathname + location.search, { preserveFocus: false }); }
                });
        });
    }
})();
</script>
@endpush