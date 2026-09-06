@extends('layouts.institute')

@section('title', 'Edit Stock — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Batch {{ $stock->batch_number }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.stock.show', $stock) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.pharmacy.stock.update', $stock) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="medicine_id">Medicine <span class="text-danger">*</span></label>
                        <select id="medicine_id" name="medicine_id" class="form-select @error('medicine_id') is-invalid @enderror" required>
                            <option value="">Select Medicine</option>
                            @foreach($medicines as $medicine)
                                <option value="{{ $medicine->id }}" @selected((string) old('medicine_id', $stock->medicine_id) === (string) $medicine->id)>
                                    {{ $medicine->display_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('medicine_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="batch_number">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" id="batch_number" name="batch_number" maxlength="50"
                               class="form-control @error('batch_number') is-invalid @enderror"
                               value="{{ old('batch_number', $stock->batch_number) }}" required>
                        @error('batch_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="manufacturing_date">Mfg Date</label>
                        <input type="date" id="manufacturing_date" name="manufacturing_date"
                               class="form-control @error('manufacturing_date') is-invalid @enderror"
                               value="{{ old('manufacturing_date', $stock->manufacturing_date?->format('Y-m-d')) }}">
                        @error('manufacturing_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="expiry_date">Expiry Date <span class="text-danger">*</span></label>
                        <input type="date" id="expiry_date" name="expiry_date"
                               class="form-control @error('expiry_date') is-invalid @enderror"
                               value="{{ old('expiry_date', $stock->expiry_date?->format('Y-m-d')) }}" required>
                        @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="received_date">Received Date</label>
                        <input type="date" id="received_date" name="received_date"
                               class="form-control @error('received_date') is-invalid @enderror"
                               value="{{ old('received_date', $stock->received_date?->format('Y-m-d')) }}">
                        @error('received_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="quantity_received">Qty Received <span class="text-danger">*</span></label>
                        <input type="number" id="quantity_received" name="quantity_received" min="1"
                               class="form-control @error('quantity_received') is-invalid @enderror"
                               value="{{ old('quantity_received', $stock->quantity_received) }}" required>
                        @error('quantity_received')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="current_quantity">Current Qty <span class="text-danger">*</span></label>
                        <input type="number" id="current_quantity" name="current_quantity" min="0"
                               class="form-control @error('current_quantity') is-invalid @enderror"
                               value="{{ old('current_quantity', $stock->current_quantity) }}" required>
                        @error('current_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="purchase_price">Buy Price <span class="text-danger">*</span></label>
                        <input type="number" id="purchase_price" name="purchase_price" min="0" step="0.01"
                               class="form-control @error('purchase_price') is-invalid @enderror"
                               value="{{ old('purchase_price', $stock->purchase_price) }}" required>
                        @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="selling_price">Sell Price <span class="text-danger">*</span></label>
                        <input type="number" id="selling_price" name="selling_price" min="0" step="0.01"
                               class="form-control @error('selling_price') is-invalid @enderror"
                               value="{{ old('selling_price', $stock->selling_price) }}" required>
                        @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="supplier_invoice_no">Supplier Invoice No</label>
                        <input type="text" id="supplier_invoice_no" name="supplier_invoice_no" maxlength="50"
                               class="form-control @error('supplier_invoice_no') is-invalid @enderror"
                               value="{{ old('supplier_invoice_no', $stock->supplier_invoice_no) }}">
                        @error('supplier_invoice_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <input type="text" id="notes" name="notes"
                               class="form-control @error('notes') is-invalid @enderror"
                               value="{{ old('notes', $stock->notes) }}">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Stock
                </button>
                <a href="{{ route('medical.pharmacy.stock.show', $stock) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
