@extends('layouts.institute')

@php $isAcademic = \App\Support\InstituteDomain::isAcademic($institute ?? null); @endphp
@section('title', mawa_e('settings_page.title') . ' — AccumenAI')

@push('styles')
<style>
  /* HIDE navbar/sidebar only when inside Settings menu */
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('settings_page.title') }} <span class="badge bg-success ms-2" style="font-size:.7rem"><i class="bi bi-circle-fill me-1" style="font-size:.45rem"></i>Active</span></h4>
        <p class="page-header-desc mb-0">{{ mawa_e('settings_page.subtitle') }}</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('settings_page.back') }}
    </a>
</div>

<div class="settings-layout">

    <div class="settings-nav">
        <div class="settings-nav-title">{{ mawa_e('settings_page.title') }}</div>
        <button class="settings-nav-item settings-tab-btn active" type="button" data-target="pane-account" aria-selected="true">
            <i class="bi bi-person-gear"></i>
            <span>{{ mawa_e('settings_page.account') }}</span>
        </button>
        @if ($canManageSettings && $setting)
            <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-branding" aria-selected="false">
                <i class="bi bi-image"></i>
                <span>Branding</span>
            </button>
            <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-general" aria-selected="false">
                <i class="bi bi-sliders"></i>
                <span>{{ mawa_e('settings_page.general') }}</span>
            </button>
            <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-appearance" aria-selected="false">
                <i class="bi bi-palette"></i>
                <span>{{ mawa_e('settings_page.appearance') }}</span>
            </button>
        @endif
