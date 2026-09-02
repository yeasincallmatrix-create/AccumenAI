@extends('layouts.institute')

@section('title', 'Admission Pipeline Report — AccumenAI')

@php
    $funnelLabels = [
        'leads'      => 'Leads',
        'interested' => 'Interested',
        'applicants' => 'Applicants',
        'admitted'   => 'Admitted',
        'enrolled'   => 'Enrolled',
        'won'        => 'Won',
        'lost'       => 'Lost',
    ];
    $funnelColors = [
        'leads'      => 'secondary',
        'interested' => 'info',
        'applicants' => 'warning',
        'admitted'   => 'success',
        'enrolled'   => 'primary',
        'won'        => 'success',
        'lost'       => 'dark',
    ];
    $max = max(1, (int) max(array_values($funnel)));
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Admission Pipeline Funnel</h4>
        <p class="page-header-desc mb-0">Conversion funnel from CRM lead through to enrollment</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a href="{{ route('admissions.pipeline', request()->query()) }}" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3-fill me-1"></i>Pipeline Board
        </a>
        @if ($user->hasPermission('students.manage'))
            <a href="{{ route('admissions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Application
            </a>
        @endif
    </div>
</div>

<div class="admin-card">
    <div class="p-3 border-bottom d-flex flex-wrap gap-3">
        @foreach ($funnelLabels as $key => $label)
            <div class="pipeline-funnel-stat">
                <span class="badge bg-{{ $funnelColors[$key] }}">{{ $label }}</span>
                <span class="fw-bold fs-5 ms-2">{{ $funnel[$key] }}</span>
            </div>
        @endforeach
    </div>

    <div class="p-4">
        <h6 class="fw-semibold text-uppercase small text-muted mb-3">Funnel</h6>
        @foreach ($funnelLabels as $key => $label)
            @php
                $count = (int) $funnel[$key];
                $pct = round(($count / $max) * 100, 1);
            @endphp
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="text-muted small" style="width:110px;">{{ $label }}</div>
                <div class="progress flex-grow-1" style="height:22px;">
                    <div class="progress-bar bg-{{ $funnelColors[$key] }}" style="width:{{ $pct }}%"></div>
                </div>
                <div class="fw-semibold text-end" style="width:50px;">{{ $count }}</div>
            </div>
        @endforeach
    </div>

    <div class="p-4 border-top">
        <h6 class="fw-semibold text-uppercase small text-muted mb-3">Applications by course (applicants + admitted + enrolled)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th class="text-center">Applicants</th>
                        <th class="text-center">Admitted</th>
                        <th class="text-center">Enrolled</th>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byCourse as $row)
                        <tr>
                            <td>{{ $row['course'] }}</td>
                            <td class="text-center">{{ $row['applicants'] }}</td>
                            <td class="text-center">{{ $row['admitted'] }}</td>
                            <td class="text-center">{{ $row['enrolled'] }}</td>
                            <td class="text-center fw-semibold">{{ $row['applicants'] + $row['admitted'] + $row['enrolled'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No course data for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection