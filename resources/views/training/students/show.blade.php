@extends('layouts.institute')

@section('title', $student->full_name . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'active'    => 'bg-success',
        'completed' => 'bg-primary',
        'dropped'   => 'bg-secondary',
        'suspended' => 'bg-danger',
    ];
    $enrollStatusBadge = [
        'active'      => 'bg-success',
        'completed'   => 'bg-primary',
        'dropped'     => 'bg-secondary',
        'transferred' => 'bg-info',
    ];
    $batchStatusBadge = [
        'upcoming'  => 'bg-secondary',
        'running'   => 'bg-success',
        'completed' => 'bg-primary',
        'cancelled' => 'bg-danger',
    ];
    $certStatusBadge = [
        'pending'  => 'bg-warning',
        'active'   => 'bg-success',
        'rejected' => 'bg-danger',
        'revoked'  => 'bg-dark',
    ];
    $addrLine = function ($student, string $type): string {
        $parts = [];
        $l1 = (int) ($student->{$type . '_admin_1_id'} ?? 0);
        $l2 = (int) ($student->{$type . '_admin_2_id'} ?? 0);
        $l3 = (int) ($student->{$type . '_admin_3_id'} ?? 0);
        if ($l1 || $l2 || $l3) {
            $unitIds = array_filter([$l1, $l2, $l3]);
            $names = \App\Models\AdministrativeUnit::query()
                ->whereIn('id', $unitIds)
                ->pluck('name', 'id');
            if ($l3 && $names->has($l3)) { $parts[] = $names[$l3]; }
            if ($l2 && $names->has($l2)) { $parts[] = $names[$l2]; }
            if ($l1 && $names->has($l1)) { $parts[] = $names[$l1]; }
        }
        if ($student->{$type . '_post_office'}) { $parts[] = $student->{$type . '_post_office'}; }
        if ($student->{$type . '_zip_code'}) { $parts[] = 'ZIP ' . $student->{$type . '_zip_code'}; }
        return implode(', ', $parts);
    };
@endphp

@php $isAcademic = \App\Support\InstituteDomain::isAcademic($institute ?? null); @endphp
@php $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <a href="{{ route('training.students.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to {{ $isProfessional ? 'Trainees' : 'Students' }}
        </a>
        <h4 class="page-header-title">
            {{ $student->full_name }}
            <span class="badge {{ $statusBadge[$student->status] ?? 'bg-secondary' }} ms-1">{{ ucfirst($student->status) }}</span>
            @if (($lifecycle['outcome'] ?? 'active') !== 'active')
                <span class="badge bg-info ms-1">{{ ucwords(str_replace('_', ' ', $lifecycle['outcome'])) }}</span>
            @endif
        </h4>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($user->hasPermission('students.manage') && ($lifecycle['outcome'] ?? 'active') === 'active' && ($lifecycle['hasActivePlacement'] ?? false))
            <form method="POST" action="{{ route('training.students.transfer', $student) }}" class="d-inline" onsubmit="return confirm('Transfer {{ $student->full_name }} to another batch?');">
                @csrf
                <input type="hidden" name="batch_id" value="{{ old('batch_id', $student->preferred_batch_id) }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left-right me-1"></i>Transfer
                </button>
            </form>
            <form method="POST" action="{{ route('training.students.withdraw', $student) }}" class="d-inline" onsubmit="return confirm('Withdraw {{ $student->full_name }} from the training program?');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-right me-1"></i>Withdraw
                </button>
            </form>
        @endif
        @if ($user->hasPermission('students.manage'))
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignBatchModal">
                <i class="bi bi-send me-1"></i>Assign to Batch
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editStudentModal">
                <i class="bi bi-pencil-square me-1"></i>Edit
            </button>
        @endif
    </div>
</div>

