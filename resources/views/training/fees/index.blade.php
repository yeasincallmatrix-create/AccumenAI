@extends('layouts.institute')
@section('title', 'Fees — Training')
@section('page_title', 'Fees')
@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Fees</li>
    </ol>
</nav>
<div class="page-header">
    <h4>Training Fees</h4>
    <p class="text-muted small">Course & batch fee collection — dedicated training view.</p>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Batch</th><th>Course</th><th>Fee</th><th>Enrolled</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td class="fw-semibold">{{ $batch->name }}</td>
                    <td class="small text-muted">{{ $batch->course?->name ?? '—' }}</td>
                    <td>{{ $batch->course?->fee ? number_format($batch->course->fee,2) : '—' }}</td>
                    <td>{{ $batch->enrollments_count }}</td>
                    <td class="text-end"><a href="{{ route('batches.show', $batch->id) }}" class="btn btn-sm btn-outline-primary">View Batch</a> <span class="badge text-bg-light ms-1">{{ $batch->enrollments_count }} trainees</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No batches yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
