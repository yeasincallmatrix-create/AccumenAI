@extends('layouts.standalone')

@section('title', ($teacher ? 'Edit Teacher — AccumenAI' : 'New Teacher — AccumenAI'))
@section('page_title', $teacher ? 'Edit Teacher' : 'New Teacher')

@section('content')

<div class="standalone-heading">
    <h4>{{ $teacher ? 'Edit Teacher' : 'New Teacher' }}</h4>
    <p>A teacher is a staff account (institute_users) with a professional profile. Institute and branch are derived from the signed-in account — they can never be spoofed from the form.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $teacher ? route('teachers.update', $teacher) : route('teachers.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($teacher) @method('PUT') @endif

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Identity &amp; account</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">First name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="first_name" value="{{ old('first_name', $teacher?->first_name) }}" required maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="form-label">Last name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="last_name" value="{{ old('last_name', $teacher?->last_name) }}" required maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select class="form-select form-select-sm" name="gender">
                    <option value="">— Not set —</option>
                    @foreach (['male', 'female', 'other'] as $gender)
                        <option value="{{ $gender }}" @selected(old('gender', $teacher?->gender) === $gender)>{{ ucfirst($gender) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control form-control-sm" name="email" value="{{ old('email', $teacher?->email) }}" required maxlength="150">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone <span class="text-danger">*</span></label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'teacher_phone', 'value' => old('phone', $teacher?->phone), 'required' => true])
            </div>
            <div class="col-md-4">
                <label class="form-label">Branch <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="branch_id" required>
                    <option value="">— Select —</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $teacher?->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            @if (! $teacher)
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control form-control-sm" name="password" required minlength="8">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control form-control-sm" name="password_confirmation" required minlength="8">
                </div>
            @endif
            <div class="col-md-4">
                <label class="form-label">Photo</label>
                <input type="file" class="form-control form-control-sm" name="photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG/PNG/WebP, max 100 KB — auto-resized to 7:9.</div>
            </div>
            @if ($teacher?->photo)
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                        <label class="form-check-label" for="remove_photo">Remove current photo</label>
                    </div>
                </div>
            @endif
        </div>

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Employment</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Designation</label>
                <input type="text" class="form-control form-control-sm" name="designation" value="{{ old('designation', $teacher?->designation) }}" maxlength="80">
            </div>
            <div class="col-md-4">
                <label class="form-label">Department</label>
                <input type="text" class="form-control form-control-sm" name="department" value="{{ old('department', $teacher?->department) }}" maxlength="80">
            </div>
            <div class="col-md-4">
                <label class="form-label">Qualification</label>
                <input type="text" class="form-control form-control-sm" name="qualification" value="{{ old('qualification', $teacher?->qualification) }}" maxlength="150">
            </div>
            <div class="col-md-3">
                <label class="form-label">Joining date</label>
                <input type="date" class="form-control form-control-sm" name="joining_date" value="{{ old('joining_date', $teacher?->joining_date ? \Illuminate\Support\Carbon::parse($teacher->joining_date)->format('Y-m-d') : '') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment type</label>
                <select class="form-select form-select-sm" name="employment_type">
                    <option value="">— Not set —</option>
                    @foreach ($employmentTypes as $employmentType)
                        <option value="{{ $employmentType }}" @selected(old('employment_type', $profile?->employment_type) === $employmentType)>{{ ucwords(str_replace('_', ' ', $employmentType)) }}</option>
                    @endforeach
                </select>
            </div>
            @if (! $teacher)
                <div class="col-md-3">
                    <label class="form-label">Employment status</label>
                    <select class="form-select form-select-sm" name="employment_status">
                        @foreach ($employmentStatuses as $employmentStatus)
                            <option value="{{ $employmentStatus }}" @selected(old('employment_status', 'active') === $employmentStatus)>{{ ucwords(str_replace('_', ' ', $employmentStatus)) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <label class="form-label">Experience (years)</label>
                <input type="number" class="form-control form-control-sm" name="experience_years" value="{{ old('experience_years', $profile?->experience_years) }}" min="0" max="70">
            </div>
            <div class="col-md-3">
                <label class="form-label">Specialization</label>
                <input type="text" class="form-control form-control-sm" name="specialization" value="{{ old('specialization', $profile?->specialization) }}" maxlength="150">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of birth</label>
                <input type="date" class="form-control form-control-sm" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" class="form-control form-control-sm" name="address" value="{{ old('address', $profile?->address) }}" maxlength="255">
            </div>
            <div class="col-md-3">
                <label class="form-label">Emergency contact name</label>
                <input type="text" class="form-control form-control-sm" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}" maxlength="120">
            </div>
            <div class="col-md-3">
                <label class="form-label">Emergency contact phone</label>
                @include('partials.phone', ['name' => 'emergency_contact_phone', 'id' => 'teacher_emergency_phone', 'value' => old('emergency_contact_phone', $profile?->emergency_contact_phone)])
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-semibold mb-2">Professional</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Skills <span class="text-muted">(comma separated)</span></label>
                <input type="text" class="form-control form-control-sm" name="skills" value="{{ old('skills', $profile?->skills ? implode(', ', $profile->skills) : '') }}" maxlength="1000">
            </div>
            <div class="col-md-6">
                <label class="form-label">Languages <span class="text-muted">(comma separated)</span></label>
                <input type="text" class="form-control form-control-sm" name="languages" value="{{ old('languages', $profile?->languages ? implode(', ', $profile->languages) : '') }}" maxlength="500">
            </div>
            <div class="col-md-6">
                <label class="form-label">Bio</label>
                <textarea class="form-control form-control-sm" name="bio" rows="3" maxlength="5000">{{ old('bio', $profile?->bio) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <textarea class="form-control form-control-sm" name="notes" rows="3" maxlength="5000">{{ old('notes', $profile?->notes) }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $teacher ? 'Save changes' : 'Create teacher' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ $teacher ? route('teachers.show', $teacher) : route('teachers.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection