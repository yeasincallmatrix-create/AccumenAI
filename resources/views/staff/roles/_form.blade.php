{{-- Shared role form: $role, $groupedPermissions (module => permissions), $assigned (slugs). --}}
@php
    $isEdit = $role->exists;
    $checked = collect(old('permissions', $assigned ?? []))->map(fn($s) => (string) $s)->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label" for="role_name">Role Name <span class="text-danger">*</span></label>
            <input type="text" id="role_name" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $role->name) }}" required maxlength="80" placeholder="e.g. Front Desk">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="role_slug">Slug</label>
            <input type="text" id="role_slug" name="slug" class="form-control @error('slug') is-invalid @enderror"
                   value="{{ old('slug', $role->slug) }}" maxlength="80" placeholder="auto-generated">
            <div class="form-text">Lowercase letters, numbers, dashes. Auto-generated if empty.</div>
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="role_status">Status <span class="text-danger">*</span></label>
            <select id="role_status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                <option value="active" {{ old('status', $role->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $role->status ?? 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<h6 class="mt-2 mb-1"><i class="bi bi-key me-1"></i>Permissions</h6>
<p class="text-muted small">Tick any permission to grant it to this role — mix modules freely (e.g. front desk + reports).</p>
@error('permissions')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

<div class="accordion" id="permAccordion">
    @foreach($groupedPermissions as $module => $permissions)
    <div class="accordion-item">
        <h2 class="accordion-header" id="perm-head-{{ $loop->index }}">
            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button"
                    data-bs-toggle="collapse" data-bs-target="#perm-body-{{ $loop->index }}"
                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="perm-body-{{ $loop->index }}">
                <strong class="text-capitalize">{{ str_replace('_', ' ', $module) }}</strong>
                <span class="badge bg-secondary ms-2 perm-count" data-module="{{ $loop->index }}">{{ $permissions->whereIn('slug', $checked)->count() }}/{{ $permissions->count() }}</span>
            </button>
        </h2>
        <div id="perm-body-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
             aria-labelledby="perm-head-{{ $loop->index }}" data-bs-parent="#permAccordion">
            <div class="accordion-body">
                <button type="button" class="btn btn-sm btn-outline-secondary mb-2 perm-toggle" data-target="perm-body-{{ $loop->index }}">
                    Toggle all in {{ str_replace('_', ' ', $module) }}
                </button>
                <div class="row">
                    @foreach($permissions as $permission)
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check mb-2">
                            <input class="form-check-input perm-check" type="checkbox" name="permissions[]"
                                   value="{{ $permission->slug }}" id="perm-{{ $permission->id }}"
                                   {{ in_array($permission->slug, $checked, true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="perm-{{ $permission->id }}" title="{{ $permission->slug }}">
                                {{ $permission->name }}
                                <small class="text-muted d-block">{{ $permission->slug }}</small>
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<script>
(function () {
    function refreshCounts() {
        document.querySelectorAll('#permAccordion .accordion-item').forEach(function (item, idx) {
            const boxes = item.querySelectorAll('.perm-check');
            const on = item.querySelectorAll('.perm-check:checked').length;
            const badge = item.querySelector('.perm-count');
            if (badge) badge.textContent = on + '/' + boxes.length;
        });
    }
    document.querySelectorAll('.perm-check').forEach(function (box) {
        box.addEventListener('change', refreshCounts);
    });
    document.querySelectorAll('.perm-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const boxes = document.querySelectorAll('#' + btn.dataset.target + ' .perm-check');
            const allOn = Array.from(boxes).every(function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allOn; });
            refreshCounts();
        });
    });
    const slugInput = document.getElementById('role_slug');
    const nameInput = document.getElementById('role_name');
    if (slugInput && nameInput && !slugInput.value) {
        nameInput.addEventListener('input', function () {
            if (document.activeElement !== slugInput) {
                slugInput.value = nameInput.value.toLowerCase().trim()
                    .replace(/[^a-z0-9\s-]/g, '').replace(/[\s_]+/g, '-').replace(/-+/g, '-');
            }
        });
    }
})();
</script>
