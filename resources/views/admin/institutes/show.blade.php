@extends('layouts.admin')

@section('title', $institute->name . ' — AccumenAI')

@section('content')
@php
    $statusBadge = [
        'pending'   => 'text-bg-warning',
        'active'    => 'text-bg-success',
        'suspended' => 'text-bg-danger',
        'expired'   => 'text-bg-dark',
        'cancelled' => 'text-bg-secondary',
    ];
@endphp

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $institute->name }}
            <span class="badge {{ $statusBadge[$institute->status] ?? 'text-bg-secondary' }}">{{ $institute->status }}</span>
        </h4>
        <p class="page-header-desc">{{ $institute->institute_code ?? $institute->slug }}</p>
    </div>
    <div class="page-header-actions">
        @if ($institute->status === 'pending')
            <form class="d-inline" method="POST" action="{{ route('admin.institutes.action', $institute) }}" data-ajax-action="1" data-confirm="Approve {{ $institute->name }}?">
                @csrf
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-success" type="submit"><i class="bi bi-check-circle"></i> Approve</button>
            </form>
        @elseif ($institute->status === 'suspended' || $institute->status === 'expired')
            <form class="d-inline" method="POST" action="{{ route('admin.institutes.action', $institute) }}" data-ajax-action="1" data-confirm="Reactivate {{ $institute->name }}?">
                @csrf
                <input type="hidden" name="action" value="reactivate">
                <button class="btn btn-success" type="submit"><i class="bi bi-play-circle"></i> Reactivate</button>
            </form>
        @elseif ($institute->status === 'active')
            <form class="d-inline" method="POST" action="{{ route('admin.institutes.action', $institute) }}" data-ajax-action="1" data-confirm="Suspend {{ $institute->name }}?">
                @csrf
                <input type="hidden" name="action" value="suspend">
                <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-pause-circle"></i> Suspend</button>
            </form>
        @endif
        @if (is_null($institute->deleted_at))
            <button class="btn btn-outline-danger del-btn-show" type="button" data-name="{{ $institute->name }}" data-action="{{ route('admin.institutes.action', $institute) }}"><i class="bi bi-trash3"></i> Delete</button>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('admin.institutes.index') }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <a class="btn btn-warning" href="{{ route('admin.institutes.modules', $institute) }}">
            <i class="bi bi-puzzle-fill"></i> Module Access
        </a>
        <a class="btn btn-success" href="{{ route('admin.institutes.entitlements.index', $institute) }}">
            <i class="bi bi-clock-history"></i> Entitlements
        </a>
        <a class="btn btn-primary" href="{{ route('admin.institutes.edit', $institute) }}">
            <i class="bi bi-pencil-square"></i> Edit
        </a>
    </div>
</div>

<div class="admin-card mb-4 py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-left:3px solid var(--bs-primary,#0d6efd)">
    @php $currentMode = $institute->settings?->certificate_approval_mode ?? \App\Models\InstituteSetting::CERTIFICATE_APPROVAL_ADMIN; $isAdminControlled = $currentMode === \App\Models\InstituteSetting::CERTIFICATE_APPROVAL_ADMIN; @endphp
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <i class="bi bi-award text-primary fs-5"></i>
        <span class="fw-semibold small">Certificate Approval</span>
        <span class="badge {{ $isAdminControlled ? 'bg-success' : 'bg-warning text-dark' }}" style="font-size:.7rem">{{ $isAdminControlled ? 'Admin Controlled' : 'Super Admin Required' }}</span>
        <span class="text-muted small d-none d-lg-inline">— {{ $isAdminControlled ? 'Institute Admin can issue directly' : 'Super Admin approval required' }}</span>
    </div>
    <form method="POST" action="{{ route('admin.institutes.certificate-approval-mode.update', $institute) }}" id="certApprovalForm" class="d-flex align-items-center gap-2 m-0">
        @csrf
        @method('PUT')
        <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" role="switch" id="certApprovalToggle" name="certificate_approval_mode" value="admin" @checked($isAdminControlled)>
            <label class="form-check-label small fw-medium" for="certApprovalToggle">{{ $isAdminControlled ? 'Admin Controlled' : 'Super Admin' }}</label>
        </div>
        <input type="hidden" name="certificate_approval_mode_fallback" value="super_admin">
    </form>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-building"></i> Institute Details</div>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-4">Short name</dt><dd class="col-sm-8">{{ $institute->short_name ?? '—' }}</dd>
        <dt class="col-sm-4">System code</dt><dd class="col-sm-8">{{ $institute->institute_code ?? '—' }}</dd>
        <dt class="col-sm-4">Industry</dt><dd class="col-sm-8">{{ $institute->industry ? (\App\Support\IndustryRules::label($institute->country ?? '', $institute->industry) ?? $institute->industry) : '—' }}</dd>
        <dt class="col-sm-4">Slug</dt><dd class="col-sm-8">/{{ $institute->slug }}</dd>
        <dt class="col-sm-4">Package</dt><dd class="col-sm-8">{{ $institute->package->name ?? '—' }}</dd>
        <dt class="col-sm-4">Subscription expiry</dt><dd class="col-sm-8">
            {{ $institute->subscription_expiry ? \Illuminate\Support\Carbon::parse($institute->subscription_expiry)->format('d M Y') : '—' }}
        </dd>
        <dt class="col-sm-4">Verified</dt><dd class="col-sm-8">{{ $institute->verified ? 'Yes' : 'No' }}</dd>
        <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $institute->phone ?? '—' }}</dd>
        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $institute->email ?? '—' }}</dd>
        <dt class="col-sm-4">Website</dt><dd class="col-sm-8">{{ $institute->website ?? '—' }}</dd>
        <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $institute->address ?? '—' }}</dd>
        <dt class="col-sm-4">Location</dt><dd class="col-sm-8">
            {{ collect([$institute->adminLevel1?->name, $institute->adminLevel2?->name, $institute->adminLevel3?->name])->filter()->implode(', ') ?: '—' }}
        </dd>
        <dt class="col-sm-4">Founded</dt><dd class="col-sm-8">{{ $institute->founded_year ?? '—' }}</dd>
        <dt class="col-sm-4">Registered</dt><dd class="col-sm-8">{{ $institute->created_at->format('d M Y') }}</dd>
        <dt class="col-sm-4">Institute UID</dt>
        <dd class="col-sm-8">
            <x-uid-with-copy :uid="$institute->uid" label="Institute UID" />
            <small class="text-muted d-block mt-1">Stable 6-character identifier</small>
        </dd>
        <dt class="col-sm-4">Logo</dt>
        <dd class="col-sm-8">
            @if($institute->logo)
                <img src="{{ $institute->logo_url }}" alt="{{ $institute->name }} logo" style="height:48px;max-width:200px;object-fit:contain;border:1px solid #e9ecef;border-radius:6px;background:#fff;padding:4px;">
                <div class="small text-muted mt-1">{{ $institute->logo }}</div>
            @else
                <span class="text-muted">No logo – showing default</span>
                <img src="{{ $institute->logo_url }}" alt="default logo" style="height:32px;opacity:.6;" class="ms-2">
            @endif
        </dd>
    </dl>
</div>

