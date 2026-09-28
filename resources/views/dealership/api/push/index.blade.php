@extends('layouts.standalone')

@section('title', 'Push Notifications — AccumenAI')
@section('page_title', 'Push Notifications')

@section('content')
<div class="standalone-heading">
    <h4>Push Notifications (পুশ বিজ্ঞপ্তি)</h4>
    <p>Dispatch queue only — no provider keys yet.</p>
</div>

@include('dealership.api._nav')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('dealership.push.index') }}" class="row g-2">
        <div class="col-md-2"><label class="form-label">SR</label><input type="number" name="sr" class="form-control form-control-sm" value="{{ $filters['sr'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm"><option value="">— All —</option>@foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
        </div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </form>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Recipient SR</th><th>Title</th><th>Status</th><th>Scheduled</th><th>Sent</th><th></th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->recipient_sales_force_id }}</td><td>{{ $row->title }}</td>
                        <td><span class="badge bg-secondary">{{ $row->status }}</span></td>
                        <td>{{ $row->scheduled_at ?? '—' }}</td><td>{{ $row->sent_at ?? '—' }}</td>
                        <td>@if($row->status === 'queued')<form method="POST" action="{{ route('dealership.push.cancel', $row) }}">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">কোনো বিজ্ঞপ্তি নেই (No notifications yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rows->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন বিজ্ঞপ্তি (Compose)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.push.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="col-md-5"><label class="form-label">Body</label><input type="text" name="body" class="form-control" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success btn-sm" type="submit">Queue</button></div>
        </form>
    </div>
</div>
@endsection
