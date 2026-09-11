@extends('layouts.institute')

@section('title', 'Doctors — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Doctors</li>
    </ol>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Doctor Management</h4>
        <p class="page-header-desc">{{ $doctors->total() }} doctors</p>
    </div>
    <div class="page-header-actions">
        @if(! mawa_fenced_doctor_id())
        <a href="{{ route('medical.doctors.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Doctor
        </a>
        @endif
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control"
                       placeholder="Search by name, registration, phone..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="department_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="specialty_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Specialties</option>
                    @foreach($specialties as $spec)
                        <option value="{{ $spec->id }}" {{ request('specialty_id') == $spec->id ? 'selected' : '' }}>
                            {{ $spec->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Registration</th>
                        <th>Name</th>
                        <th>Specialty</th>
                        <th>Department</th>
                        <th>Fee</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $fenceId = mawa_fenced_doctor_id(); @endphp
                    @forelse($doctors as $doctor)
                    <tr>
                        <td>{{ $doctor->registration_number }}</td>
                        <td>{{ $doctor->full_name }}</td>
                        <td>{{ $doctor->specialty_name }}</td>
                        <td>{{ $doctor->department_name }}</td>
                        <td>৳{{ number_format((float) $doctor->consultation_fee, 2) }}</td>
                        <td>{{ $doctor->availabilities->pluck('day_of_week')->map(fn($d) => ucfirst(substr($d, 0, 3)))->unique()->join(', ') ?: '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $doctor->is_active ? 'success' : 'danger' }}">
                                {{ $doctor->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('medical.doctors.show', $doctor) }}" class="btn btn-sm btn-info" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if(! $fenceId || (int) $doctor->user_id === $fenceId)
                            <a href="{{ route('medical.doctors.edit', $doctor) }}" class="btn btn-sm btn-warning" title="Edit / availability">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endif
                            @if(! $fenceId)
                            <button class="btn btn-sm btn-danger" title="Remove" onclick="confirmDelete({{ $doctor->id }})">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-person-slash"></i> No doctors found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $doctors->links() }}
    </div>
</div>

<form id="delete-form" method="POST" style="display:none;">
    @csrf @method('DELETE')
</form>

<script>
function confirmDelete(id) {
    if (confirm('Are you sure you want to remove this doctor?')) {
        const form = document.getElementById('delete-form');
        form.action = `{{ route('medical.doctors.index') }}/${id}`;
        form.submit();
    }
}
</script>
@endsection
