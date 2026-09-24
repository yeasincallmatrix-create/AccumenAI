<div class="modal fade" id="gpaModelModal" tabindex="-1" aria-labelledby="gpaModelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('training.exams.gpa-model') }}" id="gpaModelForm">
                @csrf
                <input type="hidden" name="tab" value="{{ $activeTab ?? 'exams' }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="gpaModelModalLabel">
                        <i class="bi bi-gear me-1"></i>Configure GPA Model
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Define grade bands used for exam results. Each row maps a grade letter to a score range.
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger small mb-3">
                            @foreach ($errors->all() as $message)
                                <div>{{ $message }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="gpaBandsTable">
                            <thead>
                                <tr>
                                    <th style="width:35%">Grade</th>
                                    <th style="width:25%">From Min</th>
                                    <th style="width:25%">To Max</th>
                                    <th style="width:15%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $gpaRows = old('rows') ?? ($gpaModel ?? []); @endphp
                                @forelse ($gpaRows as $index => $row)
                                    @php $row = is_array($row) ? $row : (array) $row; @endphp
                                    <tr>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="rows[{{ $index }}][grade]"
                                                   value="{{ $row['grade'] ?? '' }}" maxlength="20" placeholder="A+" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="rows[{{ $index }}][min_score]"
                                                   value="{{ $row['min_score'] ?? 0 }}" step="0.01" min="0" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="rows[{{ $index }}][max_score]"
                                                   value="{{ $row['max_score'] ?? 0 }}" step="0.01" min="0" required>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger gpa-remove-row" title="Remove row">
                                                <i class="bi bi-dash-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="gpa-empty-row">
                                        <td colspan="4" class="text-center text-muted py-3">No grade bands — click + to add.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="gpaAddRow">
                        <i class="bi bi-plus-lg me-1"></i>Add Row
                    </button>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save GPA Model
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var table = document.getElementById('gpaBandsTable');
    if (!table) { return; }
    var tbody = table.querySelector('tbody');
    var addBtn = document.getElementById('gpaAddRow');

    function reindex() {
        Array.prototype.slice.call(tbody.querySelectorAll('tr')).forEach(function (tr, i) {
            tr.querySelectorAll('input').forEach(function (el) {
                if (el.name) {
                    el.name = el.name.replace(/rows\[\d+\]/, 'rows[' + i + ']');
                }
            });
        });
    }

    function removeEmpty() {
        var empty = tbody.querySelector('.gpa-empty-row');
        if (empty) { empty.remove(); }
    }

    function bindRemove(btn) {
        btn.addEventListener('click', function () {
            btn.closest('tr').remove();
            if (!tbody.querySelectorAll('tr').length) {
                tbody.innerHTML = '<tr class="gpa-empty-row"><td colspan="4" class="text-center text-muted py-3">No grade bands — click + to add.</td></tr>';
            }
            reindex();
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            removeEmpty();
            var i = tbody.querySelectorAll('tr').length;
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" class="form-control form-control-sm" name="rows[' + i + '][grade]" maxlength="20" placeholder="A+" required></td>' +
                '<td><input type="number" class="form-control form-control-sm" name="rows[' + i + '][min_score]" value="0" step="0.01" min="0" required></td>' +
                '<td><input type="number" class="form-control form-control-sm" name="rows[' + i + '][max_score]" value="0" step="0.01" min="0" required></td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger gpa-remove-row" title="Remove row"><i class="bi bi-dash-lg"></i></button></td>';
            tbody.appendChild(tr);
            bindRemove(tr.querySelector('.gpa-remove-row'));
        });
    }

    tbody.querySelectorAll('.gpa-remove-row').forEach(bindRemove);
})();
</script>
@endpush
