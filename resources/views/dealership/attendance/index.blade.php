@extends('layouts.standalone')

@section('title', 'Attendance — AccumenAI')
@section('page_title', 'Attendance')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>হাজিরা (Attendance)</h4>
</div>

<div class="filter-card card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('dealership.attendance.index') }}" class="row g-2">
            <div class="col-md-3"><label class="form-label">SR (sales_force_id)</label><input type="number" name="sr" class="form-control" value="{{ $filters['sr'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">From / হইতে</label><x-tdate-input name="from" :value="$filters['from'] ?? null" /></div>
            <div class="col-md-2"><label class="form-label">To / পর্যন্ত</label><x-tdate-input name="to" :value="$filters['to'] ?? null" /></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm" type="submit">Filter</button></div>
        </form>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>SR</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Beat</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr><td>{{ $row->sales_force_id }}</td><td>{{ $row->attendance_date }}</td><td>{{ $row->check_in_at ?? '—' }}</td><td>{{ $row->check_out_at ?? '—' }}</td><td>{{ $row->beat_id ?? '—' }}</td><td><span class="badge bg-secondary">{{ $row->status }}</span></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">কোনো হাজিরা নেই (No attendance yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rows->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন হাজিরা (Mark Attendance)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.attendance.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Date</label><x-tdate-input name="attendance_date" /></div>
            <div class="col-md-2"><label class="form-label">Status</label>
                <select name="status" class="form-select">@foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select>
            </div>
            <div class="col-md-2"><label class="form-label">Beat ID</label><input type="number" name="beat_id" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Save</button></div>
        </form>
    </div>
</div>
@endsection
