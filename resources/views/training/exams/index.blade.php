@extends('layouts.institute')

@section('title', 'Training Exams — AccumenAI')

@php
    $activeTab ??= 'exams';
    $statusBadge = [
        'scheduled' => 'bg-secondary',
        'ongoing'   => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    $statusNames = [
        'scheduled' => mawa_lang('exams.schedule'),
        'ongoing'   => mawa_lang('exams.ongoing'),
        'completed' => mawa_lang('exams.completed'),
        'cancelled' => mawa_lang('exams.cancelled'),
    ];
    $resultStatusBadge = [
        'pass'    => 'bg-success',
        'fail'    => 'bg-danger',
        'pending' => 'bg-secondary',
    ];
    $resultStatusNames = [
        'pass'    => mawa_lang('status.pass'),
        'fail'    => mawa_lang('status.fail'),
        'pending' => mawa_lang('results.unpublished'),
    ];
    $fmtDateTime = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y, h:i A') : '—';
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
@endphp

@push('styles')
<style>
    .print-only { display: none; }
    @media print {
        .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
        .page-header-desc, .pagination, .d-none, .table-responsive, .nav-tabs,
        .btn-primary { display: none !important; }
        .content { margin-left: 0 !important; padding: 0 !important; }
        .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-only { display: block !important; }
        #examsTablePrint, #resultsTablePrint { width: 100%; font-size: 11px; }
</style>
@endpush

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Exams</li>
    </ol>
</nav>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Training Exams</h4>
        <p class="page-header-desc mb-0">Training exams — independent from Education</p>
    </div>
    <div class="d-flex gap-2">
        @if ($user->hasPermission('exams.manage'))
            <button type="button" class="btn btn-primary" data-create-exam>
                <i class="bi bi-plus-circle me-1"></i>{{ mawa_e('exams.create_exam') }}
            </button>
        @endif
    </div>
</div>

<ul class="nav nav-tabs mb-3 mt-3" role="tablist" data-tab-switch>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'exams' ? 'active' : '' }}" href="{{ route('exams.index', ['tab' => 'exams']) }}" role="tab">
            <i class="bi bi-clipboard-check me-1"></i>Exams
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'results' ? 'active' : '' }}" href="{{ route('exams.index', ['tab' => 'results']) }}" role="tab">
            <i class="bi bi-bar-chart me-1"></i>Results
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'certificates' ? 'active' : '' }}" href="{{ route('exams.index', ['tab' => 'certificates']) }}" role="tab">
            <i class="bi bi-patch-check-fill me-1"></i>Certificates
        </a>
    </li>
</ul>

<div class="tab-content">

