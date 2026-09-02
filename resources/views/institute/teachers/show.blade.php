@extends('layouts.standalone')

@php $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
@section('title', ($teacher->name ?? ($isProfessional ? 'Trainer' : 'Teacher')).' — AccumenAI')
@section('page_title', $isProfessional ? 'Trainer Profile' : 'Teacher Profile')

@section('content')

<div class="standalone-heading">
    <h4>{{ $teacher->name ?? ($teacher->first_name.' '.$teacher->last_name) }}</h4>
    <p>Instructor profile, employment and teaching history. Data is tenant- and branch-scoped.</p>
    @if ($canManage)
        <a href="{{ route('teachers.edit', $teacher) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit</a>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card mb-3">
            <div class="d-flex flex-column align-items-center text-center py-2">
                @if ($teacher->photo)
                    <img src="{{ Storage::url($teacher->photo) }}" class="rounded mb-2" style="width:110px;height:140px;object-fit:cover" alt="">
                @else
                    <span class="avatar-circle avatar-initials mb-2" style="width:110px;height:110px;font-size:2rem">{{ strtoupper(substr($teacher->name ?? ($teacher->first_name.' '.$teacher->last_name), 0, 1)) }}</span>
                @endif
                <h5 class="mb-0">{{ $teacher->name ?? ($teacher->first_name.' '.$teacher->last_name) }}</h5>
                <div class="text-muted small">{{ $teacher->designation ?? ($isProfessional ? 'Trainer' : 'Teacher') }}</div>
                @php
                    $employmentStatus = $profile?->employment_status ?? $teacher->status;
                @endphp
                <span class="badge mt-2 {{ $employmentStatus === 'active' ? 'text-bg-success' : ($employmentStatus === 'suspended' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ ucwords(str_replace('_', ' ', $employmentStatus)) }}</span>
                @if ($teacher->employee_id)
                    <code class="mt-2">#{{ $teacher->employee_id }}</code>
                @endif
                @php $teacherUid = $teacher->user->uid ?? null; if(!$teacherUid){ $foundUser = \App\Models\User::where('email', $teacher->email)->first(); $teacherUid = $foundUser?->uid; } @endphp
                @if($teacherUid)
                    <div class="mt-2"><x-uid-with-copy :uid="$teacherUid" label="Staff UID" /></div>
                @elseif($teacher->uuid)
                    <div class="mt-2"><x-uid-with-copy :uid="\Illuminate\Support\Str::substr($teacher->uuid,0,6)" label="Staff UID" /></div>
                @endif
            </div>
            <hr>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Branch</dt>
                <dd class="col-7">{{ $teacher->branch?->name ?? '—' }}</dd>
                <dt class="col-5 text-muted">Email</dt>
                <dd class="col-7 text-break">{{ $teacher->email ?? '—' }}</dd>
                <dt class="col-5 text-muted">Phone</dt>
                <dd class="col-7">{{ $teacher->phone ?? '—' }}</dd>
                <dt class="col-5 text-muted">Gender</dt>
                <dd class="col-7">{{ $teacher->gender ? ucfirst($teacher->gender) : '—' }}</dd>
                <dt class="col-5 text-muted">Date of birth</dt>
                <dd class="col-7">{{ $profile?->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                <dt class="col-5 text-muted">Joining date</dt>
                <dd class="col-7">{{ $teacher->joining_date ? \Illuminate\Support\Carbon::parse($teacher->joining_date)->format('d M Y') : '—' }}</dd>
                <dt class="col-5 text-muted">Department</dt>
                <dd class="col-7">{{ $teacher->department ?? '—' }}</dd>
                <dt class="col-5 text-muted">Qualification</dt>
                <dd class="col-7">{{ $teacher->qualification ?? '—' }}</dd>
                <dt class="col-5 text-muted">Specialization</dt>
                <dd class="col-7">{{ $profile?->specialization ?? '—' }}</dd>
                <dt class="col-5 text-muted">Experience</dt>
                <dd class="col-7">{{ $profile?->experience_years !== null ? $profile->experience_years.' years' : '—' }}</dd>
                <dt class="col-5 text-muted">Employment type</dt>
                <dd class="col-7">{{ $profile?->employment_type ? ucwords(str_replace('_', ' ', $profile->employment_type)) : '—' }}</dd>
                <dt class="col-5 text-muted">Address</dt>
                <dd class="col-7">{{ $profile?->address ?? '—' }}</dd>
                <dt class="col-5 text-muted">Emergency contact</dt>
                <dd class="col-7">{{ $profile?->emergency_contact_name ? $profile->emergency_contact_name.' ('.$profile->emergency_contact_phone.')' : '—' }}</dd>
            </dl>
            @if ($profile?->skills)
                <hr>
                <div class="small fw-semibold mb-1">Skills</div>
                @foreach ($profile->skills as $skill)
                    <span class="badge text-bg-light border me-1">{{ $skill }}</span>
                @endforeach
            @endif
            @if ($profile?->languages)
                <div class="small fw-semibold mb-1 mt-2">Languages</div>
                @foreach ($profile->languages as $language)
                    <span class="badge text-bg-info-subtle text-dark border me-1">{{ $language }}</span>
                @endforeach
            @endif
            @if ($profile?->bio)
                <hr>
                <div class="small fw-semibold mb-1">Bio</div>
                <p class="small mb-0">{{ $profile->bio }}</p>
            @endif
            @if ($profile?->notes)
                <hr>
                <div class="small fw-semibold mb-1">Notes</div>
                <p class="small mb-0">{{ $profile->notes }}</p>
            @endif
        </div>

        @if ($canManage)
            <div class="admin-card mb-3">
                <h6 class="mb-2">Change employment status</h6>
                <form method="POST" action="{{ route('teachers.status', $teacher) }}">
                    @csrf
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" name="employment_status">
                            @foreach ($employmentStatuses ?? [] as $employmentStatusOption)
                                <option value="{{ $employmentStatusOption }}" @selected($employmentStatusOption === $employmentStatus)>{{ ucwords(str_replace('_', ' ', $employmentStatusOption)) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                    </div>
                    <div class="form-text">History (past assignments) is always preserved.</div>
                </form>
            </div>
        @endif
    </div>

    <div class="col-lg-8">
        <div class="admin-card mb-3">
            <h6 class="mb-3">Current workload <span class="text-muted small fw-normal">(calculated from assignments)</span></h6>
            <div class="row g-2">
                <div class="col-6 col-md-3"><div class="summary-box"><span class="summary-value">{{ $workload['active_assignments'] }}</span><span class="summary-label">Active assignments</span></div></div>
                <div class="col-6 col-md-3"><div class="summary-box"><span class="summary-value">{{ $workload['completed_assignments'] }}</span><span class="summary-label">Completed</span></div></div>
                <div class="col-6 col-md-3"><div class="summary-box"><span class="summary-value">{{ $workload['distinct_courses'] }}</span><span class="summary-label">Courses</span></div></div>
                <div class="col-6 col-md-3"><div class="summary-box"><span class="summary-value">{{ $workload['distinct_subjects'] }}</span><span class="summary-label">Subjects</span></div></div>
                <div class="col-6 col-md-3"><div class="summary-box"><span class="summary-value">{{ $workload['distinct_batches'] }}</span><span class="summary-label">Batches</span></div></div>
                <div class="col-6 col-md-3"><div class="summary-box"><span class="summary-value">{{ $workload['students_assigned'] }}</span><span class="summary-label">{{ $isProfessional ? 'Trainees' : 'Students' }}</span></div></div>
                <div class="col-12">
                    @if ($workload['by_responsibility'] !== [])
                        @foreach ($workload['by_responsibility'] as $responsibility => $count)
                            <span class="badge text-bg-light border me-1 mb-1">{{ ucwords(str_replace('_', ' ', $responsibility)) }}: {{ $count }}</span>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="admin-card mb-3">
                <h6 class="mb-3">Add teaching assignment</h6>
                <form method="POST" action="{{ route('teachers.assign', $teacher) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label mb-1">Academic year <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="academic_year_id" required>
                                <option value="">— Select —</option>
                                @foreach ($academicYears as $year)
                                    <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Branch <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="branch_id" required>
                                <option value="">— Select —</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id || ($branch->id == optional($teacher->branch)->id && $branches->count() === 1))>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Responsibility <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="responsibility" required>
                                @foreach ($responsibilities as $responsibility)
                                    <option value="{{ $responsibility }}" @selected(old('responsibility') === $responsibility)>{{ ucwords(str_replace('_', ' ', $responsibility)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Course</label>
                            <select class="form-select form-select-sm" name="course_id">
                                <option value="">— None —</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Subject</label>
                            <select class="form-select form-select-sm" name="subject_id">
                                <option value="">— None —</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Batch / class</label>
                            <select class="form-select form-select-sm" name="batch_id">
                                <option value="">— None —</option>
                                @foreach ($batches as $batch)
                                    <option value="{{ $batch->id }}" @selected(old('batch_id') == $batch->id)>{{ $batch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Class (academic)</label>
                            <select class="form-select form-select-sm" name="class_grade_id">
                                <option value="">— None —</option>
                                @foreach ($classGrades as $classGrade)
                                    <option value="{{ $classGrade->id }}" @selected(old('class_grade_id') == $classGrade->id)>{{ $classGrade->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Group</label>
                            <select class="form-select form-select-sm" name="academic_group_id">
                                <option value="">— None —</option>
                                @foreach ($academicGroups as $group)
                                    <option value="{{ $group->id }}" @selected(old('academic_group_id') == $group->id)>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Assigned on</label>
                            <input type="date" class="form-control form-control-sm" name="assigned_at" value="{{ old('assigned_at', now()->toDateString()) }}">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label mb-1">Notes</label>
                            <input type="text" class="form-control form-control-sm" name="notes" value="{{ old('notes') }}" maxlength="2000">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus-lg me-1"></i>Add assignment</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        <div class="admin-card mb-3">
            <h6 class="mb-3">Teaching history</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-muted">#</th>
                            <th>Year</th>
                            <th>Responsibility</th>
                            <th>Course / Subject</th>
                            <th>Batch / Class</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td class="text-muted">{{ $assignments->firstItem() + $loop->index }}</td>
                                <td>
                                    {{ $assignment->academicYear?->name ?? '—' }}
                                    <div class="text-muted small">{{ $assignment->branch?->name ?? '' }}</div>
                                </td>
                                <td>{{ ucwords(str_replace('_', ' ', $assignment->responsibility)) }}</td>
                                <td>
                                    @if ($assignment->course)
                                        <div>{{ $assignment->course->name }}</div>
                                    @endif
                                    @if ($assignment->subject)
                                        <div class="text-muted small">{{ $assignment->subject->name }}</div>
                                    @endif
                                    @if (! $assignment->course && ! $assignment->subject)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->batch)
                                        <div>
                                            <a href="{{ route('batches.show', $assignment->batch) }}" class="text-decoration-none">{{ $assignment->batch->name }}</a>
                                        </div>
                                    @endif
                                    @if ($assignment->classGrade)
                                        <div class="text-muted small">{{ $assignment->classGrade->name }}{{ $assignment->academicGroup ? ' / '.$assignment->academicGroup->name : '' }}</div>
                                    @endif
                                    @if (! $assignment->batch && ! $assignment->classGrade)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($assignment->status === 'active')
                                        <span class="badge text-bg-success">Active</span>
                                        <div class="text-muted small">since {{ $assignment->assigned_at?->format('d M Y') }}</div>
                                    @else
                                        <span class="badge text-bg-secondary">Completed</span>
                                        <div class="text-muted small">{{ $assignment->completed_at?->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($canManage)
                                        @if ($assignment->status === 'active')
                                            <form method="POST" action="{{ route('teachers.complete', $assignment) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" type="submit" data-confirm="Mark this assignment as completed?"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('teachers.remove', $assignment) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this assignment?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No teaching assignments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($assignments->hasPages())
                <div class="p-2 border-top">{{ $assignments->links() }}</div>
            @endif
        </div>
    </div>

    @if (optional(auth('institute_user')->user())->hasPermission('documents.view'))
        <div class="col-12">
            <div class="admin-card">
                @include('documents._panel', ['entityType' => 'teacher', 'entityId' => $teacher->id])
            </div>
        </div>
    @endif
</div>

@endsection
