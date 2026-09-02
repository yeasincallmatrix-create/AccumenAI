@extends('layouts.institute')
@section('title','Payment Attempt — AccumenAI')
@section('content')
<div class="page-header"><div class="page-header-text"><h4 class="page-header-title">Payment Attempt #{{ $attempt->id }}</h4><p class="page-header-desc">Invoice {{ $attempt->invoice->invoice_number ?? $attempt->invoice_id }} | Amount {{ number_format($attempt->amount,2) }} BDT | Status <span class="badge bg-{{ $attempt->status==='paid'?'success':'warning' }}">{{ $attempt->status }}</span></p></div></div>
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="admin-card p-3">
    <p class="small text-muted">Gateway reference: {{ $attempt->gateway_reference ?? '—' }} | Currency: {{ $attempt->currency_code ?? 'BDT' }}</p>
    <p class="small">If you completed bKash payment, click verify. Do not expose credentials.</p>
    <form method="POST" action="{{ route('saas.callback', $attempt) }}">
        @csrf
        <input type="hidden" name="paymentID" value="{{ $attempt->gateway_reference }}">
        <input type="hidden" name="amount" value="{{ $attempt->amount }}">
        <input type="hidden" name="currency" value="BDT">
        <input type="hidden" name="status" value="success">
        <button type="submit" class="btn btn-primary btn-sm">Verify & Activate (server-side)</button>
    </form>
    <a href="{{ route('saas.packages') }}" class="btn btn-outline-secondary btn-sm mt-2">Back to Packages</a>
</div>
@endsection
