@extends('layouts.standalone')

@section('title', 'CRM Dashboard — AccumenAI')
@section('page_title', 'CRM')

@section('content')

<div class="standalone-heading">
    <h4>CRM Dashboard</h4>
    <p>Contacts, organizations and leads for {{ $institute->name }}. Search, filter, assign ownership and follow up — everything is branch-aware and permission-controlled.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('crm.contacts.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-people-fill me-1"></i>Contacts</a>
        <a href="{{ route('crm.organizations.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-building me-1"></i>Organizations</a>
        <a href="{{ route('crm.leads.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-lightning-charge-fill me-1"></i>Leads</a>
        <a href="{{ route('crm.tasks.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-check me-1"></i>Tasks</a>
        <a href="{{ route('crm.activities.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-clock-history me-1"></i>Activity Feed</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Contacts</div>
            <div class="fs-4 fw-semibold">{{ $contactsCount }}</div>
            <div class="small text-muted">{{ $customerContactsCount }} customers</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Organizations</div>
            <div class="fs-4 fw-semibold">{{ $organizationsCount }}</div>
            <div class="small text-muted">{{ $customerOrganizationsCount }} customers</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Leads</div>
            <div class="fs-4 fw-semibold">{{ $leadsCount }}</div>
            <div class="small text-muted">{{ $openLeadsCount }} in pipeline</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="admin-card h-100">
            <div class="small text-muted">Open Tasks</div>
            <div class="fs-4 fw-semibold">{{ $openTasksCount }}</div>
            <div class="small {{ $overdueTasksCount > 0 ? 'text-danger' : 'text-muted' }}">{{ $overdueTasksCount }} overdue</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="admin-card mb-3">
            <h6 class="card-title">Leads by status</h6>
            @forelse ($leadStatuses as $status)
                @php $row = $leadsByStatus->firstWhere('status_id', $status->id); @endphp
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span><span class="badge" style="background: {{ $status->color ?? '#6c757d' }}">{{ $status->name }}</span></span>
                    <span class="fw-semibold">{{ $row->total ?? 0 }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No lead statuses configured.</p>
            @endforelse
        </div>
        <div class="admin-card">
            <h6 class="card-title">Upcoming follow-ups</h6>
            @forelse ($openTasks as $task)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-truncate">{{ $task->title }}</span>
                    <span class="small text-muted ms-2">{{ $task->due_at?->format('Y-m-d') ?? 'No due date' }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No open tasks.</p>
            @endforelse
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <h6 class="card-title">Recent activity</h6>
            @forelse ($recentActivities as $activity)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-truncate">
                        <span class="badge text-bg-light border me-1">{{ $activity->type }}</span>
                        {{ $activity->summary }}
                    </span>
                    <span class="small text-muted ms-2">{{ $activity->activity_at?->format('Y-m-d H:i') }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection