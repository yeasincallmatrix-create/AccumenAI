@extends('layouts.institute')

@section('title', 'Pending Admissions — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Pending Admissions</h4>
        <p class="page-header-desc mb-0">{{ $admissions->total() }} {{ $admissions->total() === 1 ? 'admission' : 'admissions' }} awaiting approval</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admissions.index') }}">
            <i class="bi bi-arrow-left"></i> All Admissions
        </a>
    </div>
</div>

@include('students._tabs', ['activeTab' => 'admissions'])

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Applicant</th>
                    <th>Course</th>
                    <th>Batch</th>
                    <th>Submitted by</th>
                    <th>Date</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($admissions as $student)
                    <tr>
                        <td class="text-muted small">{{ $student->application_number ?? '—' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $student->full_name }}</div>
                            <small class="text-muted">{{ $student->phone ?? '—' }}</small>
                        </td>
                        <td>{{ $student->appliedCourse?->name ?? '—' }}</td>
                        <td>{{ $student->preferredBatch?->name ?? '—' }}</td>
                        <td>
                            <small class="text-muted">{{ $student->creator?->name ?? '—' }}</small>
                        </td>
                        <td><small class="text-muted">{{ $student->application_date?->format('d M Y') ?? '—' }}</small></td>
                        <td class="text-end">
                            <a href="{{ route('admissions.review', $student) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i>Review
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                            No pending admissions. All caught up!
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($admissions->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $admissions->links() }}
        </div>
    @endif
</div>
@endsection
