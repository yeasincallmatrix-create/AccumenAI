@extends('layouts.standalone')

@section('title', 'Final Results — AccumenAI')
@section('page_title', 'Final Results')

@php
    $statusBadge = [
        'draft'    => 'text-bg-secondary',
        'active'   => 'text-bg-success',
        'archived' => 'text-bg-light',
    ];
    $cycleBadge = [
        'review'    => ['Review', 'text-bg-secondary'],
        'approved'  => ['Approved', 'text-bg-info'],
        'locked'    => ['Locked', 'text-bg-warning'],
        'published' => ['Published', 'text-bg-success'],
    ];
@endphp

@section('content')

@include('institute.academic._step-nav', ['currentStep'=>7,'currentLabel'=>'Result Cycles','prevRoute'=>'settings.academic.grading.index','prevLabel'=>'6 · Grade Overrides','nextRoute'=>'settings.academic.promotions.index','nextLabel'=>'8 · Promotions'])
@include('institute.academic._dependency-banner', ['context'=>'final-results'])

<div class="standalone-heading">
    <h4>7 · Result Cycles — Final Results</h4>
    <p>Step 7 of 7 — Take a Weight Scheme from <a href="{{ route('settings.academic.aggregations.index') }}">5 · Weight Schemes</a> and <a href="{{ route('settings.academic.grading.index') }}">6 · Grade Overrides</a> through preview → policy → review/approve → lock (freeze) → publish (official). Requires both.</p>
</div>

@php
    $blockedSchemes = isset($schemes) ? $schemes->filter(fn($s) => !$s->weightIsValid())->count() : 0;
@endphp
@if($blockedSchemes > 0)
<div class="alert alert-warning py-2 small d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>{{ $blockedSchemes }} scheme(s) have invalid total weight (must be 100%) — fix weight in <a href="{{ route('settings.academic.aggregations.index') }}">Weight Schemes</a> before generation.</span>
    <span class="badge text-bg-warning ms-auto">{{ $blockedSchemes }} blocked</span>
</div>
@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Scheme</th>
                    <th>Academic Year</th>
                    <th>Class / Grade</th>
                    <th>Group</th>
                    <th>Total Weight</th>
                    <th>Policy</th>
                    <th>In-flight Cycle</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schemes as $scheme)
                    @php
                        $policy = $policies->get($scheme->id);
                        $active = $policy !== null ? ($activeResults[$policy->id] ?? null) : null;
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td>
                            <span class="fw-semibold">{{ $scheme->name }}</span>
                            @if ($scheme->branch)
                                <div class="text-muted small">{{ $scheme->branch->name }}</div>
                            @endif
                        </td>
                        <td>{{ $scheme->academicYear?->name ?? '—' }}</td>
                        <td>{{ $scheme->classGrade?->name ?? '—' }}</td>
                        <td>{{ $scheme->academicGroup?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $scheme->weightIsValid() ? 'text-bg-success' : 'text-bg-warning' }}">{{ $scheme->totalWeight() }}%</span>
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge[$policy?->status ?? 'draft'] ?? 'text-bg-secondary' }}">
                                {{ $policy !== null ? ucfirst($policy->status) : 'No policy' }}
                            </span>
                            @if ($policy && ! $policy->absent_renormalization)
                                <div class="text-muted small mt-1">Absents not re-normalized</div>
                            @endif
                        </td>
                        <td>
                            @if ($active)
                                <span class="badge {{ $cycleBadge[$active->status][1] ?? 'text-bg-secondary' }}">{{ $cycleBadge[$active->status][0] ?? ucfirst($active->status) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($active)
                                <a href="{{ route('settings.academic.final-results.show', $active) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-arrow-right-square me-1"></i>{{ ucfirst($active->status) }} result
                                </a>
                            @else
                                <a href="{{ route('settings.academic.final-results.policy', $scheme) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-gear me-1"></i>Configure
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No aggregation schemes yet. Create one under Settings → Academic → Aggregation Schemes first.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection