@extends('layouts.standalone')

@section('title', 'SR Commissions — AccumenAI')
@section('page_title', 'SR Commissions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-percent me-2"></i>কমিশন (Commissions)</h4>
</div>

<div class="filter-card card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('dealership.commissions.index') }}" class="row g-2">
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
            <thead><tr><th>SR</th><th>Period</th><th>Base</th><th>Rate %</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($commissions as $row)
                    <tr>
                        <td>{{ $row->sales_force_id }}</td><td>{{ $row->period_start }} → {{ $row->period_end }}</td>
                        <td>{{ number_format($row->base_amount, 2) }}</td><td>{{ $row->commission_rate }}</td>
                        <td>{{ number_format($row->commission_amount, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ $row->status }}</span></td>
                        <td>@if($row->status === 'pending')<form method="POST" action="{{ route('dealership.commissions.approve', $row) }}">@csrf<button class="btn btn-sm btn-success" type="submit">Approve</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো কমিশন নেই (No commissions yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $commissions->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন কমিশন (New Commission)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.commissions.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Start</label><x-tdate-input name="period_start" /></div>
            <div class="col-md-2"><label class="form-label">End</label><x-tdate-input name="period_end" /></div>
            <div class="col-md-2"><label class="form-label">Base Amount</label><input type="number" step="0.01" name="base_amount" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Rate %</label><input type="number" step="0.01" name="commission_rate" class="form-control" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Save</button></div>
        </form>
    </div>
</div>
@endsection
