@extends('layouts.institute')

@section('title', 'New Invoice — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">New Invoice</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.billing.invoices.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.billing.invoices.store') }}" method="POST" id="invoice-form">
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
                        <label class="form-label" for="type">Invoice Type <span class="text-danger">*</span></label>
                        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            @foreach(['opd' => 'OPD', 'ipd' => 'IPD', 'pharmacy' => 'Pharmacy', 'lab' => 'Lab', 'surgery' => 'Surgery'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $invoiceType) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4" id="admission-wrap" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label" for="admission_id">Admission (required for IPD)</label>
                        <select id="admission_id" name="admission_id" class="form-select @error('admission_id') is-invalid @enderror">
                            <option value="">Select Admission</option>
                            @foreach($admissions as $admission)
                                <option value="{{ $admission->id }}"
                                    @selected((string) old('admission_id', $selectedAdmission->id ?? '') === (string) $admission->id)>
                                    {{ $admission->patient->full_name ?? '' }} — <x-tdate :value="$admission->admission_date" fallback="d M Y" />
                                </option>
                            @endforeach
                        </select>
                        @error('admission_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <input type="text" id="notes" name="notes"
                               class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mt-3 mb-2">Line Items <span class="text-danger">*</span> <small class="text-muted">(5% tax auto-applied)</small></h6>
            @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="invoice-items-table">
                    <thead><tr><th style="min-width:240px;">Description</th><th>Amount (৳)</th><th>Qty</th><th>Discount (৳)</th><th></th></tr></thead>
                    <tbody id="invoice-items-body">
                        @foreach(old('items', [['description' => '', 'amount' => '', 'quantity' => 1, 'discount' => 0]]) as $i => $item)
                        <tr>
                            <td><input type="text" name="items[{{ $i }}][description]" class="form-control form-control-sm" required maxlength="255" value="{{ $item['description'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][amount]" class="form-control form-control-sm inv-amount" min="0" step="0.01" required value="{{ $item['amount'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control form-control-sm inv-qty" min="1" value="{{ $item['quantity'] ?? 1 }}" required></td>
                            <td><input type="number" name="items[{{ $i }}][discount]" class="form-control form-control-sm inv-disc" min="0" step="0.01" value="{{ $item['discount'] ?? 0 }}"></td>
                            <td><button type="button" class="btn btn-sm btn-danger inv-remove">×</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" id="invoice-add-item">
                <i class="bi bi-plus-lg me-1"></i>Add Item
            </button>
            <p class="mt-2 mb-0"><strong>Estimated Total: ৳<span id="invoice-estimate">0.00</span></strong> <small class="text-muted">(incl. 5% tax, minus discounts)</small></p>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Create Invoice
                </button>
                <a href="{{ route('medical.billing.invoices.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.getElementById('invoice-items-body');
    var index = body.querySelectorAll('tr').length;
    var typeSel = document.getElementById('type');
    var admWrap = document.getElementById('admission-wrap');

    function toggleAdmission() {
        admWrap.style.display = typeSel.value === 'ipd' ? '' : 'none';
    }
    typeSel.addEventListener('change', toggleAdmission);
    toggleAdmission();

    function estimate() {
        var total = 0, disc = 0;
        body.querySelectorAll('tr').forEach(function (tr) {
            var a = parseFloat(tr.querySelector('.inv-amount').value) || 0;
            var q = parseInt(tr.querySelector('.inv-qty').value, 10) || 0;
            var d = parseFloat(tr.querySelector('.inv-disc').value) || 0;
            total += a * q; disc += d;
        });
        document.getElementById('invoice-estimate').textContent = (total * 1.05 - disc).toFixed(2);
    }

    body.addEventListener('input', estimate);
    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('inv-remove') && body.querySelectorAll('tr').length > 1) {
            e.target.closest('tr').remove();
            estimate();
        }
    });

    document.getElementById('invoice-add-item').addEventListener('click', function () {
        var tmp = document.createElement('tbody');
        tmp.innerHTML = '<tr>' +
            '<td><input type="text" name="items[' + index + '][description]" class="form-control form-control-sm" required maxlength="255"></td>' +
            '<td><input type="number" name="items[' + index + '][amount]" class="form-control form-control-sm inv-amount" min="0" step="0.01" required></td>' +
            '<td><input type="number" name="items[' + index + '][quantity]" class="form-control form-control-sm inv-qty" min="1" value="1" required></td>' +
            '<td><input type="number" name="items[' + index + '][discount]" class="form-control form-control-sm inv-disc" min="0" step="0.01" value="0"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger inv-remove">×</button></td>' +
            '</tr>';
        index++;
        body.appendChild(tmp.firstChild);
    });

    document.getElementById('invoice-form').addEventListener('submit', function (e) {
        if (body.querySelectorAll('tr').length === 0) {
            e.preventDefault();
            alert('Please add at least one line item.');
        }
    });

    estimate();
})();
</script>
@endpush
