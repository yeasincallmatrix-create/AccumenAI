@extends('layouts.admin')

@section('title', 'Emergency Override — ' . $institute->name . ' — AccumenAI')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.institutes.modules', $institute) }}" class="text-decoration-none">Module Access</a></li>
        <li class="breadcrumb-item active">Emergency Override</li>
    </ol>
</nav>

<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title text-danger">
            <i class="bi bi-exclamation-octagon-fill"></i> Emergency Override — {{ $institute->name }}
        </h4>
        <p class="page-header-desc">
            Industry: <strong>{{ $institute->industry ?? '—' }}</strong>
            | Sub-Category: <strong>{{ $institute->subcategory_key ?? '—' }}</strong>
            | Country: <strong>{{ $institute->country_code ?? 'BD' }}</strong>
            | Package: <strong>{{ $institute->package->name ?? '—' }}</strong>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.modules', $institute) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="alert alert-danger border-3 border-danger">
    <h6 class="alert-heading"><i class="bi bi-shield-exclamation me-1"></i> HARD BOUNDARY VIOLATION</h6>
    <p class="mb-1 small">
        This action bypasses a <strong>hard boundary</strong> (industry or country layer) that normal admins cannot touch.
        The override is <strong>time-limited</strong>, fully audited, and an <strong>alert email</strong> is sent.
    </p>
    <ul class="small mb-0">
        <li><strong>🔒 Industry boundary</strong> — module belongs to another industry (Layer 7)</li>
        <li><strong>🔒 Country boundary</strong> — tax module not allowed for this country (Layer 8)</li>
    </ul>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error)
            <div class="small">{{ $error }}</div>
        @endforeach
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $totalBlocked = count($blocked['industry']) + count($blocked['country']);
@endphp

<div class="row g-4">
    <div class="col-lg-7">
        <div class="admin-card p-4">
            <h6 class="mb-3"><i class="bi bi-key-fill text-danger me-1"></i> Apply Emergency Override</h6>

            @if ($totalBlocked === 0)
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-1"></i> No hard-boundary blocks for this tenant — no override needed.
                </div>
            @else
            <form method="POST" action="{{ route('super-admin.institutes.emergency-override.store', $institute) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Override Layer <span class="text-danger">*</span></label>
                    <select name="override_layer" id="overrideLayer" class="form-select" required
                            onchange="updateModuleOptions()">
                        <option value="">Select layer…</option>
                        <option value="industry" {{ old('override_layer') === 'industry' ? 'selected' : '' }}>
                            🔒 Industry ({{ count($blocked['industry']) }} blocked)
                        </option>
                        <option value="country" {{ old('override_layer') === 'country' ? 'selected' : '' }}>
                            🔒 Country ({{ count($blocked['country']) }} blocked)
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Blocked Module <span class="text-danger">*</span></label>
                    <select name="module_key" id="moduleKey" class="form-select" required>
                        <option value="">— select layer first —</option>
                    </select>
                    <div class="form-text">Only modules actually blocked by the selected hard boundary are listed.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Justification (min. 50 characters) <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="4" minlength="50" maxlength="1000" required
                              placeholder="Explain why this tenant requires bypassing the hard boundary (regulatory, migration, contractual…)">{{ old('reason') }}</textarea>
                    <div class="form-text"><span id="reasonCount">{{ mb_strlen(old('reason', '')) }}</span>/50 min</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">2FA Code <span class="text-danger">*</span></label>
                        <input type="text" name="two_factor_code" class="form-control" inputmode="numeric"
                               pattern="[0-9]{6}" maxlength="6" required
                               placeholder="6-digit code" value="{{ old('two_factor_code') }}">
                        <div class="form-text">From your authenticator app (required).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Duration <span class="text-danger">*</span></label>
                        <select name="expiry_days" class="form-select" required>
                            <option value="1" {{ old('expiry_days') == 1 ? 'selected' : '' }}>24 hours</option>
                            <option value="7" {{ old('expiry_days', 7) == 7 ? 'selected' : '' }}>7 days (default)</option>
                            <option value="30" {{ old('expiry_days') == 30 ? 'selected' : '' }}>30 days</option>
                            <option value="365" {{ old('expiry_days') == 365 ? 'selected' : '' }}>365 days</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Typed Confirmation <span class="text-danger">*</span></label>
                    <input type="text" name="confirmation_text" class="form-control" required
                           placeholder="Type: I UNDERSTAND THE RISK" value="{{ old('confirmation_text') }}">
                    <div class="form-text">Type exactly: <code>I UNDERSTAND THE RISK</code></div>
                </div>

                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('Apply EMERGENCY OVERRIDE? This bypasses a hard boundary and will be audited + emailed.');">
                    <i class="bi bi-exclamation-octagon-fill me-1"></i> Apply Emergency Override
                </button>
            </form>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card p-4 mb-4">
            <h6 class="mb-3"><i class="bi bi-list-check me-1"></i> Blocked Modules ({{ $totalBlocked }})</h6>
            @if ($totalBlocked === 0)
                <p class="text-muted small mb-0">None.</p>
            @else
                @if (count($blocked['industry']))
                    <div class="mb-2">
                        <span class="badge bg-danger mb-1">🔒 Industry</span>
                        @foreach ($blocked['industry'] as $key)
                            <div><code>{{ $key }}</code></div>
                        @endforeach
                    </div>
                @endif
                @if (count($blocked['country']))
                    <div class="mb-2">
                        <span class="badge bg-danger mb-1">🔒 Country</span>
                        @foreach ($blocked['country'] as $key)
                            <div><code>{{ $key }}</code></div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        <div class="admin-card p-4">
            <h6 class="mb-3"><i class="bi bi-clock-history me-1"></i> Active Overrides ({{ $activeOverrides->count() }})</h6>
            @forelse ($activeOverrides as $ov)
                <div class="border-bottom pb-2 mb-2">
                    <code>{{ $ov->module_key }}</code>
                    <span class="badge bg-warning text-dark">{{ $ov->override_layer }}</span>
                    <br><small class="text-muted">
                        expires {{ $ov->expires_at ?? 'never' }}
                        @if ($ov->two_factor_verified)
                            · <i class="bi bi-patch-check-fill text-success"></i> 2FA
                        @endif
                    </small>
                    <br><small class="text-muted fst-italic">{{ \Illuminate\Support\Str::limit($ov->reason, 90) }}</small>
                </div>
            @empty
                <p class="text-muted small mb-0">No active overrides.</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
const blocked = @json($blocked);
const names = @json(\App\Models\ModuleRegistry::where('status', 'active')->pluck('name', 'key'));

function updateModuleOptions() {
    const layer = document.getElementById('overrideLayer').value;
    const select = document.getElementById('moduleKey');
    select.innerHTML = '<option value="">— select layer —</option>';
    (blocked[layer] || []).forEach(key => {
        const opt = document.createElement('option');
        opt.value = key;
        opt.textContent = key + (names[key] ? ' — ' + names[key] : '');
        select.appendChild(opt);
    });
}
document.addEventListener('DOMContentLoaded', updateModuleOptions);

const reasonBox = document.querySelector('textarea[name="reason"]');
if (reasonBox) {
    reasonBox.addEventListener('input', () => {
        document.getElementById('reasonCount').textContent = reasonBox.value.length;
    });
}
</script>
@endpush
@endsection
