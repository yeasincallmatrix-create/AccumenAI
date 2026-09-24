@if ($user->hasPermission('training.courses.manage'))
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubjectModalLabel">
                    <i class="bi bi-journal-plus me-1"></i>Add Subject
                    <small class="text-muted d-block">{{ $course->name }}</small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-attach-tab" data-bs-toggle="tab" data-bs-target="#tab-attach" type="button" role="tab">
                            <i class="bi bi-check2-square me-1"></i>Attach Existing
                            <span class="badge bg-secondary ms-1">{{ $availableSubjects->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-create-tab" data-bs-toggle="tab" data-bs-target="#tab-create" type="button" role="tab">
                            <i class="bi bi-plus-square me-1"></i>Create New
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- Tab 1: Attach existing --}}
                    <div class="tab-pane fade show active" id="tab-attach" role="tabpanel">
                        <form method="POST" action="{{ route('training.courses.course-subjects.attach', $course) }}" id="attachSubjectsForm">
                            @csrf
                            <div class="form-check mb-2">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" id="modalSelectAllSubjects">
                                    Select all
                                </label>
                            </div>

                            <div class="border rounded p-2" style="max-height:320px; overflow-y:auto;">
                                @forelse ($availableSubjects as $subject)
                                    <div class="form-check py-1 border-bottom">
                                        <label class="form-check-label d-flex align-items-center justify-content-between w-100">
                                            <span>
                                                <input type="checkbox" class="form-check-input me-2 modal-subject-check"
                                                       name="subjects[]" value="{{ $subject->id }}"
                                                       @checked(in_array((int) $subject->id, $attachedIds, true))>
                                                <span class="fw-semibold">{{ $subject->name }}</span>
                                                @if ($subject->subject_code)
                                                    <span class="text-muted small ms-1">{{ $subject->subject_code }}</span>
                                                @endif
                                            </span>
                                            <span class="d-flex align-items-center gap-2">
                                                @if ($subject->category)
                                                    <span class="badge text-bg-light text-dark small">{{ $subject->category->name }}</span>
                                                @endif
                                                @if (in_array((int) $subject->id, $attachedIds, true))
                                                    <span class="badge text-bg-success">Attached</span>
                                                @endif
                                            </span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-muted small p-3 text-center">
                                        No active subjects found. Use the "Create New" tab.
                                    </div>
                                @endforelse
                            </div>

                            <div class="form-text text-muted small mt-2">
                                Checked subjects will be attached. Unchecked already-attached subjects will be removed.
                            </div>

                            <button type="submit" class="btn btn-primary mt-2">
                                <i class="bi bi-check-lg me-1"></i>Save Attached Subjects
                            </button>
                        </form>
                    </div>

                    {{-- Tab 2: Create new --}}
                    <div class="tab-pane fade" id="tab-create" role="tabpanel">
                        <form method="POST" action="{{ route('training.courses.course-subjects.create', $course) }}" id="createSubjectForm">
                            @csrf
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="modal_subject_name">Subject Name <span class="text-danger">*</span></label>
                                    <input type="text" id="modal_subject_name" name="name" class="form-control" maxlength="255"
                                           required placeholder="e.g. Adobe Premiere Pro">
                                    @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="modal_subject_short">Short Name</label>
                                    <input type="text" id="modal_subject_short" name="short_name" class="form-control" maxlength="100"
                                           placeholder="e.g. APP">
                                    @error('short_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="modal_subject_code">Code</label>
                                    <input type="text" id="modal_subject_code" name="subject_code" class="form-control" maxlength="50"
                                           placeholder="e.g. VD-02">
                                    @error('subject_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="modal_subject_category">Category <span class="text-danger">*</span></label>
                                    <select id="modal_subject_category" name="category_id" class="form-select" required>
                                        <option value="">— Select category —</option>
                                        @foreach ($subjectCategories as $cat)
                                            <option value="{{ $cat->id }}" @selected($course->category_id == $cat->id)>
                                                {{ $cat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="modal_subject_desc">Description</label>
                                    <textarea id="modal_subject_desc" name="description" class="form-control" rows="2"
                                              placeholder="Optional description"></textarea>
                                    @error('description') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            @if ($subjectCategories->isEmpty())
                                <div class="alert alert-warning small mt-3 mb-0">
                                    No categories found. Create a course category first.
                                </div>
                            @endif

                            <button type="submit" class="btn btn-primary mt-3" @disabled($subjectCategories->isEmpty())>
                                <i class="bi bi-plus-lg me-1"></i>Create &amp; Attach Subject
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var selectAll = document.getElementById('modalSelectAllSubjects');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.modal-subject-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }
})();
</script>
@endpush
@endif
