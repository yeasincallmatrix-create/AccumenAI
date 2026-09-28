{{-- Shared tab nav for dealership API screens (Phase 5). --}}
@php
    $apiTabs = [
        'dealership.api.tokens.index' => 'টোকেন',
        'dealership.api.endpoints.index' => 'এন্ডপয়েন্ট',
        'dealership.api.docs.index' => 'ডকস',
        'dealership.push.index' => 'পুশ',
    ];
@endphp
<ul class="nav nav-pills mb-3">
    @foreach ($apiTabs as $route => $label)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
               href="{{ route($route, request()->query()) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>
