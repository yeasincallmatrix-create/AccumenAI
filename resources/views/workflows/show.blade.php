@extends('layouts.institute')

@section('title', mawa_e('workflows.title') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $workflow->title }}</h4>
        <p class="page-header-desc">
            {{ config("workflows.types.{$workflow->workflow_type}.label", $workflow->workflow_type) }}
            @if ($workflow->student)
                — {{ trim($workflow->student->first_name.' '.$workflow->student->last_name) }}
            @endif
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="{{ route('workflows.index') }}">
            <i class="bi bi-arrow-left me-1"></i>{{ mawa_e('workflows.back') }}
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $badge = match ($workflow->status) {
        'completed', 'approved' => 'text-bg-success',
        'rejected', 'cancelled' => 'text-bg-danger',
        'returned' => 'text-bg-warning',
        'under_review', 'submitted' => 'text-bg-info',
        default => 'text-bg-secondary',
    };
    $canManage = optional(auth('institute_user')->user())->hasPermission('workflows.manage');
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ mawa_e('workflows.steps') }}</h5>
                <span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $workflow->status)) }}</span>
            </div>
            <div class="card-body">
                <ol class="list-group list-group-numbered">
                    @foreach ($workflow->steps as $step)
                        @php
                            $stepBadge = match ($step->status) {
                                'approved' => 'text-bg-success',
                                'rejected' => 'text-bg-danger',
                                'in_progress' => 'text-bg-info',
                                'skipped' => 'text-bg-secondary',
                                default => 'text-bg-light border',
                            };
                        @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-semibold">
                                    {{ $step->name }}
                                    @if ($step->step_order === $workflow->current_step && ! $workflow->isTerminal())
                                        <span class="badge text-bg-info ms-1">{{ mawa_e('workflows.current') }}</span>
                                    @endif
                                </div>
                                @if ($step->responsible_role)
                                    <div class="text-muted small">{{ mawa_e('workflows.role') }} {{ str_replace('-', ' ', $step->responsible_role) }}</div>
                                @endif
                                @if ($step->comment)
                                    <div class="text-muted small mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $step->comment }}</div>
                                @endif
                                @if ($step->acted_at)
                                    <div class="text-muted small">{{ mawa_e('workflows.actor') }} {{ $step->actor?->name ?? mawa_e('workflows.system') }} · {{ $step->acted_at->format('d M Y H:i') }}</div>
                                @endif
                            </div>
                            <span class="badge {{ $stepBadge }}">{{ ucfirst(str_replace('_', ' ', $step->status)) }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($canManage && ! $workflow->isTerminal() && in_array($workflow->status, ['submitted', 'under_review'], true))
                    <hr>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('workflows.approve-step', $workflow) }}" class="d-flex gap-2 align-items-center">
                            @csrf
                            <input type="text" name="comment" class="form-control form-control-sm" placeholder="{{ mawa_e('workflows.comment_optional') }}" style="min-width:220px">
                            <button type="submit" class="btn btn-sm btn-success">{{ mawa_e('workflows.approve_step') }}</button>
                        </form>
                        <form method="POST" action="{{ route('workflows.reject-step', $workflow) }}" class="d-flex gap-2 align-items-center">
                            @csrf
                            <input type="text" name="comment" class="form-control form-control-sm" placeholder="{{ mawa_e('workflows.rejection_reason') }}" style="min-width:220px" required>
                            <button type="submit" class="btn btn-sm btn-danger">{{ mawa_e('workflows.reject') }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="admin-card">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('workflows.approval_history') }}</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ mawa_e('workflows.action') }}</th>
                                <th>{{ mawa_e('workflows.from') }}</th>
                                <th>{{ mawa_e('workflows.to') }}</th>
                                <th>{{ mawa_e('workflows.actor') }}</th>
                                <th>{{ mawa_e('workflows.comment') }}</th>
                                <th>{{ mawa_e('workflows.when') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workflow->histories as $history)
                                <tr>
                                    <td class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $history->action)) }}</td>
                                    <td>{{ $history->from_status ? ucfirst(str_replace('_', ' ', $history->from_status)) : '—' }}</td>
                                    <td>{{ $history->to_status ? ucfirst(str_replace('_', ' ', $history->to_status)) : '—' }}</td>
                                    <td>{{ $history->actor?->name ?? 'System' }}</td>
                                    <td class="text-muted">{{ $history->comment ?? '—' }}</td>
                                    <td class="text-muted small">{{ $history->created_at?->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">{{ mawa_e('workflows.no_history') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('workflows.details') }}</h5></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.type_label') }}</dt>
                    <dd class="col-7">{{ config("workflows.types.{$workflow->workflow_type}.label", $workflow->workflow_type) }}</dd>
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.status') }}</dt>
                    <dd class="col-7"><span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $workflow->status)) }}</span></dd>
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.student_label') }}</dt>
                    <dd class="col-7">{{ $workflow->student ? trim($workflow->student->first_name.' '.$workflow->student->last_name) : '—' }}</dd>
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.initiated_by') }}</dt>
                    <dd class="col-7">{{ $workflow->initiator?->name ?? mawa_e('workflows.system') }}</dd>
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.assigned_to') }}</dt>
                    <dd class="col-7">{{ $workflow->assignee?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.started') }}</dt>
                    <dd class="col-7">{{ $workflow->started_at?->format('d M Y H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">{{ mawa_e('workflows.completed') }}</dt>
                    <dd class="col-7">{{ $workflow->completed_at?->format('d M Y H:i') ?? '—' }}</dd>
                </dl>
                @if ($workflow->notes)
                    <hr>
                    <div class="small text-muted">{{ $workflow->notes }}</div>
                @endif
            </div>
        </div>

        @if ($canManage && ! $workflow->isTerminal())
            <div class="admin-card">
                <div class="card-header"><h5 class="mb-0">{{ mawa_e('workflows.apply_transition') }}</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('workflows.transition', $workflow) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small mb-1">{{ mawa_e('workflows.move_to_status') }}</label>
                            <select name="status" class="form-select form-select-sm" required>
                                <option value="">{{ mawa_e('workflows.select_placeholder') }}</option>
                                @foreach ($nextStatuses as $s)
                                    <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1">{{ mawa_e('workflows.comment') }}</label>
                            <input type="text" name="comment" class="form-control form-control-sm" placeholder="{{ mawa_e('workflows.rejection_reason') }}">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">{{ mawa_e('workflows.apply_transition') }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
