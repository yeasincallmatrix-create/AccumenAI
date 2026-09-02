@extends('layouts.institute')

@section('title', mawa_lang('classes.title') . ' — AccumenAI')

@php
    $statusBadge = [
        'active'   => 'text-bg-success',
        'inactive' => 'text-bg-secondary',
        'draft'    => 'text-bg-warning',
    ];
    $statusNames = [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'draft'    => 'Draft',
    ];
    $modeLookup = [];
    foreach ($filterModes ?? [] as $modeItem) {
        $modeLookup[$modeItem] = ucfirst($modeItem);
    }
@endphp

@section('content')
@push('styles')
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
@endpush
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('classes.title') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('classes.desc') }} — {{ $institute->name ?? '' }}</p>
    </div>
</div>

<div class="admin-card mb-3">
    @include('classes._tabs', [
        'activeTab' => 'classes',
        'classesCount' => $classesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="admin-card" data-ajax-table>

    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — {{ mawa_e('classes.tab_classes') }}</h4>
        <p class="mb-0 text-muted">{{ $classesCount }} classes · {{ now()->format('d M Y') }}</p>
    </div>

    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end monetix-print-hidden" method="GET" action="{{ route('classes.index') }}" data-ajax-filter>
        <div style="flex:1 1 280px;min-width:220px">
            <input type="text" name="q" class="form-control" value="{{ $q }}"
                   placeholder="{{ mawa_e('classes.search_placeholder') }}">
        </div>
        <div style="width:200px">
            <select name="branch_id" class="form-select">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ mawa_e('actions.search') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('classes.index') }}" title="{{ mawa_e('actions.reset') }}">
            <i class="bi bi-arrow-counterclockwise"></i>
        </a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <div class="dropdown">
                <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i>{{ mawa_e('actions.columns') }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end col-toggle-menu">
                    <li><h6 class="dropdown-header">{{ mawa_e('actions.show_hide_columns') }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial'   => '#',
                        'code'     => mawa_e('classes.table_code'),
                        'class'    => mawa_e('classes.table_class'),
                        'category' => mawa_e('classes.table_category'),
                        'mode'     => mawa_e('classes.table_mode'),
                        'fee'      => mawa_e('classes.table_fee'),
                        'subjects' => mawa_e('classes.table_subjects'),
                        'batches'  => mawa_e('classes.table_batches'),
                        'status'   => mawa_e('classes.table_status'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="class-col-{{ $col }}">
                                <input type="checkbox" id="class-col-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2" onclick="window.print()" title="{{ mawa_e('actions.print') }}">
                <i class="bi bi-printer"></i>{{ mawa_e('actions.print') }}
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>#</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_code') }}</th>
                    <th data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_class') }}</th>
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_category') }}</th>
                    <th data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_mode') }}</th>
                    <th data-col="fee" @if(!in_array('fee', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_fee') }}</th>
                    <th data-col="subjects" @if(!in_array('subjects', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_subjects') }}</th>
                    <th data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_batches') }}</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classes as $item)
                    @php $course = $item->course; @endphp
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $classes->firstItem() + $loop->index }}</td>
                        <td data-col="code" class="text-muted" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $course->course_code ?? '—' }}</td>
                        <td data-col="class" @if(!in_array('class', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $course->name ?? '—' }}</div>
                            @if ($course->course_code)
                                <div class="text-muted small">{{ $course->course_code }}</div>
                            @endif
                        </td>
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $course->category->name ?? '—' }}</td>
                        <td data-col="mode" @if(!in_array('mode', $visibleColumns, true)) style="display:none" @endif>{{ ucfirst($course->mode ?? '—') }}</td>
                        <td data-col="fee" @if(!in_array('fee', $visibleColumns, true)) style="display:none" @endif>{{ mawa_currency_symbol($institute->country ?? null) }} {{ number_format($course->fee ?? 0, 0) }}</td>
                        <td data-col="subjects" @if(!in_array('subjects', $visibleColumns, true)) style="display:none" @endif>{{ $course->subjects?->count() ?? 0 }}</td>
                        <td data-col="batches" @if(!in_array('batches', $visibleColumns, true)) style="display:none" @endif>{{ $course->batches->count() }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $statusBadge[$course->status] ?? 'text-bg-secondary' }}">{{ $statusNames[$course->status] ?? $course->status ?? '—' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">{{ mawa_e('classes.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2 monetix-print-hidden" data-ajax-pagination>
        {{ $classes->links('pagination::bootstrap-5') }}
        @if ($classes->total() > 0)
            <span class="text-muted small">
                Showing {{ $classes->firstItem() ?? 0 }}–{{ $classes->lastItem() ?? 0 }} of {{ $classes->total() }} classes
            </span>
        @endif
    </nav>

</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.querySelector('.table-responsive .table');
    var checks = document.querySelectorAll('.col-toggle-check');
    if (!table || !checks.length) { return; }
    checks.forEach(function (check) {
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
            checks.forEach(function (c) { if (c.checked) { visible.push(c.getAttribute('data-col')); } });
            fetch('{{ route('ui.columns.save') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ key: 'classes', columns: visible })
            });
        });
    });
})();
</script>
@endpush