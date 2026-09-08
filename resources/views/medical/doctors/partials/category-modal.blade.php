{{-- On-the-fly Department / Specialty creator (used by doctor create + edit). --}}
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="categoryForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="categoryType">Category Type <span class="text-danger">*</span></label>
                        <select id="categoryType" name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="department">Department</option>
                            <option value="specialty">Specialty</option>
                        </select>
                    </div>

                    <div id="departmentFields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label" for="deptName">Department Name <span class="text-danger">*</span></label>
                            <input type="text" id="deptName" class="form-control" placeholder="Enter department name" maxlength="100">
                            <div class="invalid-feedback" id="deptNameError"></div>
                        </div>
                    </div>

                    <div id="specialtyFields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label" for="specialtyDepartmentId">Department <span class="text-danger">*</span></label>
                            <select id="specialtyDepartmentId" class="form-select">
                                <option value="">Select Department</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="specDeptError"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="specName">Specialty Name <span class="text-danger">*</span></label>
                            <input type="text" id="specName" class="form-control" placeholder="Enter specialty name" maxlength="100">
                            <div class="invalid-feedback" id="specNameError"></div>
                        </div>
                    </div>

                    <div id="categoryError" class="alert alert-danger" style="display:none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCategoryBtn">
                    <i class="bi bi-check-lg me-1"></i>Save Category
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const typeSelect = document.getElementById('categoryType');
    const deptFields = document.getElementById('departmentFields');
    const specFields = document.getElementById('specialtyFields');
    const deptName = document.getElementById('deptName');
    const specName = document.getElementById('specName');
    const specDept = document.getElementById('specialtyDepartmentId');
    const saveBtn = document.getElementById('saveCategoryBtn');
    const categoryForm = document.getElementById('categoryForm');
    const categoryError = document.getElementById('categoryError');
    const categoryModal = document.getElementById('categoryModal');

    const storeUrl = @json(route('medical.categories.store'));
    const departmentsUrl = @json(route('medical.categories.departments'));
    const specialtiesUrlTemplate = @json(route('medical.departments.specialties', ['department' => '__ID__']));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    if (!typeSelect || !saveBtn) return;

    typeSelect.addEventListener('change', function () {
        const type = this.value;
        deptFields.style.display = type === 'department' ? 'block' : 'none';
        specFields.style.display = type === 'specialty' ? 'block' : 'none';
        clearErrors();
    });

    saveBtn.addEventListener('click', function () {
        const type = typeSelect.value;
        if (!type) {
            categoryError.textContent = 'Please select a category type.';
            categoryError.style.display = 'block';
            return;
        }

        const payload = { type: type };
        if (type === 'department') {
            payload.name = deptName.value.trim();
        } else {
            payload.name = specName.value.trim();
            payload.department_id = specDept.value || null;
        }
        clearErrors();
        saveBtn.disabled = true;

        fetch(storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        }).then(function (r) {
            return r.json().then(function (j) { return { status: r.status, body: j }; });
        }).then(function (res) {
            saveBtn.disabled = false;
            const data = res.body || {};
            if (res.status === 200 && data.success) {
                bootstrap.Modal.getInstance(categoryModal)?.hide();
                if (data.data.type === 'department') {
                    refreshDepartments(data.data.id, null);
                } else {
                    refreshDepartments(data.data.department_id, data.data.id);
                }
                flashSuccess(data.message || 'Category created successfully.');
                return;
            }
            showErrors(data.errors || {}, data.message);
        }).catch(function () {
            saveBtn.disabled = false;
            categoryError.textContent = 'Network error. Please try again.';
            categoryError.style.display = 'block';
        });
    });

    function clearErrors() {
        [deptName, specName, specDept].forEach(function (el) { el?.classList.remove('is-invalid'); });
        categoryError.style.display = 'none';
    }

    function showErrors(errors, fallbackMessage) {
        if (errors.name) {
            const target = typeSelect.value === 'department' ? deptName : specName;
            const errBox = typeSelect.value === 'department'
                ? document.getElementById('deptNameError')
                : document.getElementById('specNameError');
            target.classList.add('is-invalid');
            if (errBox) errBox.textContent = errors.name[0];
        }
        if (errors.department_id) {
            specDept.classList.add('is-invalid');
            document.getElementById('specDeptError').textContent = errors.department_id[0];
        }
        if (errors.type) {
            categoryError.textContent = errors.type[0];
            categoryError.style.display = 'block';
        } else if (!errors.name && !errors.department_id) {
            categoryError.textContent = fallbackMessage || 'Something went wrong.';
            categoryError.style.display = 'block';
        }
    }

    // Rebuild the main department dropdown; then handle the specialty side.
    function refreshDepartments(selectedDeptId, newSpecialtyId) {
        fetch(departmentsUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json(); }).then(function (j) {
            const list = (j && j.data) || [];
            const deptSelect = document.getElementById('department_id');
            const current = selectedDeptId || (deptSelect ? deptSelect.value : null);
            if (deptSelect) {
                deptSelect.innerHTML = '<option value="">Select department...</option>';
                list.forEach(function (d) {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;
                    if (String(d.id) === String(current)) opt.selected = true;
                    deptSelect.appendChild(opt);
                });
            }
            // Keep the modal's department list fresh too.
            specDept.innerHTML = '<option value="">Select Department</option>';
            list.forEach(function (d) {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name;
                specDept.appendChild(opt);
            });

            if (newSpecialtyId) {
                refreshSpecialties(current, newSpecialtyId);
            } else if (deptSelect) {
                // New department has no specialties yet → show the empty state.
                renderSpecialtyOptions([], null);
            }
        });
    }

    function refreshSpecialties(departmentId, selectedId) {
        if (!departmentId) return;
        fetch(specialtiesUrlTemplate.replace('__ID__', departmentId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json(); }).then(function (j) {
            renderSpecialtyOptions((j && j.data) || [], selectedId);
        });
    }

    function renderSpecialtyOptions(list, selectedId) {
        const specSelect = document.getElementById('specialty_id');
        if (!specSelect) return;
        specSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select specialty... (optional)';
        specSelect.appendChild(placeholder);
        if (list.length > 0) {
            list.forEach(function (s) {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                if (String(s.id) === String(selectedId)) opt.selected = true;
                specSelect.appendChild(opt);
            });
        } else {
            const msg = document.createElement('option');
            msg.value = '';
            msg.textContent = 'No sub-specialties — registers as General (department level)';
            msg.disabled = true;
            specSelect.appendChild(msg);
        }
    }

    function flashSuccess(message) {
        const card = document.querySelector('.card .card-body');
        if (!card) return;
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show';
        alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        card.prepend(alert);
        setTimeout(function () { alert.remove(); }, 5000);
    }

    categoryModal.addEventListener('hidden.bs.modal', function () {
        categoryForm.reset();
        deptFields.style.display = 'none';
        specFields.style.display = 'none';
        clearErrors();
        saveBtn.disabled = false;
    });
})();
</script>
