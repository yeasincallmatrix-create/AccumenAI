@extends('layouts.standalone')

@section('title', 'Invoice Aging — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Invoice Aging</h4>
    <p>Receivables and payables side by side, bucketed by days overdue, as of {{ $asOf->toDateString() }}.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

@include('accounting.aging._nav')

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('accounting.aging.invoice') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">As of</label>
                <x-tdate-input class="form-control form-control-sm" name="as_of" value="{{ $asOf->toDateString() }}" />
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('accounting.aging.invoice') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <h6 class="mb-3"><i class="bi bi-arrow-down-circle text-success"></i> Receivables (AR) — {{ number_format($ar['grand_total'], 2) }}</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bucket</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ar['buckets'] as $bucket)
                            <tr>
                                <td>{{ $bucket['label'] }}</td>
                                <td class="text-end">{{ count($bucket['rows']) }}</td>
                                <td class="text-end">{{ number_format($bucket['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end">{{ $ar['row_count'] }}</td>
                            <td class="text-end">{{ number_format($ar['grand_total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card">
            <h6 class="mb-3"><i class="bi bi-arrow-up-circle text-danger"></i> Payables (AP) — {{ number_format($ap['grand_total'], 2) }}</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bucket</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ap['buckets'] as $bucket)
                            <tr>
                                <td>{{ $bucket['label'] }}</td>
                                <td class="text-end">{{ count($bucket['rows']) }}</td>
                                <td class="text-end">{{ number_format($bucket['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end">{{ $ap['row_count'] }}</td>
                            <td class="text-end">{{ number_format($ap['grand_total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
