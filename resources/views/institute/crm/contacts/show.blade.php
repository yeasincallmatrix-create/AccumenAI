@extends('layouts.standalone')

@section('title', 'Contact — AccumenAI')
@section('page_title', 'Contact Details')

@section('content')

<div class="standalone-heading">
    <h4>{{ $contact->displayName() }}</h4>
    <p>Full contact record with assignment, activity timeline, notes and follow-up tasks.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('crm.contacts.edit', $contact) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit</a>
        <a href="{{ route('crm.contacts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
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
                <dd class="col-8">{{ $contact->email ?? '—' }}</dd>
                <dt class="col-4 text-muted">Phone</dt>
                <dd class="col-8">{{ $contact->phone ?? '—' }}</dd>
                <dt class="col-4 text-muted">Alt / WhatsApp</dt>
                <dd class="col-8">{{ $contact->phone_alt ?? $contact->whatsapp ?? '—' }}</dd>
                <dt class="col-4 text-muted">Type</dt>
                <dd class="col-8">{{ $contact->contactType?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Organization</dt>
                <dd class="col-8">{{ $contact->organization?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Designation</dt>
                <dd class="col-8">{{ $contact->designation ?? '—' }}</dd>
                <dt class="col-4 text-muted">Address</dt>
                <dd class="col-8">{{ trim($contact->address_line1.' '.($contact->city ?? '')) ?: '—' }}</dd>
                <dt class="col-4 text-muted">Country</dt>
                <dd class="col-8">{{ $contact->country?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Source</dt>
                <dd class="col-8">{{ $contact->source?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Status</dt>
                <dd class="col-8">
                    @if ($contact->is_customer)<span class="badge text-bg-success me-1">Customer</span>@endif
                    @if ($contact->is_prospect)<span class="badge text-bg-info me-1">Prospect</span>@endif
                    <span class="badge {{ $contact->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($contact->status) }}</span>
                </dd>
            </dl>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Ownership</h6>
            <form method="POST" action="{{ route('crm.contacts.assign', $contact) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col">
                    <select class="form-select form-select-sm" name="assigned_user_id">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected((string) $contact->assigned_user_id === (string) $member->id)>{{ $member->name }}</option>
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
                <input type="hidden" name="subject_type" value="contact">
                <input type="hidden" name="subject_id" value="{{ $contact->id }}">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="type">
                        @foreach (['call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'follow_up' => 'Follow-up', 'note' => 'Note'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" name="summary" value="{{ old('summary') }}" placeholder="Short summary" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm w-100" type="submit">Add</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Add note</h6>
            <form method="POST" action="{{ route('crm.notes.store', ['subject' => $contact]) }}" class="row g-2 align-items-end">
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
                <input type="hidden" name="subject_type" value="contact">
                <input type="hidden" name="subject_id" value="{{ $contact->id }}">
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
                @include('documents._panel', ['entityType' => 'crm-contact', 'entityId' => $contact->id])
            </div>
        </div>
    </div>
@endif

@endsection