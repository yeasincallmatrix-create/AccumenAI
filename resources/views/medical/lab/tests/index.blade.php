@extends('layouts.institute')

@section('title', 'Lab Tests — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Lab Test Catalog</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.lab.tests.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Add Test
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                               placeholder="Search name or code..." value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.lab.tests.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Code</th><th>Test</th><th>Normal Range</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($tests as $test)
                    <tr>
                        <td><strong>{{ $test->code }}</strong></td>
                        <td>{{ $test->display_name }}</td>
                        <td class="small">{{ $test->normal_range ?? '—' }}</td>
                        <td>৳{{ number_format($test->price, 2) }}</td>
                        <td>
                            @if($test->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.lab.tests.show', $test) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.lab.tests.edit', $test) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-danger" title="Delete"
                                        onclick="if(confirm('Delete this test? Tests with results cannot be deleted.')){document.getElementById('labtest-delete-{{ $test->id }}').submit();}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="labtest-delete-{{ $test->id }}"
                                  action="{{ route('medical.lab.tests.destroy', $test) }}"
                                  method="POST" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2"></i>
                            No lab tests found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tests->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
