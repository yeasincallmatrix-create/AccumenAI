@extends('layouts.standalone')

@section('title', 'Promotion Policy — '.$policy->name.' — AccumenAI')
@section('page_title', $policy->name)

@php
    $policyBadge = [
        'draft'    => 'text-bg-secondary',
        'active'   => 'text-bg-success',
        'archived' => 'text-bg-light',
    ];
    $decisionBadge = [
        'pending'  => ['Pending', 'text-bg-secondary'],
        'review'   => ['In Review', 'text-bg-info'],
        'approved' => ['Approved', 'text-bg-success'],
    ];
    $ruleLabels = [
        'overall_pass'       => 'Overall pass',
        'gpa_threshold'      => 'GPA threshold',
        'max_failed_subjects' => 'Max failed subjects',
        'mandatory_pass'     => 'Mandatory subjects pass',
        'conditional'        => 'Conditional',
    ];
@endphp

@section('content')

<div class="standalone-heading">
    <h4>{{ $policy->name }}</h4>
    <p>
        <span class="badge {{ $policyBadge[$policy->status] ?? 'text-bg-secondary' }}">{{ ucfirst($policy->status) }}</span>
        &nbsp;{{ $policy->academicYear?->name ?? '—' }} · {{ $policy->classGrade?->name ?? '—' }}@if ($policy->academicGroup) · {{ $policy->academicGroup->name }}@endif
    </p>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('settings.academic.promotions.policies.edit', $policy) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <form method="POST" action="{{ route('settings.academic.promotions.policies.status', $policy) }}" class="d-inline">
        @csrf
        @if ($policy->status === 'archived')
            <button class="btn btn-sm btn-outline-success" name="status" value="active" type="submit">Reactivate</button>
        @else
            <button class="btn btn-sm btn-outline-secondary" name="status" value="archived" type="submit">Archive</button>
        @endif
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="admin-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Promotion Rules</h6>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#ruleForm">
                    <i class="bi bi-plus-lg me-1"></i>Add rule
                </button>
            </div>

            @if ($policy->rules->isEmpty())
                <p class="text-muted mb-0">No rules yet. Add at least one rule before evaluating a published result.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-muted">#</th>
                                <th>Rule</th>
                                <th>Condition</th>
                                <th>Pass</th>
                                <th>Fail</th>
                                <th>On</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($policy->rules as $rule)
                                <tr>
                                    <td class="text-muted">{{ $rule->display_order }}</td>
                                    <td class="fw-semibold">{{ $ruleLabels[$rule->rule_type] ?? $rule->rule_type }}</td>
                                    <td class="small text-muted">
                                        @if ($rule->isBooleanRule())
                                            —
                                        @else
                                            {{ $rule->field ?? 'failed_count' }} {{ $rule->operator ?? '>=' }} {{ $rule->value ?? '' }}
                                        @endif
                                    </td>
                                    <td><span class="badge text-bg-success">{{ $rule->pass_action }}</span></td>
                                    <td><span class="badge text-bg-danger">{{ $rule->fail_action }}</span></td>
                                    <td>
                                        <form method="POST" action="{{ route('settings.academic.promotions.rules.update', $rule) }}" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="rule_type" value="{{ $rule->rule_type }}">
                                            <input type="hidden" name="field" value="{{ $rule->field }}">
                                            <input type="hidden" name="operator" value="{{ $rule->operator }}">
                                            <input type="hidden" name="value" value="{{ $rule->value }}">
                                            <input type="hidden" name="pass_action" value="{{ $rule->pass_action }}">
                                            <input type="hidden" name="fail_action" value="{{ $rule->fail_action }}">
                                            <input type="hidden" name="display_order" value="{{ $rule->display_order }}">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit" name="status" value="{{ $rule->status ? 0 : 1 }}">
                                                {{ $rule->status ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('settings.academic.promotions.rules.destroy', $rule) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="collapse mt-3" id="ruleForm">
                <form method="POST" action="{{ route('settings.academic.promotions.rules.store', $policy) }}" class="border-top pt-3">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Rule type</label>
                            <select name="rule_type" class="form-select form-select-sm" required>
                                @foreach (['overall_pass', 'gpa_threshold', 'max_failed_subjects', 'mandatory_pass', 'conditional'] as $type)
                                    <option value="{{ $type }}">{{ $ruleLabels[$type] ?? $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Field</label>
                            <select name="field" class="form-select form-select-sm">
                                <option value="">Auto</option>
                                <option value="gpa">gpa</option>
                                <option value="failed_count">failed_count</option>
                                <option value="incomplete_count">incomplete_count</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Operator</label>
                            <select name="operator" class="form-select form-select-sm">
                                <option value=">=">&gt;=</option>
                                <option value=">">&gt;</option>
                                <option value="<=">&lt;=</option>
                                <option value="<">&lt;</option>
                                <option value="==">==</option>
                                <option value="!=">!=</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Value</label>
                            <input type="text" name="value" class="form-control form-control-sm" placeholder="e.g. 2.0">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small text-muted mb-1">Order</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="{{ $policy->rules->max('display_order') + 1 }}" min="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Pass / Fail</label>
                            <div class="d-flex gap-1">
                                <select name="pass_action" class="form-select form-select-sm">
                                    @foreach (['promoted', 'conditional', 'repeat', 'not_promoted', 'completed', 'graduated'] as $action)
                                        <option value="{{ $action }}" @selected($action === 'promoted')>{{ $action }}</option>
                                    @endforeach
                                </select>
                                <select name="fail_action" class="form-select form-select-sm">
                                    @foreach (['repeat', 'promoted', 'conditional', 'not_promoted', 'completed', 'graduated'] as $action)
                                        <option value="{{ $action }}" @selected($action === 'repeat')>{{ $action }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12 mt-2 d-flex gap-2">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Add rule</button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ruleForm">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card h-100">
            <h6 class="mb-2">Start a Decision</h6>
            <p class="text-muted small">Only PUBLISHED final results are eligible. The result's academic context must match this policy.</p>

            @if ($policy->status !== 'active')
                <div class="alert alert-warning small mb-3">This policy is not active. Activate it before starting a decision.</div>
            @endif

            @if ($publishedResults->isEmpty())
                <p class="text-muted mb-0">No published results match this policy's context yet. Publish a final result for {{ $policy->academicYear?->name ?? 'this year' }} · {{ $policy->classGrade?->name ?? '' }} first.</p>
            @else
                <form method="POST" action="{{ route('settings.academic.promotions.decisions.store', $policy) }}" class="d-flex gap-2">
                    @csrf
                    <select name="result_id" class="form-select form-select-sm" required>
                        <option value="">Select published result</option>
                        @foreach ($publishedResults as $result)
                            <option value="{{ $result->id }}">
                                {{ $result->name }} — {{ $result->scheme?->academicYear?->name ?? '' }} · {{ $result->scheme?->classGrade?->name ?? '' }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit" @if ($policy->status !== 'active') disabled @endif>
                        <i class="bi bi-play me-1"></i>Evaluate
                    </button>
                </form>
            @endif

            @if ($activeDecision)
                <div class="alert alert-info small mt-3 mb-0">
                    An in-flight decision exists:
                    <a href="{{ route('settings.academic.promotions.decisions.show', $activeDecision) }}" class="alert-link">
                        {{ ucfirst($activeDecision->status) }} decision
                    </a>.
                </div>
            @endif
        </div>

        <div class="admin-card mt-3">
            <h6 class="mb-2">Decision History</h6>
            @if ($policy->decisions->isEmpty())
                <p class="text-muted mb-0">No decisions yet.</p>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach ($policy->decisions as $decision)
                        <li class="d-flex justify-content-between align-items-center py-1">
                            <a href="{{ route('settings.academic.promotions.decisions.show', $decision) }}">
                                {{ $decision->result?->name ?? ('Decision #'.$decision->id) }}
                            </a>
                            <span class="badge {{ $decisionBadge[$decision->status][1] ?? 'text-bg-secondary' }}">
                                {{ $decisionBadge[$decision->status][0] ?? ucfirst($decision->status) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

@endsection
