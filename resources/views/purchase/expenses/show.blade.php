@extends('layouts.institute')
@section('title','Expense '.$expense->expense_number)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Expense {{ $expense->expense_number }}</h4>
    <a href="{{ route('purchase.expenses.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="row mb-2"><div class="col-4 text-muted">Date</div><div class="col-8"><x-tdate :value="$expense->expense_date" fallback="Y-m-d" /></div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Vendor</div><div class="col-8">{{ $expense->vendor_name ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Category</div><div class="col-8">{{ $expense->expense_category ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Amount</div><div class="col-8 fw-bold">{{ number_format((float)$expense->amount,2) }} {{ $expense->currency }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Reference</div><div class="col-8">{{ $expense->reference_number ?? '—' }}</div></div>
        <div class="row mb-2"><div class="col-4 text-muted">Description</div><div class="col-8">{{ $expense->description ?? '—' }}</div></div>
    </div>
</div>
@endsection
