@php
    // Non-destructive dependency banner — shows warnings for missing prerequisites
    // Expects context variable $context: placements|assessments|aggregations|grading|final-results|promotions
    // Relies on passed collections: $classes, $academicYears, $assessments, $schemes, $publishedResults etc.
    $context = $context ?? null;
@endphp

@if($context === 'placements')
    @if(empty($classes) || (isset($academicYears) && $academicYears->isEmpty()))
        <div class="alert alert-warning py-2 small d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>
                @if(empty($classes))
                    <strong>Structure required:</strong> No classes configured. Please complete <a href="{{ route('settings.academic.index') }}">1 · Structure</a> first.
                @elseif($academicYears->isEmpty())
                    <strong>Academic Year required:</strong> No years exist. Please create one in <a href="{{ route('settings.academic.academic-years.index') }}">2 · Academic Years</a> before placements.
                @endif
            </span>
        </div>
    @endif
@endif

@if($context === 'assessments')
    @php $hasPlacements = isset($placementsCount) ? $placementsCount > 0 : (isset($hasPlacements) ? $hasPlacements : true); @endphp
    @if(isset($academicYears) && $academicYears->isEmpty())
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i> No Academic Years — create one in <a href="{{ route('settings.academic.academic-years.index') }}">2 · Academic Years</a> before assessments.</div>
    @elseif(empty($classes))
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i> No classes in Structure — complete <a href="{{ route('settings.academic.index') }}">1 · Structure</a> first.</div>
    @endif
@endif

@if($context === 'aggregations')
    @php
        $aggHasAssessments = $hasAssessments ?? null;
        if ($aggHasAssessments === null) {
            if (isset($assessments)) $aggHasAssessments = count($assessments) > 0;
            else {
                try { $aggHasAssessments = \App\Models\AcademicAssessment::count() > 0; } catch (\Throwable $e) { $aggHasAssessments = true; }
            }
        }
    @endphp
    @if(!$aggHasAssessments)
        <div class="alert alert-info py-2 small d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i>
            <span>No assessments yet — create one in <a href="{{ route('settings.academic.assessments.index') }}">4 · Assessments</a> before building a Weight Scheme.</span>
        </div>
    @endif
@endif

@if($context === 'grading')
    @php $gradingClasses = $classLabels ?? $classes ?? []; @endphp
    @if(empty($gradingClasses))
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i> No classes configured — grading scales resolve per class. Complete <a href="{{ route('settings.academic.index') }}">1 · Structure</a>.</div>
    @endif
@endif

@if($context === 'final-results')
    @php
        $hasSchemes = isset($schemes) ? count($schemes) > 0 : true;
        $hasScales = isset($hasScales) ? $hasScales : true;
    @endphp
    @if(!$hasSchemes)
        <div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i> No Weight Schemes — create one in <a href="{{ route('settings.academic.aggregations.index') }}">5 · Weight Schemes</a> first.</div>
    @endif
    @if(isset($hasScales) && !$hasScales)
        <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i> No grade scales resolve for your classes — add an override in <a href="{{ route('settings.academic.grading.index') }}">6 · Grade Overrides</a>.</div>
    @endif
@endif

@if($context === 'promotions')
    @php $publishedCount = isset($publishedResults) ? count($publishedResults) : (isset($publishedCount) ? $publishedCount : null); @endphp
    @if($publishedCount !== null && $publishedCount === 0)
        <div class="alert alert-warning py-2 small d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>No published Final Results — <a href="{{ route('settings.academic.final-results.index') }}">7 · Result Cycles</a> must publish at least one result before promotions.</span>
        </div>
    @endif
@endif
