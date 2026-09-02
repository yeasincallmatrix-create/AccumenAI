@if ($user->hasPermission('exams.manage'))
    @push('modals')
    {{-- Send for Exam modal --}}
    <div class="modal fade" id="sendExamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" method="POST" id="sendExamForm" data-ajax-enabled>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-send me-1"></i>{{ mawa_e('exams.send_exam_heading') }}
                        <small class="text-muted d-block" id="sendExamBatchHint"></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3" id="sendExamBatchRow" style="display:none">
                        <div class="col-12">
                            <label class="form-label" for="sendExamBatchSelect">{{ mawa_e('exams.select_batch') }} *</label>
                            <select id="sendExamBatchSelect" class="form-select">
                                <option value="">{{ mawa_e('exams.select_batch') }}</option>
                                @foreach ($sendExamBatches ?? [] as $batch)
                                    <option value="{{ $batch->id }}" data-url="{{ route('exams.send-to-exam', $batch) }}">{{ $batch->name }} ({{ $batch->batch_code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-7">
                            <label class="form-label" for="sendExamTitle">{{ mawa_e('exams.title_field') }} *</label>
<textarea id="sendExamTitle" name="title" class="form-control" maxlength="150" rows="3"
                               placeholder="{{ mawa_e('exams.title_placeholder') }}" required autocomplete="off"></textarea>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="sendExamDate">{{ mawa_e('exams.exam_date') }} *</label>
                            <input type="datetime-local" id="sendExamDate" name="exam_date" class="form-control" required>
                        </div>
                    </div>

                    <label class="form-label">{{ mawa_e('exams.select_subjects') }} *</label>
                    <p class="text-muted small mb-2">{{ mawa_e('exams.subject_help') }}</p>
                    <div class="form-check mb-2">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" id="sendExamCheckAll">
                            {{ mawa_e('actions.select_all') }}
                        </label>
                    </div>
                    <div id="sendExamSubjects" class="border rounded p-2" style="max-height:260px;overflow-y:auto">
                        {{-- subject checkboxes injected via JS --}}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ mawa_e('actions.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>{{ mawa_e('exams.send_exam') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
    (function () {
        const modalEl = document.getElementById('sendExamModal');
        if (!modalEl) { return; }
        const form = document.getElementById('sendExamForm');
        const subjectBox = document.getElementById('sendExamSubjects');
        const checkAll = document.getElementById('sendExamCheckAll');
        const titleInput = document.getElementById('sendExamTitle');
        const examDateInput = document.getElementById('sendExamDate');
        const batchHint = document.getElementById('sendExamBatchHint');

        const LABEL_PRACTICAL = @json(mawa_lang('exams.practical'));
        const LABEL_VIVA = @json(mawa_lang('exams.viva'));
        const LABEL_TOTAL = @json(mawa_lang('exams.total_marks'));
        const MSG_MARKS_REQUIRED = @json(mawa_lang('exams.marks_required'));
        const MSG_NO_SUBJECTS = @json(mawa_lang('exams.no_subjects'));
        const MSG_BATCH_REQUIRED = @json(mawa_lang('exams.batch_required'));

        // batch_id => [{ id, name }, ...] populated by the controller.
        const SUBJECTS_BY_BATCH = @json($sendExamSubjects ?? []);

        let subjects = [];

        function localDatetimeValue(d) {
            const pad = function (n) { return String(n).padStart(2, '0'); };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        function renderSubjectRows() {
            const boxEl = document.getElementById('sendExamSubjects');
            if (!boxEl) { return; }
            boxEl.innerHTML = '';
            if (!subjects.length) {
                boxEl.innerHTML = '<div class="text-muted small p-2">' + MSG_NO_SUBJECTS + '</div>';
                return;
            }
            const dateToday = document.getElementById('sendExamDate');
            subjects.forEach(function (subject) {
                const row = document.createElement('div');
                row.className = 'exam-subject-row mb-2 p-2 border rounded';

                const head = document.createElement('div');
                head.className = 'd-flex flex-wrap align-items-center justify-content-between gap-2';
                const label = document.createElement('label');
                label.className = 'form-check';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input exam-subject-check';
                checkbox.value = subject.id;
                checkbox.name = 'subjects[]';
                const name = document.createElement('span');
                name.className = 'fw-semibold ms-1';
                name.textContent = subject.name;
                label.appendChild(checkbox); label.appendChild(name);
                head.appendChild(label);

                const dateWrap = document.createElement('div');
                dateWrap.className = 'd-flex align-items-center gap-2';
                const dateInput = document.createElement('input');
                dateInput.type = 'datetime-local';
                dateInput.className = 'form-control form-control-sm exam-subject-date';
                dateInput.name = 'subject_dates[' + subject.id + ']';
                dateInput.value = dateToday ? dateToday.value : '';
                dateInput.addEventListener('change', function () {
                    const today = document.getElementById('sendExamDate');
                    if (today && today.value !== dateInput.value) {
                        today.value = dateInput.value;
                    }
                });
                dateWrap.appendChild(dateInput);
                head.appendChild(dateWrap);
                row.appendChild(head);

                const marks = document.createElement('div');
                marks.className = 'row g-2 mt-1 exam-subject-marks d-none';
                marks.dataset.subject = subject.id;
                marks.innerHTML = [
                    '<div class="col-6 col-md-4">',
                    '  <label class="form-label small">' + LABEL_PRACTICAL + '</label>',
                    '  <input type="number" class="form-control form-control-sm exam-marks-input" name="marks[' + subject.id + '][practical]" min="0" step="1" inputmode="numeric" value="80">',
                    '</div>',
                    '<div class="col-6 col-md-4">',
                    '  <label class="form-label small">' + LABEL_VIVA + '</label>',
                    '  <input type="number" class="form-control form-control-sm exam-marks-input" name="marks[' + subject.id + '][viva]" min="0" step="1" inputmode="numeric" value="20">',
                    '</div>',
                    '<div class="col-6 col-md-4">',
                    '  <label class="form-label small">' + LABEL_TOTAL + '</label>',
                    '  <input type="text" class="form-control form-control-sm bg-light" readonly>',
                    '</div>',
                ].join('');
                row.appendChild(marks);
                boxEl.appendChild(row);
            });
        }

        function ensureRowTotal(row) {
            let sum = 0;
            row.querySelectorAll('.exam-marks-input').forEach(function (i) { sum += parseInt(i.value, 10) || 0; });
            const sumBox = row.querySelector('input[readonly]');
            if (sumBox) { sumBox.value = sum ? String(sum) : ''; }
        }

        subjectBox.addEventListener('wheel', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('exam-marks-input')) {
                e.preventDefault();
            }
        }, { passive: false });

        subjectBox.addEventListener('input', function (e) {
            const el = e.target;
            if (!el || !el.classList || !el.classList.contains('exam-marks-input')) { return; }
            const cleaned = el.value.replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, '');
            if (cleaned !== el.value) { el.value = cleaned; }
            const row = el.closest('.exam-subject-row');
            if (row) { ensureRowTotal(row); }
        });

        subjectBox.addEventListener('change', function (e) {
            const cb = e.target.closest('.exam-subject-check');
            if (!cb) { return; }
            const row = e.target.closest('.exam-subject-row');
            const marks = row ? row.querySelector('.exam-subject-marks') : null;
            if (marks) { marks.classList.toggle('d-none', !cb.checked); }
            if (cb.checked && row) { ensureRowTotal(row); }
        });

        if (examDateInput) {
            examDateInput.addEventListener('change', function () {
                document.querySelectorAll('#sendExamSubjects .exam-subject-date').forEach(function (el) {
                    el.value = examDateInput.value;
                });
            });
        }

        checkAll.addEventListener('change', function () {
            const items = document.querySelectorAll('#sendExamSubjects .exam-subject-row');
            const want = checkAll.checked;
            items.forEach(function (row) {
                const cb = row.querySelector('.exam-subject-check');
                if (cb && cb.checked !== want) {
                    cb.checked = want;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        function clearErrors() {
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
            var errBox = document.getElementById('sendExamFormErrors');
            if (errBox) { errBox.remove(); }
        }

        function toFieldName(key) {
            // Convert validation keys like "marks.3.written" into
            // the actual input name "marks[3][written]".
            var parts = String(key || '').split('.');
            if (parts.length === 1) { return parts[0]; }
            var head = parts.shift();
            var name = head + '[' + parts.join('][') + ']';
            return name;
        }

        function showErrors(errors) {
            clearErrors();
            Object.keys(errors || {}).forEach(function (key) {
                var field = form.querySelector('[name="' + toFieldName(key) + '"]');
                if (field) {
                    field.classList.add('is-invalid');
                    var msg = document.createElement('div');
                    msg.className = 'text-danger small mt-1';
                    msg.textContent = (errors[key] || []).join(', ');
                    field.parentNode.insertBefore(msg, field.nextSibling);
                }
            });
            var header = modalEl.querySelector('.modal-header');
            if (header && !document.getElementById('sendExamFormErrors')) {
                var errBox = document.createElement('div');
                errBox.id = 'sendExamFormErrors';
                errBox.className = 'alert alert-danger m-3 mb-0 py-2';
                errBox.textContent = Object.values(errors || {}).flat().join(' ');
                header.insertAdjacentElement('afterend', errBox);
            }
        }

        const batchRow = document.getElementById('sendExamBatchRow');
        const batchSelect = document.getElementById('sendExamBatchSelect');

        function fillBatch(batchId, url, name) {
            form.action = url;
            const raw = SUBJECTS_BY_BATCH[batchId] || [];
            const seen = {};
            subjects = raw.filter(function (s) {
                if (seen[s.id]) { return false; }
                seen[s.id] = true;
                return true;
            });
            batchHint.textContent = name || '';
            batchRow.style.display = 'none';
            renderSubjectRows();
        }

        if (window.Monetix && Monetix.delegate) {
            Monetix.delegate('click', '[data-send-exam], [data-create-exam]', function (e, btn) {
                e.preventDefault();
                const modalEl = document.getElementById('sendExamModal');
                const form = document.getElementById('sendExamForm');
                if (!modalEl || !form) { return; }

                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
                var errBox = document.getElementById('sendExamFormErrors');
                if (errBox) { errBox.remove(); }

                const titleInput = document.getElementById('sendExamTitle');
                if (titleInput) { titleInput.value = ''; }
                const examDateInput = document.getElementById('sendExamDate');
                if (examDateInput) { examDateInput.value = localDatetimeValue(new Date()); }
                const checkAll = document.getElementById('sendExamCheckAll');
                if (checkAll) { checkAll.checked = false; }
                const hint = document.getElementById('sendExamBatchHint');
                const batchRow = document.getElementById('sendExamBatchRow');
                const batchSelect = document.getElementById('sendExamBatchSelect');

                const batchId = btn.getAttribute('data-batch-id');
                if (batchId) {
                    form.action = btn.getAttribute('data-href') || '';
                    const raw = SUBJECTS_BY_BATCH[batchId] || [];
                    const seen = {};
                    subjects = raw.filter(function (s) {
                        if (seen[s.id]) { return false; }
                        seen[s.id] = true;
                        return true;
                    });
                    if (hint) { hint.textContent = btn.getAttribute('data-batch-name') || ''; }
                    if (batchRow) { batchRow.style.display = 'none'; }
                    renderSubjectRows();
                } else {
                    form.action = '';
                    subjects = [];
                    if (hint) { hint.textContent = ''; }
                    if (batchSelect) { batchSelect.value = ''; }
                    if (batchRow) { batchRow.style.display = ''; }
                    renderSubjectRows();
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }, 'mtx-send-exam');
        }

        if (batchSelect) {
            batchSelect.addEventListener('change', function () {
                const opt = batchSelect.selectedOptions[0];
                if (opt && opt.value) {
                    fillBatch(opt.value, opt.getAttribute('data-url') || '', opt.textContent);
                }
            });
        }

        form.addEventListener('submit', function (e) {
            if (!form.hasAttribute('data-ajax-enabled')) { return; }
            e.preventDefault();
            if (!window.Monetix || !Monetix.request) { return; }
            clearErrors();

            if (batchRow && batchRow.style.display !== 'none') {
                const opt = batchSelect.selectedOptions[0];
                if (!opt || !opt.value) {
                    const msg = document.createElement('div');
                    msg.className = 'text-danger small mt-1';
                    msg.textContent = MSG_BATCH_REQUIRED;
                    batchSelect.classList.add('is-invalid');
                    batchSelect.parentNode.appendChild(msg);
                    return;
                }
                fillBatch(opt.value, opt.getAttribute('data-url') || '', opt.textContent);
            }

            const checkedBoxes = document.querySelectorAll('.exam-subject-check:checked');
            let ok = true;
            checkedBoxes.forEach(function (cb) {
                if (!cb.checked) { return; }
                const row = cb.closest('.exam-subject-row');
                const marks = row ? row.querySelector('.exam-subject-marks') : null;
                if (!marks) { return; }
                const total = marks.querySelector('input[readonly]');
                if (total && total.value === '') {
                    ok = false;
                    total.classList.add('is-invalid');
                    const msg = document.createElement('div');
                    msg.className = 'text-danger small mt-1';
                    msg.textContent = MSG_MARKS_REQUIRED;
                    total.parentNode.insertBefore(msg, total.nextSibling);
                }
            });
            if (!ok) { return; }

            const submitBtn = form.querySelector('[type="submit"]');
            const restore = Monetix.loading(submitBtn);
            Monetix.request(form.action, { method: 'POST', body: new FormData(form) })
                .then(function (res) {
                    if (restore) { restore(); }
                    if (res && res.errors) { showErrors(res.errors); return; }
                    if (res && res.success === false) {
                        if (Monetix.toast) { Monetix.toast(res.message || 'Could not create the exam.', 'danger'); }
                        return;
                    }
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) { modal.hide(); }
                    if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
                    if (res && res.data && res.data.url) {
                        if (window.location.pathname === new URL(res.data.url, window.location.origin).pathname) {
                            if (Monetix.loadPage) { Monetix.loadPage(res.data.url, { preserveFocus: false }); }
                        } else {
                            window.location.href = res.data.url;
                        }
                    }
                })
                .catch(function () {
                    if (restore) { restore(); }
                    if (Monetix.toast) { Monetix.toast('Could not create the exam. Please try again.', 'danger'); }
                });
        });
    })();
    </script>
    @endpush
@endif