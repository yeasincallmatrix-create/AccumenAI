@extends('layouts.standalone')
@section('title', 'Cash Forecast — AccumenAI')
@section('page_title', 'Executive')

@section('content')
<div class="standalone-heading">
    <h4>Cash Forecast</h4>
    <p>Current cash position and projected flow based on AR/AP aging.
        @if ($branch && $branch->id)
            <span class="badge text-bg-primary ms-1">Branch: {{ $branch->name }}</span>
        @else
            <span class="badge text-bg-light border ms-1">All branches</span>
        @endif
    </p>

    <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.executive.cash') }}">
        <div>
            <label class="form-label mb-1">As of Date</label>
            <input type="date" class="form-control form-control-sm" name="as_of_date" value="{{ $as_of_date }}">
        </div>
        <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('accounting.executive.index') }}"><i class="bi bi-arrow-left"></i> Back</a>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Current Cash Balance</small>
            <h4 class="mb-0 mt-1">{{ number_format($current_balance, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Projected Inflow (AR)</small>
            <h4 class="mb-0 mt-1 text-success">{{ number_format($projected_inflow, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Projected Outflow (AP)</small>
            <h4 class="mb-0 mt-1 text-danger">{{ number_format($projected_outflow, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Projected Balance</small>
            <h4 class="mb-0 mt-1 {{ $projected_balance < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($projected_balance, 2) }}</h4>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Total Accounts Receivable</small>
            <h4 class="mb-0 mt-1">{{ number_format($ar_total, 2) }}</h4>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Total Accounts Payable</small>
            <h4 class="mb-0 mt-1 text-danger">{{ number_format($ap_total, 2) }}</h4>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card mb-4">
            <h6>AR Aging (Customer)</h6>
            @if ($customer_aging->isEmpty())
                <p class="text-muted mb-0">No receivables.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">Current</th>
                                <th class="text-end">31-60</th>
                                <th class="text-end">61-90</th>
                                <th class="text-end">91+</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer_aging as $customer)
                                @php $aging = $customer->aging ?? []; @endphp
                                <tr>
                                    <td>{{ $customer->name }}</td>
                                    <td class="text-end">{{ number_format($customer->balance, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['current'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['31_60'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['61_90'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['91_plus'] ?? 0, 2) }}</td>
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
            <h6>AP Aging (Supplier)</h6>
            @if ($supplier_aging->isEmpty())
                <p class="text-muted mb-0">No payables.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Supplier</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">Current</th>
                                <th class="text-end">31-60</th>
                                <th class="text-end">61-90</th>
                                <th class="text-end">91+</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($supplier_aging as $supplier)
                                @php $aging = $supplier->aging ?? []; @endphp
                                <tr>
                                    <td>{{ $supplier->name }}</td>
                                    <td class="text-end">{{ number_format($supplier->balance, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['current'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['31_60'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['61_90'] ?? 0, 2) }}</td>
                                    <td class="text-end">{{ number_format($aging['91_plus'] ?? 0, 2) }}</td>
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
