@extends('layouts.institute')
@section('title','Sales Invoices')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-receipt me-2"></i>Sales Invoices</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-cart-check me-1"></i>Orders</a>
        <a href="{{ route('sales.payments.create') }}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-cash me-1"></i>Receive Payment</a>
    </div>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['unpaid','partial','paid','cancelled'] as $s)
                        <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('sales.invoices.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Payable</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $inv)
                <tr>
                    <td class="fw-semibold">{{ $inv->invoice_number }}</td>
                    <td>{{ $inv->party?->name ?? '—' }}</td>
                    <td class="text-end">{{ number_format((float)$inv->total_amount,2) }}</td>
                    <td class="text-end">{{ number_format((float)$inv->payable_amount,2) }}</td>
                    <td class="text-end text-success">{{ number_format((float)$inv->paid_amount,2) }}</td>
                    <td class="text-end text-danger">{{ number_format((float)$inv->due_amount,2) }}</td>
                    <td>
                        @php $colors=['paid'=>'success','partial'=>'warning','unpaid'=>'danger','cancelled'=>'secondary']; @endphp
                        <span class="badge bg-{{ $colors[$inv->status]??'secondary' }}">{{ ucfirst($inv->status) }}</span>
                    </td>
                    <td><x-tdate :value="$inv->created_at" fallback="Y-m-d" /></td>
                    <td class="text-end">
                        <a href="{{ route('sales.invoices.show',$inv) }}" class="btn btn-sm btn-outline-primary rounded-pill" title="View"><i class="bi bi-eye"></i></a>
                        @if($inv->due_amount > 0)
                        <a href="{{ route('sales.payments.create') }}?invoice_id={{ $inv->id }}" class="btn btn-sm btn-outline-success rounded-pill" title="Receive Payment"><i class="bi bi-cash"></i></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No invoices found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())<div class="card-footer">{{ $invoices->links() }}</div>@endif
</div>
@endsection
