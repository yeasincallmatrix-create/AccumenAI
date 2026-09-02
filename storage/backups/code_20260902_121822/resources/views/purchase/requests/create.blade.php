@extends('layouts.standalone')

@section('title', 'New Purchase Request — AccumenAI')
@section('page_title', 'New Purchase Request')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>New Purchase Request</h4>
    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="{{ route('purchase.requests.index') }}"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<form method="POST" action="{{ route('purchase.requests.store') }}">
    @csrf
    <div class="card mb-4">
        <div class="card-header"><strong>Request Information</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Request Date <span class="text-danger">*</span></label>
                    <input type="date" name="request_date" value="{{ old('request_date', now()->toDateString()) }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Required By</label>
                    <input type="date" name="required_by_date" value="{{ old('required_by_date') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">None</option>
                        @php
                            $warehouses = \App\Models\InventoryWarehouse::withoutGlobalScopes()->where('institute_id', $institute->id)->orderBy('name')->get();
                        @endphp
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (string)old('warehouse_id') === (string)$wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Justification</label>
                    <textarea name="justification" class="form-control" rows="2">{{ old('justification') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Line Items</strong>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="linesTable">
                <thead>
                    <tr>
                        <th style="width:30%">Description *</th>
                        <th style="width:15%">Qty *</th>
                        <th style="width:10%">Unit</th>
                        <th style="width:20%">Est. Unit Price</th>
                        <th style="width:15%">Total</th>
                        <th style="width:10%"></th>
                    </tr>
                </thead>
                <tbody id="linesBody">
                    <tr class="line-row">
                        <td><input type="text" name="lines[0][description]" class="form-control form-control-sm" required></td>
                        <td><input type="number" name="lines[0][quantity]" class="form-control form-control-sm line-qty" step="0.01" min="0.01" required></td>
                        <td><input type="text" name="lines[0][unit]" class="form-control form-control-sm"></td>
                        <td><input type="number" name="lines[0][estimated_unit_price]" class="form-control form-control-sm line-price" step="0.01" min="0"></td>
                        <td class="line-total text-end">0.00</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-line"><i class="bi bi-trash"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <button class="btn btn-primary rounded-pill" type="submit"><i class="bi bi-check-lg me-1"></i>Create Request</button>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('addLine').addEventListener('click', function() {
    var tbody = document.getElementById('linesBody');
    var idx = tbody.querySelectorAll('.line-row').length;
    var row = document.createElement('tr');
    row.className = 'line-row';
    row.innerHTML =
        '<td><input type="text" name="lines['+idx+'][description]" class="form-control form-control-sm" required></td>' +
        '<td><input type="number" name="lines['+idx+'][quantity]" class="form-control form-control-sm line-qty" step="0.01" min="0.01" required></td>' +
        '<td><input type="text" name="lines['+idx+'][unit]" class="form-control form-control-sm"></td>' +
        '<td><input type="number" name="lines['+idx+'][estimated_unit_price]" class="form-control form-control-sm line-price" step="0.01" min="0"></td>' +
        '<td class="line-total text-end">0.00</td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-line"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(row);
    attachRemove(row);
});

document.querySelectorAll('.remove-line').forEach(function(btn) {
    attachRemove(btn.closest('.line-row'));
});

function attachRemove(row) {
    row.querySelector('.remove-line').addEventListener('click', function() {
        var tbody = document.getElementById('linesBody');
        if (tbody.querySelectorAll('.line-row').length > 1) {
            row.remove();
        }
    });
}
</script>
@endpush
