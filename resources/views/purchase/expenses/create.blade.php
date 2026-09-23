@extends('layouts.institute')
@section('title','Record Expense')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Record Expense</h4>
    <a href="{{ route('purchase.expenses.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('purchase.expenses.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', now()->format('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Vendor Name</label>
                    <select name="vendor_name" class="form-select">
                        <option value="">— None —</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->name }}" {{ old('vendor_name')==$v->name?'selected':'' }}>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Category</label>
                    <input type="text" name="expense_category" value="{{ old('expense_category') }}" class="form-control" maxlength="50">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="form-control" maxlength="50">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Currency <span class="text-danger">*</span></label>
                    <input type="text" name="currency" value="{{ old('currency', 'BDT') }}" class="form-control" maxlength="3" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Payment Account ID <span class="text-danger">*</span></label>
                    <input type="number" name="payment_account_id" value="{{ old('payment_account_id') }}" class="form-control" required>
                    <small class="text-muted">Chart of account ID</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Expense Account ID <span class="text-danger">*</span></label>
                    <input type="number" name="expense_account_id" value="{{ old('expense_account_id') }}" class="form-control" required>
                    <small class="text-muted">Chart of account ID</small>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" maxlength="500">{{ old('description') }}</textarea>
            </div>
            <button class="btn btn-success">Record Expense</button>
        </form>
    </div>
</div>
@endsection
