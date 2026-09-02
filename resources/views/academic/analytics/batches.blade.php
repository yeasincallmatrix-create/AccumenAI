@extends('layouts.institute')

@section('title', 'Batch Analytics')

@section('content')

<x-academic.analytics.header
    title="Batch Analytics"
    subtitle="Per-batch cohort size, completion, graduation, dropout, frozen pass/fail and attendance."
    export="{{ route('academic.analytics.batches.export', request()->query()) }}"
/>

<x-academic.analytics.year-filter :filters="$filters" :options="$options" />

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-collection-fill"></i> Batches</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Course</th>
                    <th class="text-end">Students</th>
                    <th class="text-end">Active</th>
                    <th class="text-end">Completed</th>
                    <th class="text-end">Graduated</th>
                    <th class="text-end">Dropped</th>
                    <th class="text-end">Transferred</th>
                    <th class="text-end">Pass %</th>
                    <th class="text-end">Attendance %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fw-semibold">
                            {{ $row['label']->name }}
                            @if ($row['label']->batch_code)
                                <div class="text-muted small">{{ $row['label']->batch_code }}</div>
                            @endif
                        </td>
                        <td>{{ $row['course']?->name ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ $row['students'] }}</td>
                        <td class="text-end">{{ $row['active'] }}</td>
                        <td class="text-end">{{ $row['completed'] }}</td>
                        <td class="text-end text-success">{{ $row['graduated'] }}</td>
                        <td class="text-end {{ $row['dropped'] > 0 ? 'text-danger' : '' }}">{{ $row['dropped'] }}</td>
                        <td class="text-end">{{ $row['transferred'] }}</td>
                        <td class="text-end">{{ $row['pass_rate'] !== null ? number_format($row['pass_rate'], 1) . '%' : '—' }}</td>
                        <td class="text-end">{{ $row['attendance']['present_percent'] !== null ? number_format($row['attendance']['present_percent'], 1) . '%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No batches found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection