@extends('layouts.institute')
@section('title', 'Purchase Returns — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-arrow-return-left me-2"></i>Purchase Returns</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('purchase.returns.create') }}"><i class="bi bi-plus-lg me-1"></i>New Return</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@include('purchase._tabs', ['activeTab' => 'returns'])
<div class="card mb-4"><div class="card-body">
<form method="GET" class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label">Search</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Return or credit note"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select form-select-sm"><option value="">All</option>@foreach(['draft','submitted','approved','posted','cancelled','reversed'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-md-3"><button class="btn btn-sm btn-primary rounded-pill" type="submit">Filter</button> <a href="{{ route('purchase.returns.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a></div>
</form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Return</th><th>Credit Note</th><th>Supplier</th><th>PO/GRN</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
@forelse($returns as $ret)
<tr>
<td class="fw-semibold">{{ $ret->return_number }}</td>
<td><small class="text-muted">{{ $ret->credit_note_number ?? '—' }}</small></td>
<td>{{ $ret->supplier?->name ?? '—' }}</td>
<td><small class="text-muted">{{ $ret->purchaseOrder?->order_number ?? '—' }} @if($ret->goodsReceipt) / {{ $ret->goodsReceipt->receipt_number }} @endif</small></td>
<td class="text-end">{{ number_format($ret->grand_total,2) }}</td>
<td>@php $colors=['draft'=>'secondary','submitted'=>'info','approved'=>'warning','posted'=>'success','cancelled'=>'dark','reversed'=>'dark']; @endphp <span class="badge bg-{{ $colors[$ret->status]??'secondary' }}">{{ ucfirst($ret->status) }}</span></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('purchase.returns.show',$ret) }}"><i class="bi bi-eye"></i></a></td>
</tr>
@empty<tr><td colspan="7" class="text-center text-muted py-4">No purchase returns.</td></tr>@endforelse
</tbody>
</table></div>
@if($returns->hasPages())<div class="card-footer">{{ $returns->links() }}</div>@endif
</div>
@endsection
