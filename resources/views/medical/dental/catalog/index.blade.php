@extends('layouts.institute')

@section('title', 'Procedure Catalog — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-book"></i> Procedure Catalog</h4>
        @if($user && $user->hasPermission('medical.dental.catalog.manage'))
            <a href="{{ route('medical.dental.catalog.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Add Entry
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name/code..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $k => $v)
                            <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
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
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Body Site</th>
                            <th>Default Fee</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($catalog as $item)
                            <tr>
                                <td>{{ $item->code ?? '—' }}</td>
                                <td><strong>{{ $item->name }}</strong></td>
                                <td><span class="badge bg-info">{{ $item->categoryLabel() }}</span></td>
                                <td>{{ $item->body_site ?? '—' }}</td>
                                <td>{{ number_format($item->default_fee, 2) }}</td>
                                <td>{{ $item->default_duration_minutes }} min</td>
                                <td>
                                    @if($item->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user && $user->hasPermission('medical.dental.catalog.manage'))
                                        <a href="{{ route('medical.dental.catalog.edit', $item) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No catalog entries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $catalog->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
