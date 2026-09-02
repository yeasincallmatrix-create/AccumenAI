@extends('layouts.institute')

@section('title', mawa_e('sidebar.recycle_bin') . ' — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-trash-fill me-1"></i>{{ mawa_e('sidebar.recycle_bin') }}</h4>
        <p class="page-header-desc">Soft-deleted students awaiting restore or permanent deletion</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('students.index') }}">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('sidebar.students') }}
        </a>
    </div>
</div>

<div class="admin-card" data-ajax-table>

    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-people-fill"></i> {{ $students->total() }} trashed students</div>
    </div>

    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end" method="GET" action="{{ route('recycle.index') }}" data-ajax-filter>
        <div style="flex:1 1 280px;min-width:220px">
            <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Search name, ID number...">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>{{ mawa_e('actions.search') }}</button>
        @if ($q)
            <a class="btn btn-outline-secondary" href="{{ route('recycle.index') }}">
                <i class="bi bi-x-lg me-1"></i>{{ mawa_e('actions.clear') }}
            </a>
        @endif
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ mawa_e('students.table_name') }}</th>
                    <th>{{ mawa_e('students.table_no') }}</th>
                    <th>{{ mawa_e('students.roll_number') }}</th>
                    <th>{{ mawa_e('students.table_admission') }}</th>
                    <th>Deleted At</th>
                    <th class="text-end">{{ mawa_e('actions.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $student->full_name }}</div>
                            @if ($student->branch)
                                <div class="text-muted small">{{ $student->branch->name }}</div>
                            @endif
                        </td>
                        <td>{{ $student->student_id }}</td>
                        <td>{{ $student->roll_number ?? '—' }}</td>
                        <td>{{ $student->admission_date ? $student->admission_date->format('d M Y') : '—' }}</td>
                        <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($student->deleted_at)->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            @if ($user->hasPermission('students.manage'))
                                <form class="d-inline" method="POST" action="{{ route('recycle.students.restore', $student) }}"
                                      data-ajax-action="1" data-confirm="Restore {{ $student->full_name }}?">
                                    @csrf
                                    <button class="btn btn-success btn-sm" type="submit">
                                        <i class="bi bi-arrow-counterclockwise"></i> {{ mawa_e('actions.restore') }}
                                    </button>
                                </form>
                                <button class="btn btn-outline-danger btn-sm force-del-btn" type="button"
                                        data-name="{{ $student->full_name }}"
                                        data-action="{{ route('recycle.students.force-delete', $student) }}">
                                    <i class="bi bi-x-octagon"></i> {{ mawa_e('actions.delete_perm') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No trashed students.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $students->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $students->total() }} trashed students</span>
    </nav>
</div>

<div class="admin-card mt-4" data-ajax-table>
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-collection-fill"></i> {{ $batches->total() }} trashed batches</div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ mawa_e('batches.table_name') }}</th>
                    <th>{{ mawa_e('batches.table_code') }}</th>
                    <th>{{ mawa_e('batches.table_course') }}</th>
                    <th>Deleted At</th>
                    <th class="text-end">{{ mawa_e('actions.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="fw-semibold">{{ $batch->name }}</td>
                        <td><span class="badge bg-dark bg-opacity-75">{{ $batch->batch_code }}</span></td>
                        <td>{{ $batch->course?->name ?? '—' }}</td>
                        <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($batch->deleted_at)->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            @if ($user->hasPermission('batches.manage'))
                                <form class="d-inline" method="POST" action="{{ route('recycle.batches.restore', $batch) }}"
                                      data-ajax-action="1" data-confirm="Restore {{ $batch->name }}?">
                                    @csrf
                                    <button class="btn btn-success btn-sm" type="submit">
                                        <i class="bi bi-arrow-counterclockwise"></i> {{ mawa_e('actions.restore') }}
                                    </button>
                                </form>
                                <button class="btn btn-outline-danger btn-sm force-del-btn" type="button"
                                        data-name="{{ $batch->name }}"
                                        @disabled($batch->attended_exams > 0)
                                        data-action="{{ route('recycle.batches.force-delete', $batch) }}">
                                    <i class="bi bi-x-octagon"></i> {{ mawa_e('actions.delete_perm') }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No trashed batches.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $batches->links('pagination::bootstrap-5') }}
        <span class="text-muted small">{{ $batches->total() }} trashed batches</span>
    </nav>
</div>

<div class="modal fade" id="forceDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="forceDeleteForm" data-ajax-enabled>
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Permanent Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">This permanently removes the student and cannot be undone.</div>
                <h6 id="fd_name" class="fw-bold mb-3"></h6>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon"></i> {{ mawa_e('actions.delete_perm') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('forceDeleteModal');
    var form = document.getElementById('forceDeleteForm');
    var nameEl = document.getElementById('fd_name');

    document.querySelectorAll('.force-del-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.getAttribute('data-action');
            nameEl.textContent = btn.getAttribute('data-name');
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });
})();

(function () {
    var form = document.getElementById('forceDeleteForm');
    var modal = document.getElementById('forceDeleteModal');
    if (!form || !modal || !window.Monetix || !Monetix.request) { return; }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
    }

    form.addEventListener('submit', function (e) {
        if (!form.hasAttribute('data-ajax-enabled')) { return; }
        e.preventDefault();
        clearErrors();
        var submitBtn = form.querySelector('[type="submit"]');
        var restore = Monetix.loading(submitBtn, 'Deleting…');
        Monetix.request(form.action, { method: 'DELETE', body: new FormData(form) })
            .then(function (res) {
                if (restore) { restore(); }
                if (res && res.errors) {
                    Object.keys(res.errors).forEach(function (key) {
                        var field = form.querySelector('[name="' + key + '"]');
                        if (field) {
                            field.classList.add('is-invalid');
                            var msg = document.createElement('div');
                            msg.className = 'text-danger small mt-1';
                            msg.textContent = (res.errors[key] || []).join(', ');
                            field.parentNode.insertBefore(msg, field.nextSibling);
                        }
                    });
                    return;
                }
                if (res && res.success === false) {
                    if (Monetix.toast) { Monetix.toast(res.message || 'Could not delete the record.', 'danger'); }
                    return;
                }
                var m = bootstrap.Modal.getInstance(modal);
                if (m) { m.hide(); }
                if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false }); }
            })
            .catch(function () {
                if (restore) { restore(); }
                if (Monetix.toast) { Monetix.toast('Could not delete the record. Please try again.', 'danger'); }
            });
    });
})();
</script>
@endpush