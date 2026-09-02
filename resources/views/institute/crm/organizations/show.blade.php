@extends('layouts.standalone')

@section('title', $organization->name.' — AccumenAI')
@section('page_title', 'Organization Details')

@section('content')

<div class="standalone-heading">
    <h4>{{ $organization->name }}</h4>
    <p>Organization record with contacts, leads, activity timeline, notes and follow-up tasks.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('crm.organizations.edit', $organization) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit</a>
        <a href="{{ route('crm.organizations.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success" data-auto-dismiss><i class="bi bi-check-circle-fill"></i> {{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" data-auto-dismiss><i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="admin-card mb-3">
            <h6 class="card-title">Overview</h6>
            <dl class="row mb-0 small">
                <dt class="col-4 text-muted">Email</dt>
                <dd class="col-8">{{ $organization->email ?? '—' }}</dd>
                <dt class="col-4 text-muted">Phone</dt>
                <dd class="col-8">{{ $organization->phone ?? '—' }}</dd>
                <dt class="col-4 text-muted">Website</dt>
                <dd class="col-8">{{ $organization->website ?? '—' }}</dd>
                <dt class="col-4 text-muted">Industry</dt>
                <dd class="col-8">{{ $organization->industry ?? '—' }}</dd>
                <dt class="col-4 text-muted">Country</dt>
                <dd class="col-8">{{ $organization->country?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Status</dt>
                <dd class="col-8">
                    @if ($organization->is_customer)<span class="badge text-bg-success me-1">Customer</span>@endif
                    @if ($organization->is_prospect)<span class="badge text-bg-info me-1">Prospect</span>@endif
                </dd>
            </dl>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Ownership</h6>
            <form method="POST" action="{{ route('crm.organizations.assign', $organization) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col">
                    <select class="form-select form-select-sm" name="assigned_user_id">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected((string) $organization->assigned_user_id === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-person-check"></i> Assign</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Open tasks</h6>
            @forelse ($openTasks as $task)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-truncate">{{ $task->title }}</span>
                    <form method="POST" action="{{ route('crm.tasks.toggle', $task) }}" class="ms-2">
                        @csrf
                        <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-check-lg"></i></button>
                    </form>
                </div>
            @empty
                <p class="text-muted mb-0">No open tasks.</p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Log activity</h6>
            <form method="POST" action="{{ route('crm.activities.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="subject_type" value="organization">
                <input type="hidden" name="subject_id" value="{{ $organization->id }}">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="type">
                        @foreach (['call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'follow_up' => 'Follow-up', 'note' => 'Note'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="summary" placeholder="Short summary" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm w-100" type="submit">Add</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Add note</h6>
            <form method="POST" action="{{ route('crm.notes.store', ['subject' => $organization]) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col">
                    <textarea class="form-control form-control-sm" name="body" rows="2" placeholder="Write a note..." required></textarea>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary btn-sm" type="submit">Save note</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Add follow-up task</h6>
            <form method="POST" action="{{ route('crm.tasks.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="subject_type" value="organization">
                <input type="hidden" name="subject_id" value="{{ $organization->id }}">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="title" placeholder="Task title" required>
                </div>
                <div class="col-md-3">
                    <input type="datetime-local" class="form-control form-control-sm" name="due_at">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-primary btn-sm w-100" type="submit">Create task</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Contacts ({{ $contacts->count() }})</h6>
            <ul class="list-unstyled mb-0">
                @forelse ($contacts as $contact)
                    <li class="mb-1"><a href="{{ route('crm.contacts.show', $contact) }}">{{ $contact->displayName() }}</a> <span class="text-muted small">{{ $contact->contactType?->name }}</span></li>
                @empty
                    <li class="text-muted">No contacts linked.</li>
                @endforelse
            </ul>
        </div>

        <div class="admin-card">
            <h6 class="card-title">Timeline</h6>
            @forelse ($timeline as $entry)
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-truncate">
                        <span class="badge text-bg-light border me-1">{{ $entry['label'] }}</span>
                        {{ $entry['summary'] }}
                    </span>
                    <span class="small text-muted ms-2">{{ $entry['meta'] }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>

@if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="admin-card">
                @include('documents._panel', ['entityType' => 'crm-organization', 'entityId' => $organization->id])
            </div>
        </div>
    </div>
@endif

@endsection