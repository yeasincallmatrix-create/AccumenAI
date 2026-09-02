@php
    $statusBadge = [
        'pending'  => 'text-bg-warning',
        'active'   => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        'revoked'  => 'text-bg-secondary',
    ];
    $statusNames = [
        'pending'  => 'Pending',
        'active'   => 'Active',
        'rejected' => 'Rejected',
        'revoked'  => 'Revoked',
    ];
@endphp

<div class="admin-card">

    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — Certificates</h4>
        <p class="mb-0 text-muted">{{ $certificates->total() }} certificates issued · {{ now()->format('d M Y') }}</p>
    </div>

    <div class="filter-card mb-3">
        <div class="filter-layout">
            <div class="filter-search-row align-items-end">
                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.350ms="search"
                           placeholder="Search by certificate no, student or course...">
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">Branch</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.branch_id">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetFilters" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $certificates->total() }} Certificates</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i>Columns <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial'          => '#',
                        'certificate_no'  => 'Certificate No',
                        'type'            => 'Type',
                        'student'         => 'Student',
                        'course'          => 'Course',
                        'batch'           => 'Batch',
                        'issue_date'      => 'Issue Date',
                        'status'          => 'Status',
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item" for="cert-col-{{ $col }}">
                                <input type="checkbox" id="cert-col-{{ $col }}" class="form-check-input me-2"
                                       wire:click="toggleColumn('{{ $col }}')"
                                       @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:32px"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    @if (in_array('serial', $visibleColumns, true))<th class="text-muted">#</th>@endif
                    @if (in_array('certificate_no', $visibleColumns, true))<th>Certificate No</th>@endif
                    @if (in_array('type', $visibleColumns, true))<th>Type</th>@endif
                    @if (in_array('student', $visibleColumns, true))<th>Student</th>@endif
                    @if (in_array('course', $visibleColumns, true))<th>Course</th>@endif
                    @if (in_array('batch', $visibleColumns, true))<th>Batch</th>@endif
                    @if (in_array('issue_date', $visibleColumns, true))<th>Issue Date</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>Status</th>@endif
                    @if ($isAdminControlled)<th>Actions</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($certificates as $certificate)
                    <tr>
                        <td class="text-center"><input type="checkbox" class="form-check-input row-check"></td>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $certificates->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('certificate_no', $visibleColumns, true))<td class="text-muted">{{ $certificate->certificate_number ?? '—' }}</td>@endif
                        @if (in_array('type', $visibleColumns, true))<td>{{ $certificate->type?->name ?? '—' }}</td>@endif
                        @if (in_array('student', $visibleColumns, true))<td>
                            <span class="fw-semibold">{{ $certificate->student->full_name ?? '—' }}</span>
                            @if ($certificate->student?->student_id)
                                <span class="text-muted small d-block">{{ $certificate->student->student_id }}</span>
                            @endif
                        </td>@endif
                        @if (in_array('course', $visibleColumns, true))<td>{{ $certificate->course->name ?? '—' }}</td>@endif
                        @if (in_array('batch', $visibleColumns, true))<td>{{ $certificate->batch->name ?? '—' }}</td>@endif
                        @if (in_array('issue_date', $visibleColumns, true))<td>{{ $certificate->issue_date?->format('d M Y') ?? '—' }}</td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge {{ $statusBadge[$certificate->status] ?? 'text-bg-secondary' }}">{{ $statusNames[$certificate->status] ?? $certificate->status }}</span>
                        </td>@endif
                        @if ($isAdminControlled)
                        <td class="text-end text-nowrap">
                            @if ($certificate->status === 'pending')
                                <form class="d-inline" method="POST" action="{{ route('certificates.action', $certificate) }}"
                                      data-ajax-action="1" data-confirm="Approve and issue this certificate?">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-success btn-sm btn-icon" type="submit" title="Approve"><i class="bi bi-check-circle"></i></button>
                                </form>
                                <button class="btn btn-outline-danger btn-sm btn-icon rev-btn" type="button"
                                        data-id="{{ $certificate->id }}" data-action="reject"
                                        data-action-url="{{ route('certificates.action', $certificate) }}" title="Reject">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            @elseif ($certificate->status === 'rejected')
                                <span class="text-muted small">Rejected</span>
                            @elseif ($certificate->status === 'active')
                                <a class="btn btn-outline-primary btn-sm btn-icon" href="{{ route('certificates.index') }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="99" class="text-center text-muted py-4">No certificates issued yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($isAdminControlled)
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
    @endif

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $certificates->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $certificates->total() }} Certificates</span>
    </div>

</div>

<style>
@media print {
    .topbar, .sidebar, .sidebar-backdrop, .page-header, .monetix-print-hidden { display: none !important; }
    .layout { display: block !important; min-height: 0 !important; }
    .content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .admin-card { box-shadow: none !important; border: none !important; }
    .print-header { display: block !important; margin-bottom: 12px; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse; }
}
</style>

@if ($isAdminControlled)
@push('scripts')
<script>
(function () {
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
                title.textContent = 'Reject certificate';
                confirmBtn.textContent = 'Reject';
                confirmBtn.className = 'btn btn-danger';
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
                    Livewire.all().forEach(function(component) { if(component.name === 'CertificateList') component.refresh(); });
                });
        });
    }
})();
</script>
@endpush
@endif
