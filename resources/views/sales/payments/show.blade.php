@extends('layouts.institute')
@section('title','Payment #'.$payment->id)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Payment #{{ $payment->id }}</h4>
    <a href="{{ route('sales.payments.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="row mb-2"><div class="col-4 text-muted">Invoice</div><div class="col-8">{{ $payment->invoice?->invoice_number ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Customer</div><div class="col-8">{{ $payment->party?->name ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Amount</div><div class="col-8 fw-bold">{{ number_format((float)$payment->amount,2) }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Method</div><div class="col-8">{{ $payment->paymentMethod?->name ?? $payment->payment_method }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Paid At</div><div class="col-8"><x-tdate :value="$payment->paid_at" fallback="Y-m-d" /></div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Transaction ID</div><div class="col-8">{{ $payment->transaction_id ?? '—' }}</div></div>
    </div>
</div>
@endsection
