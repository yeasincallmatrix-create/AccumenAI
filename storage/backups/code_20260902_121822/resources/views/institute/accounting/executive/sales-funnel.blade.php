@extends('layouts.standalone')
@section('title', 'Sales Funnel — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>Sales Funnel</h4>
    <p>Lead-to-delivery pipeline for {{ $institute->name }}.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.sales-funnel') }}">
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
            <small class="text-muted">Total Leads</small>
            <h4 class="mb-0 mt-1">{{ number_format($leads_count) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Quotations Sent</small>
            <h4 class="mb-0 mt-1 text-primary">{{ number_format($quotations_sent) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Orders Confirmed</small>
            <h4 class="mb-0 mt-1 text-success">{{ number_format($orders_confirmed) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Deliveries Completed</small>
            <h4 class="mb-0 mt-1 text-info">{{ number_format($deliveries_completed) }}</h4>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <h6>Funnel Conversion Rates</h6>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th class="text-end">Count</th>
                    <th class="text-end">Conversion Rate</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Leads</td>
                    <td class="text-end">{{ number_format($leads_count) }}</td>
                    <td class="text-end">—</td>
                </tr>
                <tr>
                    <td>Leads → Quotations</td>
                    <td class="text-end">{{ number_format($quotations_sent) }}</td>
                    <td class="text-end {{ $lead_to_quotation_rate >= 50 ? 'text-success' : 'text-warning' }}">{{ $lead_to_quotation_rate }}%</td>
                </tr>
                <tr>
                    <td>Quotations → Orders</td>
                    <td class="text-end">{{ number_format($orders_confirmed) }}</td>
                    <td class="text-end {{ $quotation_to_order_rate >= 50 ? 'text-success' : 'text-warning' }}">{{ $quotation_to_order_rate }}%</td>
                </tr>
                <tr>
                    <td>Orders → Deliveries</td>
                    <td class="text-end">{{ number_format($deliveries_completed) }}</td>
                    <td class="text-end {{ $order_to_delivery_rate >= 80 ? 'text-success' : 'text-warning' }}">{{ $order_to_delivery_rate }}%</td>
                </tr>
                <tr class="table-light fw-bold">
                    <td>Overall Conversion (Lead → Completed Order)</td>
                    <td class="text-end">{{ number_format($orders_completed) }}</td>
                    <td class="text-end text-primary">{{ $overall_conversion_rate }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Lead Outcomes</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <tbody>
                        <tr><td>Total Leads</td><td class="text-end">{{ number_format($leads_count) }}</td></tr>
                        <tr><td>Won</td><td class="text-end text-success">{{ number_format($leads_won) }}</td></tr>
                        <tr><td>Lost</td><td class="text-end text-danger">{{ number_format($leads_lost) }}</td></tr>
                        <tr><td>Win Rate</td><td class="text-end">{{ $leads_count > 0 ? round(($leads_won / $leads_count) * 100, 2) : 0 }}%</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>Order Pipeline</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <tbody>
                        <tr><td>Quotations Sent</td><td class="text-end">{{ number_format($quotations_sent) }}</td></tr>
                        <tr><td>Quotations Accepted</td><td class="text-end text-success">{{ number_format($quotations_accepted) }}</td></tr>
                        <tr><td>Orders Confirmed</td><td class="text-end text-primary">{{ number_format($orders_confirmed) }}</td></tr>
                        <tr><td>Orders Completed</td><td class="text-end text-success">{{ number_format($orders_completed) }}</td></tr>
                        <tr><td>Deliveries Completed</td><td class="text-end text-info">{{ number_format($deliveries_completed) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
