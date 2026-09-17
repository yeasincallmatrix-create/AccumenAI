@extends('layouts.institute')

@section('title', 'Treatment Plan ' . $plan->plan_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-list-check"></i> {{ $plan->plan_number }}</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical.dental.plan.manage'))
                <a href="{{ route('medical.dental.plans.edit', $plan) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                @if($plan->status === 'active')
                    <form method="POST" action="{{ route('medical.dental.plans.discontinue', $plan) }}" class="d-inline" onsubmit="return confirm('Discontinue this plan?')">
                        @csrf
                        <input type="hidden" name="reason" value="Discontinued from plan view">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Discontinue</button>
                    </form>
                @endif
            @endif
            <a href="{{ route('medical.dental.plans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Plan:</strong> {{ $plan->plan_number }}</div>
                        <div class="col-md-6"><strong>Status:</strong> <span class="badge bg-{{ $plan->statusColor() }}">{{ $plan->statusLabel() }}</span></div>
                        <div class="col-md-6"><strong>Patient:</strong> {{ $plan->patient->full_name ?? 'N/A' }}</div>
                        <div class="col-md-6"><strong>Dentist:</strong> {{ $plan->dentist->name ?? 'N/A' }}</div>
                        <div class="col-md-6"><strong>Start:</strong> {{ $plan->start_date->format('d M Y') }}</div>
                        <div class="col-md-6"><strong>Expected End:</strong> {{ $plan->expected_end_date?->format('d M Y') ?? '—' }}</div>
                        <div class="col-12"><strong>Chief Complaint:</strong> {{ $plan->chief_complaint }}</div>
                        <div class="col-12"><strong>Diagnosis:</strong> {{ $plan->diagnosis ?? '—' }}</div>
                        <div class="col-12"><strong>Treatment Summary:</strong> {{ $plan->treatment_summary ?? '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- Treatment Steps --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Treatment Steps</h6>
                    <div class="progress" style="width:150px; height:20px;">
                        <div class="progress-bar bg-success" style="width:{{ $plan->progressPercent() }}%">{{ $plan->progressPercent() }}%</div>
                    </div>
                </div>
                <div class="card-body">
                    @forelse($plan->planned_steps ?? [] as $index => $step)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2 {{ ($step['status'] ?? '') === 'completed' ? 'text-decoration-line-through opacity-75' : '' }}">
                            <div>
                                <strong>{{ $step['procedure'] ?? 'Step ' . ($index + 1) }}</strong>
                                @if(!empty($step['tooth']))
                                    <span class="badge bg-light text-dark">{{ $step['tooth'] }}</span>
                                @endif
                                @if(!empty($step['estimated_fee']))
                                    <small class="text-muted"> — Fee: {{ number_format($step['estimated_fee'], 2) }}</small>
                                @endif
                            </div>
                            <div>
                                @if(($step['status'] ?? '') === 'completed')
                                    <span class="badge bg-success"><i class="bi bi-check"></i> Done</span>
                                @elseif($plan->status === 'active' && $user && $user->hasPermission('medical.dental.plan.manage'))
                                    <form method="POST" action="{{ route('medical.dental.plans.complete-step', [$plan, $index]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle"></i> Complete</button>
                                    </form>
                                @else
                                    <span class="badge bg-secondary">Pending</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No steps planned.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Summary</h6></div>
                <div class="card-body">
                    <p><strong>Steps:</strong> {{ $plan->completed_steps }} / {{ $plan->total_steps }}</p>
                    <p><strong>Estimated Fee:</strong> {{ number_format($plan->total_estimated_fee, 2) }}</p>
                    @if($plan->notes)
                        <p><strong>Notes:</strong> {{ $plan->notes }}</p>
                    @endif
                    @if($plan->discontinue_reason)
                        <p class="text-danger"><strong>Discontinue Reason:</strong> {{ $plan->discontinue_reason }}</p>
                    @endif
                </div>
            </div>

            <a href="{{ route('medical.dental.chart.show', $plan->patient_id) }}" class="btn btn-outline-primary w-100 mb-2">
                <i class="bi bi-emoji-smile"></i> View Dental Chart
            </a>
        </div>
    </div>
</div>
@endsection
