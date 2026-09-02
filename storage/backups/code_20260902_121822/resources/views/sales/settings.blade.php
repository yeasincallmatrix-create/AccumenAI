@extends('layouts.institute')

@section('title', 'Sales Settings — AccumenAI')
@section('page_title', 'Sales Foundation')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Sales Foundation — Settings <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">Industry-neutral sales configuration via <code>sales_config</code> JSON and <code>sales_sequences</code> numbering.</p>
    </div>
    <a href="{{ route('sales.reports.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Sales Dashboard</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('sales.settings.update') }}" class="admin-card p-4 mb-4">
    @csrf
    @method('PUT')

    <h6 class="mb-3"><i class="bi bi-gear me-1"></i> General</h6>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled" @checked($config['enabled'])>
                <label class="form-check-label" for="enabled">Sales enabled</label>
            </div>
            <small class="text-muted">Master toggle for sales module.</small>
        </div>
        <div class="col-md-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="quotation_enabled" value="1" id="quotation_enabled" @checked($config['quotation_enabled'])>
                <label class="form-check-label" for="quotation_enabled">Quotation enabled</label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="sales_order_enabled" value="1" id="sales_order_enabled" @checked($config['sales_order_enabled'])>
                <label class="form-check-label" for="sales_order_enabled">Sales order enabled</label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="delivery_enabled" value="1" id="delivery_enabled" @checked($config['delivery_enabled'])>
                <label class="form-check-label" for="delivery_enabled">Delivery enabled</label>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="invoice_integration" value="1" id="invoice_integration" @checked($config['invoice_integration'])>
                <label class="form-check-label" for="invoice_integration">Sales invoice integration</label>
            </div>
            <small class="text-muted">Uses existing <code>invoices</code> architecture.</small>
        </div>
    </div>

    <h6 class="mb-3"><i class="bi bi-currency-exchange me-1"></i> Defaults</h6>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Default currency</label>
            <input type="text" name="default_currency" class="form-control form-control-sm" value="{{ $config['default_currency'] }}" placeholder="BDT" maxlength="10">
        </div>
        <div class="col-md-3">
            <label class="form-label">Default payment terms</label>
            <select name="default_payment_terms" class="form-select form-select-sm">
                @foreach (['net_7' => 'Net 7', 'net_15' => 'Net 15', 'net_30' => 'Net 30', 'net_45' => 'Net 45', 'due_on_receipt' => 'Due on receipt'] as $v => $l)
                    <option value="{{ $v }}" @selected($config['default_payment_terms'] === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Default tax behavior</label>
            <select name="default_tax_behavior" class="form-select form-select-sm">
                <option value="exclusive" @selected($config['default_tax_behavior']==='exclusive')>Exclusive</option>
                <option value="inclusive" @selected($config['default_tax_behavior']==='inclusive')>Inclusive</option>
                <option value="none" @selected($config['default_tax_behavior']==='none')>None</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Default discount behavior</label>
            <select name="default_discount_behavior" class="form-select form-select-sm">
                <option value="per_line" @selected($config['default_discount_behavior']==='per_line')>Per line</option>
                <option value="per_total" @selected($config['default_discount_behavior']==='per_total')>Per total</option>
                <option value="none" @selected($config['default_discount_behavior']==='none')>None</option>
            </select>
        </div>
    </div>

    <h6 class="mb-3"><i class="bi bi-123 me-1"></i> Numbering — centralized, branch-safe</h6>
    <p class="text-muted small">Prefixes and padding for <code>sales_sequences</code>. Next numbers are atomic <code>nextNumber()</code> per <code>institute + branch + type</code>. Do not place logic in controllers.</p>
    <div class="row g-3">
        @foreach (['quotation' => 'Quotation', 'sales_order' => 'Sales Order', 'delivery' => 'Delivery'] as $type => $label)
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <strong>{{ $label }}</strong> <small class="text-muted">next: <code>{{ $previews[$type] }}</code></small>
                    <div class="mt-2">
                        <label class="form-label">Prefix</label>
                        <input type="text" name="numbering[{{ $type }}][prefix]" class="form-control form-control-sm" value="{{ $config['numbering'][$type]['prefix'] ?? '' }}" maxlength="20">
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Padding</label>
                        <input type="number" name="numbering[{{ $type }}][padding]" class="form-control form-control-sm" value="{{ $config['numbering'][$type]['padding'] ?? 5 }}" min="3" max="10">
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i> Save sales settings</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
</form>

<div class="admin-card p-3">
    <h6><i class="bi bi-info-circle me-1"></i> Integration notes</h6>
    <ul class="small text-muted mb-0">
        <li><strong>Inventory:</strong> Sales will read <code>inventory_items</code> / <code>inventory_stock_levels</code> via <code>SalesInventoryIntegration</code> — no duplicate product-stock tables.</li>
        <li><strong>Finance:</strong> When <em>invoice integration</em> is on, later steps will post to existing <code>invoices</code> / <code>journal</code> via <code>JournalPostingService</code>.</li>
        <li><strong>Statuses:</strong> Quotation <code>draft/sent/accepted/rejected/expired/cancelled</code>, Order <code>draft/pending/approved/processing/completed/cancelled</code>, Delivery <code>draft/pending/delivered/cancelled</code> — reusable via <code>SalesDocumentStatus</code>.</li>
        <li><strong>Audit:</strong> Uses existing <code>audit_logs</code> (<code>module=sales</code>) — no new audit table.</li>
    </ul>
</div>
@endsection
