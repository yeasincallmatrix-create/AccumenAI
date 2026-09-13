@extends('layouts.institute')

@section('title', 'Edit Patient — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Patient — {{ $patient->full_name }} ({{ clinical_no($patient->mr_number) }})</h4>
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
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               class="form-control @error('first_name') is-invalid @enderror"
                               value="{{ old('first_name', $patient->first_name) }}" required maxlength="50">
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name"
                               class="form-control @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name', $patient->last_name) }}" maxlength="50">
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <x-tdate-input name="date_of_birth" :value="old('date_of_birth', $patient->date_of_birth?->format('Y-m-d'))" id="date_of_birth" :class="'form-control'.($errors->has('date_of_birth') ? ' is-invalid' : '')" />
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="age">Age <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" id="age" name="age" min="0" max="150"
                                   class="form-control @error('age') is-invalid @enderror"
                                   value="{{ old('age', $patient->age) }}" required>
                            <select id="age_unit" name="age_unit" class="form-select flex-grow-0 w-auto @error('age_unit') is-invalid @enderror">
                                <option value="days" @selected(old('age_unit') === 'days')>Days</option>
                                <option value="months" @selected(old('age_unit') === 'months')>Months</option>
                                <option value="years" @selected(old('age_unit', 'years') === 'years')>Years</option>
                            </select>
                        </div>
                        @error('age')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('age_unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
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
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="mb-3">
                        <label class="form-label" for="blood_group">Blood Group</label>
                        <select id="blood_group" name="blood_group" class="form-select @error('blood_group') is-invalid @enderror">
                            <option value="">Select Blood Group</option>
                            @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'UKN'] as $bg)
                                <option value="{{ $bg }}" @selected(old('blood_group', $patient->blood_group) === $bg)>{{ $bg === 'UKN' ? 'UKN (Unknown)' : $bg }}</option>
                            @endforeach
                        </select>
                        @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $patient->phone) }}" maxlength="20">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $patient->email) }}" maxlength="100">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-4">
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

            <h6 class="mb-3 mt-3">Family</h6>
            <div class="row">
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="relation_to_primary">Relation</label>
                        <select id="relation_to_primary" name="relation_to_primary" class="form-select @error('relation_to_primary') is-invalid @enderror">
                            @foreach(['Self', 'Son', 'Daughter', 'Wife', 'Husband', 'Father', 'Mother', 'Brother', 'Sister', 'Other'] as $rel)
                                <option value="{{ $rel }}" @selected(old('relation_to_primary', $patient->relation_to_primary ?? 'Self') === $rel)>{{ $rel }}</option>
                            @endforeach
                        </select>
                        @error('relation_to_primary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Primary Contact</label>
                        <div class="form-control-plaintext">
                            @if($patient->primaryContact)
                                <a href="{{ route('medical.patients.show', $patient->primaryContact) }}">{{ $patient->primaryContact->full_name }}</a>
                                <span class="text-muted">({{ clinical_no($patient->primaryContact->mr_number) }})</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
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
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                               class="form-control @error('emergency_contact_name') is-invalid @enderror"
                               value="{{ old('emergency_contact_name', $patient->emergency_contact_name) }}" maxlength="100">
                        @error('emergency_contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-6">
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
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="2"
                                  class="form-control @error('allergies') is-invalid @enderror">{{ old('allergies', $patient->allergies) }}</textarea>
                        @error('allergies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chronic_conditions">Chronic Conditions</label>
                        <textarea id="chronic_conditions" name="chronic_conditions" rows="2"
                                  class="form-control @error('chronic_conditions') is-invalid @enderror">{{ old('chronic_conditions', $patient->chronic_conditions) }}</textarea>
                        @error('chronic_conditions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-12">
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

@push('scripts')
<script>
(function () {
    var dob = document.getElementById('date_of_birth');
    var age = document.getElementById('age');
    var ageUnit = document.getElementById('age_unit');
    var UNIT_MAX = { days: 36500, months: 1800, years: 150 };
    function unit() { return ageUnit ? ageUnit.value : 'years'; }
    function ageFromDob() {
        if (!dob.value) return;
        var b = new Date(dob.value + 'T00:00:00');
        var now = new Date();
        if (isNaN(b) || b > now) return;
        var u = unit(), v;
        if (u === 'days') v = Math.floor((now - b) / 86400000);
        else if (u === 'months') {
            v = (now.getFullYear() - b.getFullYear()) * 12 + (now.getMonth() - b.getMonth());
            if (now.getDate() < b.getDate()) v--;
        } else {
            v = now.getFullYear() - b.getFullYear();
            var m = now.getMonth() - b.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < b.getDate())) v--;
        }
        if (v >= 0 && v <= UNIT_MAX[u]) age.value = v;
    }
    if (!dob || !age) return;
    dob.addEventListener('change', ageFromDob);
    dob.addEventListener('input', function () {
        if (!dob.value) age.value = '';
        else ageFromDob();
    });
    // Use change (not input) so typing "30" doesn't lock DOB to the intermediate "3".
    age.addEventListener('change', function () {
        var a = parseInt(age.value, 10);
        if (isNaN(a) || a < 0) return;
        var u = unit();
        if (a > UNIT_MAX[u]) return;
        var d = new Date();
        if (u === 'days') d.setDate(d.getDate() - a);
        else if (u === 'months') d.setMonth(d.getMonth() - a);
        else d.setFullYear(d.getFullYear() - a);
        if (!dob.value) { dob.value = d.toISOString().slice(0, 10); if (window.tdateSync) window.tdateSync('date_of_birth'); }
    });
    if (ageUnit) ageUnit.addEventListener('change', function () {
        age.max = UNIT_MAX[unit()];
        ageFromDob();
    });
})();
</script>
@endpush
