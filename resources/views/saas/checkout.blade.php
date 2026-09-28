@extends('layouts.institute')
@section('title','SaaS Checkout — AccumenAI')
@section('content')
<div class="page-header">
    <div class="page-header-text"><h4 class="page-header-title">SaaS Checkout</h4><p class="page-header-desc">Select package and billing cycle — bKash (Bangladesh, BDT)</p></div>
</div>
@if($institute->country !== 'Bangladesh')<div class="alert alert-danger">bKash checkout rejected — institute country is {{ $institute->country }}, not Bangladesh.</div>@endif
<div class="admin-card p-4">
    <form method="POST" action="{{ route('saas.checkout') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Package</label>
                <select name="package_id" class="form-select" required>
                    @foreach($packages as $pkg) @if($pkg->status==='active')
                        @php $p = $prices[$pkg->id] ?? ['monthly' => $pkg->price_monthly, 'yearly' => $pkg->price_yearly, 'currency' => 'BDT', 'is_localized' => false, 'country_code' => 'BD']; @endphp
                        <option value="{{ $pkg->id }}">{{ $pkg->name }} ({{ $pkg->slug }}) — {{ \App\Support\CurrencyFormatter::format($p['monthly'], $p['currency']) }}/mo / {{ \App\Support\CurrencyFormatter::format($p['yearly'], $p['currency']) }}/yr @if($p['is_localized']) ({{ $p['country_code'] }}) @endif</option>
                    @endif @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Billing Cycle</label>
                <select name="billing_cycle" class="form-select" required>
                    <option value="monthly">Monthly — BDT</option>
                    <option value="yearly">Yearly — BDT</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100" @if($institute->country !== 'Bangladesh') disabled @endif><i class="bi bi-phone"></i> Pay with bKash (BDT)</button>
            </div>
        </div>
        <small class="text-muted d-block mt-2">Price calculated server-side from subscription_packages. Currency BDT. FREE not payable.</small>
    </form>
</div>
@endsection
