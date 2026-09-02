@extends('layouts.standalone')

@section('title', 'Fixed Assets — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Fixed Assets</h4>
    <p>Register of long-term assets. Draft assets can be edited; capitalized assets trigger depreciation.</p>
    <a href="{{ route('fixed_assets.assets.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Asset</a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('fixed_assets.assets.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, code or serial">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Category</label>
                <select class="form-select form-select-sm" name="category_id" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Location</label>
                <select class="form-select form-select-sm" name="location_id" onchange="this.form.submit()">
                    <option value="">All locations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('fixed_assets.assets.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">NBV</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assets as $asset)
                    <tr>
                        <td class="text-muted">{{ $asset->asset_code }}</td>
                        <td>
                            <a href="{{ route('fixed_assets.assets.show', $asset) }}" class="text-decoration-none fw-semibold">{{ $asset->name }}</a>
                            @if ($asset->serial_number)
                                <div class="text-muted small">S/N: {{ $asset->serial_number }}</div>
                            @endif
                        </td>
                        <td>{{ $asset->category?->name ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-{{ in_array($asset->status, ['active']) ? 'success' : (in_array($asset->status, ['draft', 'acquired']) ? 'warning' : 'secondary') }}">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span>
                        </td>
                        <td class="text-end">{{ number_format($asset->cost(), 2) }}</td>
                        <td class="text-end">{{ number_format($asset->netBookValue(), 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('fixed_assets.assets.show', $asset) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('fixed_assets.assets.edit', $asset) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No fixed assets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($assets->hasPages())
        <div class="p-2 border-top">{{ $assets->links() }}</div>
    @endif
</div>

@endsection
