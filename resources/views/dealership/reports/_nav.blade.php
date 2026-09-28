{{-- Shared tab nav for the 5 dealership report screens (Phase 4). --}}
@php
    $dealerTabs = [
        'dealership.reports.sr.index' => 'SR রিপোর্ট',
        'dealership.reports.sales.index' => 'Sales রিপোর্ট',
        'dealership.reports.collection.index' => 'Collection রিপোর্ট',
        'dealership.reports.targets.index' => 'Target রিপোর্ট',
        'dealership.dashboard.index' => 'ড্যাশবোর্ড',
    ];
@endphp
<ul class="nav nav-pills mb-3">
    @foreach ($dealerTabs as $route => $label)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
               href="{{ route($route, request()->query()) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>
