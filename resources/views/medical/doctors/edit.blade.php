@extends('layouts.institute')

@section('title', 'Edit Doctor — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Doctor — {{ $doctor->full_name }}</h4>
        <p class="page-header-desc">{{ $doctor->registration_number }} · {{ $doctor->specialty_name }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        @if(($user ?? null) && $user->hasPermission('medical_doctors.create'))
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="bi bi-plus-circle me-1"></i>Add Category
            </button>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.doctors.show', $doctor) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('medical.doctors.update', $doctor) }}" method="POST">
            @csrf @method('PUT')

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="user_id">User (Doctor Account) <span class="text-danger">*</span></label>
                        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id', $doctor->user_id) == $user->id ? 'selected' : '' }}>
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
                        <input type="text" id="registration_number" name="registration_number" class="form-control @error('registration_number') is-invalid @enderror" value="{{ old('registration_number', $doctor->registration_number) }}" required maxlength="50">
                        @error('registration_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="department_id">Department <span class="text-danger">*</span></label>
                        <select id="department_id" name="department_id" class="form-select" required>
                            <option value="">Select department...</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ (string) old('department_id', $doctor->specialty?->department_id) === (string) $dept->id ? 'selected' : '' }}>
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
                        <input type="text" id="qualification" name="qualification" class="form-control @error('qualification') is-invalid @enderror" value="{{ old('qualification', $doctor->qualification) }}">
                        @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="experience_years">Experience (years)</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control @error('experience_years') is-invalid @enderror" value="{{ old('experience_years', $doctor->experience_years) }}" min="0">
                        @error('experience_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <script>
                        (function () {
                            var el = document.getElementById('experience_years');
                            if (!el) return;
                            el.addEventListener('change', function () {
                                var raw = (el.value || '').trim();
                                if (raw === '') return;
                                var n = Number(raw);
                                if (!isFinite(n) || n < 0) return;
                                el.value = String(Math.floor(n));
                            });
                        })();
                        </script>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="consultation_fee">Consultation Fee (৳)</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" class="form-control @error('consultation_fee') is-invalid @enderror" value="{{ old('consultation_fee', $doctor->consultation_fee) }}" min="0" step="0.01">
                        @error('consultation_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="first_visit_fee">First Visit Fee (৳)</label>
                        <input type="number" id="first_visit_fee" name="first_visit_fee" class="form-control @error('first_visit_fee') is-invalid @enderror" value="{{ old('first_visit_fee', $doctor->first_visit_fee ?? 700) }}" min="0" step="0.01">
                        @error('first_visit_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_fee">Follow-up Fee (৳)</label>
                        <input type="number" id="follow_up_fee" name="follow_up_fee" class="form-control @error('follow_up_fee') is-invalid @enderror" value="{{ old('follow_up_fee', $doctor->follow_up_fee ?? 500) }}" min="0" step="0.01">
                        @error('follow_up_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_days">Follow-up Period (days)</label>
                        <input type="number" id="follow_up_days" name="follow_up_days" class="form-control @error('follow_up_days') is-invalid @enderror" value="{{ old('follow_up_days', $doctor->follow_up_days ?? 30) }}" min="1" max="365">
                        <div class="form-text">Days within which follow-up fee applies.</div>
                        @error('follow_up_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $doctor->phone) }}" maxlength="20">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $doctor->email) }}" maxlength="100">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="chamber_address">Chamber Address</label>
                        <input type="text" id="chamber_address" name="chamber_address" class="form-control @error('chamber_address') is-invalid @enderror" value="{{ old('chamber_address', $doctor->chamber_address) }}">
                        @error('chamber_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="bio">Bio</label>
                        <textarea id="bio" name="bio" class="form-control @error('bio') is-invalid @enderror" rows="3">{{ old('bio', $doctor->bio) }}</textarea>
                        @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Visit Fee Collection</label>
                        <div class="form-check form-switch">
                            <input type="checkbox" id="collect_fee_before_visit" name="collect_fee_before_visit" value="1" class="form-check-input" {{ old('collect_fee_before_visit', $doctor->collect_fee_before_visit ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="collect_fee_before_visit" id="feeToggleLabel">Post-visit</label>
                        </div>
                        <div class="form-text" id="feeToggleHelp">Fee collected after prescription.</div>
                        @error('collect_fee_before_visit')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
                <script>
                (function () {
                    var t = document.getElementById('collect_fee_before_visit');
                    if (!t) return;
                    function syncFeeToggle() {
                        var on = t.checked;
                        var label = document.getElementById('feeToggleLabel');
                        var help = document.getElementById('feeToggleHelp');
                        if (label) label.textContent = on ? 'Pre-visit' : 'Post-visit';
                        if (help) help.textContent = on ? 'Fee collected before entering chamber.' : 'Fee collected after prescription.';
                    }
                    t.addEventListener('change', syncFeeToggle);
                    syncFeeToggle();
                })();
                </script>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label" for="room_no">Room / Chamber No.</label>
                        <input type="text" id="room_no" name="room_no" class="form-control @error('room_no') is-invalid @enderror" value="{{ old('room_no', $doctor->room_no) }}" maxlength="50" placeholder="Room 204">
                        @error('room_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-check mb-3">
                        <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', $doctor->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            @include('medical.doctors.partials.availability-fields')

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Update Doctor</button>
                <a href="{{ route('medical.doctors.show', $doctor) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const deptSelect = document.getElementById('department_id');
    const specSelect = document.getElementById('specialty_id');
    const urlTemplate = @json(route('medical.departments.specialties', ['department' => '__ID__']));
    const currentSpecialty = @json(old('specialty_id', $doctor->specialty_id));
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

    if (deptSelect && deptSelect.value) {
        loadSpecialties(deptSelect.value, currentSpecialty, true);
    }
})();
</script>

@include('medical.doctors.partials.category-modal')
@endsection
