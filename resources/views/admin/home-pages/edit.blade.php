@extends('layouts.standalone')
@php $backUrl = route('admin.home-pages.index'); @endphp
@section('title', 'Edit Home Page — Accumen AI')
@section('page_title', 'Edit: ' . $homePage->name)

@section('content')
<div class="standalone-heading d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4><i class="bi bi-pencil-square"></i> Edit Home Page</h4>
        <p>Update landing page content and country assignments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.home-pages.preview', $homePage) }}" target="_blank" class="btn btn-outline-info rounded-pill px-3">
            <i class="bi bi-eye me-1"></i>Preview
        </a>
        <a href="{{ route('admin.home-pages.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i>Back to List
        </a>
    </div>
</div>

@if ($errors->any())
<div class="alert alert-danger" data-auto-dismiss>
    <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
</div>
@endif

@if(session('status'))
<div class="alert alert-success" data-auto-dismiss>
    <i class="bi bi-check-circle-fill"></i> {{ session('status') }}
</div>
@endif

<form method="POST" action="{{ route('admin.home-pages.update', $homePage) }}">
    @csrf
    @method('PUT')

    <div class="row g-4">
        {{-- Left Column --}}
        <div class="col-lg-8">
            <div class="admin-card p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Page Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $homePage->name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $homePage->slug) }}" required>
                        <div class="form-text">View: <code>home-pages/{{ $homePage->slug }}</code></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $homePage->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="admin-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-image me-2"></i>Hero Section</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Hero Title <span class="text-danger">*</span></label>
                        <input type="text" name="hero_title" class="form-control" value="{{ old('hero_title', $homePage->hero_title) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hero Subtitle</label>
                        <textarea name="hero_subtitle" class="form-control" rows="2">{{ old('hero_subtitle', $homePage->hero_subtitle) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Badge Text</label>
                        <input type="text" name="hero_badge" class="form-control" value="{{ old('hero_badge', $homePage->hero_badge) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CTA Button Text</label>
                        <input type="text" name="hero_cta_text" class="form-control" value="{{ old('hero_cta_text', $homePage->hero_cta_text) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CTA Button URL</label>
                        <input type="text" name="hero_cta_url" class="form-control" value="{{ old('hero_cta_url', $homePage->hero_cta_url) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hero Image URL (optional)</label>
                        <input type="text" name="hero_image_url" class="form-control" value="{{ old('hero_image_url', $homePage->hero_image_url) }}">
                    </div>
                </div>
            </div>

            <div class="admin-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-code-slash me-2"></i>Sections JSON <small class="text-muted">(Advanced)</small></h6>
                <textarea name="sections_json" class="form-control font-monospace" rows="8">{{ old('sections_json', is_array($homePage->sections_json) ? json_encode($homePage->sections_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $homePage->sections_json) }}</textarea>
                <div class="form-text">Optional JSON to override features, testimonials, pricing sections.</div>
            </div>
        </div>

        {{-- Right Column --}}
        <div class="col-lg-4">
            <div class="admin-card p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2"></i>Settings</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', $homePage->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="isActive">Active</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_global" value="1" id="isGlobal" {{ old('is_global', $homePage->is_global) ? 'checked' : '' }}>
                    <label class="form-check-label" for="isGlobal">Global Default</label>
                    <div class="form-text">Fallback when no country-specific page exists.</div>
                </div>
            </div>

            <div class="admin-card p-4 mt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-globe2 me-2"></i>Assign Countries</h6>
                <p class="text-muted small mb-2">Select countries where this home page should be shown.</p>

                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="countrySearch" placeholder="Search countries...">
                </div>

                <div class="border rounded p-2" style="max-height:300px;overflow-y:auto" id="countryList">
                    @forelse($countries as $country)
                    <div class="form-check country-item">
                        <input class="form-check-input" type="checkbox" name="countries[]" value="{{ $country->id }}"
                               id="country_{{ $country->id }}" {{ in_array($country->id, $assignedCountryIds) ? 'checked' : '' }}>
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
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>

            <div class="admin-card p-3 mt-3">
                <h6 class="fw-bold mb-2 small"><i class="bi bi-info-circle me-1"></i>Page Info</h6>
                <div class="text-muted small">
                    <div>Created: {{ $homePage->created_at?->format('d M Y H:i') }}</div>
                    <div>Updated: {{ $homePage->updated_at?->format('d M Y H:i') }}</div>
                    <div>Template: <code>{{ $homePage->slug }}</code></div>
                </div>
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
</script>
@endsection
