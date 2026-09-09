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
                <div class="col-md-12">
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <label class="form-label mb-0" for="user_id">User (Doctor Account) <span class="text-danger">*</span></label>
                            @if(($user ?? null) && $user->hasPermission('staff.manage'))
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#quickUserModal">
                                    <i class="bi bi-person-plus me-1"></i>Add Doctor
                                </button>
                            @endif
                        </div>
                        <select id="user_id" name="user_id" class="form-select mt-2 @error('user_id') is-invalid @enderror" required>
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
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="registration_number">Registration Number <span class="text-danger">*</span></label>
                        <input type="text" id="registration_number" name="registration_number" class="form-control @error('registration_number') is-invalid @enderror" value="{{ old('registration_number') }}" required maxlength="50" placeholder="D-2026-001">
                        @error('registration_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="qualification">Qualification</label>
                        <input type="text" id="qualification" name="qualification" class="form-control @error('qualification') is-invalid @enderror" value="{{ old('qualification') }}" placeholder="MBBS, MD">
                        @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="experience_years">Experience (yrs)</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control @error('experience_years') is-invalid @enderror" value="{{ old('experience_years', 0) }}" min="0">
                        @error('experience_years')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" maxlength="20" placeholder="017XXXXXXXX">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" maxlength="100" placeholder="dr.name@hospital.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="room_no">Room No.</label>
                        <input type="text" id="room_no" name="room_no" class="form-control @error('room_no') is-invalid @enderror" value="{{ old('room_no') }}" maxlength="50" placeholder="Room 204">
                        @error('room_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="department_id">Department <span class="text-danger">*</span></label>
                        <select id="department_id" name="department_id" class="form-select" required>
                            <option value="">Select...</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="specialty_id">Specialty</label>
                        <select id="specialty_id" name="specialty_id" class="form-select @error('specialty_id') is-invalid @enderror">
                            <option value="">Select dept first...</option>
                        </select>
                        @error('specialty_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="consultation_fee">Consult. Fee (৳)</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" class="form-control @error('consultation_fee') is-invalid @enderror" value="{{ old('consultation_fee', 0) }}" min="0" step="0.01">
                        @error('consultation_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="first_visit_fee">First Visit (৳)</label>
                        <input type="number" id="first_visit_fee" name="first_visit_fee" class="form-control @error('first_visit_fee') is-invalid @enderror" value="{{ old('first_visit_fee', 700) }}" min="0" step="0.01">
                        @error('first_visit_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_fee">Follow-up (৳)</label>
                        <input type="number" id="follow_up_fee" name="follow_up_fee" class="form-control @error('follow_up_fee') is-invalid @enderror" value="{{ old('follow_up_fee', 500) }}" min="0" step="0.01">
                        @error('follow_up_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label" for="follow_up_days">Follow-up (days)</label>
                        <input type="number" id="follow_up_days" name="follow_up_days" class="form-control @error('follow_up_days') is-invalid @enderror" value="{{ old('follow_up_days', 30) }}" min="1" max="365">
                        @error('follow_up_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                        <label class="form-label" for="bio">Bio</label>
                        <textarea id="bio" name="bio" class="form-control @error('bio') is-invalid @enderror" rows="1">{{ old('bio') }}</textarea>
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

@if(($user ?? null) && $user->hasPermission('staff.manage'))
{{-- Quick "Add Doctor Account" popup (same fields as staff invite). --}}
<div class="modal fade" id="quickUserModal" tabindex="-1" aria-labelledby="quickUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="quickUserForm" action="{{ route('medical.doctors.quick-user') }}" method="POST" novalidate>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="quickUserModalLabel"><i class="bi bi-person-plus me-1"></i>Add Doctor Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="qu-errors" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="qu_first_name">First Name <span class="text-danger">*</span></label>
                            <input id="qu_first_name" type="text" class="form-control" name="first_name" required maxlength="60" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qu_last_name">Last Name <span class="text-danger">*</span></label>
                            <input id="qu_last_name" type="text" class="form-control" name="last_name" required maxlength="60" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qu_email">Email <span class="text-danger">*</span></label>
                            <input id="qu_email" type="email" class="form-control" name="email" required maxlength="150" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qu_phone">Phone <span class="text-danger">*</span></label>
                            @include('partials.phone', ['name' => 'phone', 'id' => 'qu_phone', 'value' => '', 'required' => true])
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="qu_role">Role <span class="text-danger">*</span></label>
                            <select id="qu_role" name="role_id" class="form-select" required>
                                <option value="">— Select a role —</option>
                                @foreach(($inviteRoles ?? []) as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qu_password">Temporary password <span class="text-danger">*</span></label>
                            <input id="qu_password" type="password" class="form-control" name="password" required autocomplete="new-password">
                            <div class="form-text">The invited staff will sign in with this temporary password and should change it after first login.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qu_password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                            <input id="qu_password_confirmation" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="qu-submit">
                        <i class="bi bi-person-plus me-1"></i>Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('quickUserForm');
    if (!form) return;
    const errBox = document.getElementById('qu-errors');
    const submitBtn = document.getElementById('qu-submit');
    const userSelect = document.getElementById('user_id');

    function showErrors(messages) {
        errBox.innerHTML = '';
        messages.forEach(function (msg) {
            const div = document.createElement('div');
            div.className = 'small';
            div.textContent = msg;
            errBox.appendChild(div);
        });
        errBox.classList.remove('d-none');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('d-none');
        errBox.innerHTML = '';
        submitBtn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        }).then(async function (res) {
            let data = {};
            try { data = await res.json(); } catch (_) {}
            if (res.ok && data.success && data.data) {
                const opt = document.createElement('option');
                opt.value = data.data.id;
                opt.textContent = data.data.name + ' (' + data.data.email + ')';
                opt.selected = true;
                userSelect.appendChild(opt);
                userSelect.value = String(data.data.id);
                form.reset();
                bootstrap.Modal.getInstance(document.getElementById('quickUserModal'))?.hide();
                if (window.Monetix && Monetix.toast) Monetix.toast(data.message || 'Doctor account created.', 'success');
                return;
            }
            if (res.status === 422 && data.errors) {
                showErrors(Object.values(data.errors).flat());
            } else {
                showErrors([data.message || 'Could not create the account. Please try again.']);
            }
        }).catch(function () {
            showErrors(['Network error. Please check your connection and try again.']);
        }).finally(function () {
            submitBtn.disabled = false;
        });
    });
})();
</script>
@endif
@endsection
