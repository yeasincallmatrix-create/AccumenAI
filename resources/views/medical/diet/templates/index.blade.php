@extends('layouts.institute')

@section('title', 'Diet Templates — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-book"></i> Diet Templates</h4>
        @if($user && $user->hasPermission('medical.diet.template.manage'))
            <a href="{{ route('medical.diet.templates.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Template
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="diet_type" class="form-select form-select-sm">
                        <option value="">All diet types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('diet_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Diet Type</th>
                            <th>Calories</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $t)
                            <tr>
                                <td><strong>{{ $t->name }}</strong><br><small class="text-muted">{{ \Illuminate\Support\Str::limit($t->description ?? '', 60) }}</small></td>
                                <td>{{ $t->dietTypeLabel() }}</td>
                                <td>{{ $t->total_calories ?? '—' }}</td>
                                <td>{{ $t->isGlobal() ? 'Global' : 'Institute' }}</td>
                                <td>{{ $t->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('medical.diet.templates.show', $t) }}" class="btn btn-outline-primary btn-sm">View</a>
                                    <a href="{{ route('medical.diet.templates.edit', $t) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No templates found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $templates->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
