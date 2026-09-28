@extends('layouts.standalone')

@section('title', 'Target Report — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Target vs Achievement (টার্গেট বনাম অর্জন)</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('dealership.reports.targets.export', request()->query()) }}"><i class="bi bi-download"></i> CSV</a>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

@include('dealership.reports._nav')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('dealership.reports.targets.index') }}" class="row g-2">
        <div class="col-md-2"><label class="form-label">SR</label><input type="number" name="sr" class="form-control form-control-sm" value="{{ $filters['sr'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label">Period</label>
            <select name="period" class="form-select form-select-sm"><option value="">— All —</option>@foreach($periods as $p)<option value="{{ $p }}" @selected(($filters['period'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
        </div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </form>
</div>

<div class="admin-card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Period</th><th>Target</th><th>Achieved</th><th>Variance</th><th>Ach. %</th></tr></thead>
            <tbody>
                @forelse($report['rows'] as $row)
                    <tr><td>{{ $row['sales_force_id'] }}</td><td>{{ $row['period_type'] }} {{ $row['period_start'] }}</td><td>{{ number_format($row['target'], 2) }}</td><td>{{ number_format($row['achieved'], 2) }}</td><td>{{ number_format($row['variance'], 2) }}</td><td>{{ $row['achievement_pct'] }}%</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">কোনো তথ্য নেই (No data)</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><th colspan="2">Total</th><th>{{ number_format($report['totals']['target'], 2) }}</th><th>{{ number_format($report['totals']['achieved'], 2) }}</th><th>{{ number_format($report['totals']['variance'], 2) }}</th><th></th></tr></tfoot>
        </table>
    </div>
</div>
@endsection
