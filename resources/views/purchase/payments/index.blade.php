@extends('layouts.institute')
@section('title','Bill Payments')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-cash-stack me-2"></i>Bill Payments</h4>
    <a href="{{ route('purchase.payments.create') }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-plus-lg me-1"></i>Pay Bill</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('purchase.payments.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Supplier</th><th>Bill</th><th class="text-end">Amount</th><th>Method</th><th>Transaction</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td class="fw-semibold">#{{ $p->id }}</td>
                    <td>{{ $p->supplier?->name ?? '—' }}</td>
                    <td>{{ $p->purchaseInvoice?->invoice_number ?? '—' }}</td>
                    <td class="text-end fw-semibold">{{ number_format((float)$p->amount,4) }}</td>
                    <td><span class="badge bg-light text-dark">{{ $p->paymentMethod?->name ?? $p->payment_method }}</span></td>
                    <td><small class="text-muted">{{ $p->transaction_id ?? '—' }}</small></td>
                    <td><x-tdate :value="$p->paid_at" fallback="Y-m-d" /></td>
                    <td class="text-end"><a href="{{ route('purchase.payments.show',$p) }}" class="btn btn-sm btn-outline-primary rounded-pill" title="View"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No bill payments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())<div class="card-footer">{{ $payments->links() }}</div>@endif
</div>
@endsection
