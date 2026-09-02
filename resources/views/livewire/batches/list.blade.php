@php
    $statusBadge = [
        'upcoming'  => 'bg-secondary',
        'running'   => 'bg-success',
        'completed' => 'bg-primary',
        'cancelled' => 'bg-danger',
        'archived'  => 'bg-dark',
    ];
    $statusNames = [
        'upcoming'  => mawa_lang('status.upcoming'),
        'running'   => mawa_lang('status.running'),
        'completed' => mawa_lang('status.completed'),
        'cancelled' => mawa_lang('status.cancelled'),
        'archived'  => mawa_lang('status.archived'),
    ];
    $shiftNames = [
        'morning' => mawa_lang('options.shift_morning'),
        'day'     => mawa_lang('options.shift_day'),
        'evening' => mawa_lang('options.shift_evening'),
        'weekend' => mawa_lang('options.shift_weekend'),
        'online'  => mawa_lang('options.shift_online'),
    ];
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
@endphp

<div class="admin-card" data-ajax-table>

    <div class="filter-card">
        <div class="filter-layout">
            <div class="filter-search-row align-items-end">
                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.350ms="search"
                           placeholder="{{ mawa_e('batches.search_placeholder') }}">
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('batches.branch') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.branch_id">
                        <option value="">{{ mawa_e('students.all_branches') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('batches.table_course') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.course_id">
                        <option value="">{{ mawa_e('batches.all_courses') }}</option>
                        @foreach ($courses as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @php $isProfessionalBatchList = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
                @if(!$isProfessionalBatchList)
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('batches.academic_year') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.academic_year_id">
                        <option value="">{{ mawa_e('batches.all_years') }}</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('batches.instructor') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.instructor_id">
                        <option value="">{{ mawa_e('batches.all_instructors') }}</option>
                        @foreach ($instructors as $instructor)
                            <option value="{{ $instructor->id }}">{{ $instructor->first_name }} {{ $instructor->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('batches.status') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.status">
                        <option value="">{{ mawa_e('batches.all_status') }}</option>
                        @foreach ($statusNames as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-outline-secondary btn-sm" wire:click="resetFilters" title="{{ mawa_e('actions.reset') }}"><i class="bi bi-arrow-counterclockwise"></i> {{ mawa_e('actions.reset') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="table-toolbar">
        <div class="toolbar-info">
            <span class="badge text-bg-success badge-soft">{{ $batches->total() }} Batches</span>
        </div>
        <div class="toolbar-actions">
            <div class="dropdown">
                <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns"></i> {{ mawa_e('actions.columns') }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">{{ mawa_e('actions.show_hide_columns') }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial' => '#',
                        'code'   => mawa_e('batches.table_code'),
                        'name'   => mawa_e('batches.table_name'),
                        'course' => mawa_e('batches.table_course'),
                        'shift'  => mawa_e('batches.table_shift'),
                        'start'  => mawa_e('batches.table_start'),
                        'seats'  => mawa_e('batches.table_seats'),
                        'status' => mawa_e('batches.table_status'),
                        'action' => mawa_e('actions.actions'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item" for="batch-col-{{ $col }}">
                                <input type="checkbox" id="batch-col-{{ $col }}" class="form-check-input me-2"
                                       wire:click="toggleColumn('{{ $col }}')"
                                       @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-success" onclick="window.print()"><i class="bi bi-printer"></i> {{ mawa_e('actions.print') }}</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:32px"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    @if (in_array('serial', $visibleColumns, true))<th class="text-muted">#</th>@endif
                    @if (in_array('code', $visibleColumns, true))<th>{{ mawa_e('batches.table_code') }}</th>@endif
                    @if (in_array('name', $visibleColumns, true))<th>{{ mawa_e('batches.table_name') }}</th>@endif
                    @if (in_array('course', $visibleColumns, true))<th>{{ mawa_e('batches.table_course') }}</th>@endif
                    @if (in_array('shift', $visibleColumns, true))<th>{{ mawa_e('batches.table_shift') }}</th>@endif
                    @if (in_array('start', $visibleColumns, true))<th>{{ mawa_e('batches.table_start') }}</th>@endif
                    @if (in_array('seats', $visibleColumns, true))<th>{{ mawa_e('batches.table_seats') }}</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>{{ mawa_e('batches.table_status') }}</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end">{{ mawa_e('actions.actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr class="batch-row" data-href="{{ route('batches.show', $batch) }}">
                        <td class="text-center"><input type="checkbox" class="form-check-input row-check"></td>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $batches->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('code', $visibleColumns, true))<td>
                            <a class="text-decoration-none" href="{{ route('batches.show', $batch) }}"><span class="badge bg-dark bg-opacity-75">{{ $batch->batch_code }}</span></a>
                        </td>@endif
                        @if (in_array('name', $visibleColumns, true))<td class="fw-semibold">
                            <a class="fw-semibold text-decoration-none" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>
                            @if ($batch->academicYear)
                                <small class="text-muted d-block">{{ $batch->academicYear->name }}</small>
                            @endif
                        </td>@endif
                        @if (in_array('course', $visibleColumns, true))<td>{{ $batch->course?->name ?? '—' }}</td>@endif
                        @if (in_array('shift', $visibleColumns, true))<td>{{ $shiftNames[$batch->shift] ?? $batch->shift }}</td>@endif
                        @if (in_array('start', $visibleColumns, true))<td>{{ $fmtDate($batch->start_date) }}</td>@endif
                        @if (in_array('seats', $visibleColumns, true))<td>
                            {{ $batch->seat_filled }} / {{ $batch->seat_capacity }}
                            <small class="text-muted d-block">{{ mawa_e('batches.filled') }}</small>
                        </td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }}">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
                        </td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end text-nowrap">
                            @if ($user->hasPermission('batches.manage'))
                                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-batch="{{ $batch->id }}" title="{{ mawa_e('actions.edit') }}" style="min-height:32px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($batch->attended_exams === 0)
                                    <form class="d-inline" method="POST" action="{{ route('batches.destroy', $batch) }}"
                                          data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.confirm_delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ mawa_e('actions.delete') }}" style="min-height:32px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="99" class="text-center text-muted py-4">{{ mawa_e('batches.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 d-flex flex-column align-items-center gap-2">
        {{ $batches->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $batches->total() }} batches</span>
    </div>

</div>

<style>
@media print {
    .topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
    .page-header-desc, .pagination, .d-none, .table-responsive { display: none !important; }
    .content { margin-left: 0 !important; padding: 0 !important; }
    .admin-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
}
</style>

<script>
(function () {
    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', 'tr.batch-row', function (e, row) {
            if (e.target.closest('a, button, form, input, select, textarea')) { return; }
            var href = row.getAttribute('data-href');
            if (!href) { return; }
            e.preventDefault();
            if (window.Monetix && Monetix.loadPage) {
                Monetix.loadPage(href, { preserveFocus: false });
            } else {
                window.location.href = href;
            }
        }, 'mtx-batch-row-nav');
    }
})();
</script>
