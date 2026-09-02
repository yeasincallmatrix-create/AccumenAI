@extends('layouts.standalone')

@php $backUrl = route('dashboard'); @endphp

@section('title', 'My Profile — AccumenAI')
@section('page_title', 'My Profile')

@section('content')

<div class="standalone-heading">
    <h4>My Profile</h4>
    <p>View and manage your account details.</p>
</div>

{{-- Profile Card View — Avatar + Name --}}
@php
    $displayName = $user->name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->email ?? $roleLabel);
    $initials = strtoupper(mb_substr($displayName, 0, 1)) . (str_contains($displayName,' ') ? strtoupper(mb_substr(explode(' ', $displayName)[1] ?? '', 0, 1)) : '');
    $initials = mb_substr($initials, 0, 2);
    $photoUrl = null;
    if (!empty($user->photo)) { $photoUrl = asset('storage/'.$user->photo); }
    elseif (!empty($membership->photo ?? null)) { $photoUrl = asset('storage/'.$membership->photo); }
@endphp
<div class="admin-card mb-4 p-0 overflow-hidden">
    <div style="height:90px; background: linear-gradient(135deg, #0D6EFD 0%, #6f42c1 100%);"></div>
    <div class="p-4 pt-0">
        <div class="d-flex flex-column flex-md-row align-items-center align-items-md-end gap-3" style="margin-top:-48px;">
            <div class="position-relative flex-shrink-0">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $displayName }}" class="rounded-circle border border-3 border-white shadow" style="width:96px;height:96px;object-fit:cover;background:#fff;">
                @else
                    <div class="rounded-circle border border-3 border-white shadow d-inline-flex align-items-center justify-content-center fw-bold text-white" style="width:96px;height:96px;font-size:2rem;background: linear-gradient(135deg, #0D6EFD, #6f42c1);">
                        {{ $initials ?: strtoupper(substr($user->email ?? $roleLabel,0,1)) }}
                    </div>
                @endif
                <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle" style="width:16px;height:16px;" title="Active"></span>
            </div>
            <div class="flex-grow-1 text-center text-md-start pb-1">
                <h3 class="h5 mb-1 fw-bold">{{ $displayName }}</h3>
                <div class="text-muted small mb-2">{{ $user->email }}</div>
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                    <span class="badge bg-primary rounded-pill px-3 py-2"><i class="bi bi-person-badge me-1"></i>{{ $roleLabel }}</span>
                    <span class="badge {{ $user->isOwnerAccount() ? 'bg-success' : 'bg-info' }} rounded-pill px-3 py-2">{{ $user->isOwnerAccount() ? 'Owner' : 'Staff' }}</span>
                    @if ($institute)
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2"><i class="bi bi-building me-1"></i>{{ $institute->name }}</span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 pb-1">
                <a href="#edit-profile" class="btn btn-outline-primary btn-sm rounded-pill px-3"><i class="bi bi-pencil me-1"></i>Edit</a>
                <a href="{{ route('account.security') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="bi bi-shield-lock me-1"></i>Security</a>
            </div>
        </div>
        <div class="row g-3 mt-4 pt-3 border-top">
            <div class="col-6 col-md-3 text-center">
                <div class="fw-bold">{{ $user->phone ?: '—' }}</div>
                <div class="text-muted small"><i class="bi bi-telephone me-1"></i>Phone</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div class="fw-bold">{{ $institute->industry ? ucwords(str_replace('_',' ',$institute->industry)) : '—' }}</div>
                <div class="text-muted small"><i class="bi bi-briefcase me-1"></i>Industry</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div class="fw-bold">{{ $user->created_at?->format('d M Y') ?? '—' }}</div>
                <div class="text-muted small"><i class="bi bi-calendar me-1"></i>Joined</div>
            </div>
            <div class="col-6 col-md-3 text-center">
                <div class="fw-bold">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</div>
                <div class="text-muted small"><i class="bi bi-clock me-1"></i>Last Active</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6" id="edit-profile">
        <div class="admin-card mb-3">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-person-badge"></i> Account Details</div>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4">Name</dt>
                <dd class="col-sm-8">{{ $user->name ?? '—' }}</dd>
                <dt class="col-sm-4">Email</dt>
                <dd class="col-sm-8">{{ $user->email }}</dd>
                @if ($user->phone)
                    <dt class="col-sm-4">Phone</dt>
                    <dd class="col-sm-8">{{ $user->phone }}</dd>
                @endif
                <dt class="col-sm-4">Role</dt>
                <dd class="col-sm-8"><span class="badge bg-primary rounded-pill">{{ $roleLabel }}</span></dd>
                <dt class="col-sm-4">Account Type</dt>
                <dd class="col-sm-8">{{ $user->isOwnerAccount() ? 'Owner' : 'Staff' }}</dd>
                <dt class="col-sm-4">Member Since</dt>
                <dd class="col-sm-8">{{ $user->created_at?->format('d M Y') ?? '—' }}</dd>
                @if ($user->last_login_at)
                    <dt class="col-sm-4">Last Login</dt>
                    <dd class="col-sm-8">{{ $user->last_login_at->format('d M Y H:i') }}</dd>
                @endif
            </dl>
        </div>

        @if ($institute)
            <div class="admin-card">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-building"></i> Institute Details</div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8">{{ $institute->name }}</dd>
                    <dt class="col-sm-4">Institute UID</dt>
                    <dd class="col-sm-8">
                        <div class="input-group input-group-sm" style="max-width: 240px;">
                            <input type="text" class="form-control" id="instituteUid" value="{{ $institute->uid }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyUid()">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <small class="text-muted">Stable 6-character identifier</small>
                    </dd>
                    <dt class="col-sm-4">Industry</dt>
                    <dd class="col-sm-8">{{ ucwords(str_replace('_', ' ', $institute->industry ?? '—')) }}</dd>
                    @if (is_object($institute->country))
                        <dt class="col-sm-4">Country</dt>
                        <dd class="col-sm-8">{{ $institute->country->name }}</dd>
                    @endif
                    @if (is_object($institute->package))
                        <dt class="col-sm-4">Package</dt>
                        <dd class="col-sm-8">{{ $institute->package->name }}</dd>
                    @endif
                    @if ($membership?->joining_date)
                        <dt class="col-sm-4">Joined</dt>
                        <dd class="col-sm-8">{{ $membership->joining_date->format('d M Y') }}</dd>
                    @endif
                    @if ($membership?->designation)
                        <dt class="col-sm-4">Designation</dt>
                        <dd class="col-sm-8">{{ $membership->designation }}</dd>
                    @endif
                </dl>
            </div>
            @php $enabledModules = app(\App\Services\ModuleAccessService::class)->getEnabledModules($institute); @endphp
            <div class="admin-card mt-3">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-puzzle"></i> Subscription & Modules</div>
                    @if(auth()->user()->isOwnerAccount())
                        <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('admin.modules.index') }}">Manage</a>
                    @endif
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Package</dt>
                    <dd class="col-sm-8"><span class="badge bg-dark rounded-pill">{{ $institute->package?->name ?? 'FREE' }}</span> <small class="text-muted">({{ $institute->package?->slug ?? 'FREE' }})</small></dd>
                    <dt class="col-sm-4">Industry</dt>
                    <dd class="col-sm-8">{{ $institute->industry ? ucwords(str_replace('_',' ',$institute->industry)) : '—' }} @if($institute->sub_industry) <small class="text-muted">→ {{ ucwords(str_replace('_',' ',$institute->sub_industry)) }}</small> @endif</dd>
                </dl>
                <div class="mt-3">
                    <small class="text-muted">Enabled Modules (backend-enforced via <code>module_access</code>)</small>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @forelse($enabledModules as $mod => $enabled)
                            @if($enabled)
                                <span class="badge bg-success rounded-pill">{{ $mod }}</span>
                            @endif
                        @empty
                            <span class="text-muted small">No modules enabled</span>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-6">
        <div class="admin-card mb-3">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-pencil-square"></i> Edit Profile</div>
            </div>
            <form method="POST" action="{{ route('owner.profile.update') }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="phone">Phone</label>
                    @include('partials.phone', ['name' => 'phone', 'id' => 'owner_phone', 'value' => old('phone', $user->phone), 'country' => $institute->country ?? null])
                    @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="language">Language</label>
                    <select id="language" name="language" class="form-select">
                        <option value="en" {{ $preferredLanguage === 'en' ? 'selected' : '' }}>English</option>
                        <option value="bn" {{ $preferredLanguage === 'bn' ? 'selected' : '' }}>বাংলা</option>
                    </select>
                </div>
                <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </form>
        </div>

        <div class="admin-card">
            <div class="table-toolbar">
                <div class="toolbar-info"><i class="bi bi-key"></i> Change Password</div>
            </div>
            <form method="POST" action="{{ route('owner.profile.password') }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                    @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">New Password</label>
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                    @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
                <x-password-policy field="password" confirm-field="password_confirmation" />
                <button class="btn btn-primary rounded-pill px-4 mt-2" type="submit"><i class="bi bi-key me-1"></i>Update Password</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function copyUid() {
    var uidInput = document.getElementById('instituteUid');
    if (!uidInput) return;
    var value = uidInput.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(function(){
            showCopySuccess();
        }).catch(function(){
            fallbackCopy(uidInput);
        });
    } else {
        fallbackCopy(uidInput);
    }
    function fallbackCopy(el){
        el.select();
        el.setSelectionRange(0, 99999);
        try { document.execCommand('copy'); } catch(e){}
        showCopySuccess();
    }
    function showCopySuccess(){
        var btn = document.querySelector('button[onclick="copyUid()"]');
        if (!btn) return;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        if (window.Monetix && Monetix.toast) Monetix.toast('Institute UID copied to clipboard','success');
        setTimeout(function(){
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    }
}
</script>
@endpush
@endsection
