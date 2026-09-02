@extends('layouts.standalone')

@section('title', 'Asset Categories — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Asset Categories</h4>
    <p>Configure asset categories with default depreciation settings and account mappings.</p>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#categoryForm"><i class="bi bi-plus-lg me-1"></i>{{ $category ? 'Edit Category' : 'New Category' }}</button>
</div>

@if ($category)
    <div class="collapse show" id="categoryForm">
@else
    <div class="collapse" id="categoryForm">
@endif
    <div class="admin-card mb-3">
        <h6 class="card-title">{{ $category ? 'Edit Category' : 'Create Category' }}</h6>
        <form method="POST" action="{{ $category ? route('fixed_assets.categories.update', $category) : route('fixed_assets.categories.store') }}">
            @csrf
            @if ($category)
                @method('PUT')
            @endif
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $category?->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Code</label>
                    <input type="text" class="form-control form-control-sm" name="code" value="{{ old('code', $category?->code) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default Depreciation Method</label>
                    <select class="form-select form-select-sm" name="default_depreciation_method">
                        <option value="">None</option>
                        @foreach (['straight_line', 'declining_balance', 'double_declining_balance', 'sum_of_years_digits', 'units_of_production', 'reducing_balance'] as $m)
                            <option value="{{ $m }}" @selected(old('default_depreciation_method', $category?->default_depreciation_method) === $m)>{{ ucfirst(str_replace('_', ' ', $m)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default Useful Life (months)</label>
                    <input type="number" class="form-control form-control-sm" name="default_useful_life_months" value="{{ old('default_useful_life_months', $category?->default_useful_life_months) }}" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Default Residual Value %</label>
                    <input type="number" class="form-control form-control-sm" name="default_residual_value_pct" value="{{ old('default_residual_value_pct', $category?->default_residual_value_pct) }}" min="0" max="100" step="0.01">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Active</label>
                    <select class="form-select form-select-sm" name="is_active">
                        <option value="1" @selected(old('is_active', $category?->is_active ?? true))>Yes</option>
                        <option value="0" @selected(old('is_active', $category?->is_active ?? true) === false)>No</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control form-control-sm" name="description" rows="2">{{ old('description', $category?->description) }}</textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary btn-sm" type="submit">{{ $category ? 'Update' : 'Create' }}</button>
                @if ($category)
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixed_assets.categories.index') }}">Cancel</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Default Method</th>
                    <th>Default Life</th>
                    <th>Assets</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $cat)
                    <tr>
                        <td class="text-muted">{{ $cat->code ?? '—' }}</td>
                        <td>{{ $cat->name }}</td>
                        <td>{{ $cat->default_depreciation_method ? ucfirst(str_replace('_', ' ', $cat->default_depreciation_method)) : '—' }}</td>
                        <td>{{ $cat->default_useful_life_months ? $cat->default_useful_life_months.' mo' : '—' }}</td>
                        <td>{{ $cat->assets_count ?? $cat->assets_count ?? 0 }}</td>
                        <td>
                            <span class="badge text-bg-{{ $cat->is_active ? 'success' : 'secondary' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('fixed_assets.categories.edit', $cat) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No categories found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($categories instanceof \Illuminate\Pagination\LengthAwarePaginator && $categories->hasPages())
        <div class="p-2 border-top">{{ $categories->links() }}</div>
    @endif
</div>

@endsection
