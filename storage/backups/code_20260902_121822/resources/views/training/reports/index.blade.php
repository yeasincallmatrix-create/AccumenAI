@extends('layouts.institute')
@section('title', 'Reports — Training')
@section('page_title', 'Reports')
@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Reports</li>
    </ol>
</nav>
<div class="page-header">
    <h4>Training Reports</h4>
    <p class="text-muted small">Completion, enrollment trends, certificates — dedicated page.</p>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="admin-card text-center"><div class="text-muted small">Batches</div><div class="h4 mb-0">{{ $totalBatches }}</div></div></div>
    <div class="col-md-3"><div class="admin-card text-center"><div class="text-muted small">Enrollments</div><div class="h4 mb-0">{{ $totalEnrollments }}</div></div></div>
    <div class="col-md-3"><div class="admin-card text-center"><div class="text-muted small">Certificates</div><div class="h4 mb-0">{{ $totalCertificates }}</div></div></div>
    <div class="col-md-3"><div class="admin-card text-center"><div class="text-muted small">Completion</div><div class="h4 mb-0">{{ $completionRate }}%</div></div></div>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Batch</th><th>Enrolled</th><th>Exams</th><th>Certificates</th></tr></thead>
            <tbody>
            @forelse($batches as $batch)
                <tr>
                    <td class="fw-semibold">{{ $batch->name }}</td>
                    <td>{{ $batch->enrollments_count }}</td>
                    <td>{{ $batch->exams_count }}</td>
                    <td>{{ $batch->certificates_count }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
