@extends('layouts.institute')

@section('title', 'Convert Lead to Application — AccumenAI')

@php
    $genderNames = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Convert Lead to Application</h4>
        <p class="page-header-desc">{{ $lead->displayName() }} — pipeline stage: <span class="badge bg-info">{{ $lead->status?->name ?? $lead->status?->slug ?? '—' }}</span></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('crm.leads.show', $lead) }}">
            <i class="bi bi-arrow-left"></i> Back to lead
        </a>
        <a class="btn btn-outline-primary" href="{{ route('admissions.pipeline') }}">
            <i class="bi bi-diagram-3-fill"></i> Pipeline
        </a>
    </div>
</div>

@if ($errors->has('existing_student'))
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle me-2"></i>{{ $errors->first('existing_student') }}</span>
        <a href="#existing-students" class="btn btn-sm btn-warning">Find existing student</a>
    </div>
@endif

@if ($existing !== null)
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check-circle me-2"></i>This lead is already linked to application <strong>{{ $existing->application_number }}</strong> ({{ $existing->admission_status }}).</span>
        <a href="{{ route('admissions.show', $existing) }}" class="btn btn-sm btn-success">Open application</a>
    </div>
@endif

<div class="student-form-card" id="existing-students" @if ($existing !== null) style="opacity:.6;pointer-events:none;" @endif>
    <h3 class="section-title">Use an existing student instead</h3>
    <p class="hint mb-3">Search for an existing student (name, phone, email, application/registration number). Linking reuses the record and prevents duplicate applications.</p>

    <div class="field mb-3" style="max-width:520px;">
        <label for="existing_search">Search existing students</label>
        <input id="existing_search" type="text" class="form-control" placeholder="Type at least 2 characters…">
        <div id="existing_results" class="mt-2 d-flex flex-column gap-2"></div>
    </div>

    <div class="existing-empty text-muted small d-none" id="existing_empty">No matching existing students.</div>
</div>

