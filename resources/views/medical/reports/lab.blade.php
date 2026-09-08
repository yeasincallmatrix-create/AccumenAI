@extends('layouts.institute')

@section('title', 'Lab Report — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Lab Activity Report</h4>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="from_date">From</label>
                <x-tdate-input name="from_date" :value="$fromDate" id="from_date" class="form-control" onchange="if(window.tdateReady&&window.tdateReady('from_date'))this.form.submit()" />
            </div>
            <div class="col-md-4">
                <label class="form-label" for="to_date">To</label>
                <x-tdate-input name="to_date" :value="$toDate" id="to_date" class="form-control" onchange="if(window.tdateReady&&window.tdateReady('to_date'))this.form.submit()" />
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Order Volumes</h6></div>
            <div class="card-body">
                <p class="mb-1">Total orders: <strong>{{ $stats['total_orders'] }}</strong></p>
                <p class="mb-1">Completed: <strong class="text-success">{{ $stats['completed'] }}</strong></p>
                <p class="mb-1">Pending: <strong class="text-warning">{{ $stats['pending'] }}</strong></p>
                <p class="mb-0">Cancelled: <strong class="text-muted">{{ $stats['cancelled'] }}</strong></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Most-Ordered Tests</h6></div>
            <div class="card-body">
                @if($popularTests->count() > 0)
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Test</th><th class="text-end">Orders</th></tr></thead>
                        <tbody>
                            @foreach($popularTests as $row)
                            <tr>
                                <td>{{ $row->labTest->display_name ?? 'Test #'.$row->lab_test_id }}</td>
                                <td class="text-end">{{ $row->total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted mb-0">No lab activity in range.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
