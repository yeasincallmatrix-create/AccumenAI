@extends('layouts.institute')

@section('title', 'Medicines — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Medicine Catalog</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.pharmacy.medicines.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Add Medicine
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                               placeholder="{{ mawa_dgda_enabled() ? 'Search generic, brand, code or DGDA...' : 'Search generic, brand or code...' }}"
                               value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
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
                @if(mawa_dgda_enabled())
                <div class="col-md-2">
                    <select name="dgda" class="form-select" onchange="this.form.submit()" title="Filter by DGDA registry code">
                        <option value="">All DGDA</option>
                        <option value="coded" @selected(request('dgda') === 'coded')>With DGDA code</option>
                        <option value="pending" @selected(request('dgda') === 'pending')>DGDA sync pending</option>
                    </select>
                </div>
                @endif
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.pharmacy.medicines.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Medicine</th>
                        @if(mawa_dgda_enabled())<th>DGDA Code</th>@endif
                        <th>Form / Strength</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($medicines as $medicine)
                    <tr>
                        <td><strong>{{ $medicine->code }}</strong></td>
                        <td>
                            {{ $medicine->display_name }}
                            @if($medicine->is_controlled)
                                <span class="badge bg-danger ms-1">Controlled</span>
                            @endif
                            @if(!$medicine->requires_prescription)
                                <span class="badge bg-info text-dark ms-1">OTC</span>
                            @endif
                        </td>
                        @if(mawa_dgda_enabled())
                        <td>
                            @if($medicine->dgda_code)
                                <code>{{ $medicine->dgda_code }}</code>
                                @if($medicine->dgda_status && $medicine->dgda_status !== 'synced')
                                    <span class="badge bg-warning text-dark ms-1">{{ ucfirst($medicine->dgda_status) }}</span>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark" title="No DGDA code — registry sync pending">DGDA sync pending</span>
                            @endif
                        </td>
                        @endif
                        <td>{{ $medicine->dosage_form }}{{ $medicine->strength ? ' '.$medicine->strength : '' }}</td>
                        <td>৳{{ number_format($medicine->selling_price, 2) }}</td>
                        <td>
                            @if($medicine->available_stock <= 0)
                                <span class="badge bg-danger">Out of stock</span>
                            @elseif($medicine->available_stock <= $medicine->reorder_level)
                                <span class="badge bg-warning text-dark">Low ({{ $medicine->available_stock }})</span>
                            @else
                                <span class="badge bg-success">{{ $medicine->available_stock }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.pharmacy.medicines.show', $medicine) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.pharmacy.medicines.edit', $medicine) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if(mawa_dgda_enabled())
                                <button type="button" class="btn btn-success" title="Validate against DGDA registry"
                                        onclick="document.getElementById('medicine-sync-{{ $medicine->id }}').submit();">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                @endif
                                <button type="button" class="btn btn-danger" title="Delete"
                                        onclick="if(confirm('Delete this medicine? Medicines with stock cannot be deleted.')){document.getElementById('medicine-delete-{{ $medicine->id }}').submit();}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="medicine-delete-{{ $medicine->id }}"
                                  action="{{ route('medical.pharmacy.medicines.destroy', $medicine) }}"
                                  method="POST" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
                            @if(mawa_dgda_enabled())
                            <form id="medicine-sync-{{ $medicine->id }}"
                                  action="{{ route('medical.pharmacy.medicines.sync-dgda', $medicine) }}"
                                  method="POST" style="display:none;">
                                @csrf
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ mawa_dgda_enabled() ? 7 : 6 }}" class="text-center text-muted py-4">
                            <i class="bi bi-capsule fs-2 d-block mb-2"></i>
                            No medicines found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $medicines->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
