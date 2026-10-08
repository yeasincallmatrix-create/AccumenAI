@extends('layouts.institute')

@section('title', ($employee ? 'Edit Employee' : 'New Employee').' — HR')

@section('content')

<div class="standalone-heading">
    <h4>{{ $employee ? 'Edit Employee' : 'New Employee' }}</h4>
    <p>Industry-neutral master profile. Employee code is generated automatically and tenant-safe.</p>
    <a href="{{ route('hr.employees.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Employees</a>
</div>

<div class="admin-card p-3">
    <form method="POST" action="{{ $employee ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($employee) @method('PUT') @endif

        @if ($errors->any())
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $errors->first() }}</div>
        @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-control form-control-sm" value="{{ old('first_name', $employee?->first_name) }}" required maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control form-control-sm" value="{{ old('middle_name', $employee?->middle_name) }}" maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-control form-control-sm" value="{{ old('last_name', $employee?->last_name) }}" required maxlength="60">
            </div>

            <div class="col-md-3">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($genders as $g)
                        <option value="{{ $g }}" @selected(old('gender', $employee?->gender) === $g)>{{ ucfirst($g) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of Birth</label>
                <x-tdate-input name="date_of_birth" :value="old('date_of_birth', $employee?->date_of_birth?->format('Y-m-d'))" class="form-control form-control-sm" />
            </div>
            <div class="col-md-3">
                <label class="form-label">Joining Date</label>
                <x-tdate-input name="joining_date" :value="old('joining_date', $employee?->joining_date?->format('Y-m-d'))" class="form-control form-control-sm" />
            </div>
            <div class="col-md-3">
                <label class="form-label">Profile Photo</label>
                <input type="file" name="profile_photo" class="form-control form-control-sm" accept="image/*">
                @if ($employee?->profile_photo)
                    <div class="form-check mt-1">
                        <input type="checkbox" name="remove_photo" value="1" class="form-check-input" id="remove_photo">
                        <label class="form-check-label small" for="remove_photo">Remove existing photo</label>
                    </div>
                @endif
            </div>

            <div class="col-md-3">
                <label class="form-label">Blood Group</label>
                <select name="blood_group" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach (\App\Models\HrEmployee::BLOOD_GROUPS as $bg)
                        <option value="{{ $bg }}" @selected(old('blood_group', $employee?->blood_group) === $bg)>{{ $bg }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Marital Status</label>
                <select name="marital_status" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach (\App\Models\HrEmployee::MARITAL_STATUSES as $ms)
                        <option value="{{ $ms }}" @selected(old('marital_status', $employee?->marital_status) === $ms)>{{ ucwords($ms) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Education Qualification</label>
                <input type="text" name="education_qualification" class="form-control form-control-sm" value="{{ old('education_qualification', $employee?->education_qualification) }}" maxlength="500" placeholder="e.g. MBBS, MD (Cardiology)">
            </div>

            <div class="col-md-4">
                <label class="form-label">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'hr_phone', 'value' => old('phone', $employee?->phone), 'country' => $institute->country ?? null])
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $employee?->email) }}" maxlength="150">
            </div>
            <div class="col-md-4">
                <label class="form-label">National ID</label>
                <input type="text" name="national_id" class="form-control form-control-sm" value="{{ old('national_id', $employee?->national_id) }}" maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="form-label">Passport No</label>
                <input type="text" name="passport_no" class="form-control form-control-sm" value="{{ old('passport_no', $employee?->passport_no) }}" maxlength="60">
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control form-control-sm" value="{{ old('address', $employee?->address) }}" maxlength="2000" placeholder="Full address">
            </div>

            <div class="col-12">
                <div class="address-box">
                    <div class="box-title">Present Address</div>
                    <x-address :prefix="'present_'"
                               :country-id="old('present_country_id', $employee?->present_country_id ?? $defaultCountryId ?? null)"
                               :level-1-id="old('present_admin_1_id', $employee?->present_admin_1_id)"
                               :level-2-id="old('present_admin_2_id', $employee?->present_admin_2_id)"
                               :level-3-id="old('present_admin_3_id', $employee?->present_admin_3_id)"
                               :level-labels="$presentAddress['level_labels']"
                               :level-1-options="$presentAddress['level_options'][1]"
                               :level-2-options="$presentAddress['level_options'][2]"
                               :level-3-options="$presentAddress['level_options'][3]"
                               :postal-code="old('present_zip_code', $employee?->present_zip_code)"
                               :address="old('present_address', $employee?->present_address)"
                               address-label="Street / Area" />
                    @error('present_address')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-12">
                <div class="address-box">
                    <div class="box-title">
                        Permanent Address
                        <label class="check-row">
                            <input type="checkbox" id="same_as_present" name="same_as_present" value="1" @checked(old('same_as_present'))>
                            Same as present address
                        </label>
                    </div>
                    <x-address :prefix="'permanent_'"
                               :country-id="old('permanent_country_id', $employee?->permanent_country_id ?? $defaultCountryId ?? null)"
                               :level-1-id="old('permanent_admin_1_id', $employee?->permanent_admin_1_id)"
                               :level-2-id="old('permanent_admin_2_id', $employee?->permanent_admin_2_id)"
                               :level-3-id="old('permanent_admin_3_id', $employee?->permanent_admin_3_id)"
                               :level-labels="$permanentAddress['level_labels']"
                               :level-1-options="$permanentAddress['level_options'][1]"
                               :level-2-options="$permanentAddress['level_options'][2]"
                               :level-3-options="$permanentAddress['level_options'][3]"
                               :postal-code="old('permanent_zip_code', $employee?->permanent_zip_code)"
                               :address="old('permanent_address', $employee?->permanent_address)"
                               address-label="Street / Area" />
                    @error('permanent_address')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            @php
                $expertiseTags = old('expertise', $employee?->expertise ?? []);
                if (! is_array($expertiseTags)) {
                    $expertiseTags = [];
                }
            @endphp
            <div class="col-md-12">
                <label class="form-label">Expertise</label>
                <div class="border rounded p-2 bg-body" id="expertise_tags" style="min-height:38px;"></div>
                <input type="hidden" name="expertise" id="expertise_value" value="{{ json_encode($expertiseTags, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                <input type="text" id="expertise_input" class="form-control form-control-sm mt-2" placeholder="Type an expertise and press Enter (e.g. Cardiology)" autocomplete="off">
                <div class="form-text">Press Enter or comma to add a tag. Backspace removes the last one.</div>
                @error('expertise')<div class="text-danger small">{{ $message }}</div>@enderror
                @error('expertise.*')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control form-control-sm" value="{{ old('emergency_contact_name', $employee?->emergency_contact_name) }}" maxlength="120">
            </div>
            <div class="col-md-6">
                <label class="form-label">Emergency Contact Phone</label>
                @include('partials.phone', ['name' => 'emergency_contact_phone', 'id' => 'hr_emergency_phone', 'value' => old('emergency_contact_phone', $employee?->emergency_contact_phone), 'country' => $institute->country ?? null])
            </div>

            <div class="col-md-3">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">Institute-wide</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string)old('branch_id', $employee?->branch_id) === (string)$branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((string)old('department_id', $employee?->department_id) === (string)$dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Designation</label>
                <select name="designation_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($designations as $des)
                        <option value="{{ $des->id }}" @selected((string)old('designation_id', $employee?->designation_id) === (string)$des->id)>{{ $des->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Reporting Manager</label>
                <select name="reporting_manager_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}" @selected((string)old('reporting_manager_id', $employee?->reporting_manager_id) === (string)$manager->id)>{{ $manager->display_name }} ({{ $manager->employee_code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Employment Status</label>
                <select name="employment_status" class="form-select form-select-sm">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('employment_status', $employee?->employment_status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment Type</label>
                <select name="employment_type" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('employment_type', $employee?->employment_type) === $type)>{{ ucwords(str_replace('_',' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control form-control-sm" value="{{ old('notes', $employee?->notes) }}" maxlength="5000" placeholder="Optional notes">
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ $employee ? 'Update Employee' : 'Create Employee' }}</button>
            <a href="{{ $employee ? route('hr.employees.show', $employee) : route('hr.employees.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var box = document.getElementById('same_as_present');
    if (box) {
        box.addEventListener('change', function () {
            if (!box.checked) { return; }
            var presentRoot = document.querySelector('[data-address-component][data-prefix="present_"]');
            var permRoot = document.querySelector('[data-address-component][data-prefix="permanent_"]');
            if (!presentRoot || !permRoot) { return; }
            var copySelect = function (fromName, toName) {
                var src = presentRoot.querySelector('[name="' + fromName + '"]');
                var dst = permRoot.querySelector('[name="' + toName + '"]');
                if (src && dst) {
                    dst.innerHTML = src.innerHTML;
                    dst.value = src.value;
                }
            };
            var copyInput = function (fromName, toName) {
                var src = presentRoot.querySelector('[name="' + fromName + '"]');
                var dst = permRoot.querySelector('[name="' + toName + '"]');
                if (src && dst) { dst.value = src.value; }
            };
            copySelect('present_country_id', 'permanent_country_id');
            copySelect('present_admin_1_id', 'permanent_admin_1_id');
            copySelect('present_admin_2_id', 'permanent_admin_2_id');
            copySelect('present_admin_3_id', 'permanent_admin_3_id');
            copyInput('present_zip_code', 'permanent_zip_code');
            copyInput('present_address', 'permanent_address');
            if (permRoot.refresh) { permRoot.refresh(); }
        });
    }

    var hidden = document.getElementById('expertise_value');
    var input = document.getElementById('expertise_input');
    var tagsBox = document.getElementById('expertise_tags');
    if (!hidden || !input || !tagsBox) return;

    var tags = [];
    try { tags = JSON.parse(hidden.value || '[]') || []; } catch (e) { tags = []; }
    tags = tags.filter(function (t) { return typeof t === 'string' && t.trim() !== ''; });

    function render() {
        tagsBox.innerHTML = '';
        if (!tags.length) {
            var empty = document.createElement('span');
            empty.className = 'text-muted small';
            empty.textContent = 'No expertise added yet.';
            tagsBox.appendChild(empty);
        }
        tags.forEach(function (tag, index) {
            var badge = document.createElement('span');
            badge.className = 'badge text-bg-light border d-inline-flex align-items-center me-1 mb-1';
            badge.appendChild(document.createTextNode(tag));

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-link btn-sm p-0 ms-1 text-decoration-none';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove ' + tag);
            remove.addEventListener('click', function () {
                tags.splice(index, 1);
                render();
            });

            badge.appendChild(remove);
            tagsBox.appendChild(badge);
        });
        hidden.value = JSON.stringify(tags);
    }

    function addTags(raw) {
        var added = false;
        String(raw || '').split(',').forEach(function (part) {
            var tag = part.trim();
            if (!tag) return;
            var lower = tag.toLowerCase();
            if (tags.some(function (t) { return t.toLowerCase() === lower; })) return;
            tags.push(tag.slice(0, 100));
            added = true;
        });
        if (added) render();
    }

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTags(input.value);
            input.value = '';
        } else if (e.key === 'Backspace' && input.value === '' && tags.length) {
            tags.pop();
            render();
        }
    });
    input.addEventListener('blur', function () {
        if (input.value.trim()) {
            addTags(input.value);
            input.value = '';
        }
    });

    render();
})();
</script>

@endsection
