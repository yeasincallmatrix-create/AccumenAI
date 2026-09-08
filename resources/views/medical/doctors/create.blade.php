@extends('layouts.institute')

@section('title', 'Add Doctor — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Add Doctor</h4>
        <p class="page-header-desc">Register a new doctor with specialty and weekly availability</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        @if(($user ?? null) && $user->hasPermission('medical_doctors.create'))
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="bi bi-plus-circle me-1"></i>Add Category
            </button>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.doctors.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.doctors.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="user_id">User (Doctor Account) <span class="text-danger">*</span></label>
                        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Select user...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="registration_number">Registration Number <span class="text-danger">*</span></label>
                        <input type="text" id="registration_number" name="registration_number" class="form-control @error('registration_number') is-invalid @enderror" value="{{ old('registration_number') }}" required maxlength="50" placeholder="D-2026-001">
                        @error('registration_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="department_id">Department <span class="text-danger">*</span></label>
                        <select id="department_id" name="department_id" class="form-select" required>
                            <option value="">Select department...</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Selecting a department filters the specialty list.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="specialty_id">Specialty <span class="text-muted">(optional)</span></label>
                        <select id="specialty_id" name="specialty_id" class="form-select @error('specialty_id') is-invalid @enderror">
                            <option value="">Select department first...</option>
                        </select>
                        <div class="form-text">Leave empty to register at department level (General).</div>
                        @error('specialty_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="qualification">Qualification</label>
                        <input type="text" id="qualification" name="qualification" class="form-control @error('qualification') is-invalid @enderror" value="{{ old('qualification') }}" placeholder="MBBS, MD (Cardiology)">
                        @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="experience_years">Experience (years)</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control @error('experience_years') is-invalid @enderror" value="{{ old('experience_years', 0) }}" min="0">
                        @error('experience_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="consultation_fee">Consultation Fee (৳)</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" class="form-control @error('consultation_fee') is-invalid @enderror" value="{{ old('consultation_fee', 0) }}" min="0" step="0.01">
                        @error('consultation_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" maxlength="20" placeholder="017XXXXXXXX">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" maxlength="100" placeholder="dr.name@hospital.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chamber_address">Chamber Address</label>
                        <input type="text" id="chamber_address" name="chamber_address" class="form-control @error('chamber_address') is-invalid @enderror" value="{{ old('chamber_address') }}" placeholder="House 5, Road 10, Dhaka">
                        @error('chamber_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="room_no">Room / Chamber No.</label>
                        <input type="text" id="room_no" name="room_no" class="form-control @error('room_no') is-invalid @enderror" value="{{ old('room_no') }}" maxlength="50" placeholder="Room 204">
                        @error('room_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label" for="bio">Bio</label>
                        <textarea id="bio" name="bio" class="form-control @error('bio') is-invalid @enderror" rows="3">{{ old('bio') }}</textarea>
                        @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            @include('medical.doctors.partials.availability-fields')

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Doctor</button>
                <a href="{{ route('medical.doctors.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const deptSelect = document.getElementById('department_id');
    const specSelect = document.getElementById('specialty_id');
    const urlTemplate = @json(route('medical.departments.specialties', ['department' => '__ID__']));
    const oldSpecialty = @json(old('specialty_id'));
    // All specialties grouped by department (fallback when AJAX fails + initial paint).
    const allByDept = @json($specialties->groupBy('department_id')->map(fn($g) => $g->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->values())->all());

    function renderOptions(list, selectedId, placeholder) {
        specSelect.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = placeholder || 'Select specialty... (optional)';
        specSelect.appendChild(defaultOption);

        if (list && list.length > 0) {
            list.forEach(function (s) {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                if (String(s.id) === String(selectedId)) opt.selected = true;
                specSelect.appendChild(opt);
            });
        } else {
            // No sub-specialties: explain that department-level registration applies.
            const msg = document.createElement('option');
            msg.value = '';
            msg.textContent = 'No sub-specialties — registers as General (department level)';
            msg.disabled = true;
            specSelect.appendChild(msg);
        }
    }

    function loadSpecialties(deptId, selectedId, useFallbackFirst) {
        if (!deptId) {
            specSelect.innerHTML = '<option value="">Select department first...</option>';
            return;
        }
        if (useFallbackFirst && allByDept[deptId]) {
            renderOptions(allByDept[deptId], selectedId);
        } else {
            specSelect.innerHTML = '<option value="">Loading...</option>';
        }
        fetch(urlTemplate.replace('__ID__', deptId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (j && j.success && Array.isArray(j.data)) renderOptions(j.data, selectedId);
            else if (allByDept[deptId]) renderOptions(allByDept[deptId], selectedId);
        }).catch(function () {
            if (allByDept[deptId]) renderOptions(allByDept[deptId], selectedId);
            else specSelect.innerHTML = '<option value="">Could not load specialties</option>';
        });
    }

    deptSelect?.addEventListener('change', function () {
        loadSpecialties(this.value, null, false);
    });

    // Initial paint (supports old input after validation failure).
    if (deptSelect && deptSelect.value) {
        loadSpecialties(deptSelect.value, oldSpecialty, true);
    }
})();
</script>

@include('medical.doctors.partials.category-modal')
@endsection
