@extends('layouts.standalone')

@section('title', 'Contacts — AccumenAI')
@section('page_title', 'CRM Contacts')

@section('content')

<div class="standalone-heading">
    <h4>Contacts</h4>
    <p>Manage people: customers, prospects, patients, clients, suppliers and more. Duplicate emails are blocked at the service level.</p>
    <a href="{{ route('crm.contacts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Contact</a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('crm.contacts.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or phone">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="contact_type_id" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach ($contactTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['contact_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Organization</label>
                <select class="form-select form-select-sm" name="organization_id" onchange="this.form.submit()">
                    <option value="">All organizations</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((string) ($filters['organization_id'] ?? '') === (string) $organization->id)>{{ $organization->name }}</option>
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
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('crm.contacts.index') }}">Reset</a>
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
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Assigned to</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contacts as $contact)
                    <tr>
                        <td class="text-muted">{{ $contacts->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="fw-semibold">{{ $contact->displayName() }}</span>
                            @if ($contact->organization)
                                <div class="text-muted small">{{ $contact->organization->name }}</div>
                            @endif
                            @if ($contact->branch)
                                <div class="text-muted small">{{ $contact->branch->name }}</div>
                            @endif
                        </td>
                        <td>{{ $contact->email ?? '—' }}</td>
                        <td>{{ $contact->phone ?? '—' }}</td>
                        <td>{{ $contact->contactType?->name ?? '—' }}</td>
                        <td>
                            @if ($contact->is_customer)<span class="badge text-bg-success me-1">Customer</span>@endif
                            @if ($contact->is_prospect)<span class="badge text-bg-info">Prospect</span>@endif
                            @if (! $contact->is_customer && ! $contact->is_prospect)<span class="text-muted">—</span>@endif
                        </td>
                        <td>{{ $contact->assignedUser?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('crm.contacts.show', $contact) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('crm.contacts.edit', $contact) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('crm.contacts.destroy', $contact) }}" class="d-inline" data-ajax-delete="1" data-confirm="Move this contact to trash?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No contacts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($contacts->hasPages())
        <div class="p-2 border-top">{{ $contacts->links() }}</div>
    @endif
</div>

@endsection