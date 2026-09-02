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

<div class="row g-3">
@foreach($packages as $pkg)
    <div class="col-md-4">
        <div class="admin-card p-3 {{ $currentPackageId == $pkg->id ? 'border-primary' : '' }}">
            <h5>{{ $pkg->name }} <span class="badge bg-info ms-1">{{ $pkg->slug }}</span> @if($currentPackageId==$pkg->id)<span class="badge bg-success">Current</span>@endif</h5>
            <p class="small text-muted">Monthly: {{ number_format($pkg->price_monthly,2) }} BDT | Yearly: {{ number_format($pkg->price_yearly,2) }} BDT</p>
            <p class="small">Max students: {{ $pkg->max_students }}, teachers: {{ $pkg->max_teachers }}</p>
            <form method="POST" action="{{ route('saas.checkout') }}">
                @csrf
                <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                <div class="mb-2">
                    <select name="billing_cycle" class="form-select form-select-sm" required>
                        <option value="monthly">Monthly — {{ number_format($pkg->price_monthly,2) }} BDT</option>
                        <option value="yearly">Yearly — {{ number_format($pkg->price_yearly,2) }} BDT</option>
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
