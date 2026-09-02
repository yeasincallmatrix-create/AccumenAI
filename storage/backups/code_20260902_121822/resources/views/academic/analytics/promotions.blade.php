@extends('layouts.institute')

@section('title', 'Promotion Analytics')

@section('content')

<x-academic.analytics.header
    title="Promotion Analytics"
    subtitle="Promotion decision statuses and approved outcome distribution per academic year."
    export="{{ route('academic.analytics.promotions.export', request()->query()) }}"
/>

<x-academic.analytics.year-filter :filters="$filters" :options="$options" />

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-diagram-3-fill"></i> Decisions per Year</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Academic Year</th>
                    <th class="text-end">Decisions</th>
                    <th class="text-end">Pending</th>
                    <th class="text-end">In Review</th>
                    <th class="text-end">Approved</th>
                    <th class="text-end">Promoted</th>
                    <th class="text-end">Not Promoted</th>
                    <th class="text-end">Conditional</th>
                    <th class="text-end">Repeat</th>
                    <th class="text-end">Completed</th>
                    <th class="text-end">Graduated</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $statuses = $row['statuses'];
                        $outcomes = $row['outcomes'];
                        $totalDecisions = (int) ($statuses['pending'] ?? 0) + (int) ($statuses['review'] ?? 0) + (int) ($statuses['approved'] ?? 0);
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $row['year']->name }}</td>
                        <td class="text-end">{{ $totalDecisions }}</td>
                        <td class="text-end">{{ (int) ($statuses['pending'] ?? 0) }}</td>
                        <td class="text-end">{{ (int) ($statuses['review'] ?? 0) }}</td>
                        <td class="text-end text-success">{{ (int) ($statuses['approved'] ?? 0) }}</td>
                        <td class="text-end">{{ (int) ($outcomes['promoted'] ?? 0) }}</td>
                        <td class="text-end">{{ (int) ($outcomes['not_promoted'] ?? 0) }}</td>
                        <td class="text-end">{{ (int) ($outcomes['conditional'] ?? 0) }}</td>
                        <td class="text-end">{{ (int) ($outcomes['repeat'] ?? 0) }}</td>
                        <td class="text-end">{{ (int) ($outcomes['completed'] ?? 0) }}</td>
                        <td class="text-end text-success">{{ (int) ($outcomes['graduated'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No promotion decisions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection