@extends('layouts.institute')

@section('title', mawa_lang('sidebar.batches') . ' — AccumenAI')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Batches</li>
    </ol>
</nav>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('sidebar.batches') }}</h4>
        <p class="page-header-desc mb-0">{{ mawa_lang('batches.title') }} — {{ mawa_lang('batches.table_name') }}</p>
    </div>
    @if ($user->hasPermission('batches.manage'))
        <div class="page-header-actions">
            <button type="button" class="btn btn-primary" data-create-batch>
                <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('batches.add_new') }}
            </button>
        </div>
    @endif
</div>

<div class="admin-card mb-3">
    @include('courses._tabs', [
        'activeTab' => 'batches',
        'coursesCount' => $coursesCount ?? 0,
        'subjectsCount' => $subjectsCount ?? 0,
        'batchesCount' => $batchesCount ?? 0,
        'archiveCount' => $archiveCount ?? 0,
    ])
</div>

@livewire('batch-list')

@if ($user->hasPermission('exams.manage'))
    @include('exams._send_modal', ['sendExamSubjects' => $sendExamSubjects ?? []])
@endif

@if ($user->hasPermission('batches.manage'))
    <div class="modal fade" id="batchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('batches.store') }}" id="batchForm" data-ajax-enabled>
                @csrf
                <input type="hidden" name="_method" id="b_method" value="">
                <input type="hidden" name="batch_id" id="b_batch_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="b_modal_title">{{ mawa_e('batches.add_new') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="e_name">{{ mawa_e('batches.name') }} *</label>
                            <input type="text" id="e_name" name="name" class="form-control" maxlength="120" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_batch_code">{{ mawa_e('batches.table_code') }}</label>
                            <input type="text" id="e_batch_code" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_course_id">{{ mawa_e('batches.course') }} *</label>
                            <select id="e_course_id" name="course_id" class="form-select" required>
                                <option value="">{{ mawa_e('batches.select') }}</option>
                                @foreach($courses ?? [] as $course)
                                    <option value="{{ $course->id }}">{{ $course->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_shift">{{ mawa_e('batches.shift') }}</label>
                            <select id="e_shift" name="shift" class="form-select">
                                <option value="morning">{{ mawa_lang('options.shift_morning') }}</option>
                                <option value="day" selected>{{ mawa_lang('options.shift_day') }}</option>
                                <option value="evening">{{ mawa_lang('options.shift_evening') }}</option>
                                <option value="weekend">{{ mawa_lang('options.shift_weekend') }}</option>
                                <option value="online">{{ mawa_lang('options.shift_online') }}</option>
                            </select>
                        </div>
                        @if(\App\Support\InstituteDomain::isAcademic($institute ?? null))
                        <div class="col-md-4">
                            <label class="form-label" for="e_academic_year_id">{{ mawa_e('batches.academic_year') }}</label>
                            <select id="e_academic_year_id" name="academic_year_id" class="form-select">
                                <option value="">{{ mawa_e('batches.select') }}</option>
                                @foreach($academicYears ?? [] as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-md-4">
                            <label class="form-label" for="e_status">{{ mawa_e('batches.status') }}</label>
                            <select id="e_status" name="status" class="form-select">
                                <option value="upcoming" selected>Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="archived">Archived</option>
                            </select>
                            <div class="form-text small text-muted">Changing status does not affect existing exam results or certificates.</div>
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
                        <div class="col-md-3">
                            <label for="e_attendance_threshold" class="form-label">Attendance Threshold (%)</label>
                            <input type="number" id="e_attendance_threshold" name="attendance_threshold" class="form-control form-control-sm" min="0" max="100" value="80" required>
                            <small class="form-text text-muted">Minimum attendance % required for certificate eligibility.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="e_teacher_id">Trainer</label>
                            <select id="e_teacher_id" name="teacher_id" class="form-select">
                                <option value="">— Select trainer —</option>
                                @foreach(($instructors ?? []) as $ins)
                                    <option value="{{ $ins->id }}">{{ $ins->first_name }} {{ $ins->last_name }}</option>
                                @endforeach
                            </select>
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
    const STORE_URL = @json(route('batches.store'));
    const UPDATE_BASE = @json(url('batches') . '/');
    const modalEl = document.getElementById('batchModal');
    if (!modalEl) { return; }
    const form = document.getElementById('batchForm');

    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el && val !== undefined && val !== null) { el.value = val; }
    }

    // Auto-generate batch name as "[Course Name] | batch [UserInput]"
    (function() {
        const courseSelect = document.getElementById('e_course_id');
        const nameInput = document.getElementById('e_name');
        if (courseSelect && nameInput) {
            courseSelect.addEventListener('change', function() {
                if (!this.value) return;
                var courseName = (this.options[this.selectedIndex]?.text || '').trim();
                if (!courseName) return;
                var currentName = nameInput.value || '';
                if (!currentName.startsWith(courseName)) {
                    nameInput.value = courseName + ' | batch ';
                    nameInput.focus();
                    // place cursor at end
                    try { nameInput.setSelectionRange(nameInput.value.length, nameInput.value.length); } catch(e) {}
                }
            });
        }
    })();

    function resetCreate(formEl) {
        document.getElementById('b_modal_title').textContent = {{ Js::from(mawa_lang('batches.add_new')) }};
        formEl.action = STORE_URL;
        setVal('b_method', '');
        setVal('b_batch_id', '');
        setVal('e_name', '');
        setVal('e_batch_code', '');
        setVal('e_course_id', '');
        setVal('e_academic_year_id', '');
        setVal('e_teacher_id', '');
        setVal('e_shift', 'day');
        setVal('e_status', 'upcoming');
        setVal('e_start_date', new Date().toISOString().slice(0, 10));
        setVal('e_end_date', '');
        setVal('e_seat_capacity', 30);
        setVal('e_attendance_threshold', 80);
    }

    function fillEdit(formEl, id) {
        const d = EDIT_DATA[id] || {};
        document.getElementById('b_modal_title').textContent = {{ Js::from(mawa_lang('batches.edit')) }} + ' — ' + (d.name || '');
        formEl.action = UPDATE_BASE + (d.id || id);
        setVal('b_method', 'PUT');
        setVal('b_batch_id', d.id);
        setVal('e_name', d.name);
        setVal('e_batch_code', d.batch_code || '');
        setVal('e_course_id', d.course_id);
        setVal('e_academic_year_id', d.academic_year_id);
        setVal('e_teacher_id', d.teacher_id);
        setVal('e_shift', d.shift);
        // Backwards compatibility: map legacy 'running' to 'ongoing'
        var normalizedStatus = d.status === 'running' ? 'ongoing' : d.status;
        setVal('e_status', normalizedStatus);
        setVal('e_start_date', d.start_date || '');
        setVal('e_end_date', d.end_date || '');
        setVal('e_seat_capacity', d.seat_capacity);
        setVal('e_attendance_threshold', d.attendance_threshold ?? 80);
    }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-create-batch], [data-edit-batch]', function (e, btn) {
            const currentModal = document.getElementById('batchModal');
            const currentForm = document.getElementById('batchForm');
            if (!currentModal || !currentForm) { return; }
            if (btn.hasAttribute('data-create-batch')) {
                resetCreate(currentForm);
                bootstrap.Modal.getOrCreateInstance(currentModal).show();
                return;
            }
            fillEdit(currentForm, btn.getAttribute('data-edit-batch'));
            bootstrap.Modal.getOrCreateInstance(currentModal).show();
        }, 'mtx-batch-index-modal');
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
    }

    form.addEventListener('submit', function (e) {
        if (!form.hasAttribute('data-ajax-enabled')) { return; }
        e.preventDefault();
        if (!window.Monetix || !Monetix.request) { return; }
        clearErrors();
        var submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; }
        var methodInput = form.querySelector('input[name="_method"]');
        var method = (methodInput && methodInput.value) ? methodInput.value : 'POST';
        Monetix.request(form.action, {
            method: method,
            body: new FormData(form),
        }).then(function (res) {
            if (submitBtn) { submitBtn.disabled = false; }
            if (res && res.errors) { showErrors(res.errors); return; }
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) { modal.hide(); }
            if (window.Monetix && Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
            if (res && res.data && res.data.redirect) { window.location.href = res.data.redirect; return; }
            if (window.Monetix && Monetix.loadPage) { Monetix.loadPage(location.pathname + location.search, { preserveFocus: false }); }
        }).catch(function () {
            if (submitBtn) { submitBtn.disabled = false; }
        });
    });
})();
</script>
@endpush