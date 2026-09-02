@extends('layouts.standalone')

@section('title', 'Tasks — AccumenAI')
@section('page_title', 'CRM Follow-up Tasks')

@section('content')

<div class="standalone-heading">
    <h4>Tasks</h4>
    <p>Follow-up tasks assigned to staff, optionally linked to a contact, organization or lead.</p>
    <a href="#newTask" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Task</a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('crm.tasks.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Task title">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">Open / In progress</option>
                    <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                    <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>In progress</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Priority</label>
                <select class="form-select form-select-sm" name="priority" onchange="this.form.submit()">
                    <option value="">All priorities</option>
                    @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Assigned to</label>
                <select class="form-select form-select-sm" name="assigned_user_id" onchange="this.form.submit()">
                    <option value="">Anyone</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected((string) ($filters['assigned_user_id'] ?? '') === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="my_tasks" value="1" id="myTasks" @checked(! empty($filters['my_tasks'])) onchange="this.form.submit()">
                    <label class="form-check-label" for="myTasks">Only mine</label>
                </div>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('crm.tasks.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3" id="newTask">
    <h6 class="card-title">New task</h6>
    <form method="POST" action="{{ route('crm.tasks.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" name="title" placeholder="Task title" required>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="priority">
                @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="datetime-local" class="form-control form-control-sm" name="due_at">
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="assigned_user_id">
                <option value="">Unassigned</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100" type="submit">Create</button>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Task</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th>Assigned to</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr>
                        <td class="text-muted">{{ $tasks->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="{{ $task->status === 'completed' ? 'text-decoration-line-through text-muted' : 'fw-semibold' }}">{{ $task->title }}</span>
                            @if ($task->subject_type)
                                <div class="text-muted small">{{ ucfirst($task->subject_type) }} #{{ $task->subject_id }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ match ($task->priority) { 'low' => 'text-bg-light border', 'high' => 'text-bg-warning', 'urgent' => 'text-bg-danger', default => 'text-bg-secondary' } }}">{{ ucfirst($task->priority) }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $task->status === 'completed' ? 'text-bg-success' : ($task->status === 'cancelled' ? 'text-bg-danger' : 'text-bg-primary') }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                        </td>
                        <td class="{{ $task->due_at && $task->due_at->isPast() && $task->status !== 'completed' ? 'text-danger' : '' }}">{{ $task->due_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $task->assignedUser?->name ?? '—' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('crm.tasks.toggle', $task) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm {{ $task->status === 'completed' ? 'btn-outline-secondary' : 'btn-outline-success' }}" type="submit">
                                    <i class="bi {{ $task->status === 'completed' ? 'bi-arrow-counterclockwise' : 'bi-check-lg' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('crm.tasks.destroy', $task) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this task?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No tasks found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($tasks->hasPages())
        <div class="p-2 border-top">{{ $tasks->links() }}</div>
    @endif
</div>

@endsection