@extends('layouts.institute')

@section('title', 'Completion & Exit Analytics')

@section('content')

<x-academic.analytics.header
    title="Completion & Exit Analytics"
    subtitle="Cohort completion, graduation, dropout and transfer rates per academic year. Completion figures come from approved promotion outcomes."
    export="{{ route('academic.analytics.completion.export', request()->query()) }}"
/>

<x-academic.analytics.year-filter :filters="$filters" :options="$options" />

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-mortarboard-fill"></i> Cohorts per Year</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Academic Year</th>
                    <th class="text-end">Cohort</th>
                    <th class="text-end">Active</th>
                    <th class="text-end">Completed</th>
                    <th class="text-end">Graduated</th>
                    <th class="text-end">Dropped</th>
                    <th class="text-end">Transferred</th>
                    <th class="text-end">Completed %</th>
                    <th class="text-end">Graduated %</th>
                    <th class="text-end">Dropped %</th>
                    <th class="text-end">Transferred %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['year']->name }}</td>
                        <td class="text-end fw-semibold">{{ $row['cohort'] }}</td>
                        <td class="text-end">{{ $row['active'] }}</td>
                        <td class="text-end text-success">{{ $row['completed'] }}</td>
                        <td class="text-end text-success">{{ $row['graduated'] }}</td>
                        <td class="text-end {{ $row['dropped'] > 0 ? 'text-danger' : '' }}">{{ $row['dropped'] }}</td>
                        <td class="text-end">{{ $row['transferred'] }}</td>
                        <td class="text-end">{{ $row['rates']['completed'] !== null ? number_format($row['rates']['completed'], 1).'%' : '—' }}</td>
                        <td class="text-end">{{ $row['rates']['graduated'] !== null ? number_format($row['rates']['graduated'], 1).'%' : '—' }}</td>
                        <td class="text-end">{{ $row['rates']['dropped'] !== null ? number_format($row['rates']['dropped'], 1).'%' : '—' }}</td>
                        <td class="text-end">{{ $row['rates']['transferred'] !== null ? number_format($row['rates']['transferred'], 1).'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No academic years found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection