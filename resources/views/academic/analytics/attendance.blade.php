@extends('layouts.institute')

@section('title', 'Attendance Analytics')

@section('content')

<x-academic.analytics.header
    title="Attendance Analytics"
    subtitle="Whole-window totals, weekly / monthly trends and per-class breakdown. Unrecorded days are never counted as absent."
    export="{{ route('academic.analytics.attendance.export', request()->query()) }}"
/>

<x-academic.analytics.year-filter :filters="$filters" :options="$options" withFromTo />

@if (! $report['valid'])
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $report['message'] }}
    </div>
@else
    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Records', 'value' => $report['totals']['total'], 'class' => ''],
            ['label' => 'Present', 'value' => $report['totals']['present'], 'class' => 'text-success'],
            ['label' => 'Absent', 'value' => $report['totals']['absent'], 'class' => 'text-danger'],
            ['label' => 'Late / Leave', 'value' => $report['totals']['late'].' / '.$report['totals']['leave'], 'class' => ''],
            ['label' => 'Attendance %', 'value' => $report['totals']['present_percent'] !== null ? number_format($report['totals']['present_percent'], 1).'%' : '—', 'class' => 'text-success'],
        ] as $stat)
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="icon" style="background:rgba(13,110,253,.1); color:#0d6efd;"><i class="bi bi-calendar-check-fill"></i></div>
                    <div class="num {{ $stat['class'] }}">{{ $stat['value'] }}</div>
                    <div class="label">{{ $stat['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="admin-card h-100">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-graph-up-arrow"></i> {{ ucfirst($report['period']) }}ly Trend</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Records</th>
                                <th class="text-end">Present</th>
                                <th class="text-end">Absent</th>
                                <th class="text-end">Late</th>
                                <th class="text-end">Leave</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['buckets'] as $bucket)
                                <tr>
                                    <td>{{ $bucket['label'] }}</td>
                                    <td class="text-end">{{ $bucket['total'] }}</td>
                                    <td class="text-end text-success">{{ $bucket['present'] }}</td>
                                    <td class="text-end text-danger">{{ $bucket['absent'] }}</td>
                                    <td class="text-end">{{ $bucket['late'] }}</td>
                                    <td class="text-end">{{ $bucket['leave'] }}</td>
                                    <td class="text-end">{{ $bucket['present_percent'] !== null ? number_format($bucket['present_percent'], 1).'%' : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No attendance records in this window.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="admin-card h-100">
                <div class="table-toolbar">
                    <div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> Class / Grade</div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Class / Grade</th>
                                <th class="text-end">Students</th>
                                <th class="text-end">Records</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['classes'] as $class)
                                <tr>
                                    <td>{{ $class['class'] ?? '—' }}</td>
                                    <td class="text-end">{{ $class['students'] }}</td>
                                    <td class="text-end">{{ $class['total'] }}</td>
                                    <td class="text-end">{{ $class['present_percent'] !== null ? number_format($class['present_percent'], 1).'%' : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No placements in scope.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection