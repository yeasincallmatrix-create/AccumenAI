@extends('layouts.admin')

@section('title', 'Edit ' . $institute->name . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Edit Institute</h4>
        <p class="page-header-desc">{{ $institute->name }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('admin.institutes.show', $institute) }}">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<form method="POST" action="{{ route('admin.institutes.update', $institute) }}">
    @csrf
    @method('PUT')

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-building"></i> Profile</div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $institute->name) }}" required>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="short_name">Short name</label>
                <input type="text" id="short_name" name="short_name" class="form-control" value="{{ old('short_name', $institute->short_name) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="slug">Slug <span class="text-danger">*</span></label>
                <input type="text" id="slug" name="slug" class="form-control" value="{{ old('slug', $institute->slug) }}" required>
                @error('slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="institute_code">System code</label>
                <input type="text" id="institute_code" name="institute_code" class="form-control" value="{{ old('institute_code', $institute->institute_code) }}">
                @error('institute_code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="founded_year">Founded year</label>
                <input type="number" id="founded_year" name="founded_year" class="form-control" value="{{ old('founded_year', $institute->founded_year) }}">
                @error('founded_year')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="country">Country</label>
                <select id="country" name="country" class="form-select">
                    <option value="">— Select —</option>
                    @foreach (config('countries') as $value => $label)
                        <option value="{{ $value }}" @selected(old('country', $institute->country) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('country')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="industry">Industry</label>
                <select id="industry" name="industry" class="form-select">
                    <option value="">— Select —</option>
                    @foreach ($industries as $value => $label)
                        <option value="{{ $value }}" @selected(old('industry', $institute->industry) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('industry')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $institute->description) }}</textarea>
            </div>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-envelope"></i> Contact</div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'phone', 'value' => old('phone', $institute->phone), 'country' => $institute->country ?? null])
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $institute->email) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="website">Website</label>
                <input type="text" id="website" name="website" class="form-control" value="{{ old('website', $institute->website) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address">Address</label>
                <input type="text" id="address" name="address" class="form-control" value="{{ old('address', $institute->address) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="division">Division</label>
                <input type="text" id="division" name="division" class="form-control" value="{{ old('division', $institute->division) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="district">District</label>
                <input type="text" id="district" name="district" class="form-control" value="{{ old('district', $institute->district) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="upazila">Upazila</label>
                <input type="text" id="upazila" name="upazila" class="form-control" value="{{ old('upazila', $institute->upazila) }}">
            </div>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-journal-check"></i> Subscription</div>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="package_id">Package</label>
                <select id="package_id" name="package_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($packages as $pkg)
                        <option value="{{ $pkg->id }}" @selected(old('package_id', $institute->package_id) == $pkg->id)>{{ $pkg->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="subscription_expiry">Subscription expiry</label>
                <input type="date" id="subscription_expiry" name="subscription_expiry" class="form-control"
                       value="{{ old('subscription_expiry', $institute->subscription_expiry ? \Illuminate\Support\Carbon::parse($institute->subscription_expiry)->format('Y-m-d') : '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(old('status', $institute->status) === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="verified" name="verified" value="1"
                           @checked(old('verified', $institute->verified))>
                    <label class="form-check-label" for="verified">Verified</label>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-robot"></i> AI Access</div>
        </div>
        @php $aiConfig = $institute->settings?->ai_config ?? []; @endphp
        <div class="row g-3">
            <div class="col-md-4">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="ai_enabled" name="ai_enabled" value="1"
                           @checked($aiConfig['enabled'] ?? false)>
                    <label class="form-check-label" for="ai_enabled">Enable AI for this institute</label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ai_daily_limit">Daily request limit (0 = unlimited)</label>
                <input type="number" id="ai_daily_limit" name="ai_daily_limit" class="form-control" min="0"
                       value="{{ $aiConfig['daily_limit'] ?? 0 }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ai_monthly_limit">Monthly request limit (0 = unlimited)</label>
                <input type="number" id="ai_monthly_limit" name="ai_monthly_limit" class="form-control" min="0"
                       value="{{ $aiConfig['monthly_limit'] ?? 0 }}">
            </div>
            <div class="col-12">
                <label class="form-label">Enabled features</label>
                @php
                    $aiFeatures = $aiConfig['features'] ?? ['assistant'];
                    $featureOptions = [
                        'assistant' => 'Assistant chat',
                        'analytics' => 'Analytics',
                        'content'   => 'Content',
                        'reports'   => 'Reports',
                        'automation'=> 'Automation',
                    ];
                @endphp
                <div class="d-flex flex-wrap gap-4">
                    @foreach ($featureOptions as $key => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ai_features_{{ $key }}"
                                   name="ai_features[]" value="{{ $key }}"
                                   @checked(in_array($key, $aiFeatures, true))>
                            <label class="form-check-label" for="ai_features_{{ $key }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Changes</button>
</form>
@endsection