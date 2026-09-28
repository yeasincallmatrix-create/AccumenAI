@extends('layouts.standalone')

@section('title', 'Incentives — AccumenAI')
@section('page_title', 'Incentives')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gift me-2"></i>ইনসেনটিভ (Incentives)</h4>
</div>

<div class="filter-card card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('dealership.incentives.index') }}" class="row g-2">
            <div class="col-md-3"><label class="form-label">SR (sales_force_id)</label><input type="number" name="sr" class="form-control" value="{{ $filters['sr'] ?? '' }}"></div>
            <div class="col-md-3"><label class="form-label">Status / অবস্থা</label>
                <select name="status" class="form-select"><option value="">— All —</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            </div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm" type="submit">Filter</button></div>
        </form>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Rule</th><th>Type</th><th>Threshold</th><th>Amount</th><th>Earned On</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($incentives as $row)
                    <tr><td>{{ $row->sales_force_id }}</td><td>{{ $row->rule_name }}</td><td>{{ $row->rule_type }}</td><td>{{ number_format($row->threshold_amount, 2) }}</td><td>{{ number_format($row->incentive_amount, 2) }}</td><td>{{ $row->earned_on ?? '—' }}</td><td><span class="badge bg-secondary">{{ $row->status }}</span></td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো ইনসেনটিভ নেই (No incentives yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $incentives->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন ইনসেনটিভ (New Incentive)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.incentives.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Rule Name</label><input type="text" name="rule_name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Rule Type</label>
                <select name="rule_type" class="form-select"><option value="flat">flat</option><option value="percentage">percentage</option><option value="tiered">tiered</option></select>
            </div>
            <div class="col-md-2"><label class="form-label">Threshold</label><input type="number" step="0.01" name="threshold_amount" class="form-control" value="0"></div>
            <div class="col-md-2"><label class="form-label">Amount</label><input type="number" step="0.01" name="incentive_amount" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Earned On</label><x-tdate-input name="earned_on" /></div>
            <div class="col-12"><button class="btn btn-success btn-sm" type="submit">Save</button></div>
        </form>
    </div>
</div>
@endsection
