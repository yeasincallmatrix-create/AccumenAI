@extends('layouts.institute')

@section('title', 'Write Prescription — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Write Prescription</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.prescriptions.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.prescriptions.store') }}" method="POST" id="prescription-form">
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
                        <label class="form-label" for="prescription_date">Date <span class="text-danger">*</span></label>
                        <x-tdate-input name="prescription_date" :value="old('prescription_date', date('Y-m-d'))" id="prescription_date" :class="'form-control'.($errors->has('prescription_date') ? ' is-invalid' : '')" required />
                        @error('prescription_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="diagnosis">Diagnosis</label>
                        <input type="text" id="diagnosis" name="diagnosis"
                               class="form-control @error('diagnosis') is-invalid @enderror"
                               value="{{ old('diagnosis') }}">
                        @error('diagnosis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chief_complaints">Chief Complaints</label>
                        <textarea id="chief_complaints" name="chief_complaints" rows="2"
                                  class="form-control @error('chief_complaints') is-invalid @enderror">{{ old('chief_complaints') }}</textarea>
                        @error('chief_complaints')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="examination_findings">Examination Findings</label>
                        <textarea id="examination_findings" name="examination_findings" rows="2"
                                  class="form-control @error('examination_findings') is-invalid @enderror">{{ old('examination_findings') }}</textarea>
                        @error('examination_findings')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mt-3 mb-2">Medicines <span class="text-danger">*</span></h6>
            @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="rx-items-table">
                    <thead>
                        <tr>
                            <th style="min-width:220px;">Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Days</th>
                            <th>Qty</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="rx-items-body">
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
                                  class="form-control @error('advice') is-invalid @enderror">{{ old('advice') }}</textarea>
                        @error('advice')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_date">Follow-up Date</label>
                        <x-tdate-input name="follow_up_date" :value="old('follow_up_date')" id="follow_up_date" :class="'form-control'.($errors->has('follow_up_date') ? ' is-invalid' : '')" />
                        @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-2">
                <i class="bi bi-shield-check me-1"></i>
                Allergy, contraindication and duplicate-therapy checks run on save. Blocking issues refuse the prescription; milder overlaps are shown as warnings.
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Prescription (Draft)
                </button>
                <a href="{{ route('medical.prescriptions.index') }}" class="btn btn-secondary">Cancel</a>
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
    var index = 0;

    function rowHtml(i) {
        return '<tr>' +
            '<td><input type="hidden" name="items[' + i + '][medicine_id]" class="rx-med-id">' +
            '<input type="text" name="items[' + i + '][medicine_name]" class="form-control form-control-sm rx-med-name" list="rx-medicine-list" required maxlength="200" placeholder="Type or pick medicine"></td>' +
            '<td><input type="text" name="items[' + i + '][dosage]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 500mg"></td>' +
            '<td><input type="text" name="items[' + i + '][frequency]" class="form-control form-control-sm" required maxlength="50" placeholder="e.g. 1+0+1"></td>' +
            '<td><input type="number" name="items[' + i + '][duration_days]" class="form-control form-control-sm" min="1" placeholder="Days"></td>' +
            '<td><input type="number" name="items[' + i + '][quantity]" class="form-control form-control-sm" min="1" value="1" required></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger rx-remove">×</button></td>' +
            '</tr>';
    }

    function bindRow(tr) {
        var nameInput = tr.querySelector('.rx-med-name');
        var idInput = tr.querySelector('.rx-med-id');
        nameInput.addEventListener('change', function () {
            idInput.value = catalog[nameInput.value] || '';
        });
        tr.querySelector('.rx-remove').addEventListener('click', function () {
            tr.remove();
        });
    }

    function addRow() {
        var tmp = document.createElement('tbody');
        tmp.innerHTML = rowHtml(index++);
        var tr = tmp.firstChild;
        bindRow(tr);
        body.appendChild(tr);
    }

    addBtn.addEventListener('click', addRow);
    addRow();

    document.getElementById('prescription-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            alert('Please add at least one medicine.');
        }
    });
})();
</script>
@endpush
