@extends('layouts.institute')
@section('title','Purchase Reports — Dashboard')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Purchase Dashboard</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('purchase.reports.export',['type'=>'dashboard'] + request()->query()) }}" class="btn btn-sm btn-outline-success">CSV Export</a>
        <a href="{{ route('purchase.reports.print',['type'=>'dashboard'] + request()->query()) }}" target="_blank" class="btn btn-sm btn-outline-primary">Print</a>
    </div>
</div>
@include('purchase.reports._filters')
<div class="row g-3 mb-4">
    @foreach(['total_purchases'=>'Total Purchases','posted_purchases'=>'Posted','draft_purchases'=>'Draft','purchase_returns'=>'Returns','net_purchases'=>'Net Purchases','discounts'=>'Discounts','tax'=>'Tax/VAT','outstanding_payable'=>'Outstanding Payable','amount_paid'=>'Amount Paid','amount_due'=>'Amount Due','purchase_orders_count'=>'# Orders','receipts_count'=>'# Receipts','purchase_invoices_count'=>'# Invoices'] as $k=>$label)
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fw-bold fs-5">{{ is_numeric($metrics[$k]) ? number_format($metrics[$k],2) : $metrics[$k] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>
<div class="card">
    <div class="card-header d-flex gap-2">
        <a href="{{ route('purchase.reports.daily') }}" class="btn btn-sm btn-outline-secondary">Daily</a>
        <a href="{{ route('purchase.reports.supplier') }}" class="btn btn-sm btn-outline-secondary">Supplier-wise</a>
        <a href="{{ route('purchase.reports.product') }}" class="btn btn-sm btn-outline-secondary">Product-wise</a>
        <a href="{{ route('purchase.reports.payable') }}" class="btn btn-sm btn-outline-secondary">Payable</a>
        <a href="{{ route('purchase.reports.inventory') }}" class="btn btn-sm btn-outline-secondary">Inventory Recon</a>
        <a href="{{ route('purchase.reports.supplierStatement') }}" class="btn btn-sm btn-outline-secondary">Supplier Statement</a>
    </div>
    <div class="card-body text-muted small">
        All totals are derived from posted purchase invoices (finance source-of-truth for payable). Draft invoices excluded from payable. Returns reflect posted credit notes.
    </div>
</div>
@endsection
