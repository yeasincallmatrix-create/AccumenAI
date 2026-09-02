@extends('layouts.standalone')
@section('title', 'Financial Change History — AccumenAI')
@section('page_title', 'Reports')

@section('content')
<div class="standalone-heading">
    <h4>Financial Change History</h4>
    <p>All audit trail entries for financial entities in the selected period.</p>
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
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">Total Changes</small>
            <h4 class="mb-0 mt-1">{{ $summary['total_changes'] }}</h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">By Entity Type</small>
            <div class="mt-1">
                @foreach ($summary['by_entity'] as $entity => $count)
                    <span class="badge bg-secondary">{{ $entity }}: {{ $count }}</span>
                @endforeach
                @if ($summary['by_entity']->isEmpty())
                    <span class="text-muted">None</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <small class="text-muted">By Action</small>
            <div class="mt-1">
                @foreach ($summary['by_action'] as $action => $count)
                    <span class="badge bg-light text-dark">{{ $action }}: {{ $count }}</span>
                @endforeach
                @if ($summary['by_action']->isEmpty())
                    <span class="text-muted">None</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Change Log ({{ $from }} to {{ $to }})</h6>
    <table class="table table-sm mb-0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Entity Type</th>
                <th>Entity ID</th>
                <th>Action</th>
                <th>Actor</th>
                <th>IP</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($changes as $change)
                <tr>
                    <td>{{ $change->id }}</td>
                    <td><span class="badge bg-secondary">{{ $change->entity_type }}</span></td>
                    <td>{{ $change->entity_id }}</td>
                    <td><span class="badge bg-light text-dark">{{ $change->action }}</span></td>
                    <td>User #{{ $change->actor_id }}</td>
                    <td>{{ $change->ip }}</td>
                    <td>{{ $change->created_at }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">No financial changes found for the selected period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
