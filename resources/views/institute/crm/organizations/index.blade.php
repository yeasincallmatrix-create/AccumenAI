@extends('layouts.standalone')

@section('title', 'Organizations — AccumenAI')
@section('page_title', 'CRM Organizations')

@section('content')

<div class="standalone-heading">
    <h4>Organizations</h4>
    <p>Manage companies, institutions, suppliers and partners. Duplicate names are blocked at the service level.</p>
    <a href="{{ route('crm.organizations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Organization</a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('crm.organizations.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or phone">
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
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="filter-span">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_customer" value="1" id="filterCustomers" @checked(! empty($filters['is_customer'])) onchange="this.form.submit()">
                    <label class="form-check-label" for="filterCustomers">Customers</label>
                </div>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('crm.organizations.index') }}">Reset</a>
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
                    <th>Organization</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Contacts</th>
                    <th>Status</th>
                    <th>Assigned to</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td class="text-muted">{{ $organizations->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="fw-semibold">{{ $organization->name }}</span>
                            @if ($organization->branch)
                                <div class="text-muted small">{{ $organization->branch->name }}</div>
                            @endif
                        </td>
                        <td>{{ $organization->email ?? '—' }}</td>
                        <td>{{ $organization->phone ?? '—' }}</td>
                        <td>{{ $organization->contacts_count }}</td>
                        <td>
                            @if ($organization->is_customer)<span class="badge text-bg-success me-1">Customer</span>@endif
                            @if ($organization->is_prospect)<span class="badge text-bg-info">Prospect</span>@endif
                            @if (! $organization->is_customer && ! $organization->is_prospect)<span class="text-muted">—</span>@endif
                        </td>
                        <td>{{ $organization->assignedUser?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('crm.organizations.show', $organization) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('crm.organizations.edit', $organization) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('crm.organizations.destroy', $organization) }}" class="d-inline" data-ajax-delete="1" data-confirm="Move this organization to trash?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No organizations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($organizations->hasPages())
        <div class="p-2 border-top">{{ $organizations->links() }}</div>
    @endif
</div>

@endsection