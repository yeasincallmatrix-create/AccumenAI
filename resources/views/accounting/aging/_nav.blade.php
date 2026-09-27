{{-- Shared tab nav for the 5 aging report screens (Phase B). --}}
@php
    $agingTabs = [
        'accounting.aging.ar' => 'AR Aging',
        'accounting.aging.ap' => 'AP Aging',
        'accounting.aging.invoice' => 'Invoice Aging',
        'accounting.aging.summary' => 'Summary',
        'accounting.aging.config' => 'Config',
    ];
@endphp
<ul class="nav nav-pills mb-3">
    @foreach ($agingTabs as $route => $label)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"
               href="{{ route($route, request()->query()) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>
