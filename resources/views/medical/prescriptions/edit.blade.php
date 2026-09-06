@extends('layouts.institute')

@section('title', 'Edit Prescription — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Prescription — {{ $prescription->prescription_number }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.prescriptions.show', $prescription) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.prescriptions.update', $prescription) }}" method="POST" id="prescription-edit-form">
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
                                    @selected((string) old('patient_id', $prescription->patient_id) === (string) $patient->id)>
                                    {{ $patient->full_name }} ({{ $patient->mr_number }})
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
                                    @selected((string) old('doctor_id', $prescription->doctor_id) === (string) $doctor->id)>
                                    {{ $doctor->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('doctor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="prescription_date">Date <span class="text-danger">*</span></label>
                        <input type="date" id="prescription_date" name="prescription_date"
                               class="form-control @error('prescription_date') is-invalid @enderror"
                               value="{{ old('prescription_date', $prescription->prescription_date?->format('Y-m-d')) }}" required>
                        @error('prescription_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="diagnosis">Diagnosis</label>
                        <input type="text" id="diagnosis" name="diagnosis"
                               class="form-control @error('diagnosis') is-invalid @enderror"
                               value="{{ old('diagnosis', $prescription->diagnosis) }}">
                        @error('diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chief_complaints">Chief Complaints</label>
                        <textarea id="chief_complaints" name="chief_complaints" rows="2"
                                  class="form-control @error('chief_complaints') is-invalid @enderror">{{ old('chief_complaints', $prescription->chief_complaints) }}</textarea>
                        @error('chief_complaints')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="examination_findings">Examination Findings</label>
                        <textarea id="examination_findings" name="examination_findings" rows="2"
                                  class="form-control @error('examination_findings') is-invalid @enderror">{{ old('examination_findings', $prescription->examination_findings) }}</textarea>
                        @error('examination_findings')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mt-3 mb-2">Medicines <span class="text-danger">*</span></h6>
            @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="rx-items-table">
                    <thead>
                        <tr><th style="min-width:220px;">Medicine</th><th>Dosage</th><th>Frequency</th><th>Days</th><th>Qty</th><th></th></tr>
                    </thead>
                    <tbody id="rx-items-body">
                        @foreach(old('items', $prescription->items->map(fn ($i) => [
                            'medicine_id' => $i->medicine_id,
                            'medicine_name' => $i->medicine_name,
                            'dosage' => $i->dosage,
                            'frequency' => $i->frequency,
                            'duration_days' => $i->duration_days,
                            'quantity' => $i->quantity,
                        ])->all()) as $i => $item)
                        <tr>
                            <td>
                                <input type="hidden" name="items[{{ $i }}][medicine_id]" class="rx-med-id" value="{{ $item['medicine_id'] ?? '' }}">
                                <input type="text" name="items[{{ $i }}][medicine_name]" class="form-control form-control-sm rx-med-name"
                                       list="rx-medicine-list" required maxlength="200" value="{{ $item['medicine_name'] ?? '' }}">
                            </td>
                            <td><input type="text" name="items[{{ $i }}][dosage]" class="form-control form-control-sm" required maxlength="50" value="{{ $item['dosage'] ?? '' }}"></td>
                            <td><input type="text" name="items[{{ $i }}][frequency]" class="form-control form-control-sm" required maxlength="50" value="{{ $item['frequency'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][duration_days]" class="form-control form-control-sm" min="1" value="{{ $item['duration_days'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control form-control-sm" min="1" value="{{ $item['quantity'] ?? 1 }}" required></td>
                            <td><button type="button" class="btn btn-sm btn-danger rx-remove">×</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="rx-add-item">
                <i class="bi bi-plus-lg me-1"></i>Add Medicine
            </button>

            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="advice">Advice</label>
                        <textarea id="advice" name="advice" rows="2"
                                  class="form-control @error('advice') is-invalid @enderror">{{ old('advice', $prescription->advice) }}</textarea>
                        @error('advice')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_date">Follow-up Date</label>
                        <input type="date" id="follow_up_date" name="follow_up_date"
                               class="form-control @error('follow_up_date') is-invalid @enderror"
                               value="{{ old('follow_up_date', $prescription->follow_up_date?->format('Y-m-d')) }}">
                        @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Prescription
                </button>
                <a href="{{ route('medical.prescriptions.show', $prescription) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<datalist id="rx-medicine-list">
    @foreach($medicines as $medicine)
        <option data-id="{{ $medicine->id }}" value="{{ $medicine->display_name }}"></option>
    @endforeach
</datalist>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.getElementById('rx-items-body');
    var addBtn = document.getElementById('rx-add-item');
    var catalog = {};
    document.querySelectorAll('#rx-medicine-list option').forEach(function (opt) {
        catalog[opt.value] = opt.getAttribute('data-id');
    });
    var index = body.querySelectorAll('tr').length;

    function bindRow(tr) {
        var nameInput = tr.querySelector('.rx-med-name');
        var idInput = tr.querySelector('.rx-med-id');
        if (nameInput && idInput) {
            nameInput.addEventListener('change', function () {
                idInput.value = catalog[nameInput.value] || '';
            });
        }
        var rm = tr.querySelector('.rx-remove');
        if (rm) { rm.addEventListener('click', function () { tr.remove(); }); }
    }

    body.querySelectorAll('tr').forEach(bindRow);

    addBtn.addEventListener('click', function () {
        var tmp = document.createElement('tbody');
        tmp.innerHTML = '<tr>' +
            '<td><input type="hidden" name="items[' + index + '][medicine_id]" class="rx-med-id">' +
            '<input type="text" name="items[' + index + '][medicine_name]" class="form-control form-control-sm rx-med-name" list="rx-medicine-list" required maxlength="200"></td>' +
            '<td><input type="text" name="items[' + index + '][dosage]" class="form-control form-control-sm" required maxlength="50"></td>' +
            '<td><input type="text" name="items[' + index + '][frequency]" class="form-control form-control-sm" required maxlength="50"></td>' +
            '<td><input type="number" name="items[' + index + '][duration_days]" class="form-control form-control-sm" min="1"></td>' +
            '<td><input type="number" name="items[' + index + '][quantity]" class="form-control form-control-sm" min="1" value="1" required></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger rx-remove">×</button></td>' +
            '</tr>';
        index++;
        var tr = tmp.firstChild;
        bindRow(tr);
        body.appendChild(tr);
    });

    document.getElementById('prescription-edit-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            alert('Please keep at least one medicine.');
        }
    });
})();
</script>
@endpush
