@extends('layouts.standalone')

@section('title', 'Activity Feed — AccumenAI')
@section('page_title', 'CRM Activity Feed')

@section('content')

<div class="standalone-heading">
    <h4>Activity Feed</h4>
    <p>Every logged interaction (calls, emails, meetings, follow-ups, notes) across contacts, organizations and leads.</p>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('crm.activities.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach (['call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting', 'follow_up' => 'Follow-up', 'note' => 'Note', 'system' => 'System'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Subject</label>
                <select class="form-select form-select-sm" name="subject_type" onchange="this.form.submit()">
                    <option value="">All subjects</option>
                    <option value="contact" @selected(($filters['subject_type'] ?? '') === 'contact')>Contacts</option>
                    <option value="organization" @selected(($filters['subject_type'] ?? '') === 'organization')>Organizations</option>
                    <option value="lead" @selected(($filters['subject_type'] ?? '') === 'lead')>Leads</option>
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
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('crm.activities.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Type</th>
                    <th>Summary</th>
                    <th>Subject</th>
                    <th>At</th>
                    <th>Assigned to</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activities as $activity)
                    <tr>
                        <td class="text-muted">{{ $activities->firstItem() + $loop->index }}</td>
                        <td><span class="badge text-bg-light border">{{ $activity->type }}</span></td>
                        <td class="text-truncate" style="max-width:340px">{{ $activity->summary }}</td>
                        <td>
                            @if ($activity->subject_type === 'contact')
                                <a href="{{ route('crm.contacts.show', $activity->subject_id) }}" class="small">Contact #{{ $activity->subject_id }}</a>
                            @elseif ($activity->subject_type === 'organization')
                                <a href="{{ route('crm.organizations.show', $activity->subject_id) }}" class="small">Organization #{{ $activity->subject_id }}</a>
                            @elseif ($activity->subject_type === 'lead')
                                <a href="{{ route('crm.leads.show', $activity->subject_id) }}" class="small">Lead #{{ $activity->subject_id }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="small">{{ $activity->activity_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $activity->assignedUser?->name ?? '—' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('crm.activities.destroy', $activity) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this activity?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No activities found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($activities->hasPages())
        <div class="p-2 border-top">{{ $activities->links() }}</div>
    @endif
</div>

@endsection