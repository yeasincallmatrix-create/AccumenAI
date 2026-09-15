@extends('layouts.standalone')
@php $backUrl = route('admin.home-pages.index'); @endphp
@section('title', 'Create Home Page — Accumen AI')
@section('page_title', 'Create Home Page')

@section('content')
<div class="standalone-heading d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-plus-circle"></i> Create Home Page</h4>
        <p>Add a new landing page and configure its content.</p>
    </div>
    <a href="{{ route('admin.home-pages.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i>Back to List
    </a>
</div>

@if ($errors->any())
<div class="alert alert-danger" data-auto-dismiss>
    <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
</div>
@endif

<form method="POST" action="{{ route('admin.home-pages.store') }}">
    @csrf

    <div class="row g-4">
        {{-- Left Column: Basic Info --}}
        <div class="col-lg-8">
            <div class="admin-card p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Page Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required
                               placeholder="e.g. Default Landing, Education Bangladesh">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug') }}" required
                               placeholder="e.g. default, education">
                        <div class="form-text">Must match a Blade template: <code>home-pages/{slug}</code></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Admin note for this page">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="admin-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-image me-2"></i>Hero Section</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Hero Title <span class="text-danger">*</span></label>
                        <input type="text" name="hero_title" class="form-control" value="{{ old('hero_title', 'The Future of Business & Education Management') }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hero Subtitle</label>
                        <textarea name="hero_subtitle" class="form-control" rows="2">{{ old('hero_subtitle', 'Unifies CRM, HR, Finance, Inventory, Academics and AI assistance into one powerful platform.') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Badge Text</label>
                        <input type="text" name="hero_badge" class="form-control" value="{{ old('hero_badge', 'Trusted by 500+ Institutes Worldwide') }}" placeholder="e.g. Trusted by 500+ Institutes">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CTA Button Text</label>
                        <input type="text" name="hero_cta_text" class="form-control" value="{{ old('hero_cta_text', 'Get Started Free') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CTA Button URL</label>
                        <input type="text" name="hero_cta_url" class="form-control" value="{{ old('hero_cta_url', route('owner.register')) }}" placeholder="Route or URL">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hero Image URL (optional)</label>
                        <input type="text" name="hero_image_url" class="form-control" value="{{ old('hero_image_url') }}" placeholder="https://... or /images/hero.png">
                    </div>
                </div>
            </div>

            <div class="admin-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-code-slash me-2"></i>Sections JSON <small class="text-muted">(Advanced)</small></h6>
                <textarea name="sections_json" class="form-control font-monospace" rows="8" placeholder='{"features": [...], "testimonials": [...], "pricing": [...]}'>{{ old('sections_json') }}</textarea>
                <div class="form-text">Optional JSON to override features, testimonials, pricing sections. Leave empty to use template defaults.</div>
            </div>
        </div>

        {{-- Right Column: Settings --}}
        <div class="col-lg-4">
            <div class="admin-card p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2"></i>Settings</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', '1') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="isActive">Active</label>
                    <div class="form-text">Inactive pages won't be served to visitors.</div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_global" value="1" id="isGlobal">
                    <label class="form-check-label" for="isGlobal">Global Default</label>
                    <div class="form-text">If no country-specific page is found, this page is shown.</div>
                </div>
            </div>

            <div class="admin-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-globe2 me-2"></i>Assign Countries</h6>
                <p class="text-muted small mb-2">Select countries where this home page should be shown. Leave empty if global.</p>

                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="countrySearch" placeholder="Search countries...">
                </div>

                <div class="border rounded p-2" style="max-height:300px;overflow-y:auto" id="countryList">
                    @forelse($countries as $country)
                    <div class="form-check country-item">
                        <input class="form-check-input" type="checkbox" name="countries[]" value="{{ $country->id }}" id="country_{{ $country->id }}">
                        <label class="form-check-label" for="country_{{ $country->id }}">
                            {{ $country->name }} <span class="text-muted">({{ $country->iso2 }})</span>
                        </label>
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No countries found.</p>
                    @endforelse
                </div>

                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.country-item input').forEach(c => c.checked = true)">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.country-item input').forEach(c => c.checked = false)">Clear All</button>
                </div>
            </div>

            <div class="d-grid mt-3">
                <button type="submit" class="btn btn-primary rounded-pill py-2">
                    <i class="bi bi-check-lg me-1"></i>Create Home Page
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
document.getElementById('countrySearch').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('.country-item').forEach(function(el) {
        el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

document.querySelector('input[name="name"]').addEventListener('input', function() {
    var slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
});
</script>
@endsection
