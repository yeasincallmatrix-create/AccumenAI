@extends('layouts.institute')
@section('title','Sales Returns — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-arrow-return-left me-2"></i>Sales Returns</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.returns.create') }}"><i class="bi bi-plus-lg me-1"></i>New Return</a>
</div>
<div class="card mb-4"><div class="card-body">
<form method="GET" class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label">Search</label><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Return / Credit Note / Customer"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option>@foreach(['draft','approved','posted','cancelled','reversed'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button><a href="{{ route('sales.returns.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a></div>
</form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Return #</th><th>Credit Note #</th><th>Invoice</th><th>Customer</th><th>Date</th><th class="text-end">Total</th><th>Status</th><th>Refund</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($returns as $r)<tr><td class="fw-semibold">{{ $r->return_number }}</td><td>{{ $r->credit_note_number ?? '—' }}</td><td>{{ $r->invoice?->invoice_number ?? '#'.$r->invoice_id }}</td><td>{{ $r->customer?->name ?? '—' }}</td><td>{{ $r->return_date->format('Y-m-d') }}</td><td class="text-end">{{ number_format($r->grand_total,2) }}</td><td><span class="badge bg-{{ ['draft'=>'secondary','approved'=>'info','posted'=>'success','cancelled'=>'dark','reversed'=>'warning'][$r->status] ?? 'secondary' }}">{{ ucfirst($r->status) }}</span></td><td><span class="badge bg-light text-dark">{{ ucfirst($r->refund_status) }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.returns.show',$r) }}"><i class="bi bi-eye"></i></a><a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('sales.returns.credit-note',$r) }}"><i class="bi bi-receipt"></i></a></td></tr>@empty<tr><td colspan="9" class="text-center text-muted py-4">No returns found.</td></tr>@endforelse
</tbody></table></div>@if($returns->hasPages())<div class="card-footer">{{ $returns->links() }}</div>@endif</div>
@endsection
