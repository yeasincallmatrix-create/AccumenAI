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
                           placeholder="{{ mawa_e('exams.search_placeholder') }}">
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('exams.select_batch') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.batch_id">
                        <option value="">{{ mawa_e('exams.all_batches') }}</option>
                        @foreach ($batches as $batch)
                            <option value="{{ $batch->id }}">{{ $batch->name }} ({{ $batch->batch_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-span" style="flex:1 1 0; min-width:180px;">
                    <label class="form-label mb-1">{{ mawa_e('exams.status') }}</label>
                    <select class="form-select form-select-sm" wire:model.live="filters.status">
                        <option value="">{{ mawa_e('exams.all_status') }}</option>
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
            <span class="badge text-bg-success badge-soft">{{ $exams->total() }} {{ mawa_e('exams.heading') }}</span>
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
                        'serial'   => '#',
                        'title'    => mawa_e('exams.table_title'),
                        'course'   => mawa_e('batches.table_course'),
                        'batch'    => mawa_e('batches.table_name'),
                        'subjects' => mawa_e('exams.subjects'),
                        'date'     => mawa_e('exams.table_date'),
                        'marks'    => mawa_e('exams.table_marks'),
                        'students' => mawa_e('exams.table_students'),
                        'pass'     => mawa_lang('status.pass'),
                        'fail'     => mawa_lang('status.fail'),
                        'status'   => mawa_e('batches.table_status'),
                        'action'   => mawa_e('actions.actions'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item" for="exam-col-{{ $col }}">
                                <input type="checkbox" id="exam-col-{{ $col }}" class="form-check-input me-2"
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
                    @if (in_array('title', $visibleColumns, true))<th>{{ mawa_e('exams.table_title') }}</th>@endif
                    @if (in_array('course', $visibleColumns, true))<th>{{ mawa_e('batches.table_course') }}</th>@endif
                    @if (in_array('batch', $visibleColumns, true))<th>{{ mawa_e('batches.table_name') }}</th>@endif
                    @if (in_array('subjects', $visibleColumns, true))<th>{{ mawa_e('exams.subjects') }}</th>@endif
                    @if (in_array('date', $visibleColumns, true))<th>{{ mawa_e('exams.table_date') }}</th>@endif
                    @if (in_array('marks', $visibleColumns, true))<th>{{ mawa_e('exams.table_marks') }}</th>@endif
                    @if (in_array('students', $visibleColumns, true))<th>{{ mawa_e('exams.table_students') }}</th>@endif
                    @if (in_array('pass', $visibleColumns, true))<th class="text-success">{{ mawa_lang('status.pass') }}</th>@endif
                    @if (in_array('fail', $visibleColumns, true))<th class="text-danger">{{ mawa_lang('status.fail') }}</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>{{ mawa_e('batches.table_status') }}</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end">{{ mawa_e('actions.actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($exams as $exam)
                    <tr>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $exams->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('title', $visibleColumns, true))<td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('exams.show', $exam) }}">{{ $exam->title }}</a>
                        </td>@endif
                        @if (in_array('course', $visibleColumns, true))<td>{{ $exam->course?->name ?? '—' }}</td>@endif
                        @if (in_array('batch', $visibleColumns, true))<td>
                            {{ $exam->batch?->name ?? '—' }}
                            @if ($exam->batch?->batch_code)
                                <small class="text-muted d-block">{{ $exam->batch->batch_code }}</small>
                            @endif
                        </td>@endif
                        @if (in_array('subjects', $visibleColumns, true))<td>{{ $exam->subjects->pluck('subject.name')->filter()->implode(', ') ?: '—' }}</td>@endif
                        @if (in_array('date', $visibleColumns, true))<td>{{ $fmtDate($exam->exam_date) }}</td>@endif
                        @if (in_array('marks', $visibleColumns, true))<td>{{ rtrim(rtrim(number_format($exam->full_marks, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($exam->pass_marks, 2), '0'), '.') }}</td>@endif
                        @if (in_array('students', $visibleColumns, true))<td>{{ $exam->students_count ?? $exam->results_count }}</td>@endif
                        @if (in_array('pass', $visibleColumns, true))<td class="text-success fw-semibold">{{ $exam->pass_count ?? 0 }}</td>@endif
                        @if (in_array('fail', $visibleColumns, true))<td class="text-danger fw-semibold">{{ $exam->fail_count ?? 0 }}</td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge {{ $statusBadge[$exam->status] ?? 'bg-secondary' }}">{{ $statusNames[$exam->status] ?? $exam->status }}</span>
                        </td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end d-flex gap-1 justify-content-end">
                            @php $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
                            @if($isProfessional)
                                <a href="{{ route('training.marks.index', ['exam_id' => $exam->id]) }}" class="btn btn-sm btn-primary" title="Enter Marks"><i class="bi bi-pencil-square me-1"></i> Marks</a>
                            @else
                                <a href="{{ route('exams.show', $exam) }}" class="btn btn-sm btn-primary" title="Enter Marks"><i class="bi bi-pencil-square me-1"></i> Marks</a>
                            @endif
                            <a href="{{ route('exams.show', $exam) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            @php $hasResults = ($exam->results_count ?? 0) > 0; @endphp
                            @if(!$hasResults)
                                <form action="{{ route('exams.destroy', $exam) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this exam? This action cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete Exam">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="btn btn-sm btn-secondary" disabled data-bs-toggle="tooltip" title="Cannot delete exam because it has student results.">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="99" class="text-center text-muted py-4">{{ mawa_e('exams.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($exams->hasPages())
        <div class="mt-4 d-flex flex-column align-items-center gap-2">
            {{ $exams->links('pagination::bootstrap-5') }}
            <span class="text-muted small">{{ $exams->total() }} {{ mawa_e('exams.heading') }}</span>
        </div>
    @endif

</div>
