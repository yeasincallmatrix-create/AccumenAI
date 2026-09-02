@extends('layouts.standalone')

@section('title', $selectedLabel . ' Settings — AccumenAI')
@section('page_title', $selectedLabel . ' Settings')

@section('content')

<div class="standalone-heading d-flex align-items-start justify-content-between gap-3 flex-wrap">
    <div>
        <h4>{{ $selectedLabel }} Settings</h4>
        <p>Industry-specific settings for {{ $selectedLabel }}.</p>
    </div>
    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
        <div class="dropdown country-filter">
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by country">
                <i class="bi bi-globe2"></i>
                <span class="country-filter-label">
                    @if (isset($country) && $country)
                        <img src="{{ mawa_country_flag($country) }}" class="country-flag me-1" alt="" width="18" height="13">
                        {{ config('countries')[$country] ?? $country }}
                    @else
                        {{ mawa_e('dashboard.all_countries') }}
                    @endif
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item {{ ($country ?? null) === null ? 'active' : '' }}"
                       href="{{ route('admin.industry-settings', array_filter(['industry' => $selectedKey, 'sub_industry' => $subIndustry ?? null, 'country' => null], fn ($v) => $v !== null)) }}">
                        <i class="bi bi-globe me-2"></i>{{ mawa_e('dashboard.all_countries') }}
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                @foreach (config('countries') as $value => $label)
                    <li>
                        <a class="dropdown-item {{ ($country ?? null) === $value ? 'active' : '' }}"
                           href="{{ route('admin.industry-settings', array_filter(['industry' => $selectedKey, 'sub_industry' => $subIndustry ?? null, 'country' => $value], fn ($v) => $v !== null)) }}">
                            <img src="{{ mawa_country_flag($value) }}" class="country-flag me-1" alt="" width="18" height="13">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        @if ($selectedKey !== 'all' && count($subIndustries) > 0)
            <div class="dropdown sub-industry-filter">
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by sub industry">
                <i class="bi bi-diagram-2-fill"></i>
                <span class="sub-industry-filter-label">
                    {{ ($subIndustry ?? null) ? ($subIndustries[$subIndustry] ?? $subIndustry) : mawa_e('dashboard.all_sub_industries') }}
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item {{ ($subIndustry ?? null) === null ? 'active' : '' }}"
                       href="{{ route('admin.industry-settings', array_filter(['industry' => $selectedKey, 'country' => $country ?? null, 'sub_industry' => null], fn ($v) => $v !== null)) }}">
                        <i class="bi bi-collection me-2"></i>{{ mawa_e('dashboard.all_sub_industries') }}
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                @foreach ($subIndustries as $subValue => $subLabel)
                    <li>
                        <a class="dropdown-item {{ ($subIndustry ?? null) === $subValue ? 'active' : '' }}"
                           href="{{ route('admin.industry-settings', array_filter(['industry' => $selectedKey, 'country' => $country ?? null, 'sub_industry' => $subValue], fn ($v) => $v !== null)) }}">
                            <i class="bi bi-diagram-2 me-2"></i>{{ $subLabel }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
        <div class="d-flex align-items-center gap-2">
            <label class="text-muted small mb-0" for="industrySelect">Industry:</label>
            <select id="industrySelect" class="form-select form-select-sm" style="max-width: 320px;" aria-label="Select an industry">
                <option value="{{ route('admin.industry-settings', array_filter(['country' => $country ?? null, 'sub_industry' => $subIndustry ?? null])) }}" {{ $selectedKey === 'all' ? 'selected' : '' }}>All Industries</option>
                @foreach ($industries as $key => $label)
                    <option value="{{ route('admin.industry-settings', array_filter(['industry' => $key, 'country' => $country ?? null, 'sub_industry' => $subIndustry ?? null])) }}" {{ $selectedKey === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="settings-layout">

    <div class="settings-nav">
        <div class="settings-nav-title">{{ $selectedLabel }}</div>
        <button class="settings-nav-item settings-tab-btn active" type="button" data-target="pane-general" aria-selected="true">
            <i class="bi bi-sliders"></i>
            <span>General</span>
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-manage-themes" aria-selected="false">
            <i class="bi bi-palette-fill"></i>
            <span>Manage Themes</span>
        </button>
        <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-geo" aria-selected="false">
            <i class="bi bi-globe2"></i>
            <span>Geo Settings</span>
        </button>
    </div>

    <div class="settings-content">
        <div class="admin-card settings-options-card">

            <div class="settings-pane active" id="pane-general">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-info-circle-fill"></i> How it works</div>
                </div>
                <p class="text-muted mb-0">Select an industry from the top-right to view and manage settings specific to that industry. Settings here apply platform-wide for institutes of the chosen industry. Choose "All Industries" to set a fallback used when no industry-specific theme exists, and to manage the platform themes themselves.</p>
            </div>

            <div class="settings-pane" id="pane-manage-themes">
                    <div class="table-toolbar">
                        <div class="toolbar-info"><i class="bi bi-palette-fill"></i> Manage Themes</div>
                    </div>
                    <p class="text-muted">Edit the platform themes used across all industries. The theme marked as default is the fallback used by institutes before any industry default applies.</p>

                    <div class="table-toolbar mt-4">
                        <div class="toolbar-info"><i class="bi bi-palette"></i> Default Color Theme</div>
                    </div>
                    <p class="text-muted">Choose the default theme applied to institutes of <strong>{{ $selectedLabel }}</strong> when they have not set their own theme.</p>
                    <form method="POST" action="{{ route('admin.industry-settings.theme') }}">
                        @csrf
                        <input type="hidden" name="industry_key" value="{{ $selectedKey }}">
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label">Color Theme</label>
                                <div class="row g-3">
                                    @foreach ($themes as $item)
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <label class="theme-option {{ $setting?->theme_slug === $item->slug ? 'selected' : '' }}" data-theme-option>
                                                <input type="radio" name="theme_slug" value="{{ $item->slug }}" {{ $setting?->theme_slug === $item->slug ? 'checked' : '' }}>
                                                <div class="theme-swatch">
                                                    <div class="swatch-primary" style="background:{{ $item->primary_color }}"></div>
                                                    <div class="swatch-secondary" style="background:{{ $item->secondary_color }}"></div>
                                                </div>
                                                <span class="theme-name">{{ $item->name }}</span>
                                                @if ($setting?->theme_slug === $item->slug)
                                                    <i class="bi bi-check-circle-fill theme-check"></i>
                                                @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('theme_slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                @error('industry_key')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Default Theme</button>
                        @if (session('status'))
                            <span class="text-success small ms-2">{{ session('status') }}</span>
                        @endif
                    </form>

                    <hr class="my-4">

                    @foreach ($allThemes as $item)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="d-inline-block rounded-circle" style="width:18px;height:18px;background:{{ $item->primary_color }};border:1px solid rgba(0,0,0,.15)"></span>
                                <span class="fw-semibold">{{ $item->name }}</span>
                                @if ($item->is_default)
                                    <span class="badge bg-success">Default</span>
                                @endif
                                @if ($item->is_dark)
                                    <span class="badge bg-dark">Dark</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.themes.update', $item) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label" for="name_{{ $item->id }}">Theme Name</label>
                                        <input type="text" id="name_{{ $item->id }}" name="name" class="form-control" value="{{ old('name', $item->name) }}" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="primary_{{ $item->id }}">Primary</label>
                                        <input type="color" id="primary_{{ $item->id }}" name="primary_color" class="form-control form-control-color" value="{{ old('primary_color', $item->primary_color) }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="secondary_{{ $item->id }}">Secondary</label>
                                        <input type="color" id="secondary_{{ $item->id }}" name="secondary_color" class="form-control form-control-color" value="{{ old('secondary_color', $item->secondary_color) }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="status_{{ $item->id }}">Status</label>
                                        <select id="status_{{ $item->id }}" name="status" class="form-select">
                                            <option value="active" {{ old('status', $item->status) === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ old('status', $item->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex flex-column justify-content-end gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_dark" value="1" id="dark_{{ $item->id }}" {{ $item->is_dark ? 'checked' : '' }}>
                                            <label class="form-check-label" for="dark_{{ $item->id }}">Dark Mode</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="default_{{ $item->id }}" {{ $item->is_default ? 'checked' : '' }}>
                                            <label class="form-check-label" for="default_{{ $item->id }}">Mark as default</label>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        @error('name')<span class="text-danger small me-2">{{ $message }}</span>@enderror
                                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg"></i> Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>

            <div class="settings-pane" id="pane-geo">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-globe2"></i> Geo Settings</div>
                </div>
                <p class="text-muted">Manage the geography data used across the platform — countries, administrative levels/units and geography package imports.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <a class="text-decoration-none" href="{{ route('admin.geo.index') }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fs-3"><i class="bi bi-globe"></i></span>
                                    <div>
                                        <div class="fw-semibold fs-6">Locations</div>
                                        <div class="text-muted small">Manage countries, levels and the world's modern administrative units.</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-primary small"><i class="bi bi-arrow-right"></i> Open Locations</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a class="text-decoration-none" href="{{ route('admin.geo.imports') }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fs-3"><i class="bi bi-cloud-arrow-up"></i></span>
                                    <div>
                                        <div class="fw-semibold fs-6">Import Geography Package</div>
                                        <div class="text-muted small">Import a geography package for countries and administrative units.</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-primary small"><i class="bi bi-arrow-right"></i> Open Import</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
// Guard: this.value must be a URL string, never a stringified DOM element
document.getElementById('industrySelect').addEventListener('change', function () {
    var v = this && this.value ? String(this.value) : '';
    if (!v || v.indexOf('[object') !== -1 || v.indexOf('%5Bobject') !== -1) { return; }
    window.location.href = v;
});

(function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.settings-tab-btn'));
    var panes = Array.prototype.slice.call(document.querySelectorAll('.settings-pane'));

    function activate(id) {
        tabs.forEach(function (btn) {
            var on = btn.getAttribute('data-target') === id;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(function (pane) {
            pane.classList.toggle('active', pane.id === id);
        });
        if (history.replaceState) {
            history.replaceState(null, '', '#' + id);
        }
    }

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activate(btn.getAttribute('data-target'));
        });
    });

    var hash = window.location.hash;
    if (hash && panes.some(function (p) { return '#' + p.id === hash; })) {
        activate(hash.slice(1));
    }
})();

document.querySelectorAll('[data-theme-option]').forEach(function (label) {
    var input = label.querySelector('input');
    label.addEventListener('click', function () {
        document.querySelectorAll('[data-theme-option]').forEach(function (other) {
            other.classList.remove('selected');
            var check = other.querySelector('.theme-check');
            if (check) check.remove();
        });
        label.classList.add('selected');
        var check = label.querySelector('.theme-check');
        if (!check) {
            check = document.createElement('i');
            check.className = 'bi bi-check-circle-fill theme-check';
            label.appendChild(check);
        }
        input.checked = true;
    });
});
</script>
@endpush