@php
    $activeTab ??= 'requests';
    $requestsCount ??= 0;
    $certificatesCount ??= 0;
@endphp
<ul class="nav nav-tabs mb-3" role="tablist" data-tab-switch>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'requests' ? 'active' : '' }}" href="{{ route('admin.certificates.requests') }}">
            <i class="bi bi-inbox-fill me-1"></i> Certificate Request
            <span class="badge text-bg-warning badge-soft ms-1">{{ $requestsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'certificates' ? 'active' : '' }}" href="{{ route('admin.certificates.index') }}">
            <i class="bi bi-patch-check-fill me-1"></i> Certificates
            <span class="badge text-bg-success badge-soft ms-1">{{ $certificatesCount }}</span>
        </a>
    </li>
</ul>