<button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-security" aria-selected="false">
                <i class="bi bi-shield-lock"></i>
                <span>{{ mawa_e('settings_page.security') }}</span>
            </button>
            @if ($setting && optional($user)->hasPermission('documents.view'))
                <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-documents" aria-selected="false">
                    <i class="bi bi-folder2-open"></i>
                    <span>Documents</span>
                </button>
            @endif
            @if($isAcademic)
            @if ($canManageSettings && $setting && (($institute?->industry ?? '') === 'education'))
                <button class="settings-nav-item settings-tab-btn" type="button" data-target="pane-academic-setting" aria-selected="false">
                    <i class="bi bi-mortarboard"></i>
                    <span>Academic Settings</span>
                </button>
            @endif
            @endif

    </div>

    <div class="settings-content">
        <div class="admin-card settings-options-card">

            <div class="settings-pane active" id="pane-account">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-person-gear"></i> {{ mawa_e('settings_page.account') }}</div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ mawa_e('settings_page.account_name') }}</dt><dd class="col-sm-8">{{ $user->name ?? '' }}</dd>
                    <dt class="col-sm-4">{{ mawa_e('settings_page.account_email') }}</dt><dd class="col-sm-8">{{ $user->email }}</dd>
                    <dt class="col-sm-4">{{ mawa_e('settings_page.account_role') }}</dt><dd class="col-sm-8">{{ $roleLabel }}</dd>
                    <dt class="col-sm-4">Your UID</dt><dd class="col-sm-8"><x-uid-with-copy :uid="$user->uid ?? auth()->user()->uid" label="Your UID" /></dd>
                    @if(isset($institute) && $institute)
                    <dt class="col-sm-4">Institute UID</dt><dd class="col-sm-8"><x-uid-with-copy :uid="$institute->uid" label="Institute UID" /></dd>
                    @endif
                </dl>

                <hr>

                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-key"></i> {{ mawa_e('settings_page.change_password') }}</div>
                </div>
                <form method="POST" action="{{ route('settings.password') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                            @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Update Password</button>
                </form>
            </div>

            @if ($canManageSettings && $setting)
                <div class="settings-pane" id="pane-appearance">
                    <div class="table-toolbar">
                        <div class="toolbar-info"><i class="bi bi-palette"></i> {{ mawa_e('settings_page.theme_heading') }}</div>
                    </div>
                    <form method="POST" action="{{ route('settings.appearance.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label">Color Theme</label>
                                <div class="row g-3">
                                    @foreach ($themes as $item)
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <label class="theme-option {{ $setting->theme === $item->slug ? 'selected' : '' }}" data-theme-option>
                                                <input type="radio" name="theme_slug" value="{{ $item->slug }}" {{ $setting->theme === $item->slug ? 'checked' : '' }}>
                                                <div class="theme-swatch">
                                                    <div class="swatch-primary" style="background:{{ $item->primary_color }}"></div>
                                                    <div class="swatch-secondary" style="background:{{ $item->secondary_color }}"></div>
                                                </div>
                                                <span class="theme-name">{{ $item->name }}</span>
                                                @if ($setting->theme === $item->slug)
                                                    <i class="bi bi-check-circle-fill theme-check"></i>
                                                @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('theme_slug')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="sidebar_color">{{ mawa_e('settings_page.sidebar_color') }}</label>
                                <input type="color" id="sidebar_color" name="sidebar_color" class="form-control form-control-color"
                                       value="{{ $setting->sidebar_color ?? '#FFFFFF' }}">
                                @error('sidebar_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tall Navigation</label>
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" id="tall_navigation" name="tall_navigation" value="1" @checked($setting->tall_navigation)>
                                    <label class="form-check-label small text-muted" for="tall_navigation">
                                        Topbar stretches full width, sidebar sits below it
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ mawa_e('settings_page.save') }}</button>
                    </form>
                </div>

                <div class="settings-pane" id="pane-branding">
                    <div class="table-toolbar">
                        <div class="toolbar-info"><i class="bi bi-image"></i> Institute Logo</div>
                        <span class="text-muted small">Global — shown on certificates, marksheets, receipts, quotations & profile</span>
                    </div>
                    @if (session('success') || session('status'))
                        <div class="alert alert-success py-2 small">{{ session('success') ?? session('status') }}</div>
                    @endif
                    @if ($errors->has('logo'))
                        <div class="alert alert-danger py-2 small">{{ $errors->first('logo') }}</div>
                    @endif
                    <div class="card border-0 shadow-none" style="background:transparent;">
                        <div class="card-body p-0">
                            <div class="row g-4 align-items-center">
                                <div class="col-md-3 text-center">
                                    <img src="{{ $institute?->logo_url ?? asset('images/default-logo.png') }}" alt="Institute Logo" class="img-fluid rounded border bg-white p-2" style="max-height:150px; max-width:100%; object-fit:contain;">
                                    <div class="small text-muted mt-2">{{ $institute?->name ?? 'Institute' }}</div>
                                    @if($institute?->logo_path ?? $institute?->logo)
                                        <div class="small text-muted mt-1" style="word-break:break-all;">{{ $institute->logo_path ?? $institute->logo }}</div>
                                    @endif
                                </div>
                                <div class="col-md-9">
                                    <form action="{{ route('settings.logo.upload') }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="logo" class="form-label">Upload Logo</label>
                                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" required>
                                            <small class="text-muted">Recommended: 200x200px, max 2MB. JPEG/PNG/GIF/SVG/WEBP. Stored per tenant in storage/app/public/institutes/{id}/</small>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload me-1"></i>Upload Logo</button>
                                        @if($institute?->logo_path ?? $institute?->logo)
                                            <button type="button" class="btn btn-outline-danger ms-2" onclick="event.preventDefault(); if(confirm('Remove logo?')) document.getElementById('remove-logo-form').submit();"><i class="bi bi-trash me-1"></i>Remove Logo</button>
                                        @endif
                                    </form>
                                    @if($institute?->logo_path ?? $institute?->logo)
                                        <form id="remove-logo-form" action="{{ route('settings.logo.remove') }}" method="POST" style="display:none;">
                                            @csrf @method('DELETE')
                                        </form>
                                    @endif
                                    <div class="mt-3 small text-muted">
                                        <i class="bi bi-info-circle me-1"></i>Logo appears globally: certificate templates 1-3, marksheet PDF, receipts/vouchers/quotations, Business Profile & topbar/sidebar branding. Falls back to <code>images/default-logo.png</code> when none uploaded. Run <code>php artisan storage:link</code> if images not visible.
                                    </div>
                                    <div class="mt-2 d-flex gap-2">
                                        <a href="{{ route('business.profile') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-building me-1"></i>View Business Profile (also has upload)</a>
                                        <a href="{{ route('institute.logo.upload') }}" onclick="event.preventDefault(); document.querySelector('#pane-branding form').requestSubmit();" class="btn btn-sm btn-outline-primary d-none">Legacy upload alias</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-pane" id="pane-general">
                    <div class="table-toolbar">
                        <div class="toolbar-info"><i class="bi bi-sliders"></i> {{ mawa_e('settings_page.general') }}</div>
                    </div>
                    <form method="POST" action="{{ route('settings.general.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="timezone">{{ mawa_e('settings_page.timezone') }}</label>
                                <select id="timezone" name="timezone" class="form-select form-select-sm">
                                    @php
                                        $timezones = collect(\DateTimeZone::listIdentifiers())->sort()->mapWithKeys(function ($tz) {
                                            $offset = (new DateTimeZone($tz))->getOffset(new DateTime('now', new DateTimeZone($tz)));
                                            $sign = $offset >= 0 ? '+' : '-';
                                            $hours = intdiv(abs($offset), 3600);
                                            $minutes = intdiv(abs($offset) % 3600, 60);
                                            $label = sprintf('(UTC%s%02d:%02d) %s', $sign, $hours, $minutes, $tz);
                                            return [$tz => $label];
                                        });
                                    @endphp
                                    @foreach ($timezones as $tz => $label)
                                        <option value="{{ $tz }}" @selected($setting->timezone === $tz)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="language">{{ mawa_e('settings_page.language') }}</label>
                                <select id="language" name="language" class="form-select form-select-sm">
                                    <option value="bn" @selected($setting->language === 'bn')>{{ mawa_e('settings_page.language_bn') }}</option>
                                    <option value="en" @selected($setting->language === 'en')>{{ mawa_e('settings_page.language_en') }}</option>
                                </select>
                                @error('language')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ mawa_e('settings_page.save') }}</button>
                    </form>
                </div>
            @endif

            <div class="settings-pane" id="pane-security">
                @include('security._panel')
            </div>

            @if ($setting && optional($user)->hasPermission('documents.view'))
                <div class="settings-pane" id="pane-documents">
                    @include('documents._panel', ['entityType' => 'institute', 'entityId' => $setting->institute_id])
                </div>
            @endif

            @if($isAcademic)
            @if ($canManageSettings && $setting && (($institute?->industry ?? '') === 'education'))
                <div class="settings-pane" id="pane-academic-setting">
                    <div class="table-toolbar">
                        <div class="toolbar-info"><i class="bi bi-mortarboard"></i> Academic Settings <span class="badge bg-info ms-2" style="font-size:.65rem">Education only</span></div>
                        <span class="text-muted small">All academic options as tabs</span>
                    </div>
                    <p class="text-muted small mb-3">Industry: <strong>{{ $institute->industry }}</strong> @if($institute->sub_industry) · {{ $institute->sub_industry }} @endif — these tabs are available for all education industries.</p>

                    {{-- Payroll style horizontal tab — content loads right below, no page navigation --}}
                    <ul class="nav nav-tabs mb-3" role="tablist" id="academicSettingTabs">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="tab-structure-btn" data-bs-toggle="tab" data-bs-target="#tab-structure" type="button" role="tab" aria-controls="tab-structure" aria-selected="true" data-url="{{ route('settings.academic.index') }}"><i class="bi bi-diagram-3 me-1"></i>Structure</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tab-placements-btn" data-bs-toggle="tab" data-bs-target="#tab-placements" type="button" role="tab" aria-controls="tab-placements" aria-selected="false" data-url="{{ route('settings.academic.placements.index') }}"><i class="bi bi-person-lines-fill me-1"></i>Placements</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tab-assessments-btn" data-bs-toggle="tab" data-bs-target="#tab-assessments" type="button" role="tab" aria-controls="tab-assessments" aria-selected="false" data-url="{{ route('settings.academic.assessments.index') }}"><i class="bi bi-clipboard-check me-1"></i>Assessments</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tab-aggregations-btn" data-bs-toggle="tab" data-bs-target="#tab-aggregations" type="button" role="tab" aria-controls="tab-aggregations" aria-selected="false" data-url="{{ route('settings.academic.aggregations.index') }}"><i class="bi bi-pie-chart me-1"></i>Aggregations</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tab-grading-btn" data-bs-toggle="tab" data-bs-target="#tab-grading" type="button" role="tab" aria-controls="tab-grading" aria-selected="false" data-url="{{ route('settings.academic.grading.index') }}"><i class="bi bi-award me-1"></i>Grade Scales</button></li>
                        @if (($canPromote ?? false) || ($user instanceof \App\Models\InstituteUser ? $user->hasPermission('promotion.manage') : \App\Support\Workspace::membership()?->hasPermission('promotion.manage')))
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tab-promotions-btn" data-bs-toggle="tab" data-bs-target="#tab-promotions" type="button" role="tab" aria-controls="tab-promotions" aria-selected="false" data-url="{{ route('settings.academic.promotions.index') }}"><i class="bi bi-arrow-up-circle me-1"></i>Promotions</button></li>
                        @endif
                        <li class="nav-item" role="presentation"><button class="nav-link" id="tab-results-btn" data-bs-toggle="tab" data-bs-target="#tab-results" type="button" role="tab" aria-controls="tab-results" aria-selected="false" data-url="{{ route('settings.academic.final-results.index') }}"><i class="bi bi-broadcast me-1"></i>Final Results</button></li>
                    </ul>

                    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white" style="margin-top:-1rem; min-height:260px;">
                        <div class="tab-pane fade show active" id="tab-structure" role="tabpanel" aria-labelledby="tab-structure-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Structure…</div></div></div>
                        <div class="tab-pane fade" id="tab-placements" role="tabpanel" aria-labelledby="tab-placements-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.placements.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Placements…</div></div></div>
                        <div class="tab-pane fade" id="tab-assessments" role="tabpanel" aria-labelledby="tab-assessments-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.assessments.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Assessments…</div></div></div>
                        <div class="tab-pane fade" id="tab-aggregations" role="tabpanel" aria-labelledby="tab-aggregations-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.aggregations.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Aggregations…</div></div></div>
                        <div class="tab-pane fade" id="tab-grading" role="tabpanel" aria-labelledby="tab-grading-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.grading.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Grade Scales…</div></div></div>
                        @if (($canPromote ?? false) || ($user instanceof \App\Models\InstituteUser ? $user->hasPermission('promotion.manage') : \App\Support\Workspace::membership()?->hasPermission('promotion.manage')))
                        <div class="tab-pane fade" id="tab-promotions" role="tabpanel" aria-labelledby="tab-promotions-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.promotions.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Promotions…</div></div></div>
                        @endif
                        <div class="tab-pane fade" id="tab-results" role="tabpanel" aria-labelledby="tab-results-btn"><div class="academic-tab-loader" data-url="{{ route('settings.academic.final-results.index') }}"><div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading Final Results…</div></div></div>
                        <div class="border-top mt-3 pt-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div><i class="bi bi-diagram-3 me-1"></i><strong>Learning Structure</strong> <span class="badge bg-light border text-muted ms-1">N-level</span></div>
                                <a href="{{ route('academic.structure.settings') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Manage Learning Structure</a>
                            </div>
                            <p class="text-muted small mb-0 mt-1">Configure the generic N-level hierarchy (School → Section, University → Semester, etc.) via the dedicated Learning Structure settings.</p>
                        </div>
                    </div>
                    <script>
                    (function(){
                        var loaded = {};
                        function extractContent(html){
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            // Prefer main content or admin-card area; fallback to body
                            var main = doc.querySelector('main.content') || doc.querySelector('.settings-content') || doc.querySelector('.content') || doc.body;
                            if(!main) return '<div class=\"alert alert-warning\">No content</div>';
                            // Remove layout chrome if present
                            var clone = main.cloneNode(true);
                            // Strip topbar/sidebar if leaked
                            clone.querySelectorAll('.topbar,.sidebar,.sidebar-backdrop,.skeleton-loader').forEach(function(e){ e.remove(); });
                            return clone.innerHTML;
                        }
                        function loadPane(pane){
                            var loader = pane.querySelector('.academic-tab-loader');
                            if(!loader) return;
                            var url = loader.getAttribute('data-url');
                            if(!url || loaded[url]) return;
                            loaded[url]=true;
                            fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}, credentials:'same-origin'})
                                .then(function(r){ if(!r.ok) throw new Error(r.status); return r.text(); })
                                .then(function(html){ loader.innerHTML = extractContent(html); })
                                .catch(function(e){ loader.innerHTML = '<div class=\"alert alert-danger small\">Failed to load: '+e.message+' <a href=\"'+url+'\" class=\"alert-link\">Open page</a></div>'; loaded[url]=false; });
                        }
                        document.querySelectorAll('#academicSettingTabs [data-bs-toggle=\"tab\"]').forEach(function(btn){
                            btn.addEventListener('shown.bs.tab', function(e){
                                var target = e.target.getAttribute('data-bs-target');
                                var pane = document.querySelector(target);
                                if(pane) loadPane(pane);
                            });
                        });
                        // Load initial active tab
                        var active = document.querySelector('#pane-academic-setting .tab-pane.active');
                        if(active) loadPane(active);
                    })();
                    </script>


                </div>
            @endif
            @endif

        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
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

(function () {
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
})();
</script>
@endpush