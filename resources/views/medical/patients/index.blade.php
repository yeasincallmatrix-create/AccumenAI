@extends('layouts.institute')

@section('title', 'Patients — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Patient Management</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.patients.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Register Patient
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                               placeholder="Search by MR Number, Name, or Phone..."
                               value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Patients</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <a href="{{ route('medical.patients.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>MR Number</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Blood Group</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($patients as $patient)
                    <tr>
                        <td><strong>{{ $patient->mr_number }}</strong></td>
                        <td>{{ $patient->full_name }}</td>
                        <td>{{ $patient->age !== null ? $patient->age.' years' : 'N/A' }}</td>
                        <td>{{ ucfirst($patient->gender) }}</td>
                        <td>{{ $patient->phone }}</td>
                        <td><span class="badge bg-info text-dark">{{ $patient->blood_group ?? 'N/A' }}</span></td>
                        <td>
                            @if($patient->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.patients.show', $patient) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.patients.edit', $patient) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="{{ route('medical.patients.history', $patient) }}" class="btn btn-secondary" title="History">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                <button type="button" class="btn btn-danger" title="Delete"
                                        onclick="confirmMedicalDelete({{ $patient->id }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                            No patients found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $patients->links('pagination::bootstrap-5') }}
    </div>
</div>

@foreach($patients as $patient)
<form id="medical-delete-form-{{ $patient->id }}"
      action="{{ route('medical.patients.destroy', $patient) }}"
      method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endforeach
@endsection

@push('scripts')
<script>
function confirmMedicalDelete(id) {
    if (confirm('Are you sure you want to delete this patient? This action cannot be undone.')) {
        document.getElementById('medical-delete-form-' + id).submit();
    }
}
</script>
@endpush