<form method="POST" action="{{ route('admissions.pipeline.store', $lead) }}" @if ($existing !== null) style="opacity:.6;pointer-events:none;" @endif>
    @csrf

    <div class="student-form-card">

        <h3 class="section-title">Application</h3>
        <div class="grid">
            <div class="field">
                <label for="application_date">Application Date <span class="req">*</span></label>
                <input id="application_date" type="date" name="application_date" value="{{ old('application_date', now()->format('Y-m-d')) }}" required>
                @error('application_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="admission_source">Source</label>
                <select id="admission_source" name="admission_source">
                    <option value="">-- Select --</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->slug ?? $source->name }}" @selected(old('admission_source') === ($source->slug ?? $source->name))>{{ $source->name }}</option>
                    @endforeach
                </select>
                @error('admission_source') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="branch_id">Branch <span class="req">*</span></label>
                <select id="branch_id" name="branch_id" required>
                    <option value="">-- Select --</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $lead->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="applied_course_id">Applied Course <span class="req">*</span></label>
                <select id="applied_course_id" name="applied_course_id" required>
                    <option value="">-- Select --</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((string) old('applied_course_id') === (string) $course->id)>{{ $course->name }} ({{ $course->course_code }})</option>
                    @endforeach
                </select>
                @error('applied_course_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="applied_academic_year_id">Intended Academic Year</label>
                <select id="applied_academic_year_id" name="applied_academic_year_id">
                    <option value="">-- Select --</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((string) old('applied_academic_year_id') === (string) $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
                @error('applied_academic_year_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="preferred_batch_id">Preferred Batch</label>
                <select id="preferred_batch_id" name="preferred_batch_id">
                    <option value="">-- Select --</option>
                    @foreach ($batches as $batch)
                        <option value="{{ $batch->id }}" @selected((string) old('preferred_batch_id') === (string) $batch->id)>{{ $batch->name }} ({{ $batch->course?->name }})</option>
                    @endforeach
                </select>
                @error('preferred_batch_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="admission_assigned_user_id">Assigned Staff</label>
                <select id="admission_assigned_user_id" name="admission_assigned_user_id">
                    <option value="">-- Select --</option>
                    @foreach ($staff as $user)
                        <option value="{{ $user->id }}" @selected((string) old('admission_assigned_user_id', $lead->assigned_user_id) === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('admission_assigned_user_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <h3 class="section-title">Applicant</h3>
        <div class="grid">
            <div class="field">
                <label for="full_name">Full Name <span class="req">*</span></label>
                <input id="full_name" name="full_name" value="{{ old('full_name', $lead->displayName()) }}" required maxlength="120">
                @error('full_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">-- Select --</option>
                    @foreach ($genderNames as $g => $label)
                        <option value="{{ $g }}" @selected(old('gender') === $g)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="dob">Date of Birth</label>
                <input id="dob" type="date" name="dob" value="{{ old('dob') }}">
                @error('dob') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'phone', 'value' => old('phone', $lead->phone), 'country' => $institute->country ?? null])
                @error('phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="guardian_phone">Guardian Phone</label>
                @include('partials.phone', ['name' => 'guardian_phone', 'id' => 'guardian_phone', 'value' => old('guardian_phone'), 'country' => $institute->country ?? null])
                @error('guardian_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $lead->email) }}" maxlength="150">
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="country">Country</label>
                <select id="country" name="country">
                    <option value="">-- Select --</option>
                    @foreach ($countries as $code => $country)
                        <option value="{{ $code }}" @selected(old('country') === $code)>{{ $country }}</option>
                    @endforeach
                </select>
                @error('country') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="present_zip_code">Postal / ZIP code</label>
                <input id="present_zip_code" type="text" name="present_zip_code" value="{{ old('present_zip_code') }}" maxlength="10">
                @error('present_zip_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-footer">
            <a class="btn btn-outline-secondary" href="{{ route('crm.leads.show', $lead) }}">Cancel</a>
            <button class="btn-save" type="submit" @if ($existing !== null) disabled @endif><i class="bi bi-arrow-right-circle me-1"></i>Create Application from Lead</button>
        </div>

    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('existing_search');
    const results = document.getElementById('existing_results');
    const empty = document.getElementById('existing_empty');

    if (!search) return;

    let timer = null;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        const q = search.value.trim();

        if (q.length < 2) {
            results.innerHTML = '';
            empty.classList.add('d-none');
            return;
        }

        timer = setTimeout(function () {
            fetch("{{ route('admissions.pipeline.students') }}?q=" + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (students) {
                results.innerHTML = '';
                empty.classList.toggle('d-none', students.length > 0);

                students.forEach(function (s) {
                    const row = document.createElement('div');
                    row.className = 'border rounded p-2 bg-white d-flex justify-content-between align-items-center gap-2';

                    const info = document.createElement('div');
                    info.innerHTML = '<div class="fw-semibold">' + s.full_name + '</div>' +
                        '<small class="text-muted">' + (s.application_number || '—') +
                        (s.course ? ' · ' + s.course : '') +
                        (s.phone ? ' · ' + s.phone : '') + '</small>';
                    row.appendChild(info);

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = "{{ route('admissions.pipeline.link', $lead) }}";
                    form.className = 'mb-0';

                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = "{{ csrf_token() }}";
                    form.appendChild(csrf);

                    const id = document.createElement('input');
                    id.type = 'hidden';
                    id.name = 'student_id';
                    id.value = s.id;
                    form.appendChild(id);

                    const btn = document.createElement('button');
                    btn.type = 'submit';
                    btn.className = 'btn btn-sm btn-outline-primary';
                    btn.textContent = 'Link to lead';
                    form.appendChild(btn);

                    row.appendChild(form);
                    results.appendChild(row);
                });
            });
        }, 300);
    });
});
</script>
@endpush
@endsection