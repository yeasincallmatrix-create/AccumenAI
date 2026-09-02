@extends('layouts.institute')
@section('title','Sales Dashboard — AccumenAI')
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-cart-fill me-1"></i>Sales Dashboard <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Orders, invoices, deliveries and returns — net sales and receivables.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.settings.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill"><i class="bi bi-gear me-1"></i>Settings</a>
        <a href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-download"></i> CSV</a>
        <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>
<form method="GET" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label small mb-1">From</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1">To</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-primary rounded-pill">Filter</button>
    <a href="{{ route('sales.reports.dashboard') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
</div></form>

<div class="row g-3 mb-3">
    @php $t=$data['totals']; $c=$data['counts']; @endphp
    <div class="col-md-2 col-sm-4"><div class="card text-center"><div class="card-body"><small class="text-muted">Total Sales</small><div class="fw-bold fs-5">{{ number_format($t['total_sales'],2) }}</div><small class="text-muted">{{ $c['total_orders'] }} orders</small></div></div></div>
    <div class="col-md-2 col-sm-4"><div class="card text-center"><div class="card-body"><small class="text-muted">Posted Sales</small><div class="fw-bold fs-5 text-success">{{ number_format($t['posted_sales'],2) }}</div><small class="text-muted">{{ $c['posted'] }} orders</small></div></div></div>
    <div class="col-md-2 col-sm-4"><div class="card text-center"><div class="card-body"><small class="text-muted">Pending / Draft</small><div class="fw-bold fs-5 text-warning">{{ number_format($t['pending_sales'],2) }}</div><small class="text-muted">{{ $c['draft'] + $c['pending'] }} orders</small></div></div></div>
    <div class="col-md-2 col-sm-4"><div class="card text-center"><div class="card-body"><small class="text-muted">Cancelled</small><div class="fw-bold fs-5 text-danger">{{ number_format($t['cancelled_sales'],2) }}</div><small class="text-muted">{{ $c['cancelled'] }} orders</small></div></div></div>
    <div class="col-md-2 col-sm-4"><div class="card text-center"><div class="card-body"><small class="text-muted">Returns</small><div class="fw-bold fs-5 text-danger">-{{ number_format($t['returns_total'],2) }}</div><small class="text-muted">{{ $t['returns_count'] }} credit notes</small></div></div></div>
    <div class="col-md-2 col-sm-4"><div class="card text-center border-primary"><div class="card-body"><small class="text-muted">Net Sales</small><div class="fw-bold fs-5 text-primary">{{ number_format($t['net_sales'],2) }}</div></div></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><small class="text-muted">Discounts</small><div class="fw-semibold">{{ number_format($t['discounts'],2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body text-center"><small class="text-muted">Tax / VAT</small><div class="fw-semibold">{{ number_format($t['tax'],2) }}</div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><small class="text-muted">Receivables</small><div class="fw-semibold">{{ number_format($t['receivables'],2) }}</div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><small class="text-muted">Collection</small><div class="fw-semibold text-success">{{ number_format($t['collection'],2) }}</div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body text-center"><small class="text-muted">Outstanding</small><div class="fw-semibold text-danger">{{ number_format($t['outstanding'],2) }}</div></div></div></div>
</div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between"><span>Quick Links</span><span class="small text-muted">Reuses Finance AR source of truth • Posted journals only</span></div>
<div class="card-body d-flex flex-wrap gap-2">
    <a href="{{ route('sales.reports.daily') }}" class="btn btn-sm btn-outline-primary">Daily</a>
    <a href="{{ route('sales.reports.monthly') }}" class="btn btn-sm btn-outline-primary">Monthly</a>
    <a href="{{ route('sales.reports.product') }}" class="btn btn-sm btn-outline-primary">Product-wise</a>
    <a href="{{ route('sales.reports.category') }}" class="btn btn-sm btn-outline-primary">Category-wise</a>
    <a href="{{ route('sales.reports.customer') }}" class="btn btn-sm btn-outline-primary">Customer-wise</a>
    <a href="{{ route('sales.reports.salesperson') }}" class="btn btn-sm btn-outline-primary">Salesperson</a>
    <a href="{{ route('sales.reports.branch') }}" class="btn btn-sm btn-outline-primary">Branch-wise</a>
    <a href="{{ route('sales.reports.warehouse') }}" class="btn btn-sm btn-outline-primary">Warehouse</a>
    <a href="{{ route('sales.reports.returns') }}" class="btn btn-sm btn-outline-danger">Returns</a>
    <a href="{{ route('sales.reports.statement') }}" class="btn btn-sm btn-outline-success">Customer Statement</a>
</div></div>
@endsection
