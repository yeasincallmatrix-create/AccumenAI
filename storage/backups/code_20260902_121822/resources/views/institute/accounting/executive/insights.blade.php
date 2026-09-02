@extends('layouts.standalone')
@section('title', 'Business Insights — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>Business Insights</h4>
    <p>Top customers, suppliers, inventory alerts and overdue invoices.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.insights') }}">
        <div>
            <label class="form-label mb-1">From</label>
            <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
        </div>
        <div>
            <label class="form-label mb-1">To</label>
            <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
        </div>
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('accounting.executive.index') }}"><i class="bi bi-arrow-left"></i> Back</a>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Top Customers</small>
            <h4 class="mb-0 mt-1">{{ $top_customers->count() }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Top Suppliers</small>
            <h4 class="mb-0 mt-1">{{ $top_suppliers->count() }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Low Stock Alerts</small>
            <h4 class="mb-0 mt-1 text-warning">{{ count($low_stock_alerts) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Overdue Invoices</small>
            <h4 class="mb-0 mt-1 text-danger">{{ $overdue_invoices->count() }}</h4>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Top Customers by Revenue</h6>
            @if ($top_customers->isEmpty())
                <p class="text-muted mb-0">No customer data.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($top_customers as $customer)
                                <tr>
                                    <td>{{ $customer->name }}</td>
                                    <td class="text-end">{{ number_format($customer->balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Top Suppliers by Spend</h6>
            @if ($top_suppliers->isEmpty())
                <p class="text-muted mb-0">No supplier data.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($top_suppliers as $supplier)
                                <tr>
                                    <td>{{ $supplier->name }}</td>
                                    <td class="text-end">{{ number_format($supplier->balance, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Low Stock Alerts</h6>
            @if (empty($low_stock_alerts))
                <p class="text-muted mb-0">All items are adequately stocked.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>SKU</th>
                                <th>Warehouse</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Reorder</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($low_stock_alerts as $alert)
                                <tr>
                                    <td>{{ $alert['item_name'] }}</td>
                                    <td>{{ $alert['sku'] }}</td>
                                    <td>{{ $alert['warehouse_name'] ?? '—' }}</td>
                                    <td class="text-end">{{ $alert['quantity'] }}</td>
                                    <td class="text-end">{{ $alert['reorder_level'] }}</td>
                                    <td>
                                        @if ($alert['status'] === 'out_of_stock')
                                            <span class="badge text-bg-danger">Out of Stock</span>
                                        @else
                                            <span class="badge text-bg-warning">Low Stock</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Overdue Invoices</h6>
            @if ($overdue_invoices->isEmpty())
                <p class="text-muted mb-0">No overdue invoices.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Due Date</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overdue_invoices as $invoice)
                                <tr>
                                    <td>{{ $invoice->invoice_no }}</td>
                                    <td>{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                    <td><span class="badge text-bg-danger">Overdue</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
