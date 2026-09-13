@extends('layouts.institute')

@section('title', 'Edit Lab Order — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Order — {{ clinical_no($order->order_number) }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.lab.orders.show', $order) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Only orders in <strong>ordered</strong> status can be edited. The test set is replaced wholesale (no results entered yet at this stage).
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.lab.orders.update', $order) }}" method="POST" id="lab-order-edit-form">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="patient_id">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}"
                                    @selected((string) old('patient_id', $order->patient_id) === (string) $patient->id)>
                                    {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})
                                </option>
                            @endforeach
                        </select>
                        @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="doctor_id">Doctor <span class="text-danger">*</span></label>
                        <select id="doctor_id" name="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror" required>
                            <option value="">Select Doctor</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}"
                                    @selected((string) old('doctor_id', $order->doctor_id) === (string) $doctor->id)>
                                    {{ $doctor->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('doctor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="priority">Priority <span class="text-danger">*</span></label>
                        <select id="priority" name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                            @foreach(['routine' => 'Routine', 'urgent' => 'Urgent', 'emergency' => 'Emergency'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $order->priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="clinical_notes">Clinical Notes</label>
                        <textarea id="clinical_notes" name="clinical_notes" rows="2"
                                  class="form-control @error('clinical_notes') is-invalid @enderror">{{ old('clinical_notes', $order->clinical_notes) }}</textarea>
                        @error('clinical_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            @php
                $selectedTests = old('tests')
                    ? collect(old('tests'))->pluck('lab_test_id')->map(fn ($v) => (int) $v)->all()
                    : $order->results->pluck('lab_test_id')->map(fn ($v) => (int) $v)->all();
            @endphp
            <h6 class="mt-3 mb-2">Tests <span class="text-danger">*</span></h6>
            @error('tests')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="row">
                @foreach($tests as $test)
                <div class="col-md-4">
                    <div class="form-check mb-2">
                        <input type="checkbox" id="test-{{ $test->id }}" name="tests[{{ $loop->index }}][lab_test_id]"
                               value="{{ $test->id }}" class="form-check-input"
                               @checked(in_array((int) $test->id, $selectedTests, true))>
                        <label class="form-check-label" for="test-{{ $test->id }}">
                            {{ $test->display_name }}
                        </label>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Order
                </button>
                <a href="{{ route('medical.lab.orders.show', $order) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.getElementById('lab-order-edit-form').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('#lab-order-edit-form input[type="checkbox"]:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one test.');
        }
    });
})();
</script>
@endpush
