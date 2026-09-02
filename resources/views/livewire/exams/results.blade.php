@php
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y, h:i A') : '—';
@endphp

<div class="admin-card" data-ajax-table>

    <div class="filter-card">
        <div class="filter-layout">
            <div class="filter-search-row align-items-end">
                <div class="filter-search" style="flex:1 1 0; min-width:180px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.350ms="search"
                           placeholder="{{ mawa_e('exams.results_search_placeholder') }}">
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('exams.select_batch') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.result_batch_id">
                        <option value="">{{ mawa_e('exams.all_batches') }}</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch->id }}">{{ $batch->name }} ({{ $batch->batch_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('exams.result_status') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.result_status">
                        <option value="">{{ mawa_e('exams.all_result_status') }}</option>
                        @foreach ($resultStatusNames as $slug => $label)
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
            <span class="badge text-bg-success badge-soft">{{ $results->total() }} {{ mawa_e('exams.results_heading') }}</span>
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
                        'serial'       => '#',
                        'student'      => mawa_e('exams.table_student'),
                        'student_id'   => mawa_e('exams.table_student_id'),
                        'batch'        => mawa_e('batches.table_name'),
                        'total_marks'  => mawa_e('exams.table_total_marks'),
                        'obtained'     => mawa_e('exams.table_obtained'),
                        'percentage'   => mawa_e('exams.table_percentage'),
                        'grade'        => mawa_e('exams.table_grade'),
                        'status'       => mawa_e('exams.result_status'),
                        'published_at' => mawa_e('exams.table_published_at'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item" for="result-col-{{ $col }}">
                                <input type="checkbox" id="result-col-{{ $col }}" class="form-check-input me-2"
                                       wire:click="toggleColumn('{{ $col }}')"
                                       @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    @if (in_array('serial', $visibleColumns, true))<th class="text-muted">#</th>@endif
                    @if (in_array('student', $visibleColumns, true))<th>{{ mawa_e('exams.table_student') }}</th>@endif
                    @if (in_array('student_id', $visibleColumns, true))<th>{{ mawa_e('exams.table_student_id') }}</th>@endif
                    @if (in_array('batch', $visibleColumns, true))<th>{{ mawa_e('batches.table_name') }}</th>@endif
                    @if (in_array('total_marks', $visibleColumns, true))<th>{{ mawa_e('exams.table_total_marks') }}</th>@endif
                    @if (in_array('obtained', $visibleColumns, true))<th>{{ mawa_e('exams.table_obtained') }}</th>@endif
                    @if (in_array('percentage', $visibleColumns, true))<th>{{ mawa_e('exams.table_percentage') }}</th>@endif
                    @if (in_array('grade', $visibleColumns, true))<th>{{ mawa_e('exams.table_grade') }}</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>{{ mawa_e('exams.result_status') }}</th>@endif
                    @if (in_array('published_at', $visibleColumns, true))<th>{{ mawa_e('exams.table_published_at') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($results as $result)
                    <tr>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $results->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('student', $visibleColumns, true))<td>
                            <span class="fw-semibold">{{ $result->student?->first_name }} {{ $result->student?->last_name }}</span>
                        </td>@endif
                        @if (in_array('student_id', $visibleColumns, true))<td>{{ $result->student?->student_id ?? '—' }}</td>@endif
                        @if (in_array('batch', $visibleColumns, true))<td>
                            {{ $result->batch?->name ?? '—' }}
                            @if ($result->batch?->batch_code)
                                <small class="text-muted d-block">{{ $result->batch->batch_code }}</small>
                            @endif
                        </td>@endif
                        @if (in_array('total_marks', $visibleColumns, true))<td>{{ rtrim(rtrim(number_format($result->total_marks, 2), '0'), '.') }}</td>@endif
                        @if (in_array('obtained', $visibleColumns, true))<td>{{ rtrim(rtrim(number_format($result->obtained_marks, 2), '0'), '.') }}</td>@endif
                        @if (in_array('percentage', $visibleColumns, true))<td>{{ number_format($result->percentage, 1) }}%</td>@endif
                        @if (in_array('grade', $visibleColumns, true))<td>{{ $result->grade ?? '—' }}</td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge {{ $resultStatusBadge[$result->result_status] ?? 'bg-secondary' }}">{{ $resultStatusNames[$result->result_status] ?? $result->result_status }}</span>
                        </td>@endif
                        @if (in_array('published_at', $visibleColumns, true))<td>{{ $fmtDate($result->published_at) }}</td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="99" class="text-center text-muted py-4">{{ mawa_e('exams.results_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($results->hasPages())
        <div class="mt-4 d-flex flex-column align-items-center gap-2">
            {{ $results->links('pagination::bootstrap-5') }}
            <span class="text-muted small">{{ $results->total() }} {{ mawa_e('exams.results_heading') }}</span>
        </div>
    @endif

</div>
