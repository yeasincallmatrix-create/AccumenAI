@extends('layouts.standalone')

@section('title', 'Aggregation Schemes — AccumenAI')
@section('page_title', 'Aggregation Schemes')

@php
    $statusBadge = [
        'draft'    => 'text-bg-secondary',
        'active'   => 'text-bg-success',
        'archived' => 'text-bg-light',
    ];
@endphp

@section('content')

@include('institute.academic._step-nav', ['currentStep'=>5,'currentLabel'=>'Weight Schemes','prevRoute'=>'settings.academic.assessments.index','prevLabel'=>'4 · Assessments','nextRoute'=>'settings.academic.grading.index','nextLabel'=>'6 · Grade Overrides'])
@include('institute.academic._dependency-banner', ['context'=>'aggregations'])

<div class="standalone-heading">
    <h4>5 · Weight Schemes — Aggregation Schemes</h4>
    <p>Step 5 of 7 — Define which assessments combine into a final result and assign each a weight. Requires <a href="{{ route('settings.academic.assessments.index') }}">4 · Assessments</a> with entered marks. Original marks are never altered.</p>
    <a href="{{ route('settings.academic.aggregations.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Scheme
    </a>
</div>

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('settings.academic.aggregations.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span flex-shrink-0" style="min-width:180px">
                <label class="form-label mb-1">Academic Year</label>
                <select class="form-select form-select-sm" name="academic_year_id" onchange="this.form.submit()">
                    <option value="">All years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((string) $year->id === (string) $academicYearId)>{{ $year->name }} @if($year->is_current)(Current)@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:170px">
                <label class="form-label mb-1">Class / Grade</label>
                <select class="form-select form-select-sm" name="class_grade_id" onchange="this.form.submit()">
                    <option value="">All classes</option>
                    @foreach ($classes as $entry)
                        <option value="{{ $entry['class_grade']->id }}" @selected((string) $entry['class_grade']->id === (string) $classGradeId)>{{ $entry['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0" style="min-width:150px">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-span flex-shrink-0">
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            </div>
        </div>
    </form>
</div>

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
                    <th>Assessments</th>
                    <th>Total Weight</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schemes as $scheme)
                    <tr>
                        <td class="text-muted">{{ $schemes->firstItem() + $loop->index }}</td>
                        <td>
                            <span class="fw-semibold">{{ $scheme->name }}</span>
                            @if ($scheme->branch)
                                <div class="text-muted small">{{ $scheme->branch->name }}</div>
                            @endif
                        </td>
                        <td>{{ $scheme->academicYear?->name ?? '—' }}</td>
                        <td>{{ $scheme->classGrade?->name ?? '—' }}</td>
                        <td>{{ $scheme->academicGroup?->name ?? '<span class="text-muted">—</span>' }}</td>
                        <td>
                            {{ $scheme->items_count }}
                            <a href="{{ route('settings.academic.aggregations.show', $scheme) }}" class="small text-muted ms-1">view</a>
                        </td>
                        <td>
                            <span class="badge {{ $scheme->weightIsValid() ? 'text-bg-success' : 'text-bg-warning' }}">{{ $scheme->totalWeight() }}%</span>
                        </td>
                        <td>
                            <span class="badge {{ $statusBadge[$scheme->status] ?? 'text-bg-secondary' }}">{{ ucfirst($scheme->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('settings.academic.aggregations.edit', $scheme) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" action="{{ route('settings.academic.aggregations.destroy', $scheme) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this aggregation scheme?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No aggregation schemes yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($schemes->hasPages())
        <div class="p-2 border-top">{{ $schemes->links() }}</div>
    @endif
</div>

@endsection