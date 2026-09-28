@extends('layouts.institute')

@section('title', 'SaaS Packages — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">SaaS Subscription Packages</h4>
        <p class="page-header-desc">Current Package: <span class="badge bg-primary">{{ $institute->package->name ?? 'FREE' }}</span> | Country: {{ $institute->country ?? '—' }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('saas.checkout.form') }}" class="btn btn-success btn-sm"><i class="bi bi-bag-check"></i> Checkout</a>
    </div>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($institute->country !== 'Bangladesh')
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> bKash SaaS payment is available only for Bangladesh institutes. Your institute country is <strong>{{ $institute->country }}</strong>. Checkout will be rejected server-side.</div>
@endif

<div class="admin-card p-3 mb-3">
    <form method="POST" action="{{ route('saas.country') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-4">
            <label class="form-label">Display prices for country / দেশ</label>
            <select name="country" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($countries as $c)
                    <option value="{{ $c['country_code'] }}" @selected($country === $c['country_code'])>{{ $c['country_code'] }} — {{ $c['currency_code'] }} ({{ $c['region'] }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><span class="text-muted small">Showing: <strong>{{ $country }}</strong></span></div>
    </form>
</div>

<div class="row g-3">
@foreach($packages as $pkg)
    @php $p = $prices[$pkg->id] ?? ['monthly' => $pkg->price_monthly, 'yearly' => $pkg->price_yearly, 'currency' => 'BDT', 'is_localized' => false, 'country_code' => 'BD']; @endphp
    <div class="col-md-4">
        <div class="admin-card p-3 {{ $currentPackageId == $pkg->id ? 'border-primary' : '' }}">
            <h5>{{ $pkg->name }} <span class="badge bg-info ms-1">{{ $pkg->slug }}</span> @if($currentPackageId==$pkg->id)<span class="badge bg-success">Current</span>@endif</h5>
            @if($p['is_localized'])
                <span class="badge bg-success">Local price ({{ $p['country_code'] }})</span>
            @else
                <span class="badge bg-warning text-dark">Base price (BD)</span>
            @endif
            <p class="small text-muted">Monthly: {{ \App\Support\CurrencyFormatter::format($p['monthly'], $p['currency']) }} | Yearly: {{ \App\Support\CurrencyFormatter::format($p['yearly'], $p['currency']) }}</p>
            <p class="small">Max students: {{ $pkg->max_students }}, teachers: {{ $pkg->max_teachers }}</p>
            <form method="POST" action="{{ route('saas.checkout') }}">
                @csrf
                <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                <div class="mb-2">
                    <select name="billing_cycle" class="form-select form-select-sm" required>
                        <option value="monthly">Monthly — {{ \App\Support\CurrencyFormatter::format($p['monthly'], $p['currency']) }}</option>
                        <option value="yearly">Yearly — {{ \App\Support\CurrencyFormatter::format($p['yearly'], $p['currency']) }}</option>
                    </select>
                </div>
                @if($pkg->slug==='FREE')
                    <button type="button" class="btn btn-secondary btn-sm" disabled>FREE — No payment</button>
                @elseif($institute->country !== 'Bangladesh')
                    <button type="button" class="btn btn-secondary btn-sm" disabled>bKash unavailable</button>
                @else
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-phone"></i> Pay with bKash</button>
                @endif
            </form>
        </div>
    </div>
@endforeach
</div>
@endsection
