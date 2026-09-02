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
                <input type="date" name="date_of_birth" class="form-control form-control-sm" value="{{ old('date_of_birth', $employee?->date_of_birth?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Joining Date</label>
                <input type="date" name="joining_date" class="form-control form-control-sm" value="{{ old('joining_date', $employee?->joining_date?->format('Y-m-d')) }}">
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

@endsection
