@extends('layouts.institute')

@section('title', 'Revenue Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Revenue Report</h4>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="period">Period</label>
                <select id="period" name="period" class="form-select" onchange="this.form.submit()">
                    <option value="today" @selected($period === 'today')>Today</option>
                    <option value="week" @selected($period === 'week')>Last 7 Days</option>
                    <option value="month" @selected($period === 'month')>Last 30 Days</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Billed</h6><h2 class="card-text">৳{{ number_format($revenue['total_revenue'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Collected</h6><h2 class="card-text">৳{{ number_format($revenue['total_paid'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark"><div class="card-body">
            <h6 class="card-title">Outstanding</h6><h2 class="card-text">৳{{ number_format($breakdown['outstanding'] ?? 0, 2) }}</h2>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">By Invoice Type (paid)</h6></div>
            <div class="card-body">
                @if(($revenue['by_type'] ?? collect())->count() > 0)
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Type</th><th>Count</th><th class="text-end">Revenue</th></tr></thead>
                        <tbody>
                            @foreach($revenue['by_type'] as $type => $row)
                            <tr>
                                <td>{{ strtoupper($type) }}</td>
                                <td>{{ $row['count'] }}</td>
                                <td class="text-end">৳{{ number_format($row['revenue'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted mb-0">No paid invoices in this period.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">By Status</h6></div>
            <div class="card-body">
                <table class="table table-sm align-middle">
                    <tbody>
                        <tr><td>Total Invoices</td><td class="text-end"><strong>{{ $breakdown['total'] }}</strong></td></tr>
                        <tr><td>Paid</td><td class="text-end">{{ $breakdown['paid'] }}</td></tr>
                        <tr><td>Pending</td><td class="text-end">{{ $breakdown['pending'] }}</td></tr>
                        <tr><td>Partial</td><td class="text-end">{{ $breakdown['partial'] }}</td></tr>
                        <tr><td>Cancelled</td><td class="text-end">{{ $breakdown['cancelled'] }}</td></tr>
                        <tr><td>Collected</td><td class="text-end">৳{{ number_format($breakdown['collected'], 2) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