<div class="row g-3 mt-1">

    <!-- Identity documents card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-shield-check me-1"></i>Identity Documents</h6>
            <div class="d-flex gap-3">
                <div class="flex-shrink-0 align-self-center ms-2 me-1">
                    @if ($student->photo)
                        <img src="{{ $student->photo_url }}" class="student-id-photo" id="studentPhotoImg" alt="{{ $student->full_name }}">
                    @else
                        <div class="student-id-photo student-id-photo-placeholder" id="studentPhotoPlaceholder" aria-label="No photo">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    @endif
                </div>
                <div class="flex-grow-1">
                    <dl class="row mb-0 profile-dl">
                        @if ($student->nid_number)
                            <dt class="col-5">NID Number</dt>
                            <dd class="col-7">{{ $student->nid_number }}</dd>
                        @else
                            <dt class="col-5">Birth Cert. No.</dt>
                            <dd class="col-7">{{ $student->birth_cert_number ?? 'Not provided' }}</dd>
                        @endif
                        <dt class="col-5">Passport Number</dt>
                        <dd class="col-7">{{ $student->passport_number ?? 'Not provided' }}</dd>
                        <dt class="col-5">Father's Name</dt>
                        <dd class="col-7">{{ $student->father_name ?? 'Not provided' }}</dd>
                        <dt class="col-5">Mother's Name</dt>
                        <dd class="col-7">{{ $student->mother_name ?? 'Not provided' }}</dd>
                        <dt class="col-5">Religion</dt>
                        <dd class="col-7">{{ $student->religion ?? 'Not provided' }}</dd>
                        <dt class="col-5">Nationality</dt>
                        <dd class="col-7">{{ $student->nationality ?? 'Not provided' }}</dd>
                        <dt class="col-5">Age</dt>
                        <dd class="col-7">
                            @if ($student->dob && ! $student->dob->isFuture())
                                {{ $student->dob->diff(now())->format('%y years, %m months, %d days') }}
                            @else
                                Not provided
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
            <form id="photoUploadForm" class="mt-3 pt-3 border-top" method="POST" action="{{ route('training.students.photo', $student) }}" enctype="multipart/form-data">
                @csrf
                <div class="input-group input-group-sm">
                    <input id="e_photo_upload" type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp" aria-label="Upload photo" required>
                    <button type="submit" class="btn btn-outline-primary" id="photoUploadBtn">Upload Photo</button>
                </div>
                <div class="form-text mt-1" id="photoHelpText">Passport-size portrait photo recommended. Ratio 7:9 · 350 × 450 px · below 50 KB (max 100 KB) · JPG, PNG or WebP</div>
                <div class="text-danger small mt-1 d-none" id="photoWarning" role="alert">Please select an image to upload first.</div>
                <div class="text-danger small mt-1 d-none" id="photoUploadError" role="alert"></div>
                @error('photo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </form>
        </div>
    </div>

    <!-- Registration & ID card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-person-vcard me-1"></i>Registration &amp; ID</h6>
            <dl class="row mb-0 profile-dl">
                @if(isset($student->uid) && $student->uid)
                <dt class="col-5">Student UID</dt>
                <dd class="col-7"><x-uid-with-copy :uid="$student->uid" label="Student UID" /></dd>
                @endif
                <dt class="col-5">Student ID</dt>
                <dd class="col-7 fw-semibold text-primary">{{ $student->student_id }}</dd>
                <dt class="col-5">Reg No (Global)</dt>
                <dd class="col-7">{{ $student->reg_no ?? 'Not provided' }}</dd>
                <dt class="col-5">Branch</dt>
                <dd class="col-7">{{ $student->branch->name ?? 'Not provided' }}</dd>
                <dt class="col-5">Gender</dt>
                <dd class="col-7">{{ $student->gender ? : 'Not provided' }}</dd>
                <dt class="col-5">Date of Birth</dt>
                <dd class="col-7"><x-tdate :value="$student->dob" fallback="d M Y" empty="Not provided" /></dd>
                <dt class="col-5">Blood Group</dt>
                <dd class="col-7">{{ $student->blood_group ?? 'Not provided' }}</dd>
                <dt class="col-5">Admission Date</dt>
                <dd class="col-7"><x-tdate :value="$student->admission_date" fallback="d M Y" /></dd>
            </dl>
        </div>
    </div>

    <!-- Contact card -->
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-telephone me-1"></i>Contact</h6>
            <dl class="row mb-0 profile-dl">
                <dt class="col-5">Phone</dt>
                <dd class="col-7">{{ $student->phone ?? 'Not provided' }}</dd>
                <dt class="col-5">Guardian Phone</dt>
                <dd class="col-7">{{ $student->guardian_phone ?? 'Not provided' }}</dd>
                <dt class="col-5">Email</dt>
                <dd class="col-7">{{ $student->email ?? 'Not provided' }}</dd>
                <dt class="col-5">Emergency Contact</dt>
                <dd class="col-7">{{ $student->emergency_contact_name ?? 'Not provided' }}</dd>
                <dt class="col-5">Emergency Phone</dt>
                <dd class="col-7">{{ $student->emergency_contact_phone ?? 'Not provided' }}</dd>
                @if ($student->present_address)
                    <dt class="col-5 border-top pt-2 mt-2">Present Address</dt>
                    <dd class="col-7 border-top pt-2 mt-2">
                        {{ $student->present_address }}
                        @if ($addrLine($student, 'present'))
                            <div class="text-muted small">{{ $addrLine($student, 'present') }}</div>
                        @endif
                    </dd>
                @endif
                @if ($student->permanent_address)
                    <dt class="col-5">Permanent Address</dt>
                    <dd class="col-7">
                        {{ $student->permanent_address }}
                        @if ($addrLine($student, 'permanent'))
                            <div class="text-muted small">{{ $addrLine($student, 'permanent') }}</div>
                        @endif
                    </dd>
                @endif
            </dl>
        </div>
    </div>

</div>

@if ($user->hasPermission('students.manage'))
    <!-- Assign to Batch modal -->
    <div class="modal fade" id="assignBatchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('training.students.enroll', $student) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign to Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        <i class="bi bi-person me-1"></i>{{ $student->full_name }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="ab_batch_id">Batch *</label>
                        <select id="ab_batch_id" name="batch_id" class="form-select" required>
                            <option value="">Select batch</option>
                            @foreach ($batches as $batch)
                                <option value="{{ $batch->id }}" @selected($student->preferred_batch_id == $batch->id)>
                                    {{ $batch->name }}{{ $batch->batch_code ? ' (' . $batch->batch_code . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('batch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign to Batch</button>
                </div>
            </form>
        </div>
    </div>
@endif

<!-- Courses / Certificates tabs -->
<div class="mt-4">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-courses-tab" data-bs-toggle="tab" data-bs-target="#tab-courses" type="button" role="tab" aria-controls="tab-courses" aria-selected="true">
                <i class="bi bi-mortarboard me-1"></i>Courses
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-certificates-tab" data-bs-toggle="tab" data-bs-target="#tab-certificates" type="button" role="tab" aria-controls="tab-certificates" aria-selected="false">
                <i class="bi bi-patch-check me-1"></i>Certificates
            </button>
        </li>
        @if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-documents-tab" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button" role="tab" aria-controls="tab-documents" aria-selected="false">
                    <i class="bi bi-folder2-open me-1"></i>Documents
                </button>
            </li>
        @endif
    </ul>
    <div class="tab-content admin-card border-top-0 rounded-top-0">

        <div class="tab-pane fade show active" id="tab-courses" role="tabpanel" aria-labelledby="tab-courses-tab">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Roll</th>
                            <th>Enrollment Status</th>
                            <th>Batch Status</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($student->enrollments as $enrollment)
                            @php $result = $results->get($enrollment->batch_id); @endphp
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $enrollment->batch?->course?->name ?? 'Not provided' }}</td>
                                <td>
                                    {{ $enrollment->batch?->name ?? 'Not provided' }}
                                    @if ($enrollment->batch?->batch_code)
                                        <small class="text-muted d-block">{{ $enrollment->batch->batch_code }}</small>
                                    @endif
                                </td>
                                <td>{{ $enrollment->roll_no ?: 'Not provided' }}</td>
                                <td>
                                    <span class="badge {{ $enrollStatusBadge[$enrollment->status] ?? 'bg-secondary' }}">{{ ucfirst($enrollment->status) }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $batchStatusBadge[$enrollment->batch?->status] ?? 'bg-secondary' }}">{{ ucfirst($enrollment->batch?->status ?? 'Not provided') }}</span>
                                </td>
                                <td>
                                    @php $resultStatus = $result->result_status ?? $result->status ?? null; @endphp
                                    @if ($result && $resultStatus === 'pass')
                                        <div class="d-flex align-items-center gap-1 flex-nowrap">
                                            <span class="badge bg-success">Pass</span>
                                            @if ($result->grade)
                                                <span class="badge bg-success bg-opacity-25 text-success border border-success">{{ $result->grade }}</span>
                                            @elseif (isset($result->percentage))
                                                <span class="badge bg-success bg-opacity-25 text-success border border-success">{{ number_format((float) $result->percentage, 2) }}%</span>
                                            @endif
                                        </div>
                                    @elseif ($result && $resultStatus === 'fail')
                                        <span class="badge bg-danger">Fail</span>
                                    @else
                                        <span class="text-muted small">Not published yet</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Not enrolled in any batch yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-certificates" role="tabpanel" aria-labelledby="tab-certificates-tab">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Certificate No.</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($student->certificates as $certificate)
                            <tr>
                                <td class="text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $certificate->certificate_number ?? 'Not provided' }}</td>
                                <td>{{ $certificate->course?->name ?? 'Not provided' }}</td>
                                <td>
                                    {{ $certificate->batch?->name ?? 'Not provided' }}
                                    @if ($certificate->batch?->batch_code)
                                        <small class="text-muted d-block">{{ $certificate->batch->batch_code }}</small>
                                    @endif
                                </td>
                                <td><x-tdate :value="$certificate->issue_date" fallback="d M Y" empty="Not provided" /></td>
                                <td>
                                    <span class="badge {{ $certStatusBadge[$certificate->status] ?? 'bg-secondary' }}">{{ ucfirst($certificate->status) }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($certificate->verification_url)
                                        <a href="{{ $certificate->verification_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>View Certificate
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No certificates issued yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
            <div class="tab-pane fade" id="tab-documents" role="tabpanel" aria-labelledby="tab-documents-tab">
                <div class="p-3">
                    @include('documents._panel', ['entityType' => 'student', 'entityId' => $student->id])
                </div>
            </div>
        @endif

    </div>
</div>

@if ($user->hasPermission('students.manage'))
    @include('training.students._edit_modal', ['student' => $student])
@endif

@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('photoUploadForm');
    var input = document.getElementById('e_photo_upload');
    if (!form || !input) { return; }
    var warning = document.getElementById('photoWarning');
    var help = document.getElementById('photoHelpText');
    var hideTimer = null;
    var fadeTimer = null;
    function showWarning() {
        if (!warning) { return; }
        if (hideTimer) { clearTimeout(hideTimer); }
        if (fadeTimer) { clearTimeout(fadeTimer); }
        warning.classList.remove('d-none', 'opacity-0');
        warning.style.transition = 'none';
        if (help) { help.classList.add('d-none'); }
        hideTimer = setTimeout(function () {
            warning.style.transition = 'opacity 2s';
            warning.classList.add('opacity-0');
            fadeTimer = setTimeout(function () {
                warning.classList.add('d-none');
                warning.style.transition = 'none';
                warning.classList.remove('opacity-0');
                if (help) { help.classList.remove('d-none'); }
            }, 2000);
        }, 3000);
    }
    function clearWarning() {
        if (!warning) { return; }
        if (hideTimer) { clearTimeout(hideTimer); }
        if (fadeTimer) { clearTimeout(fadeTimer); }
        warning.classList.add('d-none');
        warning.style.transition = 'none';
        warning.classList.remove('opacity-0');
        if (help) { help.classList.remove('d-none'); }
    }
    form.addEventListener('submit', function (e) {
        if (input.files && input.files.length > 0) { return; }
        e.preventDefault();
        showWarning();
        input.focus();
    });
    input.addEventListener('change', clearWarning);
})();

@if ($errors->any() && !session('photo_upload_error'))
(function () {
    var modalEl = document.getElementById('editStudentModal');
    if (modalEl) { new bootstrap.Modal(modalEl).show(); }
})();
@endif

@if (request('edit'))
(function () {
    var modalEl = document.getElementById('editStudentModal');
    if (modalEl) { new bootstrap.Modal(modalEl).show(); }
})();
@endif
</script>
@endpush

