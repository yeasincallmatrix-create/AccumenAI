@extends('layouts.institute')
@section('title','Bill Payments')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Bill Payments</h4>
    <a href="{{ route('purchase.payments.create') }}" class="btn btn-sm btn-primary rounded-pill">+ Pay Bill</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>#</th><th>Supplier</th><th>Invoice</th><th class="text-end">Amount</th><th>Method</th><th>Date</th></tr></thead>
                <tbody>
                @forelse($payments as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>{{ $p->supplier?->name ?? '—' }}</td>
                        <td>{{ $p->purchaseInvoice?->invoice_number ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float)$p->amount,4) }}</td>
                        <td>{{ $p->paymentMethod?->name ?? $p->payment_method }}</td>
                        <td><x-tdate :value="$p->paid_at" fallback="Y-m-d" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No bill payments found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $payments->links() }}
    </div>
</div>
@endsection
