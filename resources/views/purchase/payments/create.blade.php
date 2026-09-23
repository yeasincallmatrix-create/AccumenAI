@extends('layouts.institute')
@section('title','Pay Bill')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Pay Bill</h4>
    <a href="{{ route('purchase.payments.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('purchase.payments.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Bill (Purchase Invoice) <span class="text-danger">*</span></label>
                <select name="purchase_invoice_id" class="form-select" required>
                    <option value="">— Select bill —</option>
                    @foreach($bills as $b)
                        <option value="{{ $b->id }}" {{ old('purchase_invoice_id')==$b->id?'selected':'' }}>
                            {{ $b->invoice_number }} — {{ $b->supplier?->name }} — Due {{ number_format((float)$b->due_amount,2) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        @foreach(['cash','bank','bkash','nagad','other'] as $m)
                            <option value="{{ $m }}" {{ old('payment_method')==$m?'selected':'' }}>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Paid At <span class="text-danger">*</span></label>
                    <input type="date" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Transaction ID</label>
                    <input type="text" name="transaction_id" value="{{ old('transaction_id') }}" class="form-control" maxlength="100">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Payment Method ID</label>
                <input type="number" name="payment_method_id" value="{{ old('payment_method_id') }}" class="form-control">
            </div>
            <button class="btn btn-success">Record Payment</button>
        </form>
    </div>
</div>
@endsection
