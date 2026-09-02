@extends('layouts.standalone')
@section('title', 'Approval History — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Approval History</h4>
    <p>Approval requests with status, actions, and resolutions.</p>
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
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Total Requests</small>
            <h4 class="mb-0 mt-1">{{ $summary['total_requests'] }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Pending</small>
            <h4 class="mb-0 mt-1 text-warning">{{ $summary['pending'] }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Approved</small>
            <h4 class="mb-0 mt-1 text-success">{{ $summary['approved'] }}</h4>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card text-center">
            <small class="text-muted">Rejected</small>
            <h4 class="mb-0 mt-1 text-danger">{{ $summary['rejected'] }}</h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Approval Requests ({{ $from }} to {{ $to }})</h6>
    <table class="table table-sm mb-0">
        <thead>
            <tr>
                <th>Requested At</th>
                <th>Workflow</th>
                <th>Requested By</th>
                <th>Status</th>
                <th class="text-end">Amount</th>
                <th>Resolved By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $request)
                <tr>
                    <td>{{ $request->requested_at }}</td>
                    <td>{{ $request->workflow?->name ?? 'N/A' }}</td>
                    <td>{{ $request->requestedBy?->name ?? 'User #' . $request->requested_by }}</td>
                    <td>
                        @if ($request->status === 'pending')
                            <span class="badge bg-warning text-dark">Pending</span>
                        @elseif ($request->status === 'approved')
                            <span class="badge bg-success">Approved</span>
                        @else
                            <span class="badge bg-danger">Rejected</span>
                        @endif
                    </td>
                    <td class="text-end">{{ number_format((float) $request->amount, 2) }}</td>
                    <td>{{ $request->resolvedBy?->name ?? ($request->status === 'pending' ? '—' : 'System') }}</td>
                    <td>
                        @forelse ($request->actions as $action)
                            <span class="badge bg-light text-dark">{{ $action->action }} by #{{ $action->actor_id }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No approval requests found for the selected period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
