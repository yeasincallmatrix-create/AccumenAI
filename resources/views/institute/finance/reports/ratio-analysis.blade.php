@extends('layouts.standalone')

@section('title', 'Ratio Analysis — AccumenAI')
@section('page_title', 'Accounting Reports')

@section('content')

<div class="standalone-heading">
    <h4>Ratio Analysis</h4>
    <p>Liquidity, profitability, leverage and efficiency ratios as of {{ $as_of_date }}.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('accounting.reports.ratios') }}">
            <div>
                <label class="form-label mb-1">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from_date }}">
            </div>
            <div>
                <label class="form-label mb-1">As of</label>
                <input type="date" class="form-control form-control-sm" name="as_of" value="{{ $as_of_date }}">
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm">Analyze</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h5 class="text-primary">Liquidity Ratios</h5>
            <dl class="row mb-0">
                @foreach($liquidity as $key => $value)
                    <dt class="col-7 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-5 text-end fw-semibold">{{ is_numeric($value) ? number_format($value, 2) : '—' }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h5 class="text-success">Profitability Ratios</h5>
            <dl class="row mb-0">
                @foreach($profitability as $key => $value)
                    <dt class="col-7 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-5 text-end fw-semibold">{{ $value !== null ? number_format($value * 100, 2) . '%' : '—' }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h5 class="text-danger">Leverage Ratios</h5>
            <dl class="row mb-0">
                @foreach($leverage as $key => $value)
                    <dt class="col-7 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-5 text-end fw-semibold">{{ is_numeric($value) ? number_format($value, 2) : '—' }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h5 class="text-purple">Efficiency Ratios</h5>
            <dl class="row mb-0">
                @foreach($efficiency as $key => $value)
                    <dt class="col-7 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-5 text-end fw-semibold">{{ is_numeric($value) ? number_format($value, 2) . 'x' : '—' }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h5 class="text-warning">Market / Investor Ratios</h5>
            <dl class="row mb-0">
                @foreach($market as $key => $value)
                    <dt class="col-7 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-5 text-end fw-semibold">{{ is_numeric($value) ? number_format($value, 2) : '—' }}</dd>
                @endforeach
            </dl>
            @if(collect($market)->filter()->isEmpty())
                <p class="text-muted small mt-2 mb-0">Market ratios need shares outstanding configured.</p>
            @endif
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-3">
            <h5 class="text-info">Cash Flow Ratios</h5>
            <dl class="row mb-0">
                @foreach($cash_flow as $key => $value)
                    <dt class="col-7 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-5 text-end fw-semibold">{{ is_numeric($value) ? number_format($value, 2) : '—' }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
</div>

@endsection
