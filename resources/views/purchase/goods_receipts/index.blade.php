@extends('layouts.institute')
@section('title','Goods Receipts — AccumenAI')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box-seam me-1"></i>Goods Receipts</h4>
    <a href="{{ route('purchase.receipts.create') }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-plus-lg"></i> New Receipt</a>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('purchase.receipts.index') }}" class="row g-2">
            <div class="col-md-4"><input type="text" name="q" value="{{ request('q') }}" placeholder="Receipt / PO / Supplier" class="form-control form-control-sm"></div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach($statuses as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">All Warehouses</option>
                    @foreach(\App\Models\InventoryWarehouse::where('institute_id',$institute->id)->where('is_active',true)->get() as $wh)<option value="{{ $wh->id }}" @selected(request('warehouse_id')==$wh->id)>{{ $wh->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary rounded-pill flex-fill">Filter</button>
                <a href="{{ route('purchase.receipts.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Receipt</th><th>PO</th><th>Supplier</th><th>Warehouse</th><th>Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($receipts as $r)
                    <tr>
                        <td><a href="{{ route('purchase.receipts.show',$r) }}" class="fw-semibold">{{ $r->receipt_number }}</a><br><small class="text-muted">{{ $r->created_at?->format('Y-m-d H:i') }}</small></td>
                        <td><a href="{{ route('purchase.orders.show',$r->purchaseOrder) }}">{{ $r->purchaseOrder?->order_number ?? '—' }}</a><br><small class="text-muted">{{ $r->purchaseOrder?->status }}</small></td>
                        <td>{{ $r->supplier?->name ?? '—' }}<br><small class="text-muted">{{ $r->supplier?->phone }}</small></td>
                        <td>{{ $r->warehouse?->name ?? '—' }}</td>
                        <td>{{ $r->receipt_date?->format('Y-m-d') }}</td>
                        <td><span class="badge bg-{{ ['draft'=>'secondary','confirmed'=>'success','cancelled'=>'dark','reversed'=>'warning'][$r->status] ?? 'secondary' }}">{{ ucfirst($r->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('purchase.receipts.show',$r) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                            <a href="{{ route('purchase.receipts.print',$r) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No goods receipts found. <a href="{{ route('purchase.receipts.create') }}">Create one from an approved PO</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <small class="text-muted">{{ $receipts->total() }} total</small>
        <div>{{ $receipts->links() }}</div>
    </div>
</div>
@endsection
