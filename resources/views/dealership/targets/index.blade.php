@extends('layouts.standalone')

@section('title', 'SR Targets — AccumenAI')
@section('page_title', 'SR Targets')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bullseye me-2"></i>টার্গেট (SR Targets)</h4>
</div>

<div class="filter-card card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('dealership.targets.index') }}" class="row g-2">
            <div class="col-md-3"><label class="form-label">SR (sales_force_id)</label><input type="number" name="sr" class="form-control" value="{{ $filters['sr'] ?? '' }}"></div>
            <div class="col-md-3"><label class="form-label">Period / মেয়াদ</label>
                <select name="period" class="form-select"><option value="">— All —</option>@foreach($periods as $p)<option value="{{ $p }}" @selected(($filters['period'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
            </div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm" type="submit">Filter</button></div>
        </form>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Period</th><th>Start</th><th>End</th><th>Target</th><th>Achieved</th></tr></thead>
            <tbody>
                @forelse($targets as $row)
                    <tr><td>{{ $row->sales_force_id }}</td><td>{{ $row->period_type }}</td><td>{{ $row->period_start }}</td><td>{{ $row->period_end }}</td><td>{{ number_format($row->target_amount, 2) }}</td><td>{{ number_format($row->achieved_amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">কোনো টার্গেট নেই (No targets yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $targets->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন টার্গেট (New Target)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.targets.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Period Type</label>
                <select name="period_type" class="form-select">@foreach($periods as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach</select>
            </div>
            <div class="col-md-2"><label class="form-label">Start</label><x-tdate-input name="period_start" /></div>
            <div class="col-md-2"><label class="form-label">End</label><x-tdate-input name="period_end" /></div>
            <div class="col-md-2"><label class="form-label">Target Amount</label><input type="number" step="0.01" name="target_amount" class="form-control" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Save</button></div>
        </form>
    </div>
</div>
@endsection
