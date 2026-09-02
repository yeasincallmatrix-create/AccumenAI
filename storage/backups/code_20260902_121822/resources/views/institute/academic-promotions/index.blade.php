@php
    $isAjax = request()->header('X-Requested-With') === 'XMLHttpRequest';
@endphp

@if(!$isAjax)
@extends('layouts.standalone')
@section('title', 'Academic Promotion — AccumenAI')
@section('page_title', 'Academic Promotion')
@endif

@php
    $policyBadge = [
        'draft'    => 'text-bg-secondary',
        'active'   => 'text-bg-success',
        'archived' => 'text-bg-light',
    ];
    $decisionBadge = [
        'pending'  => ['Pending', 'text-bg-secondary'],
        'review'   => ['In Review', 'text-bg-info'],
        'approved' => ['Approved', 'text-bg-success'],
    ];
@endphp

@section('content')

@if(!$isAjax)
@include('institute.academic._step-nav', ['currentStep'=>8,'currentLabel'=>'Promotions','prevRoute'=>'settings.academic.final-results.index','prevLabel'=>'7 · Result Cycles','nextRoute'=>null,'nextLabel'=>null])
@php
    // Non-destructive fetch for published results ready for promotion, if controller did not provide
    $publishedResultsForBanner = $publishedResults ?? null;
    try {
        if ($publishedResultsForBanner === null) {
            $publishedResultsForBanner = \App\Models\AcademicFinalResult::where('status', 'published')->with(['scheme.academicYear','scheme.classGrade'])->orderByDesc('id')->limit(10)->get();
        }
        $publishedCount = $publishedResultsForBanner instanceof \Illuminate\Support\Collection ? $publishedResultsForBanner->count() : 0;
    } catch (\Throwable $e) { $publishedResultsForBanner = collect(); $publishedCount = 0; }
@endphp
@include('institute.academic._dependency-banner', ['context'=>'promotions','publishedResults'=>$publishedResultsForBanner,'publishedCount'=>$publishedCount])

<div class="standalone-heading">
    <h4>8 · Promotions — Academic Promotion</h4>
    <p>Step 7+ — Define promotion rules per year+class+group, evaluate a <strong>PUBLISHED</strong> Final Result and create next-year placements. Requires <a href="{{ route('settings.academic.final-results.index') }}?status=published">Official Results</a>. Source placement is never rewritten.</p>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-send-check"></i> Published Results Ready for Promotion</div>
        <span class="badge text-bg-success ms-2">{{ $publishedCount }} available</span>
    </div>
    @if($publishedCount > 0)
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Published Result</th><th>Year</th><th>Class</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                @foreach($publishedResultsForBanner as $pr)
                    <tr>
                        <td class="fw-semibold">{{ $pr->name }}</td>
                        <td>{{ $pr->scheme?->academicYear?->name ?? '—' }}</td>
                        <td>{{ $pr->scheme?->classGrade?->name ?? '—' }}</td>
                        <td class="text-end"><a href="{{ route('settings.academic.promotions.index') }}" class="btn btn-sm btn-primary">Create Decision</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="p-3 text-center text-muted small">
            <i class="bi bi-info-circle me-1"></i> No published Final Results yet. <a href="{{ route('settings.academic.final-results.index') }}">Publish a Result Cycle first</a> to enable promotions.
        </div>
    @endif
</div>
@endif

@endsection
