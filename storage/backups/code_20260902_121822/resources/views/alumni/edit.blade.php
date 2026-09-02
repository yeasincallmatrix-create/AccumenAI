@extends('layouts.institute')

@section('title', mawa_e('alumni.edit_btn') . ' ' . mawa_e('alumni.title') . ' — AccumenAI')

@php
    $student = $alumni->student;
    $name = $student->full_name ?: trim($student->first_name.' '.$student->last_name);
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <a href="{{ route('alumni.show', $alumni) }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('alumni.back_to_profile') }}
        </a>
        <h4 class="page-header-title">{{ mawa_e('alumni.edit_btn') }} {{ mawa_e('alumni.title') }} — {{ $name }}</h4>
        <p class="page-header-desc">{{ mawa_e('alumni.edit_desc') }}</p>
    </div>
</div>

<form method="POST" action="{{ route('alumni.update', $alumni) }}">
    @csrf
    @method('PUT')

    <div class="admin-card">
        <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.profile') }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">{{ mawa_e('alumni.reference_number') }} <span class="text-muted">{{ mawa_e('alumni.optional') }}</span></label>
                    <input type="text" name="alumni_reference_number" class="form-control" maxlength="40"
                           value="{{ old('alumni_reference_number', $alumni->alumni_reference_number) }}">
                    @error('alumni_reference_number')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ mawa_e('alumni.graduation_date') }}</label>
                    <input type="date" name="graduation_date" class="form-control"
                           value="{{ old('graduation_date', $alumni->graduation_date?->toDateString()) }}">
                    @error('graduation_date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.profile_visibility') }}</label>
                    <select name="profile_visibility" class="form-select">
                        <option value="private" @selected(old('profile_visibility', $alumni->profile_visibility) === 'private')>{{ mawa_e('alumni.visibility_private') }}</option>
                        <option value="public" @selected(old('profile_visibility', $alumni->profile_visibility) === 'public')>{{ mawa_e('alumni.visibility_public') }}</option>
                    </select>
                    @error('profile_visibility')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.public_contact') }}</label>
                    <select name="public_contact_preference" class="form-select">
                        <option value="private" @selected(old('public_contact_preference', $alumni->public_contact_preference) === 'private')>{{ mawa_e('alumni.visibility_private') }}</option>
                        <option value="email" @selected(old('public_contact_preference', $alumni->public_contact_preference) === 'email')>{{ mawa_e('alumni.contact_email') }}</option>
                        <option value="phone" @selected(old('public_contact_preference', $alumni->public_contact_preference) === 'phone')>{{ mawa_e('alumni.contact_phone') }}</option>
                        <option value="both" @selected(old('public_contact_preference', $alumni->public_contact_preference) === 'both')>{{ mawa_e('alumni.contact_email_phone') }}</option>
                    </select>
                    @error('public_contact_preference')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.th_status') }}</label>
                    <input type="text" class="form-control" value="{{ ucfirst($alumni->status) }}" disabled>
                    <div class="form-text">{{ mawa_e('alumni.change_via_action') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mt-3">
        <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.career') }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.current_occupation') }}</label>
                    <input type="text" name="current_occupation" class="form-control" maxlength="150"
                           value="{{ old('current_occupation', $alumni->current_occupation) }}">
                    @error('current_occupation')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.job_title') }}</label>
                    <input type="text" name="job_title" class="form-control" maxlength="150"
                           value="{{ old('job_title', $alumni->job_title) }}">
                    @error('job_title')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.employer') }}</label>
                    <input type="text" name="employer" class="form-control" maxlength="150"
                           value="{{ old('employer', $alumni->employer) }}">
                    @error('employer')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.employment_sector') }}</label>
                    <input type="text" name="employment_sector" class="form-control" maxlength="150"
                           value="{{ old('employment_sector', $alumni->employment_sector) }}">
                    @error('employment_sector')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.current_city') }}</label>
                    <input type="text" name="current_city" class="form-control" maxlength="120"
                           value="{{ old('current_city', $alumni->current_city) }}">
                    @error('current_city')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ mawa_e('alumni.current_country') }}</label>
                    <input type="text" name="current_country" class="form-control" maxlength="120"
                           value="{{ old('current_country', $alumni->current_country) }}">
                    @error('current_country')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ mawa_e('alumni.higher_education') }}</label>
                    <textarea name="higher_education" class="form-control" rows="2" maxlength="2000">{{ old('higher_education', $alumni->higher_education) }}</textarea>
                    @error('higher_education')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ mawa_e('alumni.career_notes') }}</label>
                    <textarea name="career_notes" class="form-control" rows="3" maxlength="4000">{{ old('career_notes', $alumni->career_notes) }}</textarea>
                    @error('career_notes')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">{{ mawa_e('alumni.internal_notes') }}</label>
                    <textarea name="notes" class="form-control" rows="2" maxlength="4000">{{ old('notes', $alumni->notes) }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ mawa_e('alumni.save_changes') }}</button>
        <a href="{{ route('alumni.show', $alumni) }}" class="btn btn-outline-secondary">{{ mawa_e('alumni.cancel') }}</a>
    </div>
</form>
@endsection
