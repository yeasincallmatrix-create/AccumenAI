<div class="modal fade" id="createExamModal" tabindex="-1" aria-labelledby="createExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('training.exams.store') }}" id="createExamForm">
                @csrf
                <input type="hidden" name="from_modal" value="1">
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'exams' }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="createExamModalLabel">
                        <i class="bi bi-plus-circle me-1"></i>Create Exam
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="modal_title">Exam Title *</label>
                            <input type="text" id="modal_title" name="title" class="form-control" maxlength="200" value="{{ old('title') }}" required>
                            @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal_batch_id">Batch *</label>
                            <select id="modal_batch_id" name="batch_id" class="form-select" required>
                                <option value="">— Select batch —</option>
                                @foreach ($batches as $batch)
                                    <option value="{{ $batch->id }}" @selected(old('batch_id', request('batch_id')) == $batch->id)>
                                        {{ $batch->name }}@if($batch->batch_code) ({{ $batch->batch_code }})@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('batch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @if ($batches->isEmpty())
                                <div class="form-text text-warning small">No active batches found. Create a batch first.</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal_exam_date">Exam Date</label>
                            <input type="datetime-local" id="modal_exam_date" name="exam_date" class="form-control" value="{{ old('exam_date') }}">
                            @error('exam_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_full_marks">Full Marks *</label>
                            <input type="number" id="modal_full_marks" name="full_marks" class="form-control" step="0.01" min="0.01" value="{{ old('full_marks') }}" required>
                            @error('full_marks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_pass_marks">Pass Marks *</label>
                            <input type="number" id="modal_pass_marks" name="pass_marks" class="form-control" step="0.01" min="0" value="{{ old('pass_marks') }}" required>
                            @error('pass_marks') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_written_percent">Written %</label>
                            <input type="number" id="modal_written_percent" name="written_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('written_percent', 0) }}">
                            @error('written_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_practical_percent">Practical %</label>
                            <input type="number" id="modal_practical_percent" name="practical_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('practical_percent', 0) }}">
                            @error('practical_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_viva_percent">Viva %</label>
                            <input type="number" id="modal_viva_percent" name="viva_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('viva_percent', 0) }}">
                            @error('viva_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal_status">Status</label>
                            <select id="modal_status" name="status" class="form-select">
                                @foreach (['scheduled', 'ongoing', 'completed', 'cancelled'] as $s)
                                    <option value="{{ $s }}" {{ old('status', 'scheduled') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                            @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" @disabled($batches->isEmpty())>
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var modalEl = document.getElementById('createExamModal');
    if (!modalEl) { return; }

    @if ($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    @endif
})();
</script>
@endpush
