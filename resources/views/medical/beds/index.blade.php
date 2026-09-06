@extends('layouts.institute')

@section('title', 'Beds — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Bed Management</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-success me-1" href="{{ route('medical.beds.available') }}">
            <i class="bi bi-check-circle me-1"></i>Available Beds
        </a>
        <a class="btn btn-primary" href="{{ route('medical.beds.create', request()->only('ward_id')) }}">
            <i class="bi bi-plus-lg me-1"></i>Add Bed
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="ward_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Wards</option>
                        @foreach($wards as $ward)
                            <option value="{{ $ward->id }}" @selected((string) request('ward_id') === (string) $ward->id)>
                                {{ $ward->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        @foreach(['available' => 'Available', 'occupied' => 'Occupied', 'reserved' => 'Reserved', 'maintenance' => 'Maintenance'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <a href="{{ route('medical.beds.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Bed Number</th>
                        <th>Ward</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($beds as $bed)
                    <tr>
                        <td><strong>{{ $bed->bed_number }}</strong></td>
                        <td>{{ $bed->ward->name ?? 'N/A' }}</td>
                        <td>
                            <span class="badge bg-{{ $bed->status === 'available' ? 'success' : ($bed->status === 'occupied' ? 'danger' : ($bed->status === 'reserved' ? 'warning' : 'secondary')) }}">
                                {{ ucfirst($bed->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.beds.show', $bed) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.beds.edit', $bed) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-danger" title="Delete"
                                        onclick="confirmBedDelete({{ $bed->id }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="bi bi-hospital fs-2 d-block mb-2"></i>
                            No beds found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $beds->links('pagination::bootstrap-5') }}
    </div>
</div>

@foreach($beds as $bed)
<form id="bed-delete-form-{{ $bed->id }}"
      action="{{ route('medical.beds.destroy', $bed) }}"
      method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endforeach
@endsection

@push('scripts')
<script>
function confirmBedDelete(id) {
    if (confirm('Delete this bed? Occupied beds cannot be deleted.')) {
        document.getElementById('bed-delete-form-' + id).submit();
    }
}
</script>
@endpush
