@php
    $activeTab ??= request()->routeIs('academic.analytics.*') ? 'analytics' : (request()->routeIs('academic.dashboard') ? 'academic' : 'dashboard');
    $showAcademic = true;
    if (auth('platform_admin')->check()) {
        $showAcademic = false;
    } elseif (isset($institute) && $institute) {
        // Authoritative domain resolver — do not use industry === 'education'
        $showAcademic = \App\Support\InstituteDomain::isAcademic($institute);
    } elseif (!isset($institute) && isset($isEducation) && !$isEducation) {
        $showAcademic = false;
    } elseif (!isset($institute) && isset($isCleanStudent) && $isCleanStudent) {
        $showAcademic = false;
    } elseif (!isset($institute) && isset($isHospitality) && $isHospitality) {
        $showAcademic = false;
    }
    // View composer provides $workspaceAllowedEducation
    if (isset($workspaceAllowedEducation) && !$workspaceAllowedEducation && $activeTab !== 'academic' && $activeTab !== 'analytics') {
        // keep hidden for non-education
    }
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-grid-fill me-1"></i> Dashboard
        </a>
    </li>
    @if ($showAcademic)
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'academic' ? 'active' : '' }}" href="{{ route('academic.dashboard') }}">
            <i class="bi bi-mortarboard-fill me-1"></i> Academic Dashboard
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'analytics' ? 'active' : '' }}" href="{{ route('academic.analytics.index') }}">
            <i class="bi bi-bar-chart-line-fill me-1"></i> Education Analytics
        </a>
    </li>
    @endif
</ul>
