@extends('layouts.standalone')

@section('title', 'Fee Structure — AccumenAI')
@section('page_title', 'Finance')

@section('content')

@php
    $action = $structure === null
        ? route('finance.education.fee-structures.store')
        : route('finance.education.fee-structures.update', $structure);
    $selectedItems = $structure?->items ?? collect();
@endphp

<div class="standalone-heading">
    <h4>{{ $structure === null ? 'New' : 'Edit' }} Fee Structure</h4>
    <p>Choose the optional branch / course / batch / academic-year target and the fee items that make up the bill. The installment plan spreads the payable across due dates.</p>
    <a href="{{ route('finance.education.fee-structures.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Fee Structures</a>
</div>

<form method="POST" action="{{ $action }}">
    @csrf
    @if ($structure !== null) @method('PUT') @endif

    <div class="admin-card mb-3">
        <h6 class="card-title">Details</h6>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label mb-1">Name</label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $structure?->name) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Course <span class="text-muted">(optional)</span></label>
                <select class="form-select form-select-sm" name="course_id">
                    <option value="">Any course</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected(old('course_id', $structure?->course_id) == $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Batch <span class="text-muted">(optional)</span></label>
                <select class="form-select form-select-sm" name="batch_id">
                    <option value="">Any batch</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->id }}" @selected(old('batch_id', $structure?->batch_id) == $batch->id)>{{ $batch->name }} ({{ $batch->course?->name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Academic year <span class="text-muted">(optional)</span></label>
                <select class="form-select form-select-sm" name="academic_year_id">
                    <option value="">Any year</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected(old('academic_year_id', $structure?->academic_year_id) == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $structure?->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <h6 class="card-title">Installment plan</h6>
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label mb-1">Number of installments</label>
                <input type="number" class="form-control form-control-sm" name="installments_count" min="1" max="12" value="{{ old('installments_count', $structure?->installments_count ?? 1) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Interval (days)</label>
                <input type="number" class="form-control form-control-sm" name="installments_interval_days" min="0" max="730" value="{{ old('installments_interval_days', $structure?->installments_interval_days ?? 30) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Billing Frequency</label>
                <select class="form-select form-select-sm" name="billing_frequency">
                    <option value="monthly" @selected(old('billing_frequency', $structure?->billing_frequency ?? 'monthly') === 'monthly')">Monthly</option>
                    <option value="quarterly" @selected(old('billing_frequency', $structure?->billing_frequency) === 'quarterly')">Quarterly</option>
                    <option value="annually" @selected(old('billing_frequency', $structure?->billing_frequency) === 'annually')">Annually</option>
                    <option value="one_time" @selected(old('billing_frequency', $structure?->billing_frequency) === 'one_time')">One-time</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">&nbsp;</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="auto_generate_monthly" value="1" id="autoGenerate" @checked(old('auto_generate_monthly', $structure?->auto_generate_monthly ?? false))>
                    <label class="form-check-label small" for="autoGenerate">Auto-generate monthly invoices</label>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="card-title mb-0">Fee items</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addItemRow"><i class="bi bi-plus-lg me-1"></i>Add item</button>
        </div>
        <div id="itemsContainer">
            @forelse ($selectedItems as $item)
                <div class="row g-2 mb-2 item-row">
                    <div class="col-md-5">
                        <select class="form-select form-select-sm item-head" name="items[{{ $loop->index }}][fee_head_id]" required>
                            <option value="">Select fee head</option>
                            @foreach ($feeHeads as $feeHead)
                                <option value="{{ $feeHead->id }}" @selected((int) $item->fee_head_id === (int) $feeHead->id)>{{ $feeHead->name }} ({{ ucwords(str_replace('_', ' ', $feeHead->type)) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm item-amount" name="items[{{ $loop->index }}][amount]" value="{{ $item->amount }}" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-center gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="items[{{ $loop->index }}][is_optional]" value="1" @checked($item->is_optional)>
                            <label class="form-check-label small">Optional</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            @empty
                <div class="text-muted small mb-2">No items yet — add at least one fee item.</div>
            @endforelse
        </div>
        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $structure === null ? 'Create' : 'Save' }} fee structure</button>
            <a href="{{ route('finance.education.fee-structures.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </div>
</form>

<script>
(function () {
    const container = document.getElementById('itemsContainer');
    const addBtn = document.getElementById('addItemRow');
    const heads = @json($feeHeads->map(fn ($h) => ['id' => $h->id, 'label' => $h->name.' ('.ucwords(str_replace('_', ' ', $h->type)).')']));

    function buildRow() {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 item-row';
        const index = container.querySelectorAll('.item-row').length;
        const options = heads.map(h => `<option value="${h.id}">${h.label}</option>`).join('');
        row.innerHTML = `
            <div class="col-md-5">
                <select class="form-select form-select-sm item-head" name="items[${index}][fee_head_id]" required>
                    <option value="">Select fee head</option>${options}
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm item-amount" name="items[${index}][amount]" required>
            </div>
            <div class="col-md-3 d-flex align-items-center gap-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="items[${index}][is_optional]" value="1">
                    <label class="form-check-label small">Optional</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-trash"></i></button>
            </div>`;
        return row;
    }

    addBtn.addEventListener('click', () => container.appendChild(buildRow()));

    container.addEventListener('click', (e) => {
        if (e.target.closest('.remove-item')) {
            e.target.closest('.item-row').remove();
        }
    });
})();
</script>

@endsection