@extends('layouts.institute')
@section('title', 'Training Certificates — AccumenAI')

@section('content')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #trainingCertificatesTablePrint { width: 100%; font-size: 11px; }
        #trainingCertificatesTablePrint th, #trainingCertificatesTablePrint td { padding: 4px 6px !important; white-space: nowrap; }
    }
</style>
@php
    $statusBadge = [
        'active'  => 'text-bg-success',
        'revoked' => 'text-bg-secondary',
        'pending' => 'text-bg-warning',
    ];
    $visibleColumns = ['serial','certificate_no','student','batch','issue_date','status','design','qr','action'];
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Certificates</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Training Certificates</h4>
        <p class="page-header-desc">{{ isset($certificates) ? $certificates->total() : 0 }} issued certificates — Generate via Results → Certificates popup</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        @if(($batches ?? collect())->isNotEmpty())
        <form method="GET" action="{{ route('training.certificates.index') }}" class="d-flex gap-2">
            <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($batches as $b)
                    <option value="{{ $b->id }}" @selected($selectedBatchId==$b->id)>{{ $b->name }} ({{ $b->batch_code }})</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>
</div>

{{-- Hidden generate block for popup mode only --}}
@if(request()->query('popup'))
@if(isset($certTrainees) && $certTrainees->isNotEmpty())
<div class="admin-card mb-4">
    <h6 class="mb-3"><i class="bi bi-patch-check me-1"></i> Generate Certificates — Batch: {{ $batches->firstWhere('id',$selectedBatchId)?->name ?? '' }}</h6>
    <form method="POST" action="{{ route('training.certificates.generate') }}" id="trainingCertForm">
        @csrf
        <input type="hidden" name="batch_id" value="{{ $selectedBatchId }}">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Certificate Design</label>
                <div class="btn-group" role="group" aria-label="Certificate design">
                    @for($t = 1; $t <= 3; $t++)
                        <input type="radio" class="btn-check" name="template_id" id="template{{ $t }}" value="{{ $t }}" {{ $t == 1 ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-outline-secondary btn-sm" for="template{{ $t }}">Design {{ $t }}</label>
                    @endfor
                </div>
                <small class="text-muted d-block mt-1">Choose design for generated certificates.</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th><input type="checkbox" id="certCheckAll"></th><th>Trainee</th><th>Attendance</th><th>Exam</th><th>Eligible</th></tr></thead>
                <tbody>
                @foreach($certTrainees as $ct)
                    <tr class="{{ $ct->eligible ? '' : 'opacity-50' }}">
                        <td><input type="checkbox" class="form-check-input cert-check" name="trainee_ids[]" value="{{ $ct->trainee_id }}" {{ $ct->eligible ? 'checked' : '' }} {{ !$ct->eligible ? 'disabled' : '' }}></td>
                        <td class="fw-semibold">{{ trim(($ct->trainee->first_name ?? '').' '.($ct->trainee->last_name ?? '')) ?: 'Trainee #'.$ct->trainee_id }} <div class="small text-muted">{{ $ct->trainee->email ?? '' }}</div></td>
                        <td><span class="badge {{ $ct->attendance >= $threshold ? 'text-bg-success' : 'text-bg-warning' }}">{{ $ct->attendance }}%</span> {{ $ct->attendance >= $threshold ? '✓' : '✗ < ' . $threshold . '%' }}</td>
                        <td><span class="badge {{ $ct->exam_status=='pass' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($ct->exam_status) }}</span></td>
                        <td>{!! $ct->eligible ? '<span class="badge text-bg-primary">Eligible</span>' : '<span class="badge text-bg-secondary">Not eligible</span>' !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3 d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-primary btn-sm" id="certGenerateSelected">Generate Selected</button>
            <button type="submit" class="btn btn-primary btn-sm">Generate All Eligible</button>
        </div>
    </form>
</div>
@else
<div class="admin-card mb-4 text-center text-muted py-3 small">No trainees in selected batch or no eligible batches.</div>
@endif
<script>
document.getElementById('certCheckAll')?.addEventListener('change', function(){ document.querySelectorAll('.cert-check:not(:disabled)').forEach(c=>c.checked=this.checked); });
document.getElementById('certGenerateSelected')?.addEventListener('click', function(){ document.getElementById('trainingCertForm').submit(); });
</script>
@endif

<div class="filter-card">
    <form class="filter-layout" method="GET" action="{{ route('training.certificates.index') }}">
        <input type="hidden" name="batch_id" value="{{ $selectedBatchId ?? '' }}">
        <div class="filter-search-row align-items-end">
            <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by certificate no or student..." value="{{ request('q') }}">
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Batch</label>
                <select class="form-select form-select-sm" name="batch_id" onchange="this.form.submit()">
                    <option value="">All Batches</option>
                    @foreach($batches as $b)
                        <option value="{{ $b->id }}" @selected(($selectedBatchId ?? null)==$b->id)>{{ $b->name }} ({{ $b->batch_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:130px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach(['active','revoked','pending'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('training.certificates.index') }}" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card">

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-primary badge-soft">{{ isset($certificates) ? $certificates->total() : 0 }} Certificates</span>
            <span class="text-muted ms-2 d-none d-lg-inline">Issued certificates for this workspace.</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> Columns <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu" id="colToggleMenu">
                    <li><h6 class="dropdown-header">Show / hide columns</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach([
                        'serial' => '#',
                        'certificate_no' => 'Certificate No',
                        'student' => 'Student',
                        'batch' => 'Batch',
                        'issue_date' => 'Issue Date',
                        'status' => 'Status',
                        'design' => 'Design',
                        'qr' => 'QR',
                        'action' => 'Action',
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
        <table class="table align-middle mb-0" id="trainingCertificatesTable">
            <thead>
                <tr>
                    <th class="col-handle" style="width:42px"></th>
                    <th class="col-check"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th data-col="serial" class="text-muted">#</th>
                    <th data-col="certificate_no">Certificate No</th>
                    <th data-col="student">Student</th>
                    <th data-col="batch">Batch</th>
                    <th data-col="issue_date">Issue Date</th>
                    <th data-col="status">Status</th>
                    <th data-col="design">Design</th>
                    <th data-col="qr">QR</th>
                    <th data-col="action" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($certificates ?? [] as $cert)
                <tr>
                    <td class="col-handle text-center"><i class="bi bi-grip-vertical drag-handle" draggable="true" title="Drag to reorder"></i></td>
                    <td class="col-check"><input type="checkbox" class="form-check-input row-check" data-name="{{ $cert->certificate_number ?? $cert->student?->full_name }}"></td>
                    <td data-col="serial" class="text-muted">{{ ($certificates->firstItem() ?? 1) + $loop->index }}</td>
                    <td data-col="certificate_no" class="fw-semibold">{{ $cert->certificate_number ?? '—' }}</td>
                    <td data-col="student">
                        <div class="fw-semibold">{{ $cert->student?->full_name ?? $cert->student->first_name ?? '—' }}</div>
                        @if($cert->student?->email)<div class="text-muted small">{{ $cert->student->email }}</div>@endif
                    </td>
                    <td data-col="batch">{{ $cert->batch?->name ?? '—' }}</td>
                    <td data-col="issue_date">{{ $cert->issue_date ? \Illuminate\Support\Carbon::parse($cert->issue_date)->format('d M Y') : '—' }}</td>
                    <td data-col="status"><span class="badge {{ $statusBadge[$cert->status] ?? 'text-bg-secondary' }}">{{ $cert->status }}</span></td>
                    <td data-col="design">Design {{ $cert->template_id ?? 1 }}</td>
                    <td data-col="qr" class="text-center">
                        @if($cert->certificate_number)
                            <a href="{{ route('training.certificates.qr', $cert->id) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Download QR (SVG)" download><i class="bi bi-qr-code"></i></a>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td data-col="action" class="text-end text-nowrap col-action">
                        <a href="{{ route('training.certificates.show', $cert->id) }}" class="btn btn-sm btn-outline-primary btn-icon" title="View certificate"><i class="bi bi-file-earmark-text"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-icon edit-cert-btn" data-id="{{ $cert->id }}" data-issue-date="{{ $cert->issue_date?->format('Y-m-d') }}" data-certificate-number="{{ $cert->certificate_number }}" data-student-name="{{ $cert->student?->full_name ?? '' }}" data-father-name="{{ $cert->student?->father_name ?? '' }}" data-nid="{{ $cert->student?->nid_number ?? '' }}" data-passport="{{ $cert->student?->passport_number ?? '' }}" data-course-name="{{ $cert->course?->name ?? '' }}" title="Edit certificate"><i class="bi bi-pencil"></i></button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="20" class="text-center text-muted py-4">No certificates issued yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        @if(isset($certificates) && method_exists($certificates,'links'))
            {{ $certificates->withQueryString()->links('pagination::bootstrap-5') }}
        @endif
        <span class="text-muted small">{{ isset($certificates) ? $certificates->total() : 0 }} certificates</span>
    </div>
</div>

<div class="print-only">
    <table class="table align-middle mb-0" id="trainingCertificatesTablePrint">
        <thead>
            <tr>
                <th data-col="serial">#</th>
                <th data-col="certificate_no">Certificate No</th>
                <th data-col="student">Student</th>
                <th data-col="batch">Batch</th>
                <th data-col="issue_date">Issue Date</th>
                <th data-col="status">Status</th>
                <th data-col="design">Design</th>
            </tr>
        </thead>
        <tbody>
            @forelse($certificates ?? [] as $cert)
                <tr>
                    <td data-col="serial">{{ $loop->iteration }}</td>
                    <td data-col="certificate_no">{{ $cert->certificate_number ?? '—' }}</td>
                    <td data-col="student">{{ $cert->student?->full_name ?? $cert->student->first_name ?? '—' }}</td>
                    <td data-col="batch">{{ $cert->batch?->name ?? '—' }}</td>
                    <td data-col="issue_date">{{ $cert->issue_date ? \Illuminate\Support\Carbon::parse($cert->issue_date)->format('d M Y') : '—' }}</td>
                    <td data-col="status">{{ ucfirst($cert->status) }}</td>
                    <td data-col="design">Design {{ $cert->template_id ?? 1 }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No certificates issued yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Edit Certificate Modal (all info) -->
<div class="modal fade" id="editCertificateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="editCertificateForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Certificate — <span id="editCertNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="edit_student_name">Student Name *</label>
                        <input type="text" name="full_name" id="edit_student_name" class="form-control" required maxlength="120" placeholder="e.g. NAEIM SHAIKDAR">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit_father_name">Father Name</label>
                        <input type="text" name="father_name" id="edit_father_name" class="form-control" maxlength="120" placeholder="e.g. KAWSER SHIKDER">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit_nid">NID No</label>
                        <input type="text" name="nid_number" id="edit_nid" class="form-control" maxlength="30" placeholder="e.g. 9151598134">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit_passport">Passport No</label>
                        <input type="text" name="passport_number" id="edit_passport" class="form-control" maxlength="40" placeholder="e.g. A13072167">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit_course_name">Course Name</label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-control" maxlength="150" placeholder="e.g. Welding and Fabrication">
                        <div class="form-text">Updates the linked course name.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit_issue_date">Issue Date *</label>
                        <input type="date" name="issue_date" id="edit_issue_date" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function(){
    var selectAll = document.getElementById('selectAll');
    if(selectAll){
        selectAll.addEventListener('change', function(){
            document.querySelectorAll('.row-check').forEach(function(cb){ cb.checked = selectAll.checked; });
        });
    }
    var tableBody = document.getElementById('trainingCertificatesTable');
    if(tableBody) tableBody = tableBody.querySelector('tbody');
    if(tableBody){
        var draggedRow=null;
        function reorderAndAnimate(target, after){
            var rows = Array.prototype.slice.call(tableBody.children);
            var prev = new Map();
            rows.forEach(function(tr){ prev.set(tr, tr.getBoundingClientRect().top); });
            var moved=false;
            if(after && target.nextElementSibling!==draggedRow){ tableBody.insertBefore(draggedRow, target.nextElementSibling); moved=true; }
            else if(!after && target.previousElementSibling!==draggedRow){ tableBody.insertBefore(draggedRow, target); moved=true; }
            if(!moved) return;
            var afterRows = Array.prototype.slice.call(tableBody.children);
            afterRows.forEach(function(tr){
                var delta = prev.get(tr)-tr.getBoundingClientRect().top;
                if(delta){ tr.style.transition='none'; tr.style.transform='translateY('+delta+'px)'; }
            });
            requestAnimationFrame(function(){ requestAnimationFrame(function(){
                afterRows.forEach(function(tr){ tr.style.transition='transform .3s cubic-bezier(.2,.85,.35,1)'; tr.style.transform=''; });
            });});
        }
        tableBody.addEventListener('dragstart', function(e){
            var handle=e.target.closest('.drag-handle');
            if(!handle){ e.preventDefault(); return; }
            draggedRow=handle.closest('tr');
            draggedRow.classList.add('dragging');
            e.dataTransfer.effectAllowed='move';
            e.dataTransfer.setData('text/plain','row');
        });
        tableBody.addEventListener('dragover', function(e){
            if(!draggedRow) return;
            e.preventDefault(); e.dataTransfer.dropEffect='move';
            var target=e.target.closest('tr');
            if(!target||target===draggedRow) return;
            var rect=target.getBoundingClientRect();
            reorderAndAnimate(target, (e.clientY-rect.top)>(rect.height/2));
        });
        tableBody.addEventListener('dragend', function(){
            draggedRow=null;
            tableBody.querySelectorAll('.dragging,.drag-over').forEach(function(el){ el.classList.remove('dragging','drag-over'); el.style.transition=''; el.style.transform=''; });
        });
    }
    var table=document.getElementById('trainingCertificatesTable');
    var colChecks=document.querySelectorAll('.col-toggle-check');
    if(table && colChecks.length){
        colChecks.forEach(function(check){
            check.addEventListener('change', function(){
                var col=check.getAttribute('data-col');
                var th=table.querySelector('th[data-col="'+col+'"]');
                if(!th) return;
                var index=Array.prototype.indexOf.call(th.parentNode.children, th);
                var hidden=!check.checked;
                th.style.display=hidden?'none':'';
                table.querySelectorAll('tbody tr').forEach(function(tr){
                    var td=tr.children[index];
                    if(td) td.style.display=hidden?'none':'';
                });
                var printTable=document.getElementById('trainingCertificatesTablePrint');
                if(printTable){
                    printTable.querySelectorAll('[data-col="'+col+'"]').forEach(function(el){ el.style.display=hidden?'none':''; });
                }
            });
        });
    }
    function exportTable(fileName){
        var tbl=document.getElementById('trainingCertificatesTable');
        if(!tbl) return;
        var out=[]; var headers=[];
        tbl.querySelectorAll('thead th').forEach(function(th,i){ if(i>1) headers.push(th.textContent.trim()); });
        out.push(headers.join(','));
        tbl.querySelectorAll('tbody tr').forEach(function(tr){
            var cells=tr.querySelectorAll('td');
            if(!cells.length) return;
            var row=[];
            for(var i=2;i<cells.length;i++){ row.push('"'+cells[i].textContent.trim().replace(/"/g,'""')+'"'); }
            out.push(row.join(','));
        });
        var blob=new Blob(['\ufeff'+out.join('\r\n')],{type:'text/csv;charset=utf-8;'});
        var link=document.createElement('a');
        link.href=URL.createObjectURL(blob); link.download=fileName;
        document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }
    var csvBtn=document.getElementById('exportCsvBtn');
    if(csvBtn) csvBtn.addEventListener('click', function(){ exportTable('training-certificates.csv'); });
    var excelBtn=document.getElementById('exportExcelBtn');
    if(excelBtn) excelBtn.addEventListener('click', function(){ exportTable('training-certificates.xls'); });
})();
</script>
<script>
(function(){
    var modalEl = document.getElementById('editCertificateModal');
    if(!modalEl) return;
    var form = document.getElementById('editCertificateForm');
    var dateInput = document.getElementById('edit_issue_date');
    var nameInput = document.getElementById('edit_student_name');
    var fatherInput = document.getElementById('edit_father_name');
    var nidInput = document.getElementById('edit_nid');
    var passportInput = document.getElementById('edit_passport');
    var courseInput = document.getElementById('edit_course_name');
    var certNumSpan = document.getElementById('editCertNumber');
    var urlTemplate = "{{ route('training.certificates.update', ['certificate' => '__ID__']) }}";
    var bsModal = null;
    try { bsModal = new bootstrap.Modal(modalEl); } catch(e) {}
    document.querySelectorAll('.edit-cert-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-id');
            var date = btn.getAttribute('data-issue-date');
            var num = btn.getAttribute('data-certificate-number');
            certNumSpan.textContent = num || '#' + id;
            dateInput.value = date || new Date().toISOString().slice(0,10);
            if(nameInput) nameInput.value = btn.getAttribute('data-student-name') || '';
            if(fatherInput) fatherInput.value = btn.getAttribute('data-father-name') || '';
            if(nidInput) nidInput.value = btn.getAttribute('data-nid') || '';
            if(passportInput) passportInput.value = btn.getAttribute('data-passport') || '';
            if(courseInput) courseInput.value = btn.getAttribute('data-course-name') || '';
            form.action = urlTemplate.replace('__ID__', id);
            if(bsModal) bsModal.show(); else { var m = bootstrap.Modal.getOrCreateInstance(modalEl); m.show(); }
        });
    });
})();
</script>
@endpush
