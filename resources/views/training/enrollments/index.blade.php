@extends('layouts.institute')

@section('title', 'Enrollments — AccumenAI')
@section('page_title', 'Enrollments')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Enrollments</li>
    </ol>
</nav>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4 class="mb-1">Enrollments</h4>
        <p class="text-muted small mb-0">Dedicated training enrollments with duplicate and capacity checks.</p>
    </div>
    <a href="{{ route('training.enrollments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> New Enrollment</a>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Batch</th><th>Trainee</th><th>Student ID</th><th>Roll No</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
            <tbody>
            @forelse($enrollments as $enrollment)
                <tr>
                    <td class="text-muted">{{ $enrollments->firstItem() + $loop->index }}</td>
                    <td>{{ $enrollment->batch?->name ?? '—' }}</td>
                    <td>{{ $enrollment->trainee?->full_name ?? $enrollment->trainee?->name ?? $enrollment->trainee_id }}</td>
                    <td class="fw-semibold text-primary">{{ $enrollment->trainee?->student_id ?? '—' }}</td>
                    <td><span class="badge text-bg-primary">{{ $enrollment->roll_no ?? '—' }}</span></td>
                    <td><span class="badge text-bg-light">{{ $enrollment->status }}</span></td>
                    <td><span class="badge text-bg-light">{{ $enrollment->payment_status }}</span></td>
                    <td class="small text-muted">{{ $enrollment->enrollment_date?->format('Y-m-d') ?? '—' }}</td>
                    <td class="text-end d-flex gap-1 justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editRollModal{{ $enrollment->id }}" title="Edit Roll"><i class="bi bi-pencil"></i></button>
                        <a href="{{ route('training.attendance.index', ['batch_id' => $enrollment->batch_id]) }}" class="btn btn-sm btn-outline-primary" title="Mark Attendance"><i class="bi bi-calendar-check"></i></a>
                        <form method="POST" action="{{ route('training.enrollments.destroy', $enrollment) }}" onsubmit="return confirm('Remove enrollment?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <!-- Edit Roll Modal -->
                <div class="modal fade" id="editRollModal{{ $enrollment->id }}" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <form method="POST" action="{{ route('training.enrollments.update', $enrollment) }}" class="modal-content">
                            @csrf @method('PUT')
                            <div class="modal-header"><h6 class="modal-title">Edit Roll No — {{ $enrollment->trainee?->full_name ?? $enrollment->trainee?->name ?? $enrollment->trainee_id }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <label class="form-label">Roll Number <span class="text-danger">*</span></label>
                                <input type="number" name="roll_no" class="form-control form-control-sm" min="1" required value="{{ old('roll_no', $enrollment->roll_no) }}">
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Update</button></div>
                        </form>
                    </div>
                </div>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No enrollments yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-2">{{ $enrollments->links() }}</div>
</div>
@endsection
