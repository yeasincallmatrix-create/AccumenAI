@extends('layouts.institute')

@section('title', 'Wards — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Ward Management</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.wards.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Add Ward
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        @foreach(['general' => 'General', 'cabin' => 'Cabin', 'icu' => 'ICU', 'ccu' => 'CCU', 'nicu' => 'NICU', 'private' => 'Private'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <a href="{{ route('medical.wards.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Total Beds</th>
                        <th>Available</th>
                        <th>Occupancy</th>
                        <th>Daily Rate</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wards as $ward)
                    <tr>
                        <td><strong>{{ $ward->name }}</strong></td>
                        <td>{{ strtoupper($ward->type) }}</td>
                        <td>{{ $ward->total_beds }}</td>
                        <td>{{ $ward->available_beds }}</td>
                        <td>
                            <div class="progress" style="height: 18px; min-width: 100px;">
                                <div class="progress-bar {{ $ward->occupancy_rate >= 90 ? 'bg-danger' : ($ward->occupancy_rate >= 70 ? 'bg-warning' : 'bg-success') }}"
                                     role="progressbar" style="width: {{ $ward->occupancy_rate }}%">
                                    {{ $ward->occupancy_rate }}%
                                </div>
                            </div>
                        </td>
                        <td>৳{{ number_format($ward->daily_rate, 2) }}</td>
                        <td>
                            @if($ward->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.wards.show', $ward) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.wards.edit', $ward) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-danger" title="Delete"
                                        onclick="confirmWardDelete({{ $ward->id }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-building fs-2 d-block mb-2"></i>
                            No wards found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $wards->links('pagination::bootstrap-5') }}
    </div>
</div>

@foreach($wards as $ward)
<form id="ward-delete-form-{{ $ward->id }}"
      action="{{ route('medical.wards.destroy', $ward) }}"
      method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endforeach
@endsection

@push('scripts')
<script>
function confirmWardDelete(id) {
    if (confirm('Delete this ward? Wards with beds cannot be deleted.')) {
        document.getElementById('ward-delete-form-' + id).submit();
    }
}
</script>
@endpush
