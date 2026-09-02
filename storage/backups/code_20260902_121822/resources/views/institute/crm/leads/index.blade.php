@extends('layouts.standalone')

@section('title', 'Leads — AccumenAI')
@section('page_title', 'CRM Leads')

@section('content')

<div class="standalone-heading">
    <h4>Leads</h4>
    <p>Track potential customers through the pipeline (new → contacted → qualified → proposal → won/lost). Convert a lead to a contact in one click.</p>
    <a href="{{ route('crm.leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Lead</a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('crm.leads.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or phone">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status_id" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected((string) ($filters['status_id'] ?? '') === (string) $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Source</label>
                <select class="form-select form-select-sm" name="source_id" onchange="this.form.submit()">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected((string) ($filters['source_id'] ?? '') === (string) $source->id)>{{ $source->name }}</option>
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
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('crm.leads.index') }}">Reset</a>
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
                    <th>Lead</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Assigned to</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td class="text-muted">{{ $leads->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="fw-semibold">{{ $lead->displayName() }}</span>
                            @if ($lead->organization)
                                <div class="text-muted small">{{ $lead->organization->name }}</div>
                            @endif
                            @if ($lead->branch)
                                <div class="text-muted small">{{ $lead->branch->name }}</div>
                            @endif
                        </td>
                        <td>{{ $lead->email ?? '—' }}</td>
                        <td>{{ $lead->phone ?? '—' }}</td>
                        <td>
                            <span class="badge" style="background: {{ $lead->status?->color ?? '#6c757d' }}">{{ $lead->status?->name ?? '—' }}</span>
                        </td>
                        <td>{{ $lead->source?->name ?? '—' }}</td>
                        <td>{{ $lead->assignedUser?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('crm.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('crm.leads.edit', $lead) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('crm.leads.destroy', $lead) }}" class="d-inline" data-ajax-delete="1" data-confirm="Move this lead to trash?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No leads found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($leads->hasPages())
        <div class="p-2 border-top">{{ $leads->links() }}</div>
    @endif
</div>

@endsection