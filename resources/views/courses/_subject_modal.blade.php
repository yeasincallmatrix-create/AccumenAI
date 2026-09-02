@if ($user->hasPermission('courses.manage'))
    {{-- Attach subjects to a course (batches inherit the course's subjects) --}}
    @php
        $targetCourse = $subjectCourse ?? null;
        $syncUrl = $targetCourse ? route('courses.subjects.sync', $targetCourse) : '';
        $allSubjects = $subjectOptions ?? [];
        $attached = array_map('intval', $attachedSubjectIds ?? []);
    @endphp
    <div class="modal fade" id="courseSubjectsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="POST" id="courseSubjectsForm" data-ajax-enabled>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-journal-plus me-1"></i>{{ mawa_e('courses.add_subjects') }}
                        @if ($targetCourse)
                            <small class="text-muted d-block">{{ $targetCourse->name }}</small>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check mb-2">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="courseSubjectsCheckAll">
                            {{ mawa_e('actions.select_all') }}
                        </label>
                    </div>
                    <div id="courseSubjectsList" class="border rounded p-2" style="max-height:360px;overflow-y:auto">
                        @forelse ($allSubjects as $subject)
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input course-subject-check"
                                           name="subjects[]" value="{{ $subject['id'] }}"
                                           @checked(in_array((int) $subject['id'], $attached, true))>
                                    {{ $subject['name'] }}
                                </label>
                            </div>
                        @empty
                            <div class="text-muted small p-2">{{ mawa_e('courses.subjects_empty') }}</div>
                        @endforelse
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ mawa_e('actions.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const modalEl = document.getElementById('courseSubjectsModal');
        if (!modalEl) { return; }
        const form = document.getElementById('courseSubjectsForm');
        const checkAll = document.getElementById('courseSubjectsCheckAll');
        const list = document.getElementById('courseSubjectsList');
        const SYNC_URL = @json($syncUrl);

        function clearErrors(formEl) {
            formEl.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            formEl.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
            var errBox = document.getElementById('courseSubjectsFormErrors');
            if (errBox) { errBox.remove(); }
        }

        checkAll.addEventListener('change', function () {
            list.querySelectorAll('.course-subject-check').forEach(function (cb) { cb.checked = checkAll.checked; });
        });

        if (window.Monetix && Monetix.delegate) {
            Monetix.delegate('click', '[data-manage-course-subjects]', function (e, btn) {
                e.preventDefault();
                const currentModal = document.getElementById('courseSubjectsModal');
                const currentForm = document.getElementById('courseSubjectsForm');
                if (!currentModal || !currentForm) { return; }
                clearErrors(currentForm);
                currentForm.action = btn.getAttribute('data-href') || SYNC_URL;
                bootstrap.Modal.getOrCreateInstance(currentModal).show();
            }, 'mtx-course-subjects');
        }

        form.addEventListener('submit', function (e) {
            if (!form.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            if (!window.Monetix || !Monetix.request) { return; }
            clearErrors(form);
            const submitBtn = form.querySelector('[type="submit"]');
            const restore = Monetix.loading(submitBtn);
            Monetix.request(form.action, { method: 'POST', body: new FormData(form) })
                .then(function (res) {
                    if (restore) { restore(); }
                    if (res && res.errors) {
                        var header = modalEl.querySelector('.modal-header');
                        if (header && !document.getElementById('courseSubjectsFormErrors')) {
                            var errBox = document.createElement('div');
                            errBox.id = 'courseSubjectsFormErrors';
                            errBox.className = 'alert alert-danger m-3 mb-0 py-2';
                            errBox.textContent = Object.values(res.errors).flat().join(' ');
                            header.insertAdjacentElement('afterend', errBox);
                        }
                        return;
                    }
                    if (res && res.success === false) {
                        if (Monetix.toast) { Monetix.toast(res.message || 'Could not save subjects.', 'danger'); }
                        return;
                    }
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) { modal.hide(); }
                    if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                    if (Monetix.loadPage) { Monetix.loadPage(window.location.pathname + window.location.search, { preserveFocus: false }); }
                })
                .catch(function () {
                    if (restore) { restore(); }
                    if (Monetix.toast) { Monetix.toast('Could not save subjects. Please try again.', 'danger'); }
                });
        });
    })();
    </script>
    @endpush
@endif