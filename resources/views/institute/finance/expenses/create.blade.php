@extends('layouts.standalone')

@section('title', 'New Expense — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>New Expense</h4>
    <p>Record a business expense.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.expenses.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                <input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Payment Account <span class="text-danger">*</span></label>
                <select name="payment_account_id" class="form-select" required>
                    <option value="">Select account</option>
                    @foreach ($paymentAccounts as $acc)
                        <option value="{{ $acc->id }}" @selected(old('payment_account_id') == $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Expense Account <span class="text-danger">*</span></label>
                <select name="expense_account_id" class="form-select" required>
                    <option value="">Select account</option>
                    @foreach ($expenseAccounts as $acc)
                        <option value="{{ $acc->id }}" @selected(old('expense_account_id') == $acc->id)>{{ $acc->code }} — {{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Vendor</label>
                <input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name') }}" maxlength="200">
            </div>
            <div class="col-md-4">
                <label class="form-label">Reference #</label>
                <input type="text" name="reference_number" class="form-control" value="{{ old('reference_number') }}" maxlength="50">
            </div>
            <div class="col-md-4">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="expense_category" class="form-select" required>
                    <option value="">Select category</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('expense_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Currency</label>
                <input type="text" name="currency" class="form-control" value="{{ old('currency', 'BDT') }}" maxlength="3">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tax Amount</label>
                <input type="number" name="tax_amount" class="form-control" step="0.01" min="0" value="{{ old('tax_amount', 0) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tax Group</label>
                <select name="tax_group_id" class="form-select">
                    <option value="">None</option>
                    @foreach ($taxGroups as $tg)
                        <option value="{{ $tg->id }}" @selected(old('tax_group_id') == $tg->id)>{{ $tg->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" maxlength="500">{{ old('description') }}</textarea>
            </div>

            <div class="col-12"><hr><strong>Billable Expenses</strong></div>

            <div class="col-md-2">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_billable" value="1" id="is_billable" {{ old('is_billable') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_billable">Billable</label>
                </div>
            </div>
            <div class="col-md-4" id="customer_field" style="display:{{ old('is_billable') ? 'block' : 'none' }}">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="">Select customer</option>
                    @foreach ($customers as $cust)
                        <option value="{{ $cust->id }}" @selected(old('customer_id') == $cust->id)>{{ $cust->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3" id="markup_field" style="display:{{ old('is_billable') ? 'block' : 'none' }}">
                <label class="form-label">Markup %</label>
                <input type="number" name="markup_percentage" class="form-control" step="0.01" min="0" max="100" value="{{ old('markup_percentage', 0) }}">
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Expense</button>
            <a href="{{ route('finance.expenses.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('is_billable').addEventListener('change', function() {
    const show = this.checked;
    document.getElementById('customer_field').style.display = show ? 'block' : 'none';
    document.getElementById('markup_field').style.display = show ? 'block' : 'none';
});
</script>
@endpush
