@extends('layouts.institute')

@section('title', mawa_lang('classes.tab_subjects') . ' — AccumenAI')

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
        <h4 class="page-header-title">{{ mawa_e('classes.tab_subjects') }}</h4>
        <p class="page-header-desc">{{ mawa_lang('classes.subjects_desc') }} — {{ $institute->name ?? '' }}</p>
    </div>
    @if ($user->hasPermission('courses.manage'))
        <div class="page-header-actions">
            <button type="button" class="btn btn-primary" data-subject-request-new>
                <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('courses.subject_new') }}
            </button>
        </div>
    @endif
</div>

<div class="admin-card mb-3">
    @include('classes._tabs', [
        'activeTab' => 'subjects',
        'classesCount' => $classesCount,
        'subjectsCount' => $subjectsCount,
        'batchesCount' => $batchesCount,
        'archiveCount' => $archiveCount,
    ])
</div>

<div class="admin-card" data-ajax-table>

    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — {{ mawa_e('classes.tab_subjects') }}</h4>
        <p class="mb-0 text-muted">{{ $subjectsCount }} subjects · {{ now()->format('d M Y') }}</p>
    </div>

    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end monetix-print-hidden" method="GET" action="{{ route('classes.subjects') }}" data-ajax-filter>
        <div style="flex:1 1 280px;min-width:220px">
            <input type="text" name="q" class="form-control" value="{{ $q }}"
                   placeholder="{{ mawa_e('classes.subject_search') }}">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ mawa_e('actions.search') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('classes.subjects') }}" title="{{ mawa_e('actions.reset') }}">
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
                        'name'     => mawa_e('classes.table_subject'),
                        'code'     => mawa_e('classes.table_code'),
                        'category' => mawa_e('classes.table_category'),
                        'status'   => mawa_e('classes.table_status'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item col-toggle-item" for="subject-col-{{ $col }}">
                                <input type="checkbox" id="subject-col-{{ $col }}" class="form-check-input me-2 col-toggle-check" data-col="{{ $col }}" @checked(in_array($col, $visibleColumns, true))>
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
                    <th data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_subject') }}</th>
                    <th data-col="code" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_code') }}</th>
                    <th data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_category') }}</th>
                    <th data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>{{ mawa_e('classes.table_status') }}</th>
                    @if ($user->hasPermission('courses.manage'))
                        <th data-col="action" class="text-end">{{ mawa_e('actions.action') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $subject)
                    <tr>
                        <td data-col="serial" class="text-muted" @if(!in_array('serial', $visibleColumns, true)) style="display:none" @endif>{{ $subjects->firstItem() + $loop->index }}</td>
                        <td data-col="name" @if(!in_array('name', $visibleColumns, true)) style="display:none" @endif>
                            <div class="fw-semibold">{{ $subject->name }}</div>
                            @if ($subject->short_name)
                                <div class="text-muted small">{{ $subject->short_name }}</div>
                            @endif
                        </td>
                        <td data-col="code" class="text-muted" @if(!in_array('code', $visibleColumns, true)) style="display:none" @endif>{{ $subject->subject_code }}</td>
                        <td data-col="category" @if(!in_array('category', $visibleColumns, true)) style="display:none" @endif>{{ $subject->category->name ?? '—' }}</td>
                        <td data-col="status" @if(!in_array('status', $visibleColumns, true)) style="display:none" @endif>
                            <span class="badge {{ $subject->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucwords($subject->status) }}</span>
                        </td>
                        @if ($user->hasPermission('courses.manage'))
                            <td data-col="action" class="text-end">
                                @if ((int) $subject->institute_id === (int) ($institute->id ?? 0))
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-subject-request-edit
                                            data-href="{{ route('courses.subjects.update', $subject) }}"
                                            data-name="{{ $subject->name }}"
                                            data-short_name="{{ $subject->short_name }}"
                                            data-subject_code="{{ $subject->subject_code }}"
                                            data-category="{{ $subject->category->name ?? '' }}">
                                        <i class="bi bi-pencil"></i> {{ mawa_e('actions.edit') }}
                                    </button>
                                @else
                                    <span class="text-muted small">{{ mawa_e('courses.subject_platform') }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $user->hasPermission('courses.manage') ? 6 : 5 }}" class="text-center text-muted py-4">{{ mawa_e('classes.subjects_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2 monetix-print-hidden" data-ajax-pagination>
        {{ $subjects->links('pagination::bootstrap-5') }}
        @if ($subjects->total() > 0)
            <span class="text-muted small">
                Showing {{ $subjects->firstItem() ?? 0 }}–{{ $subjects->lastItem() ?? 0 }} of {{ $subjects->total() }} subjects
            </span>
        @endif
    </nav>

</div>

@if ($user->hasPermission('courses.manage'))
    {{-- New subject request --}}
    <div class="modal fade" id="subjectRequestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" id="subjectRequestForm" action="{{ route('courses.subjects.request.global') }}" data-ajax-enabled>
                @csrf
                <input type="hidden" name="subject_type" value="academic">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('courses.subject_request_new') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i>{{ mawa_e('courses.subject_request_hint') }}
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="sr_name">{{ mawa_e('courses.subject_name') }} *</label>
                            <input type="text" id="sr_name" name="name" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_short_name">{{ mawa_e('courses.subject_short_name') }}</label>
                            <input type="text" id="sr_short_name" name="short_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_code">{{ mawa_e('courses.subject_code') }}</label>
                            <input type="text" id="sr_code" name="subject_code" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sr_category">{{ mawa_e('courses.subject_category') }} *</label>
                            <select id="sr_category" name="category_id" class="form-select" required>
                                <option value="">{{ mawa_e('courses.select') }}</option>
                                @foreach ($requestCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ mawa_e('courses.subject_category_locked_hint') }}</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="sr_description">{{ mawa_e('courses.subject_description') }}</label>
                            <textarea id="sr_description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('courses.subject_request_submit') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit approved subject (course name locked) --}}
    <div class="modal fade" id="subjectEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" id="subjectEditForm" data-ajax-enabled>
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-1"></i>{{ mawa_e('courses.subject_edit') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="se_name">{{ mawa_e('courses.subject_name') }} *</label>
                            <input type="text" id="se_name" name="name" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="se_short_name">{{ mawa_e('courses.subject_short_name') }}</label>
                            <input type="text" id="se_short_name" name="short_name" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="se_code">{{ mawa_e('courses.subject_code') }}</label>
                            <input type="text" id="se_code" name="subject_code" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ mawa_e('courses.subject_category') }}</label>
                            <input type="text" id="se_category" class="form-control" readonly>
                            <div class="form-text text-warning">
                                <i class="bi bi-lock me-1"></i>{{ mawa_e('courses.subject_course_locked_hint') }}
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="se_description">{{ mawa_e('courses.subject_description') }}</label>
                            <textarea id="se_description" name="description" class="form-control" rows="2"></textarea>
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
                body: JSON.stringify({ key: 'class_subjects', columns: visible })
            });
        });
    });
})();
</script>
@endpush

