@extends('layouts.institute')

@section('title', 'Edit Medicine — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Medicine — {{ $medicine->display_name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.medicines.show', $medicine) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.pharmacy.medicines.update', $medicine) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                        <input type="text" id="code" name="code" maxlength="50"
                               class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code', $medicine->code) }}" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="generic_name">Generic Name <span class="text-danger">*</span></label>
                        <input type="text" id="generic_name" name="generic_name" maxlength="150"
                               class="form-control @error('generic_name') is-invalid @enderror"
                               value="{{ old('generic_name', $medicine->generic_name) }}" required>
                        @error('generic_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="brand_name">Brand Name</label>
                        <input type="text" id="brand_name" name="brand_name" maxlength="150"
                               class="form-control @error('brand_name') is-invalid @enderror"
                               value="{{ old('brand_name', $medicine->brand_name) }}">
                        @error('brand_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="category">Category</label>
                        <input type="text" id="category" name="category" maxlength="100"
                               class="form-control @error('category') is-invalid @enderror"
                               value="{{ old('category', $medicine->category) }}">
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="dosage_form">Dosage Form <span class="text-danger">*</span></label>
                        <select id="dosage_form" name="dosage_form" class="form-select @error('dosage_form') is-invalid @enderror" required>
                            <option value="">Select</option>
                            @foreach(['Tablet', 'Capsule', 'Syrup', 'Injection', 'Drops', 'Cream', 'Ointment', 'Inhaler', 'Suppository'] as $form)
                                <option value="{{ $form }}" @selected(old('dosage_form', $medicine->dosage_form) === $form)>{{ $form }}</option>
                            @endforeach
                        </select>
                        @error('dosage_form')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="strength">Strength</label>
                        <input type="text" id="strength" name="strength" maxlength="50"
                               class="form-control @error('strength') is-invalid @enderror"
                               value="{{ old('strength', $medicine->strength) }}">
                        @error('strength')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="unit">Unit <span class="text-danger">*</span></label>
                        <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                            <option value="">Select</option>
                            @foreach(['Strip', 'Box', 'Bottle', 'Vial', 'Tube', 'Piece'] as $unit)
                                <option value="{{ $unit }}" @selected(old('unit', $medicine->unit) === $unit)>{{ $unit }}</option>
                            @endforeach
                        </select>
                        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="pack_size">Pack Size <span class="text-danger">*</span></label>
                        <input type="number" id="pack_size" name="pack_size" min="1"
                               class="form-control @error('pack_size') is-invalid @enderror"
                               value="{{ old('pack_size', $medicine->pack_size) }}" required>
                        @error('pack_size')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="purchase_price">Buy Price <span class="text-danger">*</span></label>
                        <input type="number" id="purchase_price" name="purchase_price" min="0" step="0.01"
                               class="form-control @error('purchase_price') is-invalid @enderror"
                               value="{{ old('purchase_price', $medicine->purchase_price) }}" required>
                        @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="selling_price">Sell Price <span class="text-danger">*</span></label>
                        <input type="number" id="selling_price" name="selling_price" min="0" step="0.01"
                               class="form-control @error('selling_price') is-invalid @enderror"
                               value="{{ old('selling_price', $medicine->selling_price) }}" required>
                        @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="vat_percentage">VAT %</label>
                        <input type="number" id="vat_percentage" name="vat_percentage" min="0" max="100" step="0.01"
                               class="form-control @error('vat_percentage') is-invalid @enderror"
                               value="{{ old('vat_percentage', $medicine->vat_percentage) }}">
                        @error('vat_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="reorder_level">Reorder Level <span class="text-danger">*</span></label>
                        <input type="number" id="reorder_level" name="reorder_level" min="0"
                               class="form-control @error('reorder_level') is-invalid @enderror"
                               value="{{ old('reorder_level', $medicine->reorder_level) }}" required>
                        @error('reorder_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="reorder_quantity">Reorder Qty <span class="text-danger">*</span></label>
                        <input type="number" id="reorder_quantity" name="reorder_quantity" min="0"
                               class="form-control @error('reorder_quantity') is-invalid @enderror"
                               value="{{ old('reorder_quantity', $medicine->reorder_quantity) }}" required>
                        @error('reorder_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="checkbox" id="requires_prescription" name="requires_prescription" value="1"
                               class="form-check-input" @checked(old('requires_prescription', $medicine->requires_prescription))>
                        <label class="form-check-label" for="requires_prescription">Requires Prescription</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input type="checkbox" id="is_controlled" name="is_controlled" value="1"
                               class="form-check-input" @checked(old('is_controlled', $medicine->is_controlled))>
                        <label class="form-check-label" for="is_controlled">Controlled Substance</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="side_effects">Side Effects</label>
                        <textarea id="side_effects" name="side_effects" rows="2"
                                  class="form-control @error('side_effects') is-invalid @enderror">{{ old('side_effects', $medicine->side_effects) }}</textarea>
                        @error('side_effects')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="contraindications">Contraindications</label>
                        <textarea id="contraindications" name="contraindications" rows="2"
                                  class="form-control @error('contraindications') is-invalid @enderror">{{ old('contraindications', $medicine->contraindications) }}</textarea>
                        @error('contraindications')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="storage_conditions">Storage Conditions</label>
                        <textarea id="storage_conditions" name="storage_conditions" rows="2"
                                  class="form-control @error('storage_conditions') is-invalid @enderror">{{ old('storage_conditions', $medicine->storage_conditions) }}</textarea>
                        @error('storage_conditions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                @if(mawa_dgda_enabled())
                <div class="col-12">
                    <hr class="my-2">
                    <h6 class="text-muted">DGDA Registry (optional)
                        @if($medicine->dgda_status)
                            <span class="badge bg-secondary ms-1">{{ ucfirst($medicine->dgda_status) }}</span>
                        @endif
                    </h6>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="dgda_code">DGDA Code</label>
                        <input type="text" id="dgda_code" name="dgda_code" maxlength="100"
                               class="form-control @error('dgda_code') is-invalid @enderror"
                               value="{{ old('dgda_code', $medicine->dgda_code) }}" placeholder="e.g. DGDA-DR-000123">
                        @error('dgda_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="dgda_dar_number">DAR Number</label>
                        <input type="text" id="dgda_dar_number" name="dgda_dar_number" maxlength="100"
                               class="form-control @error('dgda_dar_number') is-invalid @enderror"
                               value="{{ old('dgda_dar_number', $medicine->dgda_dar_number) }}" placeholder="e.g. DAR-45678">
                        @error('dgda_dar_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="dgda_concept_id">Concept ID</label>
                        <input type="text" id="dgda_concept_id" name="dgda_concept_id" maxlength="100"
                               class="form-control @error('dgda_concept_id') is-invalid @enderror"
                               value="{{ old('dgda_concept_id', $medicine->dgda_concept_id) }}">
                        @error('dgda_concept_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                @endif
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="form-check-input" @checked(old('is_active', $medicine->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Medicine
                </button>
                <a href="{{ route('medical.pharmacy.medicines.show', $medicine) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