@if ($activeTab === 'results')

    <div class="tab-pane active" id="tab-results-content" role="tabpanel">
        @livewire('exam-result-list')
    </div>

    <div class="print-only">
        <table class="table align-middle mb-0" id="resultsTablePrint">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ mawa_e('results.student') }}</th>
                    <th>{{ mawa_e('results.student_id') }}</th>
                    <th>{{ mawa_e('results.batch') }}</th>
                    <th>{{ mawa_e('results.total_marks') }}</th>
                    <th>{{ mawa_e('results.obtained') }}</th>
                    <th>{{ mawa_e('results.percentage') }}</th>
                    <th>{{ mawa_e('results.grade') }}</th>
                    <th>{{ mawa_e('results.status') }}</th>
                    <th>{{ mawa_e('results.published_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $result)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $result->student?->full_name ?? $result->student?->first_name.' '.$result->student?->last_name ?? '—' }}</td>
                        <td>{{ $result->student?->student_id ?? '—' }}</td>
                        <td>{{ $result->batch?->name ?? '—' }}</td>
                        <td>{{ rtrim(rtrim(number_format($result->total_marks, 2), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($result->obtained_marks, 2), '0'), '.') }}</td>
                        <td>{{ rtrim(rtrim(number_format($result->percentage, 2), '0'), '.') }}%</td>
                        <td>{{ $result->grade ?? '—' }}</td>
                        <td>{{ $resultStatusNames[$result->result_status] ?? $result->result_status }}</td>
                        <td>{{ $fmtDate($result->published_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@else

    <div class="tab-pane active" id="tab-exams-content" role="tabpanel">
        @livewire('exam-list')
    </div>

    <div class="print-only">
        <table class="table align-middle mb-0" id="examsTablePrint">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ mawa_e('exams.table_title') }}</th>
                    <th>{{ mawa_e('batches.table_course') }}</th>
                    <th>{{ mawa_e('batches.table_name') }}</th>
                    <th>{{ mawa_e('exams.subjects') }}</th>
                    <th>{{ mawa_e('exams.table_date') }}</th>
                    <th>{{ mawa_e('exams.table_marks') }}</th>
                    <th>{{ mawa_e('exams.table_students') }}</th>
                    <th>{{ mawa_e('batches.table_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($exams as $exam)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $exam->title }}</td>
                        <td>{{ $exam->course?->name ?? '—' }}</td>
                        <td>{{ $exam->batch?->name ?? '—' }}</td>
                        <td>{{ $exam->subjects->isNotEmpty() ? $exam->subjects->map(fn ($s) => $s->subject?->name ?? '—')->implode(', ') : '—' }}</td>
                        <td>{{ $fmtDateTime($exam->exam_date) }}</td>
                        <td>{{ rtrim(rtrim(number_format($exam->full_marks, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($exam->pass_marks, 2), '0'), '.') }}</td>
                        <td>{{ $exam->results_count }}</td>
                        <td>{{ $statusNames[$exam->status] ?? $exam->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@endif

@if ($user->hasPermission('exams.manage'))
    @include('exams._send_modal', [
        'sendExamSubjects' => $sendExamSubjects ?? [],
        'sendExamBatches' => $batches ?? [],
    ])
@endif

@endsection

@push('scripts')
<script>
(function () {
    function bindListTable(tableId, printTableId, colKey) {
        var table = document.getElementById(tableId);
        if (!table) { return; }
        var root = table.closest('[data-ajax-table]') || document;

        var selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                table.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
            });
        }

        var tbody = table.querySelector('tbody');
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

        var colChecks = root.querySelectorAll('.col-toggle-check');
        var saveCols = null;
        if (colChecks.length) {
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
                    var printTable = document.getElementById(printTableId);
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
                    body: JSON.stringify({ key: colKey, columns: visible })
                });
            };
        }

        function exportTable(fileName) {
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
        if (csvBtn) { csvBtn.addEventListener('click', function () { exportTable(colKey + '.csv'); }); }
        var excelBtn = document.getElementById('exportExcelBtn');
        if (excelBtn) { excelBtn.addEventListener('click', function () { exportTable(colKey + '.xls'); }); }
    }

    bindListTable('examsTable', 'examsTablePrint', 'exams');
    bindListTable('resultsTable', 'resultsTablePrint', 'exam_results');
})();
</script>
<script>
(function(){
    var loaded = false;
    function extractContent(html){
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var main = doc.querySelector('main.content') || doc.querySelector('.content') || doc.body;
        if(!main) return '<div class="alert alert-warning">No content</div>';
        return main.innerHTML;
    }
    function loadPane(){
        var pane = document.getElementById('tab-certificates');
        if(!pane) return;
        var loader = pane.querySelector('.academic-tab-loader');
        if(!loader) return;
        var url = loader.getAttribute('data-url');
        if(!url) return;
        // Always clear loader content first to prevent content leakage between tab switches
        loader.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
        // Always fetch fresh content (remove loaded guard that caused stale data persistence)
        loaded = false; // Reset flag so fetch always runs
        fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}, credentials:'same-origin'})
            .then(function(r){ if(!r.ok) throw new Error(r.status); return r.text(); })
            .then(function(html){ loader.innerHTML = extractContent(html); })
            .catch(function(e){ loader.innerHTML = '<div class="alert alert-danger small">Failed to load: '+e.message+' <a href="'+url+'" class="alert-link">Open page</a></div>'; loaded = false; });
    }
    var certTab = document.getElementById('tab-certificates-btn');
    if(certTab){
        certTab.addEventListener('shown.bs.tab', function(){
            loadPane();
        });
        certTab.addEventListener('hide.bs.tab', function(){
            // Reset loaded flag so Certificates tab re-fetches on next open
            loaded = false;
        });
    }
})();
</script>
@endpush
