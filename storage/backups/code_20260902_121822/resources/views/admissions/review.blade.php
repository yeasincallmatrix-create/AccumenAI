@extends('layouts.institute')

@section('title', 'Review Admission — AccumenAI')

@php
    $statusBadge = [
        'draft'        => 'bg-secondary',
        'submitted'    => 'bg-info',
        'under_review' => 'bg-warning',
        'approved'     => 'bg-success',
        'rejected'     => 'bg-danger',
        'cancelled'    => 'bg-dark',
        'enrolled'     => 'bg-primary',
        'withdrawn'    => 'bg-secondary',
    ];
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
    $isPending = \App\Services\AdmissionWorkflowService::isPendingApproval($student);
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Review Admission</h4>
        <p class="page-header-desc mb-0">
            {{ $student->application_number ?? '—' }} — {{ $student->full_name }}
            <span class="badge {{ $statusBadge[$student->admission_status] ?? 'bg-secondary' }} ms-2">{{ $student->admission_status }}</span>
        </p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admissions.pending') }}">
            <i class="bi bi-arrow-left"></i> Pending Queue
        </a>
        <a class="btn btn-outline-primary" href="{{ route('admissions.show', $student) }}">
            <i class="bi bi-eye"></i> Full View
        </a>
    </div>
</div>

<div class="row g-3">

    <div class="col-lg-8">
        <div class="admin-card p-3 mb-3">
            <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Student Information</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th class="w-25">Full Name</th>
                        <td>{{ $student->full_name }}</td>
                    </tr>
                    <tr>
                        <th>Gender / DOB</th>
                        <td>{{ $student->gender ? ucfirst($student->gender) : '—' }} / {{ $fmtDate($student->dob) }}</td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td>{{ $student->phone ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $student->email ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Guardian</th>
                        <td>{{ $student->guardian_name ?? '—' }} {{ $student->guardian_phone ? '(' . $student->guardian_phone . ')' : '' }}</td>
                    </tr>
                    <tr>
                        <th>Branch</th>
                        <td>{{ $student->branch?->name ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="admin-card p-3 mb-3">
            <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Admission Details</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th class="w-25">Application #</th>
                        <td>{{ $student->application_number ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Student ID</th>
                        <td>{{ $student->student_id ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Applied Course</th>
                        <td>{{ $student->appliedCourse ? $student->appliedCourse->name . ' (' . $student->appliedCourse->course_code . ')' : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Preferred Batch</th>
                        <td>{{ $student->preferredBatch?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Academic Year</th>
                        <td>{{ $student->appliedAcademicYear?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Application Date</th>
                        <td>{{ $fmtDate($student->application_date) }}</td>
                    </tr>
                    <tr>
                        <th>Source</th>
                        <td>{{ $student->admission_source ? ucfirst(str_replace('_', ' ', $student->admission_source)) : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="admin-card p-3 mb-3">
            <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Course Fee Information</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th class="w-25">Admission Fee</th>
                        <td>{{ $student->appliedCourse?->admission_fee ? number_format($student->appliedCourse->admission_fee, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Course Fee</th>
                        <td>{{ $student->appliedCourse?->fee ? number_format($student->appliedCourse->fee, 2) : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Registration No.</th>
                        <td>{{ $student->reg_no ?? '— (generated on approval)' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card p-3 mb-3">
            <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Submission Info</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th class="w-50">Submitted by</th>
                        <td>{{ $student->creator?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Assigned to</th>
                        <td>{{ $student->admissionAssignedUser?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="badge {{ $statusBadge[$student->admission_status] ?? 'bg-secondary' }}">{{ $student->admission_status }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($isPending)
            <div class="admin-card p-3 mb-3">
                <h6 class="text-success text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">
                    <i class="bi bi-check-circle me-1"></i> Approve Admission
                </h6>
                <p class="text-muted small mb-3">Approving will generate the registration number, create the enrollment, and make the student active.</p>
                <form method="POST" action="{{ route('admissions.approve', $student) }}">
                    @csrf
                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Are you sure you want to approve this admission?')">
                        <i class="bi bi-check-lg me-1"></i> Approve Admission
                    </button>
                </form>
            </div>

            <div class="admin-card p-3">
                <h6 class="text-danger text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">
                    <i class="bi bi-x-circle me-1"></i> Reject Admission
                </h6>
                <form method="POST" action="{{ route('admissions.reject', $student) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="reason">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" maxlength="255" required placeholder="Enter reason for rejection…"></textarea>
                        @error('reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to reject this admission?')">
                        <i class="bi bi-x-lg me-1"></i> Reject Admission
                    </button>
                </form>
            </div>
        @else
            <div class="admin-card p-3">
                <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Admission Status</h6>
                <p class="text-muted mb-0">This admission is <strong>{{ $student->admission_status }}</strong> and does not require approval action.</p>
            </div>
        @endif
    </div>

</div>
@endsection
