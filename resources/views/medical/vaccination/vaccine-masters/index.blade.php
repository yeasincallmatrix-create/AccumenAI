@extends('layouts.institute')

@section('title', 'Vaccine Catalog — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-shield-check"></i> Vaccine Catalog</h4>
        @if($user && $user->hasPermission('medical.vaccination.manage'))
            <a href="{{ route('medical.vaccination.vaccine-masters.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Add Vaccine
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
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
                            <th>Doses</th>
                            <th>Route</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vaccines as $v)
                            <tr>
                                <td>{{ $v->code ?? '—' }}</td>
                                <td><strong>{{ $v->name }}</strong></td>
                                <td><span class="badge bg-info">{{ $v->categoryLabel() }}</span></td>
                                <td>{{ $v->doses_in_series }}</td>
                                <td>{{ $v->routeLabel() ?? '—' }}</td>
                                <td>{{ number_format($v->default_fee, 2) }}</td>
                                <td><span class="badge bg-{{ $v->is_active ? 'success' : 'secondary' }}">{{ $v->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    <a href="{{ route('medical.vaccination.vaccine-masters.show', $v) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No vaccines found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $vaccines->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
