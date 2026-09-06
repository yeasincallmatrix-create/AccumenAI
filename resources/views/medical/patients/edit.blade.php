@extends('layouts.institute')

@section('title', 'Edit Patient — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Patient — {{ $patient->full_name }} ({{ $patient->mr_number }})</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.patients.show', $patient) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.patients.update', $patient) }}" method="POST">
            @csrf
            @method('PUT')

            <h6 class="mb-3">Personal Information</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               class="form-control @error('first_name') is-invalid @enderror"
                               value="{{ old('first_name', $patient->first_name) }}" required maxlength="50">
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                               class="form-control @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name', $patient->last_name) }}" required maxlength="50">
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="date_of_birth">Date of Birth <span class="text-danger">*</span></label>
                        <input type="date" id="date_of_birth" name="date_of_birth"
                               class="form-control @error('date_of_birth') is-invalid @enderror"
                               value="{{ old('date_of_birth', $patient->date_of_birth?->format('Y-m-d')) }}" required>
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                        <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                            <option value="">Select Gender</option>
                            <option value="male" @selected(old('gender', $patient->gender) === 'male')>Male</option>
                            <option value="female" @selected(old('gender', $patient->gender) === 'female')>Female</option>
                            <option value="other" @selected(old('gender', $patient->gender) === 'other')>Other</option>
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="blood_group">Blood Group</label>
                        <select id="blood_group" name="blood_group" class="form-select @error('blood_group') is-invalid @enderror">
                            <option value="">Select Blood Group</option>
                            @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                                <option value="{{ $bg }}" @selected(old('blood_group', $patient->blood_group) === $bg)>{{ $bg }}</option>
                            @endforeach
                        </select>
                        @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $patient->phone) }}" required maxlength="20">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $patient->email) }}" maxlength="100">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="is_active">Status</label>
                        <select id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                            <option value="1" @selected(old('is_active', $patient->is_active) == true)>Active</option>
                            <option value="0" @selected(old('is_active', $patient->is_active) == false)>Inactive</option>
                        </select>
                        @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mb-3 mt-3">Present Address</h6>
            <x-address :prefix="'present_'"
                       :country-id="old('present_country_id', $patient->present_country_id)"
                       :level-1-id="old('present_admin_1_id', $patient->present_admin_1_id)"
                       :level-2-id="old('present_admin_2_id', $patient->present_admin_2_id)"
                       :level-3-id="old('present_admin_3_id', $patient->present_admin_3_id)"
                       :level-labels="$presentAddress['level_labels']"
                       :level-1-options="$presentAddress['level_options'][1]"
                       :level-2-options="$presentAddress['level_options'][2]"
                       :level-3-options="$presentAddress['level_options'][3]"
                       :address="old('present_address', $patient->present_address)" />

            <h6 class="mb-3 mt-3">Emergency Contact</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                               class="form-control @error('emergency_contact_name') is-invalid @enderror"
                               value="{{ old('emergency_contact_name', $patient->emergency_contact_name) }}" maxlength="100">
                        @error('emergency_contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="text" id="emergency_contact_phone" name="emergency_contact_phone"
                               class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                               value="{{ old('emergency_contact_phone', $patient->emergency_contact_phone) }}" maxlength="20">
                        @error('emergency_contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h6 class="mb-3 mt-3">Medical Information</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="2"
                                  class="form-control @error('allergies') is-invalid @enderror">{{ old('allergies', $patient->allergies) }}</textarea>
                        @error('allergies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chronic_conditions">Chronic Conditions</label>
                        <textarea id="chronic_conditions" name="chronic_conditions" rows="2"
                                  class="form-control @error('chronic_conditions') is-invalid @enderror">{{ old('chronic_conditions', $patient->chronic_conditions) }}</textarea>
                        @error('chronic_conditions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $patient->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Update Patient
                </button>
                <a href="{{ route('medical.patients.show', $patient) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
