@extends('layouts.standalone')
@section('title', 'User Activity — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>User Activity</h4>
    <p>Audit trail grouped by actor for the selected period.</p>
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
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Total Users</small>
            <h4 class="mb-0 mt-1">{{ $summary['total_users'] }}</h4>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Total Actions</small>
            <h4 class="mb-0 mt-1">{{ $summary['total_actions'] }}</h4>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Activity by User ({{ $from }} to {{ $to }})</h6>
    <table class="table table-sm mb-0">
        <thead>
            <tr>
                <th>Actor</th>
                <th class="text-center">Actions</th>
                <th>Actions by Type</th>
                <th>Entities Touched</th>
                <th>First Action</th>
                <th>Last Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>User #{{ $user['actor_id'] }}</td>
                    <td class="text-center">{{ $user['total_actions'] }}</td>
                    <td>
                        @foreach ($user['actions_by_type'] as $action => $count)
                            <span class="badge bg-light text-dark">{{ $action }}: {{ $count }}</span>
                        @endforeach
                    </td>
                    <td>
                        @foreach ($user['entities_touched'] as $entity => $count)
                            <span class="badge bg-secondary">{{ $entity }}: {{ $count }}</span>
                        @endforeach
                    </td>
                    <td>{{ $user['first_action'] }}</td>
                    <td>{{ $user['last_action'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No user activity found for the selected period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
