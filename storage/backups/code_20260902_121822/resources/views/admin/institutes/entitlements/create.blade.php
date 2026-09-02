@extends('layouts.admin')

@section('title', $institute->name . ' — Add Entitlement — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $institute->name }} — Add Module Entitlement</h4>
        <p class="page-header-desc">
            {{ $institute->institute_code ?? $institute->slug }} |
            Industry: <strong>{{ $institute->industry ? (\App\Support\IndustryRules::label($institute->country ?? '', $institute->industry) ?? $institute->industry) : '—' }}</strong>
            @if($institute->sub_industry) / {{ $institute->sub_industry }} @endif |
            Package: <strong>{{ $institute->package->name ?? '—' }}</strong>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.institutes.entitlements.index', $institute) }}"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="admin-card">
    <form method="POST" action="{{ route('admin.institutes.entitlements.store', $institute) }}">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Module <span class="text-danger">*</span></label>
                    <select name="module_key" class="form-select @error('module_key') is-invalid @enderror" required>
                        <option value="">— select module —</option>
                        @foreach($modules as $m)
                            @php $isCompatible = $compatible[$m->key] ?? true; @endphp
                            <option value="{{ $m->key }}" {{ old('module_key')===$m->key ? 'selected' : '' }} {{ !$isCompatible ? 'disabled' : '' }}>
                                {{ $m->name }} ({{ $m->key }}) {{ !$isCompatible ? '— incompatible' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('module_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Incompatible modules are disabled and rejected server-side.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grant/Deny <span class="text-danger">*</span></label>
                    <select name="is_grant" class="form-select @error('is_grant') is-invalid @enderror" required>
                        <option value="1" {{ old('is_grant','1')=='1' ? 'selected' : '' }}>Grant</option>
                        <option value="0" {{ old('is_grant')==='0' ? 'selected' : '' }}>Deny</option>
                    </select>
                    @error('is_grant')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="active" {{ old('status','active')=='active' ? 'selected' : '' }}>active</option>
                        <option value="trialing" {{ old('status')=='trialing' ? 'selected' : '' }}>trialing</option>
                        <option value="pending" {{ old('status')=='pending' ? 'selected' : '' }}>pending</option>
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at') }}" class="form-control @error('starts_at') is-invalid @enderror">
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at') }}" class="form-control @error('ends_at') is-invalid @enderror">
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trial Start</label>
                    <input type="date" name="trial_starts_at" value="{{ old('trial_starts_at') }}" class="form-control @error('trial_starts_at') is-invalid @enderror">
                    @error('trial_starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trial End</label>
                    <input type="date" name="trial_ends_at" value="{{ old('trial_ends_at') }}" class="form-control @error('trial_ends_at') is-invalid @enderror">
                    @error('trial_ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monthly Price</label>
                    <input type="number" step="0.01" min="0" name="monthly_price" value="{{ old('monthly_price') }}" class="form-control @error('monthly_price') is-invalid @enderror">
                    @error('monthly_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Yearly Price</label>
                    <input type="number" step="0.01" min="0" name="yearly_price" value="{{ old('yearly_price') }}" class="form-control @error('yearly_price') is-invalid @enderror">
                    @error('yearly_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Billing Cycle</label>
                    <select name="billing_cycle" class="form-select @error('billing_cycle') is-invalid @enderror">
                        <option value="">—</option>
                        <option value="monthly" {{ old('billing_cycle')=='monthly' ? 'selected' : '' }}>monthly</option>
                        <option value="yearly" {{ old('billing_cycle')=='yearly' ? 'selected' : '' }}>yearly</option>
                        <option value="one_time" {{ old('billing_cycle')=='one_time' ? 'selected' : '' }}>one_time</option>
                    </select>
                    @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Auto Renew</label>
                    <select name="auto_renew" class="form-select @error('auto_renew') is-invalid @enderror">
                        <option value="0" {{ old('auto_renew','0')=='0' ? 'selected' : '' }}>No</option>
                        <option value="1" {{ old('auto_renew')=='1' ? 'selected' : '' }}>Yes</option>
                    </select>
                    @error('auto_renew')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Discount %</label>
                    <input type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ old('discount_percent') }}" class="form-control @error('discount_percent') is-invalid @enderror">
                    @error('discount_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Grant Entitlement</button>
            <a href="{{ route('admin.institutes.entitlements.index', $institute) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
