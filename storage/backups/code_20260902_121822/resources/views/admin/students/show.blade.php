@extends('layouts.admin')

@section('title', $student->full_name . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'active'    => 'text-bg-success',
        'completed' => 'text-bg-info',
        'dropped'   => 'text-bg-warning',
        'suspended' => 'text-bg-danger',
    ];
@endphp

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $student->full_name }}
            <span class="badge {{ $statusBadge[$student->status] ?? 'text-bg-secondary' }}">{{ $student->status }}</span>
        </h4>
        <p class="page-header-desc">Student ID {{ $student->student_id }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.students.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-person-vcard"></i> Student Details</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Institute</dt><dd class="col-sm-8">{{ $student->institute->name ?? '—' }}</dd>
        <dt class="col-sm-4">Branch</dt><dd class="col-sm-8">{{ $student->branch->name ?? '—' }}</dd>
        <dt class="col-sm-4">Reg No.</dt><dd class="col-sm-8">{{ $student->reg_no ?? '—' }}</dd>
        <dt class="col-sm-4">Roll</dt><dd class="col-sm-8">{{ $student->roll_number ?? '—' }}</dd>
        <dt class="col-sm-4">Gender</dt><dd class="col-sm-8">{{ $student->gender ? ucfirst($student->gender) : '—' }}</dd>
        <dt class="col-sm-4">Date of birth</dt><dd class="col-sm-8">{{ $student->dob?->format('d M Y') ?? '—' }}</dd>
        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $student->phone ?? '—' }}</dd>
        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $student->email ?? '—' }}</dd>
        <dt class="col-sm-4">Admission date</dt><dd class="col-sm-8">{{ $student->admission_date?->format('d M Y') ?? '—' }}</dd>
        <dt class="col-sm-4">Present address</dt><dd class="col-sm-8">{{ $student->present_address ?? '—' }}</dd>
        <dt class="col-sm-4">Permanent address</dt><dd class="col-sm-8">{{ $student->permanent_address ?? '—' }}</dd>
        <dt class="col-sm-4">Added</dt><dd class="col-sm-8">{{ $student->created_at->format('d M Y H:i') }}</dd>
    </dl>
</div>

@if ($student->enrollments->isNotEmpty())
    <div class="admin-card">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-journal-check"></i> Enrollments</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Batch</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($student->enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->batch?->course?->name ?? '—' }}</td>
                            <td>{{ $enrollment->batch?->name ?? '—' }}</td>
                            <td><span class="badge text-bg-secondary">{{ $enrollment->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection