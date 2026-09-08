@extends('layouts.institute')

@section('title', 'Roles — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Roles</li>
    </ol>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Role &amp; Permission Management</h4>
        <p class="page-header-desc">{{ $roles->count() }} roles available</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('staff.roles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Create Role
        </a>
        <a class="btn btn-secondary" href="{{ route('staff.invite') }}">
            <i class="bi bi-person-plus me-1"></i>Invite Staff
        </a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Permissions</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                    <tr>
                        <td>
                            <strong>{{ $role->name }}</strong>
                            @if(!$role->managed)
                                <span class="badge bg-secondary ms-1" title="Global role — read only">Global</span>
                            @endif
                        </td>
                        <td><code>{{ $role->slug }}</code></td>
                        <td><span class="badge bg-info text-dark">{{ $role->permissions_count }} permissions</span></td>
                        <td>{{ $role->member_count }}</td>
                        <td>
                            <span class="badge bg-{{ $role->status === 'active' ? 'success' : 'danger' }}">
                                {{ ucfirst($role->status) }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            @if($role->managed)
                                <a href="{{ route('staff.roles.edit', $role) }}" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" title="Delete" onclick="confirmRoleDelete({{ $role->id }}, '{{ addslashes($role->name) }}', {{ $role->member_count }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @else
                                <span class="text-muted small">Read only</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-shield-lock"></i> No roles found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="role-delete-form" method="POST" style="display:none;">
    @csrf @method('DELETE')
</form>

<script>
function confirmRoleDelete(id, name, members) {
    if (members > 0) {
        alert('Cannot delete "' + name + '": ' + members + ' member(s) still assigned to this role!');
        return;
    }
    if (confirm('Delete role "' + name + '"? This cannot be undone.')) {
        const form = document.getElementById('role-delete-form');
        form.action = `{{ route('staff.roles.index') }}/${id}`;
        form.submit();
    }
}
</script>
@endsection
