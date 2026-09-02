@extends('layouts.institute')

@section('title', ($course->name ?? 'Course') . ' — AccumenAI')

@php
    $statusBadge = [
        'upcoming'  => 'bg-secondary',
        'running'   => 'bg-success',
        'completed' => 'bg-primary',
        'cancelled' => 'bg-danger',
    ];
    $statusNames = [
        'upcoming'  => mawa_lang('status.upcoming'),
        'running'   => mawa_lang('status.running'),
        'completed' => mawa_lang('status.completed'),
        'cancelled' => mawa_lang('status.cancelled'),
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

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <a href="{{ route('courses.manage.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('courses.tab_courses') }}
        </a>
        <h4 class="page-header-title">
            {{ $course->name ?? '—' }}
            @if ($course->course_code)
                <span class="badge bg-dark bg-opacity-75 ms-1">{{ $course->course_code }}</span>
            @endif
        </h4>
        <p class="page-header-desc mb-0">{{ mawa_e('courses.show_desc') }}</p>
    </div>
    @if ($user->hasPermission('courses.manage'))
        <div class="page-header-actions d-flex gap-2">
            <a href="{{ route('curricula.index', ['course_id' => $course->id]) }}" class="btn btn-outline-primary">
                <i class="bi bi-collection me-1"></i>Curriculum
            </a>
            @if ($course->institute_id && (int) $course->institute_id === (int) ($institute?->id ?? 0))
                <a href="{{ route('courses.manage.edit', $course) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Manage
                </a>
            @endif
            <button type="button" class="btn btn-primary" data-manage-course-subjects
                    data-href="{{ route('courses.subjects.sync', $course) }}">
                <i class="bi bi-journal-plus me-1"></i>{{ mawa_e('courses.add_subjects') }}
            </button>
        </div>
    @endif
</div>

<div class="admin-card" data-ajax-table>

    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end monetix-print-hidden" method="GET" action="{{ route('courses.show', $course) }}" data-ajax-filter>
        <div style="width:200px">
            <select name="branch_id" class="form-select">
                <option value="">All Branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:180px">
            <select name="shift" class="form-select">
                <option value="">All Shifts</option>
                @foreach ($filterShifts as $shiftOption)
                    <option value="{{ $shiftOption }}" @selected((string) $shift === (string) $shiftOption)>{{ $shiftNames[$shiftOption] ?? $shiftOption }}</option>
                @endforeach
            </select>
        </div>
        <div style="width:180px">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                @foreach ($statusNames as $slug => $label)
                    <option value="{{ $slug }}" @selected((string) $status === $slug)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ mawa_e('actions.search') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('courses.show', $course) }}" title="{{ mawa_e('actions.reset') }}">
            <i class="bi bi-arrow-counterclockwise"></i>
        </a>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ mawa_e('batches.table_code') }}</th>
                    <th>{{ mawa_e('batches.table_name') }}</th>
                    <th>{{ mawa_e('batches.table_shift') }}</th>
                    <th>{{ mawa_e('batches.table_start') }}</th>
                    <th>{{ mawa_e('batches.table_seats') }}</th>
                    <th>{{ mawa_e('batches.table_status') }}</th>
                    <th class="text-end">{{ mawa_e('actions.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="text-muted">{{ $batches->firstItem() + $loop->index }}</td>
                        <td>
                            <a class="text-decoration-none" href="{{ route('batches.show', $batch) }}"><span class="badge bg-dark bg-opacity-75">{{ $batch->batch_code }}</span></a>
                        </td>
                        <td class="fw-semibold">
                            <a class="fw-semibold text-decoration-none" href="{{ route('batches.show', $batch) }}">{{ $batch->name }}</a>
                        </td>
                        <td>{{ $shiftNames[$batch->shift] ?? $batch->shift }}</td>
                        <td>{{ $fmtDate($batch->start_date) }}</td>
                        <td>
                            {{ $batch->seat_filled }} / {{ $batch->seat_capacity }}
                            <small class="text-muted d-block">{{ mawa_e('batches.filled') }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge[$batch->status] ?? 'bg-secondary' }}">{{ $statusNames[$batch->status] ?? $batch->status }}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('batches.show', $batch) }}" class="btn btn-sm btn-outline-primary" title="{{ mawa_e('actions.view') }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if ($user->hasPermission('batches.manage') && $batch->attended_exams === 0)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-batch="{{ $batch->id }}" title="{{ mawa_lang('batches.edit') }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif
                                @if ($user->hasPermission('batches.manage') && $batch->attended_exams === 0)
                                    <form class="d-inline" method="POST" action="{{ route('batches.destroy', $batch) }}"
                                          data-ajax-delete="1" data-confirm="{{ mawa_lang('batches.confirm_delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ mawa_e('actions.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">{{ mawa_e('courses.show_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2 monetix-print-hidden" data-ajax-pagination>
        {{ $batches->links('pagination::bootstrap-5') }}
        @if ($batches->total() > 0)
            <span class="text-muted small">
                Showing {{ $batches->firstItem() ?? 0 }}–{{ $batches->lastItem() ?? 0 }} of {{ $batches->total() }} batches
            </span>
        @endif
    </nav>

</div>

@if ($user->hasPermission('exams.manage'))
    @include('exams._send_modal', ['sendExamSubjects' => $sendExamSubjects ?? []])
@endif

@include('courses._subject_modal', [
    'subjectCourse' => $course,
    'subjectOptions' => $subjectOptions ?? [],
    'attachedSubjectIds' => $attachedSubjectIds ?? [],
])

@if ($user->hasPermission('batches.manage'))
    {{-- Edit Batch modal (scoped to this course) --}}
    <div class="modal fade" id="batchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('batches.update', $batches->first()?->id ?? 1) }}" id="batchForm" data-ajax-enabled>
                @csrf
                <input type="hidden" name="_method" id="b_method" value="PUT">
                <input type="hidden" name="batch_id" id="b_batch_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="b_modal_title">{{ mawa_e('batches.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="e_name">{{ mawa_e('batches.name') }} *</label>
                            <input type="text" id="e_name" name="name" class="form-control" maxlength="120" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ mawa_e('batches.table_code') }}</label>
                            <input type="text" id="e_batch_code" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ mawa_e('batches.course') }} *</label>
                            <input type="text" class="form-control bg-light" value="{{ $course->name ?? '' }}" readonly>
                            <input type="hidden" name="course_id" id="e_course_id" value="{{ $course->id }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_shift">{{ mawa_e('batches.shift') }}</label>
                            <select id="e_shift" name="shift" class="form-select">
                                @foreach ($shiftNames as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_status">{{ mawa_e('batches.status') }}</label>
                            <select id="e_status" name="status" class="form-select">
                                @foreach ($statusNames as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_start_date">{{ mawa_e('batches.start_date') }} *</label>
                            <input type="date" id="e_start_date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_end_date">{{ mawa_e('batches.end_date') }}</label>
                            <input type="date" id="e_end_date" name="end_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_seat_capacity">{{ mawa_e('batches.seat_capacity') }}</label>
                            <input type="number" id="e_seat_capacity" name="seat_capacity" class="form-control" min="1" max="10000" value="30">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const EDIT_DATA = @json($editData ?? []);
    const UPDATE_BASE = @json(url('batches') . '/');
    const modalEl = document.getElementById('batchModal');
    if (!modalEl) { return; }
    const form = document.getElementById('batchForm');

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el && val !== undefined && val !== null) { el.value = val; }
    }

    function fillEdit(id) {
        const d = EDIT_DATA[id] || {};
        document.getElementById('b_modal_title').textContent = {{ Js::from(mawa_lang('batches.edit')) }} + ' — ' + (d.name || '');
        form.action = UPDATE_BASE + (d.id || id);
        setVal('b_method', 'PUT');
        setVal('b_batch_id', d.id);
        setVal('e_name', d.name);
        setVal('e_batch_code', d.batch_code || '');
        setVal('e_course_id', d.course_id);
        setVal('e_shift', d.shift);
        setVal('e_status', d.status);
        setVal('e_start_date', d.start_date);
        setVal('e_end_date', d.end_date);
        setVal('e_seat_capacity', d.seat_capacity);
    }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
        var errBox = document.getElementById('batchFormErrors');
        if (errBox) { errBox.remove(); }
    }

    function showErrors(errors) {
        clearErrors();
        Object.keys(errors || {}).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            if (field) {
                field.classList.add('is-invalid');
                var msg = document.createElement('div');
                msg.className = 'text-danger small mt-1';
                msg.textContent = (errors[key] || []).join(', ');
                field.parentNode.insertBefore(msg, field.nextSibling);
            }
        });
        var header = modalEl.querySelector('.modal-header');
        if (header && !document.getElementById('batchFormErrors')) {
            var errBox = document.createElement('div');
            errBox.id = 'batchFormErrors';
            errBox.className = 'alert alert-danger m-3 mb-0 py-2';
            errBox.textContent = {{ Js::from(mawa_lang('batches.form_invalid')) }};
            header.insertAdjacentElement('afterend', errBox);
        }
    }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-edit-batch]', function (e, editBtn) {
            fillEdit(editBtn.getAttribute('data-edit-batch'));
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }, 'mtx-course-show-edit-batch');
    }

    form.addEventListener('submit', function (e) {
        if (!form.hasAttribute('data-ajax-enabled')) { return; }
        e.preventDefault();
        if (!window.Monetix || !Monetix.request) { return; }
        clearErrors();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; }
        Monetix.request(form.action, {
            method: 'PUT',
            body: new FormData(form),
        }).then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; }
            if (res && res.errors) { showErrors(res.errors); return; }
            if (res && res.success === false) {
                if (window.Monetix && Monetix.toast) { Monetix.toast(res.message || '{{ mawa_lang('batches.save_failed') }}', 'danger'); }
                return;
            }
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) { modal.hide(); }
            if (window.Monetix && Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
            if (window.Monetix && Monetix.loadPage) { Monetix.loadPage(location.pathname + location.search, { preserveFocus: false }); }
        }).catch(function () {
            if (submitBtn) { submitBtn.disabled = false; }
            if (window.Monetix && Monetix.toast) { Monetix.toast('{{ mawa_lang('batches.save_failed') }}', 'danger'); }
        });
    });
})();
</script>
@endpush