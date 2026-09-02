@extends('layouts.institute')

@section('title', mawa_e('alumni.title') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('alumni.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('alumni.description_index', ['academy' => $instituteId ? mawa_e('students.your_academy') : 'this academy']) }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-primary" href="{{ route('alumni.reports') }}">
            <i class="bi bi-bar-chart-line-fill me-1"></i>{{ mawa_e('alumni.nav_reports') }}
        </a>
        <a class="btn btn-outline-primary" href="{{ route('alumni.directory') }}">
            <i class="bi bi-people-fill me-1"></i>{{ mawa_e('alumni.nav_directory') }}
        </a>
        <a class="btn btn-primary" href="{{ route('alumni.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('alumni.add_alumni') }}
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_total') }}</div>
                <div class="fs-3 fw-bold">{{ $totals['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_active') }}</div>
                <div class="fs-3 fw-bold text-success">{{ $totals['active'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_inactive') }}</div>
                <div class="fs-3 fw-bold text-secondary">{{ $totals['inactive'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_employed') }}</div>
                <div class="fs-3 fw-bold text-primary">{{ $employed }}</div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">{{ mawa_e('alumni.recent_alumni') }}</h5>
        <a class="small" href="{{ route('alumni.directory') }}">{{ mawa_e('alumni.view_all') }}</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ mawa_e('alumni.th_student') }}</th>
                    <th>{{ mawa_e('alumni.th_course') }}</th>
                    <th>{{ mawa_e('alumni.th_batch') }}</th>
                    <th>{{ mawa_e('alumni.th_graduation_date') }}</th>
                    <th>{{ mawa_e('alumni.th_occupation') }}</th>
                    <th>{{ mawa_e('alumni.th_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $alumni)
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('alumni.show', $alumni) }}">
                                {{ $alumni->student->full_name ?: trim($alumni->student->first_name.' '.$alumni->student->last_name) }}
                            </a>
                            @if ($alumni->student->student_id)
                                <span class="text-muted small d-block">{{ $alumni->student->student_id }}</span>
                            @endif
                        </td>
                        <td>{{ $alumni->completedCourse?->name ?? '—' }}</td>
                        <td>{{ $alumni->completedBatch?->name ?? '—' }}</td>
                        <td>{{ $alumni->graduation_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $alumni->current_occupation ?: ($alumni->employer ? mawa_e('alumni.badge_employed') : '—') }}</td>
                        <td>
                            @if ($alumni->status === 'active')
                                <span class="badge text-bg-success">{{ mawa_e('alumni.stat_active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ mawa_e('alumni.stat_inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            {{ mawa_e('alumni.no_alumni') }}
                            <a href="{{ route('alumni.create') }}">{{ mawa_e('alumni.add_first') }}</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
