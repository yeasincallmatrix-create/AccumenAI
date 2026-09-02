@extends('layouts.standalone')
@section('title', 'Profitability Dashboard — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Profitability Dashboard</h4>
    <p>Key profitability metrics for the selected period.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Revenue', 'value' => $revenue, 'color' => 'primary'],
        ['label' => 'COGS', 'value' => $cogs, 'color' => 'warning'],
        ['label' => 'Gross Profit', 'value' => $gross_profit, 'color' => 'success'],
        ['label' => 'Operating Expenses', 'value' => $operating_expenses, 'color' => 'danger'],
        ['label' => 'Operating Income', 'value' => $operating_income, 'color' => 'info'],
        ['label' => 'Net Income', 'value' => $net_income, 'color' => 'primary'],
    ] as $card)
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">{{ $card['label'] }}</small>
            <h4 class="mb-0 mt-1">{{ number_format($card['value'], 2) }}</h4>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    @foreach ([
        ['label' => 'Gross Margin', 'value' => $gross_margin],
        ['label' => 'Operating Margin', 'value' => $operating_margin],
        ['label' => 'Net Margin', 'value' => $net_margin],
    ] as $m)
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">{{ $m['label'] }}</small>
            <h4 class="mb-0 mt-1">{{ $m['value'] }}%</h4>
        </div>
    </div>
    @endforeach
</div>
@endsection
