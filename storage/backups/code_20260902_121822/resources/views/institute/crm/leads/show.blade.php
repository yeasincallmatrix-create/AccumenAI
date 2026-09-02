@extends('layouts.standalone')

@section('title', $lead->displayName().' — AccumenAI')
@section('page_title', 'Lead Details')

@section('content')

<div class="standalone-heading">
    <h4>{{ $lead->displayName() }}</h4>
    <p>Lead record with pipeline status, assignment, activity timeline, notes and follow-up tasks.</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('crm.leads.edit', $lead) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit</a>
        @if ($lead->converted_contact_id === null)
            <form method="POST" action="{{ route('crm.leads.convert', $lead) }}">
                @csrf
                <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-person-check me-1"></i>Convert to contact</button>
            </form>
        @else
            <a href="{{ route('crm.contacts.show', $lead->convertedContact) }}" class="btn btn-success btn-sm"><i class="bi bi-person-check me-1"></i>View converted contact</a>
        @endif
        <a href="{{ route('crm.leads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
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
                <dd class="col-8">{{ $lead->email ?? '—' }}</dd>
                <dt class="col-4 text-muted">Phone</dt>
                <dd class="col-8">{{ $lead->phone ?? '—' }}</dd>
                <dt class="col-4 text-muted">Status</dt>
                <dd class="col-8"><span class="badge" style="background: {{ $lead->status?->color ?? '#6c757d' }}">{{ $lead->status?->name ?? '—' }}</span></dd>
                <dt class="col-4 text-muted">Source</dt>
                <dd class="col-8">{{ $lead->source?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Organization</dt>
                <dd class="col-8">{{ $lead->organization?->name ?? '—' }}</dd>
                <dt class="col-4 text-muted">Estimated value</dt>
                <dd class="col-8">{{ $lead->value_amount !== null ? '$ '.number_format((float) $lead->value_amount, 2) : '—' }}</dd>
                <dt class="col-4 text-muted">Converted</dt>
                <dd class="col-8">{{ $lead->converted_at ? $lead->converted_at->format('Y-m-d H:i') : 'No' }}</dd>
            </dl>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Ownership</h6>
            <form method="POST" action="{{ route('crm.leads.assign', $lead) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col">
                    <select class="form-select form-select-sm" name="assigned_user_id">
                        <option value="">Unassigned</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}" @selected((string) $lead->assigned_user_id === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-person-check"></i> Assign</button>
                </div>
            </form>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Pipeline</h6>
            <form method="POST" action="{{ route('crm.leads.update', $lead) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="first_name" value="{{ $lead->first_name }}">
                <input type="hidden" name="last_name" value="{{ $lead->last_name }}">
                <div class="input-group input-group-sm">
                    <select class="form-select" name="status_id">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((string) $lead->status_id === (string) $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-card mb-3">
            <h6 class="card-title">Log activity</h6>
            <form method="POST" action="{{ route('crm.activities.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="subject_type" value="lead">
                <input type="hidden" name="subject_id" value="{{ $lead->id }}">
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
            <form method="POST" action="{{ route('crm.notes.store', ['subject' => $lead]) }}" class="row g-2 align-items-end">
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
                <input type="hidden" name="subject_type" value="lead">
                <input type="hidden" name="subject_id" value="{{ $lead->id }}">
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
                @include('documents._panel', ['entityType' => 'crm-lead', 'entityId' => $lead->id])
            </div>
        </div>
    </div>
@endif

@endsection