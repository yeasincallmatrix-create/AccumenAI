<!-- Edit Student modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" id="editStudentDialog">
        <form class="modal-content" method="POST" action="{{ route('students.update', $student->id ?? 0) }}" enctype="multipart/form-data" id="editStudentForm" data-ajax-enabled>
            @csrf
            @method('PUT')
            <input type="hidden" name="student_id" id="e_student_id" value="{{ old('student_id', $student->id ?? '') }}">
            <input type="hidden" name="return_to_list" id="e_return_to_list" value="{{ old('return_to_list', '') }}">

            <div class="modal-header">
                <h5 class="modal-title">Edit — {{ $student->full_name }}</h5>
                <div class="d-flex align-items-center gap-1 ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-secondary border-0" id="eModalMaxBtn" title="Maximize">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">

                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="mt-2 fw-bold text-primary">Personal Information</h6>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_first_name">First Name *</label>
                        <input id="e_first_name" class="form-control" name="first_name" value="{{ old('first_name', $student->first_name) }}" required maxlength="60">
                        @error('first_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_last_name">Last Name</label>
                        <input id="e_last_name" class="form-control" name="last_name" value="{{ old('last_name', $student->last_name) }}" maxlength="60">
                        @error('last_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Student ID</label>
                        <input class="form-control bg-light" value="{{ $student->student_id }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_roll_number">Roll Number</label>
                        <input id="e_roll_number" class="form-control" name="roll_number" value="{{ old('roll_number', $student->roll_number) }}" maxlength="20" placeholder="e.g. R-1042">
                        @error('roll_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_reg_no">Reg No.</label>
                        <input id="e_reg_no" class="form-control" name="reg_no" value="{{ old('reg_no', $student->reg_no) }}" maxlength="10" placeholder="e.g. 4126085240">
                        @error('reg_no') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_gender">Gender</label>
                        <select id="e_gender" class="form-select" name="gender">
                            <option value="">Select</option>
                            @foreach (['male', 'female', 'other'] as $g)
                                <option value="{{ $g }}" @selected(old('gender', $student->gender) == $g)>{{ ucfirst($g) }}</option>
                            @endforeach
                        </select>
                        @error('gender') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_dob">Date of Birth</label>
                        <input id="e_dob" type="date" class="form-control" name="dob" value="{{ old('dob', $student->dob?->format('Y-m-d')) }}">
                        @error('dob') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_admission_date">Admission Date *</label>
                        <input id="e_admission_date" type="date" class="form-control" name="admission_date" value="{{ old('admission_date', $student->admission_date?->format('Y-m-d')) }}" required>
                        @error('admission_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="e_phone">Phone</label>
                        @include('partials.phone', ['name' => 'phone', 'id' => 'e_phone', 'value' => old('phone', $student->phone), 'country' => $country ?? null])
                        @error('phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="e_email">Email</label>
                        <input id="e_email" type="email" class="form-control" name="email" value="{{ old('email', $student->email) }}" maxlength="150">
                        @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="e_religion">Religion</label>
                        <select id="e_religion" class="form-select" name="religion">
                            <option value="">Select</option>
                            @foreach (['Islam', 'Hindu', 'Buddhist', 'Christian', 'Other'] as $rel)
                                <option value="{{ $rel }}" @selected(old('religion', $student->religion) == $rel)>{{ $rel }}</option>
                            @endforeach
                        </select>
                        @error('religion') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="e_status">Status</label>
                        <select id="e_status" class="form-select" name="status" required>
                            @foreach (['active', 'completed', 'dropped', 'suspended'] as $st)
                                <option value="{{ $st }}" @selected(old('status', $student->status) == $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <h6 class="mt-2 fw-bold text-primary">Guardian Information</h6>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_father_name">Father's Name</label>
                        <input id="e_father_name" class="form-control" name="father_name" value="{{ old('father_name', $student->father_name) }}">
                        @error('father_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_mother_name">Mother's Name</label>
                        <input id="e_mother_name" class="form-control" name="mother_name" value="{{ old('mother_name', $student->mother_name) }}">
                        @error('mother_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_guardian_phone">Guardian Phone</label>
                        @include('partials.phone', ['name' => 'guardian_phone', 'id' => 'e_guardian_phone', 'value' => old('guardian_phone', $student->guardian_phone), 'country' => $country ?? null])
                        @error('guardian_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="e_nationality">Nationality</label>
                        <input id="e_nationality" class="form-control" name="nationality" value="{{ old('nationality', $student->nationality) }}" maxlength="60">
                        @error('nationality') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <h6 class="mt-4 fw-bold text-primary">Identification Documents</h6>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="e_nid_number">NID Number</label>
                        <input id="e_nid_number" class="form-control" name="nid_number" value="{{ old('nid_number', $student->nid_number) }}" maxlength="30">
                        @error('nid_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="e_birth_cert_number">Birth Certificate No.</label>
                        <input id="e_birth_cert_number" class="form-control" name="birth_cert_number" value="{{ old('birth_cert_number', $student->birth_cert_number) }}" maxlength="30">
                        @error('birth_cert_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="e_passport">Passport Number</label>
                        <input id="e_passport" class="form-control" name="passport_number" value="{{ old('passport_number', $student->passport_number) }}" maxlength="40">
                        @error('passport_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="e_blood_group">Blood Group</label>
                        <select id="e_blood_group" class="form-select" name="blood_group">
                            <option value="">Select</option>
                            @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $bg)
                                <option value="{{ $bg }}" @selected(old('blood_group', $student->blood_group) == $bg)>{{ $bg }}</option>
                            @endforeach
                        </select>
                        @error('blood_group') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

                <h6 class="mt-4 fw-bold text-primary">Address</h6>
                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="address-box">
                            <div class="box-title">Present Address</div>
                            <x-address :prefix="'present_'"
                                       :country-id="old('present_country_id', $student->present_country_id ?? $defaultCountryId ?? null)"
                                       :level-1-id="old('present_admin_1_id', $student->present_admin_1_id)"
                                       :level-2-id="old('present_admin_2_id', $student->present_admin_2_id)"
                                       :level-3-id="old('present_admin_3_id', $student->present_admin_3_id)"
                                       :level-labels="$presentAddress['level_labels'] ?? []"
                                       :level-1-options="$presentAddress['level_options'][1] ?? []"
                                       :level-2-options="$presentAddress['level_options'][2] ?? []"
                                       :level-3-options="$presentAddress['level_options'][3] ?? []"
                                       :postal-code="old('present_zip_code', $student->present_zip_code)"
                                       :address="old('present_address', $student->present_address)" />
                            @error('present_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="address-box">
                            <div class="box-title">
                                Permanent Address
                                <label class="check-row" for="e_same_as_present">
                                    <input class="form-check-input" type="checkbox" id="e_same_as_present" name="same_as_present" value="1">
                                    Same as present address
                                </label>
                            </div>
                            <x-address :prefix="'permanent_'"
                                       :country-id="old('permanent_country_id', $student->permanent_country_id ?? $defaultCountryId ?? null)"
                                       :level-1-id="old('permanent_admin_1_id', $student->permanent_admin_1_id)"
                                       :level-2-id="old('permanent_admin_2_id', $student->permanent_admin_2_id)"
                                       :level-3-id="old('permanent_admin_3_id', $student->permanent_admin_3_id)"
                                       :level-labels="$permanentAddress['level_labels'] ?? []"
                                       :level-1-options="$permanentAddress['level_options'][1] ?? []"
                                       :level-2-options="$permanentAddress['level_options'][2] ?? []"
                                       :level-3-options="$permanentAddress['level_options'][3] ?? []"
                                       :postal-code="old('permanent_zip_code', $student->permanent_zip_code)"
                                       :address="old('permanent_address', $student->permanent_address)" />
                            @error('permanent_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-12"><h6 class="fw-bold text-primary mb-0">Emergency Contact</h6></div>
                    <div class="col-md-6">
                        <label class="form-label" for="e_emergency_contact_name">Emergency Contact Name</label>
                        <input id="e_emergency_contact_name" class="form-control" name="emergency_contact_name" value="{{ old('emergency_contact_name', $student->emergency_contact_name) }}" maxlength="120">
                        @error('emergency_contact_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="e_emergency_contact_phone">Emergency Contact Phone</label>
                        @include('partials.phone', ['name' => 'emergency_contact_phone', 'id' => 'e_emergency_contact_phone', 'value' => old('emergency_contact_phone', $student->emergency_contact_phone), 'country' => $country ?? null])
                        @error('emergency_contact_phone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
            <div class="monetix-resize-handle" id="eResizeHandle" title="Resize"></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var checkbox = document.getElementById('e_same_as_present');
    if (!checkbox) { return; }
    checkbox.addEventListener('change', function () {
        if (!this.checked) { return; }
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
})();

(function () {
    var modalEl = document.getElementById('editStudentModal');
    var maxBtn  = document.getElementById('eModalMaxBtn');
    if (!modalEl || !maxBtn) { return; }
    maxBtn.addEventListener('click', function () {
        var isMax = modalEl.classList.toggle('monetix-maximized');
        this.querySelector('i').className = isMax ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    });
})();

(function () {
    var dialog = document.getElementById('editStudentDialog');
    var handle = document.getElementById('eResizeHandle');
    if (!dialog || !handle) { return; }
    var resizing = false;
    var rs = { x: 0, y: 0, w: 0, h: 0 };
    handle.addEventListener('mousedown', function (e) {
        resizing = true;
        rs.x = e.clientX;
        rs.y = e.clientY;
        rs.w = dialog.offsetWidth;
        rs.h = dialog.offsetHeight;
        e.preventDefault();
        e.stopPropagation();
    });
    document.addEventListener('mousemove', function (e) {
        if (!resizing) { return; }
        var newW = Math.max(420, rs.w + (e.clientX - rs.x));
        var newH = Math.max(300, rs.h + (e.clientY - rs.y));
        dialog.style.width = Math.min(newW, window.innerWidth - 40) + 'px';
        dialog.style.maxWidth = dialog.style.width;
        dialog.style.height = Math.min(newH, window.innerHeight - 30) + 'px';
        dialog.style.maxHeight = dialog.style.height;
    });
    document.addEventListener('mouseup', function () { resizing = false; });
})();

(function () {
    var form = document.getElementById('editStudentForm');
    var modalEl = document.getElementById('editStudentModal');
    if (!form || !modalEl || !window.Monetix || !Monetix.request) { return; }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        form.querySelectorAll('.text-danger.small').forEach(function (el) { el.remove(); });
        var banner = document.getElementById('studentFormErrors');
        if (banner) { banner.remove(); }
    }

    function showErrors(errors) {
        clearErrors();
        Object.keys(errors || {}).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            if (field) {
                field.classList.add('is-invalid');
                var msg = document.createElement('div');
                msg.className = 'text-danger small mt-1';
                msg.textContent = (errors[key] || []).join(', ');
                field.parentNode.insertBefore(msg, field.nextSibling);
            }
        });
        if (!document.getElementById('studentFormErrors')) {
            var banner = document.createElement('div');
            banner.id = 'studentFormErrors';
            banner.className = 'alert alert-danger m-3 mb-0 py-2';
            banner.textContent = 'Please fix the highlighted fields and try again.';
            form.querySelector('.modal-header').insertAdjacentElement('afterend', banner);
        }
    }

    form.addEventListener('submit', function (e) {
        if (!form.hasAttribute('data-ajax-enabled')) { return; }
        e.preventDefault();
        clearErrors();
        var submitBtn = form.querySelector('[type="submit"]');
        var restore = Monetix.loading(submitBtn, 'Saving…');
        Monetix.request(form.action, {
            method: 'PUT',
            body: new FormData(form),
        }).then(function (res) {
            if (restore) { restore(); }
            if (res && res.errors) { showErrors(res.errors); return; }
            if (res && res.success === false) {
                if (Monetix.toast) { Monetix.toast(res.message || 'Could not save the student.', 'danger'); }
                return;
            }
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) { modal.hide(); }
            if (Monetix.toast) { Monetix.toast(res && res.message, 'success'); }
            var returnTo = form.querySelector('[name="return_to_list"]');
            if (returnTo && returnTo.value) {
                if (Monetix.loadPage) { Monetix.loadPage(location.pathname + location.search, { preserveFocus: false }); }
            } else if (window.monetixFillEdit) {
                window.monetixFillEdit(res && res.data && res.data.id);
            } else if (Monetix.loadPage) {
                Monetix.loadPage(location.pathname + location.search, { preserveFocus: false });
            }
        }).catch(function () {
            if (restore) { restore(); }
            if (Monetix.toast) { Monetix.toast('Could not save the student. Please try again.', 'danger'); }
        });
    });
})();
</script>
@endpush
