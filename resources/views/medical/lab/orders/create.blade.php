@extends('layouts.institute')

@section('title', 'New Lab Order — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">New Lab Order</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.lab.orders.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.lab.orders.store') }}" method="POST" id="lab-order-form">
            @csrf
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="patient_id">Patient <span class="text-danger">*</span></label>
                        <select id="patient_id" name="patient_id" class="form-select @error('patient_id') is-invalid @enderror" required>
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}"
                                    @selected((string) old('patient_id', $selectedPatient->id ?? '') === (string) $patient->id)>
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
                                <option value="{{ $doctor->id }}" @selected((string) old('doctor_id') === (string) $doctor->id)>
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
                                <option value="{{ $value }}" @selected(old('priority', 'routine') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="prescription_id">Linked Prescription (optional)</label>
                        <select id="prescription_id" name="prescription_id" class="form-select @error('prescription_id') is-invalid @enderror">
                            <option value="">None</option>
                            @foreach($prescriptions as $prescription)
                                <option value="{{ $prescription->id }}"
                                    @selected((string) old('prescription_id', $selectedPrescription->id ?? '') === (string) $prescription->id)>
                                    {{ clinical_no($prescription->prescription_number) }}
                                </option>
                            @endforeach
                        </select>
                        @error('prescription_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="order_date">Order Date</label>
                        <x-tdate-input name="order_date" :value="old('order_date', date('Y-m-d'))" id="order_date" :class="'form-control'.($errors->has('order_date') ? ' is-invalid' : '')" />
                        @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="clinical_notes">Clinical Notes</label>
                        <textarea id="clinical_notes" name="clinical_notes" rows="2"
                                  class="form-control @error('clinical_notes') is-invalid @enderror">{{ old('clinical_notes') }}</textarea>
                        @error('clinical_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mt-3 mb-2">Tests <span class="text-danger">*</span></h6>
            @error('tests')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="row">
                @foreach($tests as $test)
                <div class="col-md-4">
                    <div class="form-check mb-2">
                        <input type="checkbox" id="test-{{ $test->id }}" name="tests[{{ $loop->index }}][lab_test_id]"
                               value="{{ $test->id }}" class="form-check-input">
                        <label class="form-check-label" for="test-{{ $test->id }}">
                            {{ $test->display_name }}
                            <span class="text-muted">— ৳{{ number_format($test->price, 2) }}</span>
                        </label>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Create Order
                </button>
                <a href="{{ route('medical.lab.orders.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.getElementById('lab-order-form').addEventListener('submit', function (e) {
        var checked = document.querySelectorAll('#lab-order-form input[type="checkbox"]:checked');
        if (checked.length === 0) {
            e.preventDefault();
            alert('Please select at least one test.');
        }
    });
})();
</script>
@endpush