@if ($owner || $staff->isNotEmpty())
    <div class="admin-card">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-people-fill"></i> Staff</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($owner)
                        <tr>
                            <td class="fw-semibold">{{ $owner->name }}</td>
                            <td><span class="badge text-bg-primary">{{ $owner->role->name ?? 'Owner' }}</span></td>
                            <td>{{ $owner->email }}</td>
                            <td><span class="badge text-bg-{{ $owner->status === 'active' ? 'success' : 'secondary' }}">{{ $owner->status }}</span></td>
                            <td class="text-end text-muted small">Owner — cannot delete</td>
                        </tr>
                    @endif
                    @foreach ($staff as $user)
                        <tr>
                            <td>{{ $user->name ?? '—' }}</td>
                            <td>{{ $user->role->name ?? '—' }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge text-bg-{{ $user->status === 'active' ? 'success' : 'secondary' }}">{{ $user->status }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.institutes.staff.destroy', [$institute->id, $user->kind, $user->id]) }}" onsubmit="return confirm('Delete this employee? This will soft-delete the record.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete employee">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if (($hrEmployees ?? collect())->isNotEmpty())
    <div class="admin-card mt-4">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-person-badge"></i> HR Employees</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hrEmployees as $emp)
                        <tr>
                            <td><code>{{ $emp->employee_code }}</code></td>
                            <td>{{ $emp->display_name }}</td>
                            <td>{{ $emp->department?->name ?? '—' }}</td>
                            <td>{{ $emp->designation?->name ?? '—' }}</td>
                            <td><span class="badge text-bg-{{ $emp->employment_status === 'active' ? 'success' : 'secondary' }}">{{ $emp->employment_status }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.institutes.staff.destroy', [$institute->id, 'hr', $emp->id]) }}" onsubmit="return confirm('Delete this HR employee? This will soft-delete the record.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete HR employee">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="modal fade" id="deleteModalShow" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="deleteFormShow" data-ajax-enabled>
            @csrf
            <input type="hidden" name="action" value="delete">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Move to Recycle Bin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">This will remove the institution from the active institution list. It will be moved to the Recycle Bin and can be restored from there if needed. Staff access will be suspended.</div>
                <h6 id="del_name_show" class="fw-bold mb-3"></h6>
                <label class="form-label">Your password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" autocomplete="current-password" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Yes, delete</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function(){
    var toggle = document.getElementById('certApprovalToggle');
    var form = document.getElementById('certApprovalForm');
    if(toggle && form){
        toggle.addEventListener('change', function(){
            var mode = toggle.checked ? 'admin' : 'super_admin';
            var fd = new FormData();
            fd.append('_token', form.querySelector('input[name="_token"]').value);
            fd.append('_method', 'PUT');
            fd.append('certificate_approval_mode', mode);
            toggle.disabled = true;
            fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }})
                .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, body:j}; }); })
                .then(function(res){
                    toggle.disabled = false;
                    var body = res.body||{};
                    if(!res.ok || body.success===false){
                        toggle.checked = !toggle.checked;
                        var msg = (body.message||body.errors && Object.values(body.errors).flat().join(', ')||'Failed to update');
                        if(window.Monetix && Monetix.toast) Monetix.toast(msg,'danger'); else alert(msg);
                        return;
                    }
                    if(window.Monetix && Monetix.toast) Monetix.toast(body.message||'Updated','success');
                    // update badge/label without reload
                    var badge = form.parentElement.querySelector('.badge');
                    var label = form.querySelector('label[for="certApprovalToggle"]');
                    var desc = form.parentElement.querySelector('.text-muted.small');
                    if(badge){
                        badge.textContent = toggle.checked ? 'Admin Controlled' : 'Super Admin Required';
                        badge.className = 'badge ' + (toggle.checked ? 'bg-success' : 'bg-warning text-dark');
                    }
                    if(label) label.textContent = toggle.checked ? 'Admin Controlled' : 'Super Admin';
                    if(desc) desc.textContent = '— ' + (toggle.checked ? 'Institute Admin can issue directly' : 'Super Admin approval required');
                })
                .catch(function(e){
                    toggle.disabled = false; toggle.checked = !toggle.checked;
                    if(window.Monetix && Monetix.toast) Monetix.toast('Network error','danger');
                });
        });
    }
})();
</script>
<script>
(function () {
    var modal = document.getElementById('deleteModalShow');
    var form = document.getElementById('deleteFormShow');
    var nameEl = document.getElementById('del_name_show');
    if (!modal || !form) return;
    document.querySelectorAll('.del-btn-show').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.getAttribute('data-action');
            nameEl.textContent = btn.getAttribute('data-name');
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });
    if (form && window.Monetix && Monetix.request) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            Monetix.request(form.action, { method: 'POST', body: new FormData(form) })
                .then(function (res) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (res && res.errors) {
                        Object.keys(res.errors).forEach(function (key) {
                            var field = form.querySelector('[name="'+key+'"]');
                            if (field) {
                                field.classList.add('is-invalid');
                                var msg = document.createElement('div');
                                msg.className = 'text-danger small mt-1';
                                msg.textContent = (res.errors[key]||[]).join(', ');
                                field.parentNode.insertBefore(msg, field.nextSibling);
                            }
                        });
                        return;
                    }
                    if (res && res.success === false) {
                        if (Monetix.toast) Monetix.toast(res.message||'Could not delete.', 'danger');
                        return;
                    }
                    var inst = bootstrap.Modal.getInstance(modal);
                    if (inst) inst.hide();
                    if (Monetix.toast) Monetix.toast(res && res.message, 'success');
                    if (Monetix.loadPage) Monetix.loadPage('{{ route('admin.institutes.index') }}', { preserveFocus:false });
                    else window.location.href = '{{ route('admin.institutes.index') }}';
                });
        });
    }
})();
</script>

@endpush
@endsection