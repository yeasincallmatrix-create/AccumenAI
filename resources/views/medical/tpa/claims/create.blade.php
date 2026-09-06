@extends('layouts.institute')

@section('title', 'New TPA Claim — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">New TPA Claim</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.tpa.claims.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.tpa.claims.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
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
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="invoice_id">Invoice <span class="text-danger">*</span></label>
                        <select id="invoice_id" name="invoice_id" class="form-select @error('invoice_id') is-invalid @enderror" required>
                            <option value="">Select Invoice</option>
                            @foreach($invoices as $invoice)
                                <option value="{{ $invoice->id }}"
                                    @selected((string) old('invoice_id', $selectedInvoice->id ?? '') === (string) $invoice->id)>
                                    {{ $invoice->invoice_number }} — {{ $invoice->patient->full_name ?? '' }} (due ৳{{ number_format($invoice->due_amount, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('invoice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="tpa_company_name">TPA Company <span class="text-danger">*</span></label>
                        <input type="text" id="tpa_company_name" name="tpa_company_name" maxlength="150"
                               class="form-control @error('tpa_company_name') is-invalid @enderror"
                               value="{{ old('tpa_company_name') }}" required>
                        @error('tpa_company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="policy_number">Policy Number <span class="text-danger">*</span></label>
                        <input type="text" id="policy_number" name="policy_number" maxlength="50"
                               class="form-control @error('policy_number') is-invalid @enderror"
                               value="{{ old('policy_number') }}" required>
                        @error('policy_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="claim_amount">Claim Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" id="claim_amount" name="claim_amount" min="0.01" step="0.01"
                               class="form-control @error('claim_amount') is-invalid @enderror"
                               value="{{ old('claim_amount') }}" required>
                        @error('claim_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="documents">Documents</label>
                        <textarea id="documents" name="documents" rows="2"
                                  class="form-control @error('documents') is-invalid @enderror"
                                  placeholder="List attached documents...">{{ old('documents') }}</textarea>
                        @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="2"
                                  class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Submit Claim
                </button>
                <a href="{{ route('medical.tpa.claims.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
