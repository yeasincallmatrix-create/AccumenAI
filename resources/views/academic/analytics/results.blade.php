@extends('layouts.institute')

@section('title', 'Result Analytics')

@section('content')

<x-academic.analytics.header
    title="Result Analytics"
    subtitle="Published final-result pass / fail rates computed exclusively from the frozen snapshots."
    export="{{ route('academic.analytics.results.export', request()->query()) }}"
/>

<x-academic.analytics.year-filter :filters="$filters" :options="$options" />

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Final Results', 'value' => $report['totals']['results'], 'class' => ''],
        ['label' => 'Students Assessed', 'value' => $report['totals']['students'], 'class' => ''],
        ['label' => 'Subjects Passed', 'value' => $report['totals']['passed'], 'class' => 'text-success'],
        ['label' => 'Subjects Failed', 'value' => $report['totals']['failed'], 'class' => 'text-danger'],
    ] as $stat)
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(25,135,84,.1); color:#198754;"><i class="bi bi-clipboard-check-fill"></i></div>
                <div class="num {{ $stat['class'] }}">{{ $stat['value'] }}</div>
                <div class="label">{{ $stat['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-journal-check"></i> Final Results</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Final Result</th>
                    <th>Academic Year</th>
                    <th>Class / Grade</th>
                    <th>Status</th>
                    <th class="text-end">Students</th>
                    <th class="text-end">Passed</th>
                    <th class="text-end">Failed</th>
                    <th class="text-end">Pass %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['results'] as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['result']->name }}</td>
                        <td>{{ $row['year']?->name ?? '—' }}</td>
                        <td>{{ $row['class']?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($row['result']->status) }}</span></td>
                        <td class="text-end">{{ $row['students'] }}</td>
                        <td class="text-end text-success">{{ $row['passed'] }}</td>
                        <td class="text-end text-danger">{{ $row['failed'] }}</td>
                        <td class="text-end">{{ $row['pass_rate'] !== null ? number_format($row['pass_rate'], 1).'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No published final results found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> By Class / Grade</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Class / Grade</th>
                    <th class="text-end">Results</th>
                    <th class="text-end">Students</th>
                    <th class="text-end">Passed</th>
                    <th class="text-end">Failed</th>
                    <th class="text-end">Pass %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['classes'] as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['class']?->name ?? '—' }}</td>
                        <td class="text-end">{{ $row['results'] }}</td>
                        <td class="text-end">{{ $row['students'] }}</td>
                        <td class="text-end text-success">{{ $row['passed'] }}</td>
                        <td class="text-end text-danger">{{ $row['failed'] }}</td>
                        <td class="text-end">{{ $row['pass_rate'] !== null ? number_format($row['pass_rate'], 1).'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No class data found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection