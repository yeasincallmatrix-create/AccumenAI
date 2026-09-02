@extends('layouts.institute')
@section('title', 'Purchase Invoices — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Purchase Invoices</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('purchase.invoices.create') }}"><i class="bi bi-plus-lg me-1"></i>New Invoice</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-4"><div class="card-body">
<form method="GET" class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label">Search</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Number or supplier"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option>@foreach(['draft','posted','cancelled','reversed'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"></div>
    <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary rounded-pill" type="submit">Filter</button> <a href="{{ route('purchase.invoices.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a></div>
</form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Number</th><th>Supplier</th><th>PO/GRN</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Paid / Due</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
@forelse($invoices as $inv)
<tr>
<td class="fw-semibold">{{ $inv->invoice_number }}</td>
<td>{{ $inv->supplier?->name ?? '—' }}</td>
<td><small class="text-muted">{{ $inv->purchaseOrder?->order_number ?? '—' }} @if($inv->goodsReceipt) / {{ $inv->goodsReceipt->receipt_number }} @endif</small></td>
<td>{{ $inv->invoice_date->format('Y-m-d') }}</td>
<td class="text-end">{{ number_format($inv->grand_total,2) }}</td>
<td class="text-end"><span class="text-success">{{ number_format($inv->paid_amount,2) }}</span> / <span class="text-danger">{{ number_format($inv->due_amount,2) }}</span></td>
<td>@php $colors=['draft'=>'secondary','posted'=>'success','cancelled'=>'dark','reversed'=>'warning']; @endphp <span class="badge bg-{{ $colors[$inv->status]??'secondary' }}">{{ ucfirst($inv->status) }}</span></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('purchase.invoices.show',$inv) }}"><i class="bi bi-eye"></i></a></td>
</tr>
@empty<tr><td colspan="8" class="text-center text-muted py-4">No purchase invoices.</td></tr>@endforelse
</tbody>
</table></div>
@if($invoices->hasPages())<div class="card-footer">{{ $invoices->links() }}</div>@endif
</div>
@endsection
