@php
    // Non-destructive step navigation — previous / next within the 7-step Academic Core chain
    // Expects: $currentStep (int 1-7), $currentLabel (string), $prevRoute (route name or null), $prevLabel, $nextRoute, $nextLabel
    $currentStep = $currentStep ?? null;
    $currentLabel = $currentLabel ?? null;
    $prevRoute = $prevRoute ?? null;
    $prevLabel = $prevLabel ?? null;
    $nextRoute = $nextRoute ?? null;
    $nextLabel = $nextLabel ?? null;
    $totalSteps = 8;
@endphp
<nav class="step-nav d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3" aria-label="Academic workflow steps">
    <ol class="breadcrumb mb-0 small" style="--bs-breadcrumb-divider: '›';">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('academic.dashboard') }}" class="text-decoration-none">Academic</a></li>
        @if($currentStep && $currentLabel)
            <li class="breadcrumb-item active" aria-current="page">Step {{ $currentStep }}/{{ $totalSteps }} — {{ $currentLabel }}</li>
        @endif
    </ol>
    <div class="d-flex gap-2">
        @if($prevRoute)
            <a href="{{ route($prevRoute) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ $prevLabel ?? 'Previous' }}
            </a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled" style="pointer-events:none;opacity:.5"><i class="bi bi-arrow-left me-1"></i>Previous</span>
        @endif
        @if($nextRoute)
            <a href="{{ route($nextRoute) }}" class="btn btn-sm btn-primary">
                {{ $nextLabel ?? 'Next' }}<i class="bi bi-arrow-right ms-1"></i>
            </a>
        @else
            <span class="btn btn-sm btn-outline-secondary disabled" style="pointer-events:none;opacity:.5">Next<i class="bi bi-arrow-right ms-1"></i></span>
        @endif
    </div>
</nav>
@if($currentStep && $currentLabel)
<div class="progress mb-3" style="height:4px;">
    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ round($currentStep/$totalSteps*100) }}%" aria-valuenow="{{ $currentStep }}" aria-valuemin="0" aria-valuemax="{{ $totalSteps }}"></div>
</div>
@endif
