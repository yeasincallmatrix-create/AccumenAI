@extends('layouts.institute')

@section('title', 'Admission ' . ($student->application_number ?? '') . ' — AccumenAI')

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
    $needsReason = in_array('rejected', $nextStatuses, true) || in_array('cancelled', $nextStatuses, true);
    $fmtDate = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '—';
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $student->application_number ?? 'Application' }}</h4>
        <p class="page-header-desc mb-0">{{ $student->full_name }} — <span class="badge {{ $statusBadge[$student->admission_status] ?? 'bg-secondary' }}">{{ $student->admission_status }}</span></p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admissions.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        @if ($user->hasPermission('students.manage'))
            <a class="btn btn-outline-primary" href="{{ route('admissions.edit', $student) }}">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <a class="btn btn-primary" href="{{ route('students.show', $student) }}">
                <i class="bi bi-person"></i> Full Profile
            </a>
        @endif
    </div>
</div>

<div class="row g-3">

    <div class="col-lg-8">
        <div class="admin-card p-3">
            <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Application Details</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th class="w-25">Application #</th>
                        <td>{{ $student->application_number ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Applicant</th>
                        <td>{{ $student->full_name }}</td>
                    </tr>
                    <tr>
                        <th>Gender / DOB</th>
                        <td>{{ $student->gender ? ucfirst($student->gender) : '—' }} / {{ $fmtDate($student->dob) }}</td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td>{{ $student->phone }}</td>
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
                    <tr>
                        <th>Applied course</th>
                        <td>{{ $student->appliedCourse ? $student->appliedCourse->name . ' (' . $student->appliedCourse->course_code . ')' : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Intended academic year</th>
                        <td>{{ $student->appliedAcademicYear?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Applied on</th>
                        <td>{{ $fmtDate($student->application_date) }}</td>
                    </tr>
                    <tr>
                        <th>Source</th>
                        <td>{{ $student->admission_source ? ucfirst(str_replace('_', ' ', $student->admission_source)) : '—' }}</td>
                    </tr>
                    @if ($student->admission_reject_reason)
                        <tr>
                            <th>Rejection / cancellation reason</th>
                            <td class="text-danger">{{ $student->admission_reject_reason }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card p-3 mb-3">
            <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Student Record</h6>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr>
                        <th class="w-50">Student ID</th>
                        <td>{{ $student->student_id ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Registration No.</th>
                        <td>{{ $student->reg_no ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>CRM lead</th>
                        <td>{{ $student->crmLead ? 'Linked' : '—' }}</td>
                    </tr>
                    <tr>
                        <th>CRM contact</th>
                        <td>{{ $student->crmContact ? 'Linked' : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($user->hasPermission('students.manage') && count($nextStatuses))
            <div class="admin-card p-3">
                <h6 class="text-primary text-uppercase mb-3" style="font-size:13px; letter-spacing:.4px;">Move Application</h6>
                <form method="POST" action="{{ route('admissions.transition', $student) }}">
                    @csrf
                    @if ($needsReason)
                        <div class="mb-3">
                            <label class="form-label" for="reason">Reason <span class="text-danger">*</span> (required for rejection / cancellation)</label>
                            <textarea id="reason" name="reason" class="form-control" rows="2" maxlength="255" placeholder="Required only when rejecting or cancelling"></textarea>
                        </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($nextStatuses as $status)
                            <button type="submit" name="status" value="{{ $status }}"
                                    class="btn btn-sm {{ $status === 'approved' ? 'btn-success' : ($status === 'rejected' ? 'btn-danger' : ($status === 'under_review' ? 'btn-warning' : 'btn-outline-secondary')) }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </button>
                        @endforeach
                    </div>
                    @if ($errors->has('status'))
                        <div class="text-danger small mt-2">{{ $errors->first('status') }}</div>
                    @endif
                </form>
            </div>
        @endif
    </div>

</div>
@endsection