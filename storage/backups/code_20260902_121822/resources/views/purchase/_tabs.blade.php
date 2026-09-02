@php
    $activeTab ??= request()->routeIs('purchase.orders.*') ? 'orders' : (request()->routeIs('purchase.quotations.*') ? 'quotations' : (request()->routeIs('purchase.returns.*') ? 'returns' : (request()->routeIs('purchase.requests.*') ? 'requests' : 'orders')));
    $ordersCount = $ordersCount ?? (isset($institute) ? \App\Models\PurchaseOrder::where('institute_id', $institute->id)->count() : 0);
    $quotationsCount = $quotationsCount ?? (isset($institute) ? \App\Models\PurchaseQuotation::where('institute_id', $institute->id)->count() : 0);
    $returnsCount = $returnsCount ?? (isset($institute) ? \App\Models\PurchaseReturn::where('institute_id', $institute->id)->count() : 0);
    $requestsCount = $requestsCount ?? (isset($institute) ? \App\Models\PurchaseRequest::where('institute_id', $institute->id)->count() : 0);
@endphp
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'orders' ? 'active' : '' }}" href="{{ route('purchase.orders.index') }}">
            <i class="bi bi-bag-check me-1"></i> Purchase Orders
            <span class="badge text-bg-primary badge-soft ms-1">{{ $ordersCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'quotations' ? 'active' : '' }}" href="{{ route('purchase.quotations.index') }}">
            <i class="bi bi-file-earmark-text me-1"></i> Purchase Quotations
            <span class="badge text-bg-secondary badge-soft ms-1">{{ $quotationsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'requests' ? 'active' : '' }}" href="{{ route('purchase.requests.index') }}">
            <i class="bi bi-clipboard-check me-1"></i> Requests
            <span class="badge text-bg-info badge-soft ms-1">{{ $requestsCount }}</span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $activeTab === 'returns' ? 'active' : '' }}" href="{{ route('purchase.returns.index') }}">
            <i class="bi bi-arrow-return-left me-1"></i> Returns
            <span class="badge text-bg-warning badge-soft ms-1">{{ $returnsCount }}</span>
        </a>
    </li>
</ul>
