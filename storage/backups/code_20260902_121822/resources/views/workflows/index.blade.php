@extends('layouts.institute')

@section('title', mawa_e('workflows.title') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('workflows.title') }}</h4>
        <p class="page-header-desc">{{ mawa_e('workflows.description') }}</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('workflows.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('workflows.new_workflow') }}
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="admin-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('workflows.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ mawa_e('workflows.type_label') }}</label>
                <select name="workflow_type" class="form-select form-select-sm">
                    <option value="">{{ mawa_e('workflows.all_types') }}</option>
                    @foreach ($types as $slug => $def)
                        <option value="{{ $slug }}" @selected(($filters['workflow_type'] ?? '') === $slug)>{{ $def['label'] ?? $slug }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ mawa_e('workflows.status') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ mawa_e('workflows.all_statuses') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-primary">{{ mawa_e('workflows.filter') }}</button>
                <a href="{{ route('workflows.index') }}" class="btn btn-sm btn-outline-secondary">{{ mawa_e('workflows.reset') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ mawa_e('workflows.title_label') }}</th>
                    <th>{{ mawa_e('workflows.type_label') }}</th>
                    <th>{{ mawa_e('workflows.student_label') }}</th>
                    <th>{{ mawa_e('workflows.current_step') }}</th>
                    <th>{{ mawa_e('workflows.status') }}</th>
                    <th>{{ mawa_e('workflows.initiated') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workflows as $workflow)
                    <tr>
                        <td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('workflows.show', $workflow) }}">{{ $workflow->title }}</a>
                        </td>
                        <td>{{ $types[$workflow->workflow_type]['label'] ?? $workflow->workflow_type }}</td>
                        <td>
                            @if ($workflow->student)
                                {{ trim($workflow->student->first_name.' '.$workflow->student->last_name) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @php $step = $workflow->steps->firstWhere('step_order', $workflow->current_step); @endphp
                            {{ $step?->name ?? '—' }}
                        </td>
                        <td>
                            @php
                                $badge = match ($workflow->status) {
                                    'completed', 'approved' => 'text-bg-success',
                                    'rejected', 'cancelled' => 'text-bg-danger',
                                    'returned' => 'text-bg-warning',
                                    'under_review', 'submitted' => 'text-bg-info',
                                    default => 'text-bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $workflow->status)) }}</span>
                        </td>
                        <td class="text-muted small">{{ $workflow->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ mawa_e('workflows.no_workflows') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($workflows->hasPages())
        <div class="card-footer">{{ $workflows->links() }}</div>
    @endif
</div>
@endsection
