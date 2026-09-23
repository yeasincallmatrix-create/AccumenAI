@extends('layouts.institute')
@section('title','Receipt '.$receipt->memo_number)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Sales Receipt {{ $receipt->memo_number }}</h4>
    <a href="{{ route('sales.receipts.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="row mb-2"><div class="col-4 text-muted">Customer</div><div class="col-8">{{ $receipt->party?->name ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Amount</div><div class="col-8 fw-bold">{{ number_format((float)$receipt->amount,2) }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Method</div><div class="col-8">{{ $receipt->payment_method }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Description</div><div class="col-8">{{ $receipt->description ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Date</div><div class="col-8"><x-tdate :value="$receipt->created_at" fallback="Y-m-d" /></div></div>
    </div>
</div>
@endsection
