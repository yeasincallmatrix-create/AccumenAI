@extends('layouts.institute')

@section('title', 'Unlock ' . $feature->name . ' — AccumenAI')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="text-center mb-4">
                <i class="bi bi-lock text-warning" style="font-size: 3rem;"></i>
                <h2 class="mt-3">Unlock {{ $feature->name }}</h2>
                <p class="text-muted">This feature is not included in your current plan.</p>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Your Current Plan</h6>
                    <p class="mb-0 fw-semibold">
                        {{ $currentPackage->name ?? 'No plan' }}
                    </p>
                </div>
            </div>

            <div class="card mb-4 border-primary">
                <div class="card-body">
                    <h6 class="card-title text-muted mb-2">Required Plan</h6>
                    <p class="fw-semibold mb-1">
                        {{ $requiredPackage->name ?? 'Contact Sales' }}
                    </p>
                    @if($requiredPackage)
                        <p class="text-muted mb-0">
                            {{ number_format($requiredPackage->price_monthly, 2) }} BDT / month
                        </p>
                    @endif
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-center">
                @if($requiredPackage)
                    <a href="{{ url('/saas/checkout?package=' . $requiredPackage->slug) }}" class="btn btn-primary btn-lg">
                        Upgrade to {{ $requiredPackage->name }}
                    </a>
                @else
                    <a href="mailto:sales@example.com" class="btn btn-primary btn-lg">
                        Contact Sales
                    </a>
                @endif
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-lg">
                    Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</div>
@endsection