@if ($user->hasPermission('courses.manage'))
@push('scripts')
<script>
(function () {
    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
        var errBox = form.querySelector('.subject-form-errors');
        if (errBox) { errBox.remove(); }
    }

    function showErrors(form, errors) {
        var box = form.querySelector('.subject-form-errors');
        if (!box && errors) {
            box = document.createElement('div');
            box.className = 'alert alert-danger m-3 mb-0 py-2 subject-form-errors';
            box.textContent = Object.values(errors).flat().join(' ');
            form.querySelector('.modal-body').insertAdjacentElement('beforebegin', box);
        }
    }

    function submitForm(form) {
        if (!window.Monetix || !Monetix.request) { return; }
        clearErrors(form);
        var submitBtn = form.querySelector('[type="submit"]');
        var restore = Monetix.loading && Monetix.loading(submitBtn);
        Monetix.request(form.action, { method: form.method === 'PUT' ? 'PUT' : 'POST', body: new FormData(form) })
            .then(function (res) {
                if (restore) { restore(); }
                if (res && res.errors) {
                    form.querySelector('[name]') && form.querySelector('[name]').focus();
                    showErrors(form, res.errors);
                    return;
                }
                if (res && res.success === false) {
                    if (Monetix.toast) { Monetix.toast(res.message || 'Could not save subject.', 'danger'); }
                    return;
                }
                var modal = bootstrap.Modal.getInstance(form.closest('.modal'));
                if (modal) { modal.hide(); }
                if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false }); }
            })
            .catch(function () {
                if (restore) { restore(); }
                if (Monetix.toast) { Monetix.toast('Could not save subject. Please try again.', 'danger'); }
            });
    }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-subject-request-new], [data-subject-request-edit]', function (e, btn) {
            if (btn.hasAttribute('data-subject-request-new')) {
                var newModalEl = document.getElementById('subjectRequestModal');
                if (!newModalEl) { return; }
                var form = document.getElementById('subjectRequestForm');
                if (form) { clearErrors(form); form.reset(); }
                bootstrap.Modal.getOrCreateInstance(newModalEl).show();
                return;
            }

            var editModalEl = document.getElementById('subjectEditModal');
            if (!editModalEl) { return; }
            var form = document.getElementById('subjectEditForm');
            if (!form) { return; }
            clearErrors(form);
            form.action = btn.getAttribute('data-href');
            document.getElementById('se_name').value = btn.getAttribute('data-name') || '';
            document.getElementById('se_short_name').value = btn.getAttribute('data-short_name') || '';
            document.getElementById('se_code').value = btn.getAttribute('data-subject_code') || '';
            document.getElementById('se_category').value = btn.getAttribute('data-category') || '';
            document.getElementById('se_description').value = '';
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        }, 'mtx-class-subject-request');
    }

    var requestForm = document.getElementById('subjectRequestForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function (e) {
            if (!requestForm.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            if (!requestForm.checkValidity()) { requestForm.reportValidity(); return; }
            submitForm(requestForm);
        });
    }

    var editForm = document.getElementById('subjectEditForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            if (!editForm.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            if (!editForm.checkValidity()) { editForm.reportValidity(); return; }
            submitForm(editForm);
        });
    }
})();
</script>
@endpush
@endif