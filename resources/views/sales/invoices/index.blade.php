@extends('layouts.institute')
@section('title','Sales Invoices')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Sales Invoices</h4>
    <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Orders</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="text" name="status" value="{{ request('status') }}" class="form-control form-control-sm" placeholder="Status"></div>
            <div class="col-md-2"><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>#</th><th>Customer</th><th class="text-end">Payable</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td>{{ $inv->invoice_number }}</td>
                        <td>{{ $inv->party?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float)$inv->payable_amount,2) }}</td>
                        <td class="text-end">{{ number_format((float)$inv->paid_amount,2) }}</td>
                        <td class="text-end">{{ number_format((float)$inv->due_amount,2) }}</td>
                        <td><span class="badge bg-{{ $inv->status==='paid'?'success':($inv->status==='partial'?'warning':($inv->status==='cancelled'?'secondary':'danger')) }}">{{ $inv->status }}</span></td>
                        <td><x-tdate :value="$inv->created_at" fallback="Y-m-d" /></td>
                        <td><a href="{{ route('sales.invoices.show',$inv) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">No invoices found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </div>
</div>
@endsection
